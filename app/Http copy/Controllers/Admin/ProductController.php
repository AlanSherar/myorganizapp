<?php
namespace App\Http\Controllers\Admin;

use App\Models\Site;
use App\Models\Company;
use App\Models\Product;
use App\Helpers\LogHelper;
use App\Models\ControlType;
use App\Models\ProductType;
use App\Jobs\UploadAltIdCSV;
use App\Jobs\UploadLabelCSV;
use Illuminate\Http\Request;
use App\Exports\EmptyCSVFile;
use App\Jobs\UploadProductCSV;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Jobs\UploadComponentCSV;
use App\Services\ProductService;
use App\Models\PackagingReference;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Jobs\UploadUnitsOfMeasureCSV;
use App\Models\ProductMainLocation;
use App\Services\InventoryProductService;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    private ProductService $service;
    private $inventoryProductService;

    public function __construct(
        ProductService $service, 
        InventoryProductService $inventoryProductService)
    {
        $this->service = $service;
        $this->inventoryProductService = $inventoryProductService;
    }

    public function list(Request $request)
    {
        try {

            $rows = $request->per_page ?? 10;

            $query = $this->service::listQuery($request);

            $products = $query->with('company')->with('pref_package')->with('type')->with('control_type')->with('units_of_measure')->orderBy('name')->paginate($rows);
            $control_type_selected = $request->control_type ?? '';

            $control_types = ControlType::all();
            $types = ProductType::all();
            $products->appends($request->all());

            return view('products.list', compact('products', 'control_type_selected', 'types', 'control_types'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __(
                'main.unexpected_error',
                [
                    'message' => $th->getMessage(),
                ]
            ));
        }
    }

    public function create()
    {
        try {
            // get info for the select dropdowns 
            $countries = getISOCountries();
            $companies = Company::where('active', 1)->orderBy('name')->get();
            $measureUnits = getMeasureUnits();
            $types = ProductType::all();
            $control_types = ControlType::all();
            $packagings = PackagingReference::where('is_active', 1)->orderBy('sku')->get()->load('packagingProvider');
            $sites = Site::where('active', 1)->get();

            return view('products.create', compact('countries', 'packagings', 'companies', 'measureUnits', 'types', 'control_types', 'sites'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __(
                'main.unexpected_error',
                [
                    'message' => $th->getMessage(),
                ]
            ));
        }
    }

    public function store(Request $request)
    {
        try {
            // Validación de los datos de la solicitud
            $validator = Validator::make($request->all(), [
                'sku'                   => 'required|string|max:50|not_in:products,sku',
                'company_id'            => 'required|int|exists:companies,id',
                'company_item_number'   => 'required|string|max:50',
                'name'                  => 'required|string|max:100',
                'description'           => 'nullable|string|max:400',
                'barcode'               => 'required|string|max:20|not_in:barcodes,barcode',
                'barcode_notes'         => 'nullable|string|max:255',
                'units_of_measure'      => 'nullable|string',
                'alt_ids'               => 'nullable|string',
                'width'                 => 'required|numeric|between:0,9999999.999',
                'width_units'           => 'required|string|in:cm,inch',
                'length'                => 'required|numeric|between:0,9999999.999',
                'length_units'          => 'required|string|in:cm,inch',
                'height'                => 'required|numeric|between:0,9999999.999',
                'height_units'          => 'required|string|in:cm,inch',
                'weight'                => 'required|numeric|between:0,9999999.999',
                'weight_units'          => 'required|string|in:kg,lb',
                'volume'                => 'required|numeric|between:0,9999999.999',
                'volume_units'          => 'required|string|in:cm³,inch³',
                'country_origin'        => 'nullable|string|max:80',
                'hs_code'               => 'nullable|string|max:50',
                'picture'               => 'nullable|string|max:255',
                'control_type_id'       => 'required|int|exists:control_types,id',
                'type_id'               => 'required|int|exists:product_types,id',
                'kit'                   => 'nullable|int|max:1',
                'active'                => 'nullable|int|max:1',
                'discontinued'          => 'nullable|int|max:1',
                'pref_package_id'       => 'nullable|int|exists:packaging_references,id',
                'hot_item'              => 'nullable|int|max:1',
                'price'                 => 'nullable|numeric|between:0,9999999.99',
            ]);

            // if validator fails, redirect with error message and input
            if ($validator->fails()) {
                return redirect()->back()->withInput()->with('error', __(
                    'main.create_failed_info',
                    [
                        'resource' => __('entity.product'),
                        'message' => $validator->errors()->first(),
                    ]
                ));
            }

            // Check if product with same SKU and company_id already exists
            $existingProduct = Product::where('sku', $request->sku)
                ->where('company_id', $request->company_id)
                ->first();

            if ($existingProduct) {
                return redirect()->back()->withInput()->with('error', __(
                    'main.messages.already_exists',
                    [
                        'resource' => __('entity.product'),
                        'idKey' => 'sku : ' . __('entity.company'),
                        'idValue' => $request->sku . ' : ' . $request->company_id . ')'
                    ]
                ));
            }

            // get components from components, parse it from json string to php object, and make sure it is not an empty array
            $components = !empty($request->components) ? json_decode($request->components) : [];

            if ($request->kit && ($components == null || !is_array($components) || count($components) == 0)) {
                //Make sure all old() data is correctly set in view
                return redirect()->back()->withInput()->with('error', __(
                    'products/main.messages.kit_empty',
                    [
                        'resource' => __('entity.product'),
                    ]
                ));
            }

            $product_labels = !empty($request->labels) ? json_decode($request->labels) : [];

            // get altIds from alt_ids, parse it from json string to php object
            $altIds = json_decode($request->alt_ids);

            // get barcodesData from barcodes, parse it from json string to php object, and make sure it is not an empty array
            $barcodesData = json_decode($request->units_of_measure);
            $created = $this->service->store($request, $barcodesData, $components, $altIds, $product_labels);

            $product_id = $created->product->id ?? null;
            $main_location = json_decode($request->main_location);

            if($main_location && $product_id){
                $this->inventoryProductService->assignLocationMain($main_location, $product_id);
            }

            if ($created->failed) {
                return redirect()->back()->withInput()->with('error', __(
                    'main.create_failed_info',
                    [
                        'resource' => __('entity.product'),
                        'message' => $created->message,
                    ]
                ));
            } else {
                LogHelper::handleLog('create', '', '', 'product', $request->sku);
            }

            return redirect()->route('productsList')->with('success', __(
                'main.create_success_info',
                [
                    'resource' => __('entity.product'),
                    'name' => $request->sku
                ]
            ));
        } catch (\Throwable $th) {
            return redirect()->back()->withInput()->with('error', __(
                'main.create_failed_info',
                [
                    'resource' => __('entity.product'),
                    'message' => $th,
                ]
            ));
        }
    }

    public function edit($id)
    {
        try {
            $product = $this->service->getByID($id);

            if (!$product) {
                return redirect()->route('productsList')->with('error', __('messages/controller.admin.product.error.reference_not_found'));
            }
            $countries = getISOCountries();
            $companies = Company::where('active', 1)->orderBy('name')->get();
            $measureUnits = getMeasureUnits();
            $types = ProductType::all();
            $control_types = ControlType::all();
            $packagings = PackagingReference::where('is_active', 1)->orderBy('sku')->get()->load('packagingProvider');
            $sites = Site::where('active', 1)->get();

            return view('products.edit', compact('product', 'packagings', 'countries', 'companies', 'measureUnits', 'types', 'control_types', 'sites'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __(
                'main.unexpected_error',
                [
                    'message' => $th->getMessage(),
                ]
            ));
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $product = $this->service->getByID($id);

            // check if product exists
            if (!$product) {
                return redirect()->route('productsList')->with('error', __(
                    'main.messages.reference_not_found',
                    [
                        'id' => $id,
                    ]
                ));
            }

            // Validación de los datos de la solicitud
            $validator = Validator::make($request->all(), [
                'sku'                   => 'required|string|max:50',
                'company_id'            => 'required|int|exists:companies,id',
                'company_item_number'   => 'required|string|max:50',
                'name'                  => 'required|string|max:100',
                'description'           => 'nullable|string|max:400',
                'barcode'               => 'required|string|max:20',
                'barcode_notes'         => 'nullable|string|max:255',
                'units_of_measure'      => 'nullable|string',
                'alt_ids'               => 'nullable|string',
                'width'                 => 'required|numeric|between:0,9999999.999',
                'width_units'           => 'required|string|in:cm,inch',
                'length'                => 'required|numeric|between:0,9999999.999',
                'length_units'          => 'required|string|in:cm,inch',
                'height'                => 'required|numeric|between:0,9999999.999',
                'height_units'          => 'required|string|in:cm,inch',
                'weight'                => 'required|numeric|between:0,9999999.999',
                'weight_units'          => 'required|string|in:kg,lb',
                'volume'                => 'required|numeric|between:0,9999999.999',
                'volume_units'          => 'required|string|in:cm³,inch³',
                'country_origin'        => 'nullable|string|max:80',
                'hs_code'               => 'nullable|string|max:50',
                'picture'               => 'nullable|string|max:255',
                'control_type_id'       => 'required|int|exists:control_types,id',
                'type_id'               => 'required|int|exists:product_types,id',
                'kit'                   => 'nullable|int|max:1',
                'active'                => 'nullable|int|max:1',
                'discontinued'          => 'nullable|int|max:1',
                'pref_package_id'       => 'nullable|int|exists:packaging_references,id',
                'hot_item'              => 'nullable|int|max:1',
                'price'                 => 'nullable|numeric|between:0,9999999.99',
            ]);

            // if validator fails, redirect with error message and input
            if ($validator->fails()) {
                return redirect()->back()->withInput()->with('error', __(
                    'main.create_failed_info',
                    [
                        'resource' => __('entity.product'),
                        'message' => $validator->errors()->first(),
                    ]
                ));
            }

            $updated = $this->service->update($request, $product);

            $product_id = $updated->product->id ?? null;

            if($product_id){
                ProductMainLocation::where('product_id', $product_id)->delete();
            }

            $main_location = json_decode($request->main_location);

            if($main_location && $product_id){
                $this->inventoryProductService->assignLocationMain($main_location, $product_id);
            }

            if ($updated->failed) {
                return redirect()->back()->withInput()->with('error', __(
                    'main.update_failed_info',
                    [
                        'resource' => __('entity.product'),
                        'message' => $updated->message,
                    ]
                ));
            } else {
                LogHelper::handleLog('update', '', '', 'product', $request->sku);
            }

            return redirect()->route('productsList')->with('success', __(
                'main.update_success_info',
                [
                    'resource' => __('entity.product'),
                    'name' => $request->sku
                ]
            ));
        } catch (\Throwable $th) {
            return redirect()
                ->route('productEdit', ['id' => $id])
                ->withInput()
                ->with('error', __('main.unexpected_error', [
                    'message' => $th->getMessage(),
                ]));
        }
    }

    public function statusToggle(Request $request)
    {
        try {
            $product_id = $request->id;

            if (empty($request->id) || is_null($request->id) || $request->id == '') {
                return redirect()->route('productsList')->with('error',  __('main.messages.id_missing'));
            }

            $product = Product::find($product_id);

            if (!$product) {
                return redirect()->route('productsList')->with('error', __('main.messages.reference_not_found'));
            }

            $activate = ProductService::statusToggle($product);
            // recibimos 1 si fue activado y 0 si fue desactivado

            if ($activate == 1) {
                LogHelper::handleLog('activate', '', '', 'product', $product->sku);
                return redirect()->route('productsList')->with('success', __(
                    'main.messages.activate_success',
                    [
                        'resource' => __('entity.product'),
                        'id' => $product->name,
                    ]
                ));
            }

            LogHelper::handleLog('deactivate', '', '', 'product', $product->sku);
            return redirect()->route('productsList')->with('success', __(
                'main.messages.deactivate_success',
                [
                    'resource' => __('entity.product'),
                    'id' => $product->name,
                ]
            ));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('main.unexpected_error', ['message' => $th->getMessage()]));
        }
    }

    public function uploadCSV(Request $request)
    {
        try {
            // Obtener el tipo de archivo
            $type = $request->input('type');

            $request->validate([
                'csv_file' => 'required|file|mimes:csv,txt|max:29000'
            ]);

            $email = Auth::user()->email;

            $file = $request->file('csv_file');
            $filePath = $file->getRealPath();

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

                $data[] = array_combine($headers, $row);
            }

            fclose($tempFile);

            $requiredHeaders = getCSVHeaders($type);
            $missingHeaders = array_diff($requiredHeaders, $headers); // esto retorna los headers requeridos que no estan en el csv

            if (!empty($missingHeaders)) {
                return redirect()->route('productsList')
                    ->with('error', __(
                        'main.csv.missing_headers_for_type',
                        ['type' => __('entity.' . $type), 'headers' => implode(', ', $missingHeaders)]
                    ));
            }

            // Verificar que los encabezados adicionales sean válidos
            $invalidHeaders = array_diff($headers, $requiredHeaders); // esto retorna los headers que estan en el csv pero no son requeridos
            if (!empty($invalidHeaders)) {
                return redirect()->route('productsList')
                    ->with('error', __(
                        'main.csv.invalid_headers_for_type',
                        ['type' => __('entity.' . $type), 'headers' => implode(', ', $invalidHeaders)]
                    ));
            }

            $key = 'csv_upload_' . uniqid();
            $filePath = storage_path("app/tmp/{$key}.json");
            $chunks = array_chunk($data, 1000);

            $jsonData = json_encode([
                'type' => $type,
                'chunks' => $chunks,
                'headers' => $headers,
                'requiredHeaders' => $requiredHeaders,
            ]);

            if ($jsonData === false) {
                Log::error('json_encode error: ' . json_last_error_msg());
            } else {
                file_put_contents($filePath, $jsonData);
            }

            switch ($type) {
                case 'products':
                    UploadProductCSV::dispatch($key, $email)->onConnection('database');
                    //$uploadRes = $this->service->uploadProducts($type, $data, $headers, $requiredHeaders);
                    break;
                case 'alt_ids':
                    if (!Company::exists()) {
                        return redirect()->back()->with('error', 'No companies found');
                    }

                    if (!Product::exists()) {
                        return redirect()->back()->with('error', 'No products found');
                    }

                    UploadAltIdCSV::dispatch($key, $email)->onConnection('database');
                    //$uploadRes = $this->service->uploadAltIds($type, $data, $headers, $requiredHeaders);
                    break;
                case 'units_of_measure':
                    if (!Company::exists()) {
                        return redirect()->back()->with('error', 'No companies found');
                    }

                    if (!Product::exists()) {
                        return redirect()->back()->with('error', 'No products found');
                    }

                    UploadUnitsOfMeasureCSV::dispatch($key, $email)->onConnection('database');
                    //$uploadRes = $this->service->uploadUnitsOfMeasure($type, $data, $headers, $requiredHeaders);
                    break;
                case 'labels':
                    if (!Company::exists()) {
                        return redirect()->back()->with('error', 'No companies found');
                    }
                    UploadLabelCSV::dispatch($key, $email)->onConnection('database');
                    //$uploadRes = $this->service->uploadLabels($type, $data, $headers, $requiredHeaders);
                    break;
                case 'components':
                    if (!Company::exists()) {
                        return redirect()->back()->with('error', 'No companies found');
                    }

                    UploadComponentCSV::dispatch($key, $email)->onConnection('database');
                    //$uploadRes = $this->service->uploadComponents($type, $data, $headers, $requiredHeaders);
                    break;
                default:
                    return redirect()->back()->with('error', __('main.csv.invalid_type'));
                    break;
            }

            LogHelper::handleLog('import', '', '', $type, 'csv');

            return redirect()->back()->with('warning', 'Upload CSV in process...');
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function downloadEmptyCSV(Request $request)
    {
        try {
            $type = $request->type;

            $headers = getCSVHeaders($type);

            return Excel::download(new EmptyCSVFile($headers), $type . '.csv');
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function print(Request $request)
    {   
        try {
            $barcodeLabelsJson = $request->barcodeLabels;
            $lotLabelsJson = $request->lotLabels;
            $serialLabelsJson = $request->serialLabels;
            $format = $request->format;
            $orientation = $request->orientation ?? 'landscape';

            $barcodeLabels = json_decode($barcodeLabelsJson, true) ?: [];
            $lotLabels = json_decode($lotLabelsJson, true) ?: [];
            $serialLabels = json_decode($serialLabelsJson, true) ?: [];

            if (!$format) {
                if (!empty($barcodeLabels)) {
                    $format = 'sheet';
                } elseif (!empty($lotLabels) || !empty($serialLabels)) {
                    $format = 'sheet';
                }
            }

            if (empty($barcodeLabels) && empty($lotLabels) && empty($serialLabels)) {
                return redirect()->route('productsList')->with('error', __('messages/controller.main.error', ['message' => 'No labels to print']));
            }

            $data = compact('barcodeLabels', 'format', 'lotLabels', 'serialLabels');
            if ($format === 'sheet') {
                $pdf = PDF::loadView('products.print-sheet', $data);
                $pdf->setPaper('A4', 'portrait');
                $pdf->set_option('margin_top', 0);
                $pdf->set_option('margin_bottom', 0);
                $pdf->set_option('margin_left', 0);
                $pdf->set_option('margin_right', 0);
            } elseif ($format === 'roll') {
                $pdf = PDF::loadView('products.print-roll-' . $orientation, $data);
                if ($orientation === 'landscape') {
                    $pdf->setPaper([0, 0, 144, 72]);
                } else {
                    $pdf->setPaper([0, 0, 72, 144]);
                }
            } else {
                $pdf = PDF::loadView('products.print-sheet', $data);
                $pdf->setPaper('A4', 'portrait');
            }

            return response($pdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"labels_{$format}_{$orientation}.pdf\"",
            ]);
        } catch (\Exception $e) {
            return redirect()->route('productsList')->with('error', $e->getMessage());
        }
    }

    public function getByCompany($companyId)
    {
        $products = Product::with('company', 'inventory_products')->where('company_id', $companyId)->get();
        return response()->json($products);
    }


}
