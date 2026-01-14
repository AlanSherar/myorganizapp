<?php

namespace App\Http\Controllers\Admin;

use App\Models\Client;
use App\Models\Carrier;
use App\Models\Company;
use Illuminate\Http\Request;
use App\Exports\EmptyCSVFile;
use App\Jobs\UploadClientCSV;
use App\Services\clientService;
use App\Models\ClientThirdParty;
use App\Support\Client\ClientCSV;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ClientController extends Controller
{
    public function __construct() {}

    public function list(Request $request)
    {
        try {
            $clients = ClientService::list($request);

            return view('clients.list', compact('clients'));
        } catch (\Throwable $th) {
            return redirect()->route('clientsList')->with('error', $th->getMessage());
        }
    }

    public function create()
    {
        try {
            $companies = Company::where('active', 1)->get();
            $carriers = Carrier::where('active', 1)->with('services')->get();
            $countries = config('countries');
            $states = getStates();

            return view('clients.create', compact('companies', 'carriers', 'countries', 'states'));
        } catch (\Throwable $th) {
            return redirect()->route('clientCreate')->withInput()->with('error', $th->getMessage());
        }
    }

    public function store(Request $request)
    {
        try {
            $request->merge(['is_active' => 1 ]);
            $response = ClientService::store($request);
            if (!$response['ok']) {
                throw new \Exception($response['message']);
            }

            return redirect()->route('clientsList')->with('success', $response['message']);
        } catch (\Throwable $th) {
            return redirect()->back()->withInput()->with('error', $th->getMessage());
        }
    }

    public function edit($id)
    {
            try {
            $client = Client::findOrFail($id);
            $client->load('preferredCarrier', 'preferredShippingService');

            $carriers = Carrier::where('active', 1)->with('services')->get();
            $companies = Company::where('active', 1)->get();
            $countries = config('countries');
            $states = getStates();
            $third_party = ClientThirdParty::where('client_id', $id)->get();

            return view('clients.edit', compact('client', 'companies', 'carriers', 'countries', 'states', 'third_party'));
        } catch (\Throwable $th) {
            return redirect()->route('clientsList')->with('error', $th->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $client = Client::findOrFail($id);
            $response = ClientService::update($client, $request);
            if (!$response['ok']) {
                throw new \Exception($response['message']);
            }

            return redirect()->route('clientDetails', $id)->with('success', $response['message']);
        } catch (\Throwable $th) {
            return redirect()->back()->withInput()->with('error', $th->getMessage());
        }
    }

    public function details($id)
    {
        try {
            $client = Client::findOrFail($id);
            $third_party = ClientThirdParty::where('client_id', $id)->get();

            return view('clients.details', compact('client', 'third_party'));
        } catch (\Throwable $th) {
            return redirect()->route('clientsList')->with('error', $th->getMessage());
        }
    }

    public function search(Request $request)
    {
        try {
            $query = $request->get('query');
            $perPage = (int) ($request->get('per_page', 10));
            $page = (int) ($request->get('page', 1));

            $baseQuery = Client::query()->with('preferredCarrier', 'preferredShippingService', 'company');
            if ($query) {
                $baseQuery->where(function ($q) use ($query) {
                    $q->where('client_name', 'like', "%{$query}%")
                      ->orWhere('contact_email', 'like', "%{$query}%");
                });
            }

            $paginator = $baseQuery->orderBy('client_name')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $paginator->items(),
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'has_next' => $paginator->currentPage() < $paginator->lastPage(),
                    'has_prev' => $paginator->currentPage() > 1,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function import(Request $request)
    {
        try {
            // ✅ 1. Validation du fichier
            $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:29000',
            ]);

            $email = Auth::user()->email;

            $file = $request->file('file');
            $rawContent = file_get_contents($file->getRealPath());

            $rows = array_map('str_getcsv', file($file->getRealPath()));
            $header = array_shift($rows);
            $missing = ClientCSV::validateHeader($header);
            if (!empty($missing)) {
                return back()->with('error', 'Missing columns : ' . implode(', ', $missing));
            }

            $encoding = mb_detect_encoding($rawContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
            if ($encoding !== 'UTF-8') {
                $rawContent = iconv($encoding, 'UTF-8//IGNORE', $rawContent);
            }

            // ✅ 3. Parsing CSV
            $tempFile = tmpfile();
            fwrite($tempFile, $rawContent);
            rewind($tempFile);

            $data = [];
            $headers = null;

            while (($row = fgetcsv($tempFile, 0, ',')) !== false) {
                $row = array_map(function($cell) {
                    return is_string($cell) ? iconv('UTF-8', 'UTF-8//IGNORE', $cell) : $cell;
                }, $row);

                if (!$headers) {
                    $headers = array_map(function($header) {
                        return strtolower(trim($header));
                    }, $row);
                    continue;
                }

                $data[] = array_combine($headers, $row);
            }

            fclose($tempFile);

            // ✅ 4. Sauvegarde temporaire en JSON par chunks
            $key = 'csv_upload_' . uniqid();
            $tmpDir = storage_path('app/tmp');

            // Vérifie si le dossier existe, sinon le créer
            if (!file_exists($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }

            $filePath = "{$tmpDir}/{$key}.json";
            $chunks = array_chunk($data, 1000);

            $jsonData = json_encode([
                'chunks'  => $chunks,
                'headers' => $headers,
            ]);

            if ($jsonData === false) {
                Log::error('json_encode error: ' . json_last_error_msg());
                return back()->with('error', 'Failed to encode data.');
            }

            file_put_contents($filePath, $jsonData);

            UploadClientCSV::dispatch($key, $email)->onConnection('database');

/*             $companies = Company::where('active', 1)->get();
            $clients = [];
            $invalidRows = [];
            // $skusInCSV = [];

            foreach ($rows as $index => $row) {
                $data = array_combine($header, $row);

                $validation = ClientCSV::validateRow($data, $companies, $index);

                if (!empty($validation['errors'])) {
                    $invalidRows[] = [
                        'errors' => implode('; ', $validation['errors']),
                    ];
                    continue; // on saute la ligne car invalide
                }

                $client = ClientCSV::mapToClient($data, $companies);
                if ($client) {
                    $clients[] = $client;
                }
            }

            foreach ($clients as $data) {
                Client::upsert($data, ['client_name']);
            }

            if (!empty($invalidRows)) {
                $filename = storage_path('app/errors_clients.csv');
                $handle = fopen($filename, 'w');

                fputcsv($handle, ['Errors']);

                foreach ($invalidRows as $rowError) {
                    fputcsv($handle, [$rowError['errors']]);
                }

                fclose($handle);

                return response()->download($filename);
            } */

            return redirect()->back()->with('success', 'Upload CSV in process...');
        } catch (\Throwable $th) {
            Log::info($th->getMessage());
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function clientsImportTemplate()
    {
        try {
            $headers = ClientCSV::templateFields();

            return Excel::download(new EmptyCSVFile($headers), 'clients_import_template.csv');
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }
}
