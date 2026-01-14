<?php

namespace App\Http\Controllers\Supervisor;

use AuthHelper;
use App\Models\Carrier;
use App\Models\Company;
use App\Models\Setting;
use App\Models\SaleOrder;
use App\Helpers\LogHelper;
use Illuminate\Http\Request;
use App\Exports\EmptyCSVFile;
use App\Models\SaleOrderItem;
use App\Models\PackagingOrder;
use App\Services\ClientService;
use App\Models\InventoryProduct;
use App\Support\Date\ExceptedDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\SalesOrdersService;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Services\ShipStationAPIService;
use App\Support\SaleOrder\SaleOrderCSV;
use App\Support\SaleOrder\SaleOrderDataBuilder;
use App\Support\Shipstation\ShipstationSupport;

class SalesOrdersController extends Controller
{
    protected $salesOrdersService;
    protected $ShipStationAPIService;

    public function __construct(SalesOrdersService $salesOrdersService, ShipStationAPIService $ShipStationAPIService)
    {
        $this->salesOrdersService = $salesOrdersService;
        $this->ShipStationAPIService = $ShipStationAPIService;
    }

    public function saleOrdersList(Request $request)
    {
        try {
            $companies = Company::where('active', 1)->get();
            $salesOrders = $this->salesOrdersService->saleOrdersList($request);

            $orderSkuMap = $salesOrders
                ->reject(function ($order) {
                    return in_array($order->status, ['shipped', 'completed'])
                        /* || $order->order_type === 'cross_docking' */;
                })
                ->mapWithKeys(function ($order) {
                    return [
                        $order->order_number => $order->items->map(function ($item) use ($order) {
                            return [
                                'order_type'        => $order->order_type,
                                'status'            => $order->status,
                                'last_status'       => $order->last_status ?? $order->status,
                                'product_id'        => $item->product_id,
                                'site_id'           => $item->site_id,
                                'quantity'          => $item->quantity,
                            ];
                        })->toArray()
                    ];
                });

            $update = SalesOrdersService::updateOrdersStatus($orderSkuMap);

            if ($update) {
                $salesOrders = $this->salesOrdersService->saleOrdersList($request);
            }

            $importErrorsKey = session()->pull('import_errors_key');

            return response()
                ->view('sales-orders.list', compact('salesOrders', 'companies', 'importErrorsKey'))
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (\Throwable $th) {
            Log::info($th->getMessage());
            return redirect()->route('saleOrdersList')->with('error', $th->getMessage());
        }
    }

    public function saleOrderCreate()
    {
        try {
             $companies = Company::where('active', 1)->get();
            $carriers = Carrier::where('active', 1)->with('services')->get();
            $status_default = Setting::where('key', 'sales_orders_status')->value('value');
            $expectedDate = ExceptedDate::getExceptedDate();

            return view('sales-orders.create', compact('companies', 'carriers', 'status_default', 'expectedDate'));
        } catch (\Throwable $th) {
            Log::info($th->getMessage());
            return redirect()->route('saleOrdersList')->with('error', $th->getMessage());
        }
    }

    public function saleOrderStore(Request $request)
    {
        try {
            DB::beginTransaction();
            $client = ClientService::upsertClientFromSaleOrder($request);

            if (!$client["ok"]) {
                return back()->withErrors([
                    'error' => 'An error occurred: ' . $client["message"],
                ])->withInput();
            }

            $client = $client["client"];

            $request->merge(['client_id' => $client->id]);
            $request->merge([
                'status' => $request->input('status') ?? $request->input('status_hidden')
            ]);

            if (AuthHelper::authType(['CLIENT'])) {
                $request->merge(['status' => 'picked']);
            }

            $validateFields = SaleOrderDataBuilder::rulesStore(null);
            $validatedData = $request->validate($validateFields);

            $attachment = null;
            if ($request->hasFile('attachment')) {
                $attachment = file_get_contents($request->file('attachment')->getRealPath());
            }

            $attachment = SalesOrdersService::getAttachmentContent($request->file('attachment'));
            //$orderNumber = SalesOrdersService::generateOrderNumber();
            $orderNumber = SaleOrder::generateOrderNumber();

            $saleOrderData = SaleOrderDataBuilder::build($validatedData, $orderNumber, $attachment);
            $saleOrder = SaleOrder::create($saleOrderData);

            if ($saleOrder) {
                $site_id = $request->site_id ?? Auth::user()->site_id;
                $products = json_decode($request->input('products'), true);
                SalesOrdersService::storeOrderProducts($saleOrder->order_number, $products, false, $site_id);
                LogHelper::handleLog('store', '', '', 'sale_orders', $saleOrder->order_number);
                DB::commit();

                return redirect()->route('saleOrdersList')->with('success', 'Sales Order created successfully!');
            }

            return back()->withErrors(['error' => 'An error occurred']);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::info($th->getMessage());
            return back()->withErrors([
                'error' => 'An error occurred: ' . $th->getMessage(),
            ])->withInput();
        }
    }

    public function saleOrderEdit($orderNumber)
    {

        $companies = Company::where('active', 1)->get();
        $saleOrder = SaleOrder::with(
            'items.product.company',
            'items.location',
            'items.warehouse',
            'items.bin',
            'items.product.inventory_products',
            'items.product.main_locations'
        )
            ->where('order_number', $orderNumber)->first();

        if ($saleOrder->status == 'canceled' || $saleOrder->status == 'completed' || $saleOrder->status == 'shipped') {
            return redirect()->route('saleOrdersList')->with('error', 'Canceled Orders can not be edited.');
        }

        foreach ($saleOrder->items as $item) {
            $item->total_available = InventoryProduct::getTotalAvailable(
                $item->product_id,
                $item->site_id
            );
        }

        $carriers = Carrier::where('active', 1)->with('services')->get();
        $selectedCarrier = $saleOrder->shippingCarrier()->with('services')->first();

        if (!$saleOrder) {
            return redirect()->route('saleOrdersList')->with('error', 'Sales Order not found.');
        }

        return view('sales-orders.edit', compact('saleOrder', 'companies', 'carriers', 'selectedCarrier'));
    }

    public function saleOrderUpdate(Request $request, $order_number)
    {
        try {
            DB::beginTransaction();
            $saleOrder = SaleOrder::with('packaging', 'items')->where('order_number', $order_number)->first();

            if (!$saleOrder) {
                return redirect()->back()->with('error', 'Sales Order not found.');
            }

            $client = ClientService::upsertClientFromSaleOrder($request);

            if (!$client["ok"]) {
                return back()->withErrors([
                    'error' => 'An error occurred: ' . $client["message"],
                ])->withInput();
            }

            $client = $client["client"];

            $request->merge(['client_id' => $client->id]);
            $request->merge([
                'status' => $request->input('status') ?? $request->input('status_hidden')
            ]);

            $po_id = (string)$request->purchase_order;
            $validateFields = SaleOrderDataBuilder::rulesUpdate($po_id);
            $validatedData = $request->validate($validateFields);
            
            $attachment = null;
            if ($request->hasFile('attachment')) {
                $attachment = file_get_contents($request->file('attachment')->getRealPath());
            }
            
            $attachment = SalesOrdersService::getAttachmentContent($request->file('attachment'));
            
            $saleOrderData = SaleOrderDataBuilder::buildForUpdate($validatedData, $order_number, $attachment);
            $saleOrder->update($saleOrderData);

            $products = json_decode($request->input('products'), true);
            $order_number = (int) $saleOrder->order_number;
            SaleOrderItem::where('order_number', $order_number)->delete();
            SalesOrdersService::storeOrderProducts($saleOrder->order_number, $products, true, $saleOrder->site_id);
            
            if(isset($saleOrder->packaging->first()->on_shipstation) && $saleOrder->packaging->first()->on_shipstation == 1){
                $this->salesOrdersService->updateOrderOnShipstation($saleOrder);
            }
            
            LogHelper::handleLog('update', '', '', 'sale_orders', $saleOrder->order_number);
            DB::commit();

            return redirect()->route('saleOrderDetails', $saleOrder->order_number)->with('success', 'Sales Order updated successfully!');
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::info($th->getMessage());
            return back()->withErrors(['error' => 'An error occurred: ' . $th->getMessage()])->withInput();
        }
    }

    public function saleOrderDetails($orderNumber)
    {
        try {
            $saleOrder = SaleOrder::with(
                'items.product.company',
                'items.product.inventory_products',
                'items.product.main_locations',
                'items.location',
                'items.warehouse',
                'items.bin'
            )->where('order_number', $orderNumber)->first();
            
            foreach ($saleOrder->items as $item) {
                $item->total_available = InventoryProduct::getTotalAvailable(
                    $item->product_id,
                    $item->site_id
                );
            }

            return view('sales-orders.detail', compact('saleOrder'));
        } catch (\Throwable $th) {
            return back()->withErrors(['error' => 'An error occurred: ' . $th->getMessage()])->withInput();
        }
    }

    public function saleOrderImport(Request $request)
    {

        try {
            if (!$request->hasFile('file')) {
                return back()->with('error', 'No file uploaded');
            }

            $file = $request->file('file');
            if (!$file->isValid()) {
                return back()->with('error', 'Uploaded file is not valid.');
            }

            $rows = array_map('str_getcsv', file($file->getRealPath()));
            $header = array_shift($rows);

            // Check columns
            $missing = SaleOrderCSV::validateHeader($header);
            if (!empty($missing)) {
                return back()->with('error', 'Missing columns : ' . implode(', ', $missing));
            }

            $companies = Company::where('active', 1)->get();
            $saleOrders = [];
            $saleOrdersDetails = [];
            $invalidRows = [];
            $company_id = 0;
            $site_id = Auth::user()->site_id;

            $order_type = $request->input('cross_docking', false);
            $order_type = $order_type == 'on' ? 'cross_docking' : 'standard';

            $maxRows = 1000;
            if (count($rows) > $maxRows) {
                $excess = array_slice($rows, $maxRows);
                foreach ($excess as $exIndex => $_) {
                    $csvLine = ($maxRows + $exIndex) + 2;
                    $invalidRows[] = [
                        'errors' => 'Row ' . $csvLine . ': exceeded maximum of 1000 rows'
                    ];
                }
                $rows = array_slice($rows, 0, $maxRows);
            }

            foreach ($rows as $index => $row) {
                $data = array_combine($header, $row);

                $validation = SaleOrderCSV::validateRow($data, $companies, $index);

                if (!empty($validation['errors'])) {
                    $invalidRows[] = [
                        'errors' => implode('; ', $validation['errors']),
                    ];
                    continue; // skip the line because it is invalid
                }

                if ($validation['company']) {
                    $company_id = $validation['company']->id;
                }
                $order_number = SaleOrderCSV::getOrderNumber($index);
                $client_id = SaleOrderCSV::handleClientByCSV($data, $company_id);
                $saleOrder = SaleOrderCSV::mapToSaleOrder($data, $order_number, $company_id ?? 0, $client_id ?? 0, $site_id, $order_type);

                // If the function returns null → invalid line
                if (!$saleOrder || !empty($saleOrder['error'])) {
                    $message = $saleOrder['message'] ?? 'Invalid sale order data';

                    $invalidRows[] = [
                        'errors' => 'Row ' . ($index + 2) . ': ' . $message
                    ];
                    continue;
                }

                $itemResponse = SaleOrderCSV::mapToSaleOrderItem($data, $index + 2, $order_number, $site_id);

                if (!$itemResponse['ok'] || !empty($itemResponse['error'])) {
                    $message = $itemResponse['message'] ?? 'Invalid product SKU or quantity';

                    $invalidRows[] = [
                        'errors' => 'Row ' . ($index + 2) . ' - ' . $message
                    ];

                    continue;
                }

                $saleOrders[$index] = $saleOrder;
                $saleOrdersDetails[] = $itemResponse['detail'];
            }

            $validOrdersCount = count($saleOrders);
            $validDetailsCount = count($saleOrdersDetails);

            if (!empty($invalidRows)) {
                return SaleOrderCSV::downloadErrorCsv($invalidRows);
            }

            if ($validOrdersCount === 0 || $validDetailsCount === 0) {
                return redirect()->back()->with('error', 'No valid rows found. Nothing was imported.');
            }

            foreach ($saleOrders as $data) {
                SaleOrder::upsert($data, ['order_number']);
            }

            foreach ($saleOrdersDetails as $data) {
                unset($data['line_number']);
                SaleOrderItem::upsert($data, ['order_number', 'product_id'], ['quantity']);
            }

            return redirect()->back()->with('success', 'Sales Orders imported successfully!');
        } catch (\Throwable $th) {
            Log::info($th->getMessage());
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function saleOrderTemplate()
    {
        try {
            $headers = SaleOrderCSV::templateFields();

            $firstExampleRow = [
                'PO001',
                'REF001',
                'B10',
                '2',
                '',
                'John Doe',
                '1212 Main St',
                'Appt 999',
                '',
                'New York City',
                'NY',
                '10001',
                'US',
                'John Doe',
                // ⬇️ company sera ajoutée ici si besoin
                '0102030405',
                'johndoe@email.com',
                '2025-01-01',
                'ground',
            ];

            // 👉 Si l'utilisateur n'est PAS CLIENT, on ajoute company à la bonne position
            if (!AuthHelper::authType(['CLIENT'])) {
                array_splice(
                    $firstExampleRow,
                    array_search('company', $headers),
                    0,
                    'BBI'
                );
            }

            $file = new EmptyCSVFile($headers);
            $file->addRow($firstExampleRow);

            return Excel::download($file, 'sale_orders_template.csv');
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __(
                'messages/controller.main.error',
                ['message' => $th->getMessage()]
            ));
        }
    }

    public function getCrossDockingShipped(Request $request)
    {
        try {
            $sortColumn = $request->sort_column ?? 'created_at';
            $sortDirection = $request->sort_direction ?? 'DESC';
            $rows = $request->per_page ?? 10;

            $query = SaleOrder::where('status', 'shipped')->where('order_type', "cross_docking");

            if ($request->filled('order_number')) {
                $query->where('order_number', 'LIKE', '%' . $request->order_number . '%');
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $orders = $query->orderBy($sortColumn, $sortDirection)
                ->paginate($rows)
                ->appends($request->all());

            return view('sales-orders.orders-to-complete', compact('orders', 'sortColumn', 'sortDirection'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function completeSaleOrders(Request $request)
    {
        try {
            $orders = $request->selected_orders ?? [];
            $_selected_orders = array_filter($orders, fn ($val) => !is_null($val) && $val !== '');

            if (empty($_selected_orders)) {
                return redirect()->back()->with('error', 'No orders selected');
            }

            SaleOrder::whereIn('order_number', $_selected_orders)
                ->where('status', 'shipped')
                ->update(['status' => 'completed']);

            return redirect()->back()->with('success', 'Orders completed successfully');
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function checkOrder(Request $request, $orderNumber)
    {
        try {
            $saleOrder = SaleOrder::with('company', 'client')
                ->where('order_number', $orderNumber)
                ->first();

            if (!$saleOrder) {
                $order_number = PackagingOrder::where('sscc_code', $orderNumber)->value('order_number');

                if($order_number) {
                    $saleOrder = SaleOrder::with('company', 'client')
                        ->where('order_number', $order_number)
                        ->first();
                }
            }

            if (!$saleOrder) {
                throw new \Exception("Order not found");
            }

            return response()->json($saleOrder);
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function saleOrderCancel(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $saleOrder = SaleOrder::findOrFail($id);

            if (in_array($saleOrder->status, ['shipped', 'completed', 'canceled'])) {
                // We have to check later if shipping was voided. maybe
                throw new \Exception("Order can't be canceled");
            }

            $saleOrder->cancel_reason = $request->input('cancel_reason');
            $saleOrder->save();

            SalesOrdersService::cancelOrder($saleOrder);
            $order_number = $saleOrder->order_number;
            $this->salesOrdersService->cancelOrderOnShipstation($order_number);
            DB::commit();
            LogHelper::handleLog('Cancel Success', $saleOrder->order_number, '', 'Sale Order', '');

            return redirect()->route('saleOrdersList')->with('success', 'Order canceled successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::info($th->getMessage());
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function downloadImportErrors($key)
    {
        $path = storage_path("app/tmp/{$key}.csv");
        if (!file_exists($path)) {
            return redirect()->back()->with('error', 'Error file not found');
        }
        return response()->download($path)->deleteFileAfterSend();
    }
}