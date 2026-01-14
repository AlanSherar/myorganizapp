<?php

namespace App\Http\Controllers\Admin;

use App\Models\Bin;
use App\Models\Site;
use App\Models\Location;
use App\Helpers\LogHelper;
use App\Imports\CSVImport;
use App\Models\LocationType;
use Illuminate\Http\Request;
use App\Services\LocationService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductMainLocation;
// use App\Models\Warehouse;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    public function __construct() {}

    public function locationsList(Request $request)
    {
        //para usar filtros

        // Iniciamos la consulta base
        $query = LocationService::listQuery($request);

        $site_id = request()->query('site', 'all');
        $rows = request()->query('per_page', 10);

        // Ejecutamos la consulta con paginación
        $locations = $query->paginate($rows);
        // Récupère toutes les sites
        $sites = Site::all();

        $types = LocationType::where('active', 1)->get();

        // Obtenemos el site seleccionado si se especificó
        if ($site_id !== 'all' && !empty($site_id)) {
            $site_selected = $sites->find($site_id);
        } else {
            $site_selected = null; // No hay site específico seleccionado
        }

        return view('locations.list', compact('locations', 'sites', 'site_selected', 'types',  'site_id', 'rows'));
    }

    public function locationCreate()
    {
        try {
            $bins = Bin::where('status', "Active")->where('location_id', null)->get();
            $types = LocationType::all();
            $sites = Site::all();
            // $warehouses = Warehouse::all();

            return view('locations.create', compact('types', 'sites', 'bins'));
        } catch (\Throwable $th) {
            return redirect()->route('locationsList')->with('error', $th->getMessage());
        }
    }

    public function locationStore(Request $request)
    {
        try {
            // dd($request->all());
            // Validación de los datos de la solicitud
            $request->validate([
                'barecode'         => 'required|string|max:80',
                'label'            => 'required|string|max:80',
                'type_id'          => 'required|int|max:100',
                'site_id'          => 'required|int|max:100',
            ]);

            // Later on change by a filter in the product search input in location create view
            // foreach ($products as $key => $product) {
            //     $exists = ProductMainLocation::where('product_id', $product['product_id'])
            //         ->where('site_id', $request->site_id)->exists();
            //     if (!$exists) {
            //         return redirect()->route('locationsList')->with('error', __('messages/controller.admin.location.create.error.product_not_found'));
            //     }
            // }

            // Verificación de si el barcode ya existe
            $existingLocation = Location::where('barecode', $request->barecode)->first();
            if ($existingLocation) {
                return redirect()->route('locationsList')->with('error', __('messages/controller.admin.location.create.error.barecode_already_exists'));
            }

            DB::beginTransaction();

            // Creación de la ubicación en la base de datos
            $location = Location::create([
                'barecode'         =>  $request->barecode,
                'label'            =>  $request->label,
                'type_id'          =>  $request->type_id,
                'site_id'          =>  $request->site_id,
            ]);

            $bins_ids = json_decode($request->input('bins_ids'), true);

            /* foreach ($bins_ids as $key => $value) {
                DB::table('locations_bins')->insert([
                    'location_id'     => $location->id,
                    'bin_id'        => $value
                ]);
            } */

            if ($bins_ids) {
                foreach ($bins_ids as $key => $value) {
                    bin::find($value)->update([
                        'location_id' => $location->id,
                    ]);
                }
            }

            DB::commit();
            // add the same on update methode

            LogHelper::handleLog('create', '', '', 'location', $location->label);

            return redirect()->route('locationsList')->with('success', __('messages/controller.admin.location.create.success'));
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->route('locationsList')->with('error', $th->getMessage());
        }
    }

    public function locationEdit($id)
    {
        try {
            $location = Location::findOrFail($id);
            $types = LocationType::all();
            $sites = Site::all();
            $site = $location->site()->first();
            
            $bins = $site->bins()
                ->where(function ($query) use ($location) {
                    $query->where('location_id', $location->id)
                        ->orWhereNull('location_id');
                })->get();

            $linkedBinIds = $location->bins()->get()->pluck('id')->toArray();
            
            $products = $location->products();

            return view('locations.edit', compact('location', 'types', 'sites', 'bins', 'linkedBinIds', 'products'));
        } catch (\Throwable $th) {
            return redirect()->route('locationsList')->with('error', $th->getMessage());
        }
    }
    public function locationDetails($id)
    {
        try {
            $location = Location::findOrFail($id);
            $site = $location->site()->first();
            $bins = $location->bins()->get();
            $products = $location->products();

            return view('locations.details', compact('location', 'site', 'bins', 'products'));
        } catch (\Throwable $th) {
            return redirect()->route('locationsList')->with('error', $th->getMessage());
        }
    }
    public function locationUpdate(Request $request, $id)
    {
        try {
            // dd($request->all());
            $request->validate([
                'barecode'         => 'required|string|max:80',
                'label'            => 'required|string|max:80',
                'type_id'          => 'required|int|max:100',
                'site_id'          => 'required|int|max:100',
                'active'           => 'required|int|max:1',
            ]);

            $location = Location::findOrFail($id);

            // Verificación de si el barcode ya existe (excluyendo la ubicación actual)
            $existingLocation = Location::where('barecode', $request->barecode)->where('id', '!=', $id)->first();

            // Si existe, devolvemos un mensaje de error
            if ($existingLocation) {
                return redirect()->route('locationsList')->with('error', __('messages/controller.admin.location.update.error.barecode_already_exists'));
            }
            DB::beginTransaction();
            $location->update([
                'barecode'         =>  $request->barecode,
                'label'            =>  $request->label,
                'type_id'          =>  $request->type_id,
                'site_id'          =>  $request->site_id,
                'active'           =>  $request->active,
            ]);

            $bins_ids = json_decode($request->input('bins_ids'), true);

            // Clean IDs to avoid invalid data
            $bins_ids = array_filter($bins_ids, fn($id) => is_numeric($id));

            foreach ($location->bins()->get() as $bin) {
                if (!in_array($bin->id, $bins_ids)) {
                    $bin->location_id = null;
                    $bin->save();
                }
            }

            // Insert new associations if bins are selected
            if (!empty($bins_ids)) {

                foreach ($bins_ids as $bin_id) {
                    $bin = Bin::find($bin_id);
                    $bin->location_id = $location->id;
                    $bin->save();
                }
            }

            LogHelper::handleLog('update', '', '', 'location', $location->label);
            DB::commit();
            return redirect()->route('locationsList')->with('success', __('messages/controller.admin.location.update.success'));
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->withInput()->with('error', $th->getMessage());
        }
    }

    public function locationStatusToggle(Request $request)
    {
        $location_id = $request->location_id;

        if (!$location_id) {
            return redirect()->route('locationsList')->with('error',  __('messages/controller.admin.location.error.id_missing_deletion'));
        }

        $location = Location::find($location_id);

        if (!$location) {
            return redirect()->route('locationsList')->with('error', __('messages/controller.admin.location.error.reference_not_found'));
        }

        $newActive = $location->active == 1 ? 0 : 1;

        if ($location->update(['active' => $newActive])) {
            if ($newActive == 1) {
                LogHelper::handleLog('activate', '', '', 'location', $location->label);

                return redirect()->route('locationsList', ['page' => $request->page ? $request->page : 1])->with('success', __('messages/controller.admin.location.status.success_activation'));
            }

            LogHelper::handleLog('deactivate', '', '', 'location', $location->label);

            return redirect()->route('locationsList', ['page' => $request->page ? $request->page : 1])->with('success', __('messages/controller.admin.location.status.success_deactivation'));
        }

        return redirect()->route('locationsList')->with('error', __('messages/controller.main.unexpected', ['message' => $location->name]));
    }

    public function locationsUploadCSV(Request $request)
    {
        // Validar que se ha enviado un archivo
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        // Obtener el archivo
        $file = $request->file('csv_file');

        $import = new CSVImport();
        Excel::import($import, $file);

        // Abrir el archivo
        $data = $import->rows;

        // Leer la primera línea (encabezados)
        $headers = array_map('strtolower', $data[0]);
        $updateFields = array_diff($headers, ['barecode']); // excluye la clave única

        unset($data[0]);

        // Verificar que los encabezados son correctos
        $requiredHeaders = [
            'barecode',
            'label',
            'type_id',
            'site_id',
        ];

        // Verificar que los encabezados requeridos estén presente en el archivo CSV
        $missingHeaders = array_diff($requiredHeaders, $headers);

        if (!empty($missingHeaders)) {
            return redirect()->route('locationsList')
                ->with('error', __(
                    'main.csv.missing_headers',
                    ['headers' => implode(', ', $missingHeaders)]
                ));
        }

        // Verificar que los encabezados adicionales sean válidos
        $invalidHeaders = array_diff($headers, $requiredHeaders);
        if (!empty($invalidHeaders)) {
            return redirect()->route('locationsList')
                ->with('error', __(
                    'main.csv.invalid_headers',
                    ['headers' => implode(', ', $invalidHeaders)]
                ));
        }

        // Inicializar contadores
        $totalRows = 0;
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        $barecodesSeen = [];

        $upsertData = []; // Array para almacenar los datos para la inserción en la base de datos
        // Procesar cada línea del CSV
        foreach ($data as $row) {
            $totalRows++;

            // Verificar si la fila contiene datos válidos
            if (empty($row)) {
                $errorCount++;
                $errors[] = __(
                    'main.csv.invalid_row',
                    [
                        'row' => $totalRows
                    ]
                );
                continue;
            }

            // Crear un array asociativo con los datos
            $rowData = array_combine($headers, $row);
            // Validar los datos de la fila
            $validator = Validator::make($rowData, [
                'barecode' => 'required|string|max:80',
                'label' => 'required|string|max:80',
                'type_id' => 'nullable|integer|exists:location_types,id',
                'site_id' => 'nullable|integer|exists:sites,id',
            ]);

            //verificar el validator
            if ($validator->fails()) {
                $errorCount++;
                $errors[] = __(
                    'main.csv.validator_fail',
                    [
                        'row' => $totalRows,
                        'error' => $validator->errors()->first(),
                    ]
                );
                continue;
            }

            // Verificar si existe algun elemento del array upsertData que su barecode sea el del rowData barecode
            if (isset($barecodesSeen[$rowData['barecode']])) {
                $errorCount++;
                $errors[] = __(
                    'main.csv.already_exists',
                    [
                        'resource' => __('entity.location'),
                        'idKey' => 'barecode',
                        'idValue' => $rowData['barecode'],
                    ]
                );
                continue;
            }

            $barecodesSeen[$rowData['barecode']] = true;

            // Añadir el registro al array de los datos para la inserción en la base de datos
            $upsertData[] = $rowData;
            $successCount++;
        }

        // Insertar los registros en la base de datos en lotes
        if (!empty($upsertData)) {
            try {
                $batchSize = 500;
                foreach (array_chunk($upsertData, $batchSize) as $batch) {

                    Location::upsert(
                        $batch,
                        ['barecode'],     // clave única
                        $updateFields     // campos a actualizar
                    );
                }

                LogHelper::handleLog('import', '', '', 'location');
            } catch (\Exception $e) {
                $errorCount += count($upsertData);
                $errors[] = __(
                    'main.csv.insertion_error',
                    ['error' => $e->getMessage()]
                );
            }
        }

        // Preparar mensaje de respuesta
        if ($errorCount > 0) {
            $errorMessages = implode('<br>', $errors);
            $message = __(
                'main.csv.partial_success',
                [
                    'resource' => __('entity.location'),
                    'success' => $successCount,
                    'error' => $errorCount,
                    'total' => $totalRows
                ]
            );

            return redirect()->route('locationsList')
                ->with('warning', $message)
                ->with('error', $errorMessages);
        } else {
            return redirect()->route('locationsList')
                ->with('success', __(
                    'main.csv.success',
                    [
                        'resource' => __('entity.location'),
                        'count' => $successCount,
                    ]
                ));
        }
    }

    public function getLocationByBin($binId)
    {
        $bin = Bin::where('bin_id', $binId)->first();

        if (!$bin) {
            return response()->json(['location_id' => 'Bin not found'], 404);
        }

        return response()->json(['location_id' => $bin->location()->id]);
    }
}
