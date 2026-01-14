<?php

namespace App\Http\Controllers\Admin;

use ArrayObject;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\PackagingOrder;
use App\Exports\ExportCSVReport;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ShippedBillingImport;
use App\Jobs\UploadShipstationBillingCSV;
use App\Exports\ShippedBillingReportExport;
use App\Services\Dashboard\IndicatorQueryBuilder;

class ReportController extends Controller
{
    protected $queryBuilder;

    public function __construct(IndicatorQueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    public function shippedBilling()
    {
        return view('reports.shipped-billing');
    }

    public function shippedBillingReport(Request $request)
    {
        try {
            $request->validate([
                'csv_file' => 'required|file|mimes:csv,txt|max:29000'
            ]);

            $email = Auth::user()->email;

            $file = $request->file('csv_file');
            $rawContent = file_get_contents($file->getRealPath());

            // Détecter l'encodage probable
            $encoding = mb_detect_encoding($rawContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);

            // Convertir en UTF-8 si nécessaire
            if ($encoding !== 'UTF-8') {
                $rawContent = iconv($encoding, 'UTF-8//IGNORE', $rawContent);
            }

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

                // Associer les headers aux colonnes
                $data[] = array_combine($headers, $row);
            }

            fclose($tempFile);

            // Sauvegarder en fichier temporaire JSON pour traiter par chunk
            $key = 'csv_upload_' . uniqid();
            $filePath = storage_path("app/tmp/{$key}.json");
            $chunks = array_chunk($data, 1000);

            $jsonData = json_encode([
                'chunks'    => $chunks,
                'headers'   => $headers,
            ]);

            if ($jsonData === false) {
                Log::error('json_encode error: ' . json_last_error_msg());
            } else {
                file_put_contents($filePath, $jsonData);
            }

            UploadShipstationBillingCSV::dispatch($key, $email)->onConnection('database');

            return redirect()->back()->with('warning', 'CSV upload in progress. You will receive the processed file by email.');
        } catch (\Exception $e) {
            Log::error('CSV upload error: ' . $e->getMessage());
            return redirect()->back()->with('error', __('main.csv.upload_failed'));
        }
    }

    public function exportCsv($headers, $data)
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i');

        return Excel::download(new ShippedBillingReportExport($headers, $data), 'shipped_orders_report_' . $timestamp . '.csv');
    }

    public function exportReport(Request $request)
    {
        try {
            //dd($request->all());
            $title      = $request->title;
            $_params    = $request->params;
            $methode    = $request->methode;
            $_headers   = $request->header;

            $headers = json_decode($_headers, true);
            $params = json_decode($_params, true);

            $object = new ArrayObject($params, ArrayObject::ARRAY_AS_PROPS);

            $query = $this->queryBuilder->$methode($object);
            $response = $query->get()->toArray();
            $data = json_decode(json_encode($response), true);

            $timestamp = Carbon::now('America/Chicago')->format('Y-m-d_H-i');

            return Excel::download(new ExportCSVReport($headers, $data), $title . ' - ' . $timestamp . '.csv');
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function sendEmailReport(Request $request)
    {
        try {
            $title = $request->input('title') ?? '' /* 'Report' */;
            $email = $request->input('email');

            /* Check if mail is missing before moving forward with rpeort */
            // Vérifier si les paramètres sont bien envoyés
            if (!$email) {
                return response()->json(['error' => __('messages/controller.report.email.missing')], 400);
            }

            $_params = $request->params;
            $methode = $request->methode; /* is it correct as methode? $request carries methode or method? */
            $_headers = $request->header;

            $user = Auth::user()->name;

            $headers = json_decode($_headers, true);
            $params = json_decode($_params, true);

            $object = new ArrayObject($params, ArrayObject::ARRAY_AS_PROPS);

            $query = $this->queryBuilder->$methode($object);
            $_data = $query->get()->toArray();
            $data = json_decode(json_encode($_data), true);



            // Générer un nom unique pour le fichier CSV
            $fileName = 'report_' . Carbon::now()->format('Y-m-d_H-i-s') . '.csv';
            $filePath = storage_path('app/' . $fileName);
            $file = fopen($filePath, 'w');

            fputcsv($file, array_values($headers));

            // Ajouter les données filtrées
            foreach ($data as $row) {
                $filteredRow = [];
                foreach (array_keys($headers) as $column) {
                    $filteredRow[] = $row[$column] ?? null;
                }
                fputcsv($file, $filteredRow);
            }

            fclose($file);

            // Envoyer l'email avec le fichier attaché
            Mail::send([], [], function ($message) use ($email, $filePath, $title, $user) {
                $message->to($email)
                    ->subject(__('messages/controller.report.email.subject') . ' ' . $title . ' - ' . Carbon::now()->toDayDateTimeString())
                    ->html(__('messages/controller.report.email.html', ['title' => $title, 'user' => $user, 'date' => Carbon::now()->toDayDateTimeString()]))
                    ->attach($filePath);
            });

            // Supprimer le fichier après l'envoi
            unlink($filePath);

            return redirect()->back()->with('success', __('messages/controller.report.email.success'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }
}
