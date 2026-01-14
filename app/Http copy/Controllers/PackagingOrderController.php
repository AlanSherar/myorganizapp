<?php
namespace App\Http\Controllers;

use Exception;
use App\Models\Company;
use App\Models\Acctivate;
use App\Helpers\LogHelper;
use Illuminate\Http\Request;
use App\Models\PackagingType;
use App\Jobs\OrderIdUpdateJob;
use App\Models\PackagingOrder;
use App\Services\LabelService;
use App\Models\PackagingVendor;
use App\Services\RequestService;
use App\Services\SessionService;
use App\Models\PackagingReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\PackagingOrderService;
use App\Services\ShipStationAPIService;
use App\Services\PackagingReportService;
use App\Services\InventoryProductService;
use App\Services\PackagingOrderPackedUpdate;
use App\Services\ShipStationAPIv1Service;

class PackagingOrderController extends Controller
{
    protected $packagingOrderService;
    protected $request_service;
    protected $shipstationAPIservice;
    protected $acctivateModel;
    protected $packagingReportService;
    protected $sessionService;
    protected $packagingOrderModel;
    protected $inventoryProductService;
    protected $labelService;
    

    public function __construct(PackagingOrderService $packagingOrderService, 
                                RequestService $requestService,
                                Acctivate $acctivateModel,
                                PackagingOrder $packaging_order,
                                ShipStationAPIService $shipstationAPIservice,
                                PackagingReportService $packagingReportService,
                                SessionService $sessionService,
                                InventoryProductService $inventoryProductService,
                                LabelService $labelService)
    {
        $this->packagingOrderService = $packagingOrderService;
        $this->request_service = $requestService;
        $this->shipstationAPIservice = $shipstationAPIservice;
        $this->acctivateModel = $acctivateModel;
        $this->packagingReportService = $packagingReportService;
        $this->sessionService = $sessionService;
        $this->packagingOrderModel = $packaging_order;
        $this->inventoryProductService = $inventoryProductService;
        $this->labelService = $labelService;
    }
    
    public function packagingOrderSelect(Request $request)
    {
        try {
            $validated = $request->validate([
                'selected_orders' => 'required|array|min:1'
            ]);

            $result = $this->packagingOrderService->validateAndFetchOrders($request->input('selected_orders'));

            if (isset($result['success']) && empty($result['success']->toArray())) {
                return redirect()
                    ->route('getAcctivateOrders')
                    ->with('error', __("messages/controller.order.error.order_not_available"));
            }

            if (isset($result['error'])) {
                return redirect()->route('getAcctivateOrders')
                    ->with('error', __("messages/controller.order.error.{$result['error']}") 
                    . ': ' . implode(', ', $result['details'] ?? []));
            }

            $ordersSelected = $result['success'];
            $branchs = $ordersSelected->pluck('BranchID')->unique();

            $existingCount = Company::whereIn('code', $branchs)->count();

            if ($existingCount !== $branchs->count()) {
                return redirect()->route('getAcctivateOrders')
                    ->with('error', "Company branches associated with some selected orders do not exist in Logikli.");
            }

            $packagingProviders = PackagingVendor::all();
            $packagingTypes = PackagingType::all();
            $packagings = $this->packagingOrderService->getSelectPackagings();
            $topPackagings = $this->packagingOrderService->getTopSelectPackagings($packagings);

            $this->sessionService->saveOrderDataToSession($ordersSelected, $packagingProviders, $packagingTypes, $packagings);

            return view('packaging-order.select', compact('ordersSelected', 'packagingTypes', 'packagings', 'packagingProviders', 'topPackagings'));
        } catch (\Throwable $th) {
            return redirect()->route('getAcctivateOrders')->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()] ));
        }
    }
    
    public function clientPackagingStore(Request $request)
    {
        try {                   
            $objet = $request["data"]; 
            $packagings_orders = json_decode($objet, true);
            $orders = session('ordersSelected');
            $packagings = session('packagings');

            $this->packagingOrderService->handlePackagingOrderStore($packagings_orders, $orders, $packagings);

            return redirect()->route('packagingOrderWeight');
        } catch (\Exception $e) {
            return redirect()->route('getAcctivateOrders')->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()] ));
        }
    }

    public function packagingOrderWeight(Request $request)
    {
        try {                   
            $orders = session('ordersSelected');

            $ordersIds = [];
            foreach ($orders as $key => $value) {
                $ordersIds[] = $value->OrderNumber;
            }

            $distinctOrdersCount = array_unique($ordersIds);
            $distinctOrdersCount = count($distinctOrdersCount);

            $packagings_orders = PackagingOrder::whereIn('order_number', $ordersIds)->get();

            return view('packaging-order.select-weight', compact('packagings_orders', 'distinctOrdersCount'));
        } catch (\Exception $e) {
            return redirect()->route('getAcctivateOrders')->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()] ));
        }
    }

    public function packagingWeightStore(Request $request)
    {
        try {           
            $request->validate([
                'weight' => 'required|array',
            ]);

            $weight = $request->weight;
            $dimensions = $request->dimensions;
            $packagings_orders = $request->packagings_orders;
            $packagings_orders = json_decode($request->packagings_orders, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return redirect()->back()->with(['error' => __('messages/controller.json.error.invalid_format')]);
            }
    
            // Vérifier si c'est un tableau et s'il contient des commandes
            if (!is_array($packagings_orders) || empty($packagings_orders)) {
                return redirect()->back()->with(['error' => __('Order not found')]);
            }
            
            $this->packagingOrderService->validateWeight($weight);
            $this->packagingOrderService->storeWeightUnit($weight);
            $this->packagingOrderService->handleUpdateDimensions($dimensions);

            $order_numbers = array_values(
                array_unique(
                    array_column($packagings_orders, 'order_number')
                )
            );

/*             foreach ($order_numbers as $key => $order_number) {
                $shipStationAPIService = new ShipStationAPIv1Service();
                $order = $shipStationAPIService->getOrder($order_number);

                
                if(isset($order["orders"]) && count($order["orders"]) > 0){
                    $orderId = $order["orders"][0]["orderId"];
                    
                    if(!$orderId) continue;
                    
                    PackagingOrder::where('order_number', $order_number)->update([
                        'on_shipstation'    => 1,
                        'order_id'          => $orderId ?? null
                    ]);
                }
            } */

            dispatch(new OrderIdUpdateJob($order_numbers))->onQueue('order_id');

            $orders = collect($packagings_orders);

            $orderNumber = $orders->first()['order_number'] ?? null;
            $orderCount  = $orders->pluck('order_number')->unique()->count();

            if((int) Auth::user()->role_id == 3 && $orderCount <= 1 && $orderNumber){
                return redirect()->route('showCreateLabel', ['orderNumber' => $orderNumber]);
            } else {
                return redirect()->route('getAcctivateOrders')->with('success', __('messages/controller.order.packed.success'));
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()] ));
        }
    }

    public function packedOrderList(Request $request)
    {
        $client_ids = PackagingOrder::distinct()->pluck('client_id');
        $sortColumn = request()->query('sort_column', 'created_at'); 
        $sortDirection = request()->query('sort_direction', 'DESC');
        $rows = request()->query('per_page', 10);
        
        $obj = (object) [
            "client_id"         => $request->input('client_id'),
            "date_from"         => $request->input('date_from'),
            "date_to"           => $request->input('date_to'),
            "order_number"      => $request->input('order_number'),
            "sort_column"       => $sortColumn,
            "sort_direction"    => $sortDirection,
            "perPage"           => $request->input('perPage', 10),
            "status"            => 1,
            "rows"              => $rows,
            "label_void"        => 0
        ];

        $this->sessionService->saveFiltersToSession('packed_orders_filters', $request, $obj);
    
        $packaging_orders = [];
        $orders_number_shipped = [];
        $hasIncorrectStatus = true;

        while ($hasIncorrectStatus) {
            $packaging_orders = $this->packagingOrderModel->handleOrdersFilters($obj);
            $orderNumbers = $packaging_orders->pluck('order_number')->take(10);
            $orderShipped = $this->acctivateModel->ordersShipped($orderNumbers);
            //$orderCanceled = $this->acctivateModel->ordersCancel($orderNumbers);
            
            if($orderShipped->isNotEmpty()){
                $shiped_orders_number = $orderShipped->pluck('OrderNumber')->take(10);
                $packaging_order = PackagingOrder::whereIn('order_number', $shiped_orders_number);
                $orders_number_shipped[] = $packaging_order->pluck('order_number');

                $packaging_order->update([
                    'status_id'       => 3,
                    'updated_at'      => user_local_time(),
                    //'label_void'      => 0
                ]); 
            } else {
                $hasIncorrectStatus = false;
            }
        }

        $packaging_orders = $this->packagingOrderModel->handleOrdersFilters($obj);

        if(count($orders_number_shipped) > 0){
            $result = $this->inventoryProductService->refreshQuantityOrdersShipped($orders_number_shipped);
            return view('packaging-order.packed-list', compact('packaging_orders', 'client_ids', 'sortColumn', 'sortDirection'));
        }

        return view('packaging-order.packed-list', compact('packaging_orders', 'client_ids', 'sortColumn', 'sortDirection'));
    }

    public function shippedOrderList(Request $request)
    {
        try {
            $packaging_orders = [];
            $on_shipstation = [];
            
            $client_ids = PackagingOrder::where('label_void', 0)
                ->where('status_id', 3)
                ->distinct()
                ->pluck('client_id');

            $sortColumn = request()->query('sort_column', 'created_at'); 
            $sortDirection = request()->query('sort_direction', 'DESC');
            $rows = request()->query('per_page', 10);

            $obj = (object) [
                "client_id"         => $request->input('client_id'),
                "date_from"         => $request->input('date_from'),
                "date_to"           => $request->input('date_to'),
                "order_number"      => $request->input('order_number'),
                "sort_column"       => $sortColumn,
                "sort_direction"    => $sortDirection,
                "perPage"           => $request->input('perPage', 10),
                "status"            => 3,
                "label_void"        => 0,
                "rows"              => $rows
            ];
    
            $this->sessionService->saveFiltersToSession('packed_orders_filters', $request, $obj);
            
            $hasIncorrectStatus = true;
            
            while ($hasIncorrectStatus) {
                $packaging_orders = $this->packagingOrderModel->handleOrdersFilters($obj);
                $orderNumbers = $packaging_orders->where('ship_international', '!=', 1)->pluck('order_number')->take(10);
                //$orderCanceled = $this->acctivateModel->ordersCancel($orderNumbers);
                $orderUnshipped = $this->acctivateModel->ordersUnshipped($orderNumbers);

                if($orderUnshipped->isNotEmpty()){
                    $shiped_orders_number = $orderUnshipped->pluck('OrderNumber')->take(10);

                    PackagingOrder::whereIn('order_number', $shiped_orders_number)->update([
                        'status_id'       => 1,
                        'updated_at'      => user_local_time(),
                        //'label_void'      => 0
                    ]);         
                } else {
                    $hasIncorrectStatus = false;
                }
            }

            $packaging_orders = $this->packagingOrderModel->handleOrdersFilters($obj);
            $orders_numbers = $orderUnshipped->pluck('order_number')->take(10)->toArray();
            $orders_numbers = array_filter(array_unique($orders_numbers));
    
            return view('packaging-order.shipped-list', compact('packaging_orders', 'client_ids', 'sortColumn', 'sortDirection'));
        } catch (\Throwable $th) {
            return redirect()->route('packedOrderList')->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()] ));
        }

    }
    
    public function packagingOrderEdit($order_id)
    {
        try {
            $orderSelected = PackagingOrder::where('order_number', $order_id)->get();

            $obj = (object) [
                "order_number"  => $order_id
            ];

            $orderSelected = $this->packagingOrderModel->handleOrdersFilters($obj);

            if($orderSelected->isEmpty()){
                return redirect()->route('packedOrderList')->with('error', __('messages/controller.order.error.not_found'));
            }

            $packagingTypes = PackagingType::all();
            $packagings = PackagingReference::all();
            $packagingProviders = PackagingVendor::all();
            
            $this->sessionService->saveOrdersSelected($orderSelected);
            $this->sessionService->saveOrderNumber($order_id);

            return view('packaging-order.edit', compact('orderSelected', 'packagingTypes', 'packagings', 'packagingProviders'));
        } catch (\Throwable $th) {
            // En cas d'erreur, rediriger vers la liste des commandes
            return redirect()->route('packedOrderList')->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()] ));
        }
    }

    public function packagingOrderUpdate(Request $request)
    {        
        try {                   
            $objet = $request["data"]; 
            $packagings_orders = json_decode($objet, true);
            $orderId = session('orderNumber');
            $ordersSelected = session('ordersSelected');

            $updateService = new PackagingOrderPackedUpdate($this->packagingOrderService);
            $updateService->handleUpdateOrders($packagings_orders, $orderId, $ordersSelected);
            return redirect()->route('packedOrderList')->with('success', __('messages/controller.order.edited.success'));
        } catch (\Exception $e) {
            return redirect()->route('packedOrderList')->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()] ));
        }
    }

    public function packagingOrderDelete(Request $request)
    {
        try {          
            $request->validate([
                'order_number' => 'required',
            ]);

            $orderNumber = $request->order_number;

            //$this->request_service->handleDeleteRequestOrderPacked($orderNumber, $packagingId);
            $this->packagingOrderService->handleDeleteOrder($orderNumber);

            return redirect()->route('packedOrderList')->with('success', __('messages/controller.order.deleted.success'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            return redirect()->route('packedOrderList')->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()] ));
        }
    }

    public function storeWithoutPackagingProvider(Request $request)
    {
        $request->validate([
            'packaging_quantity' => 'required|integer|min:1',
        ]);

        $orders = session('ordersSelected');
        $quantity = (int) $request->packaging_quantity;

        try {                   
            $this->packagingOrderService->handlePackagingSSCCWithoutProvider($orders, $quantity);
            return redirect()->route('packagingOrderWeight');
        } catch (\Exception $e) {
            return redirect()->route('packedOrderList')->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()] ));
        }
    }

    public function packagingOrderDetail(Request $request, $orderNumber)
    {
        try {
            $orderNumber = (string) $orderNumber;
            $packagingOrder = $this->packagingOrderModel->handlePackagingDetails($orderNumber);
            $packaging_order = PackagingOrder::where('order_number', $orderNumber)
                ->with('packaging')
                ->with('shippedByUser')
                ->get();

            return view('packaging-order.detail', compact('packagingOrder', 'packaging_order', 'orderNumber'));
        } catch (\Exception $e) {
            return redirect()->route('packedOrderList')->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()] ));
        }
    }
    
    public function downloadPackagingReport(Request $request)
    {
        try {
            $filter = (object) [
                "client_id"         => $request->query('client_id'),
                "date_from"         => $request->query('date_from'),
                "date_to"           => $request->query('date_to'),
                "order_number"      => $request->query('order_number'),
            ];

            return $this->packagingReportService->exportPackagingsToCsv($filter);
        } catch (\Exception $e) {
            return redirect()->route('packedOrderList')->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()] ));
        }
    }

    public function downloadShippedOrderReport(Request $request)
    {
        try {
            $filter = (object) [
                "client_id"         => $request->query('client_id'),
                "date_from"         => $request->query('date_from'),
                "date_to"           => $request->query('date_to'),
                "order_number"      => $request->query('order_number'),
            ];

            $status = 3;

            return $this->packagingReportService->exportPackagingsToCsv($filter, $status);
        } catch (\Exception $e) {
            return redirect()->route('packedOrderList')->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()] ));
        }
    }

    public function printOrderDetail($orderNumber)
    {
        try {
            $orderNumber = (string) $orderNumber;
            $packagingOrder = $this->packagingOrderModel->handlePackagingDetails($orderNumber);
            $packaging_order = PackagingOrder::where('order_number', $orderNumber)->with('packaging')->get();
            $totalCost = $packagingOrder->sum(function ($item) {
                return $item->packaging_price * $item->packaging_quantity;
            });

            return view('packaging-order.detail-print', compact('packagingOrder', 'packaging_order', 'orderNumber', 'totalCost'));
        } catch (\Exception $e) {
            return redirect()->route('printOrderDetail', ['orderNumber' => $orderNumber])->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()] ));
        }
    }

    public function packedOrderDetails($orderNumber)
    {
        try {
            $orders = PackagingOrder::where('order_number', $orderNumber)->get();

            return response()->json($orders);
        } catch (\Throwable $th) {
            return redirect()->route('packedOrderList')->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()] ));
        }
    }

    public function getOrderOnShipstation(Request $request)
    {
        try {
            $order_number = $request->order_number;
            
            // Récupère le statut de la commande
            $status = $this->shipstationAPIservice->getStatusOrder($order_number);
    
            // Vérifie si la commande est "awaiting_shipment"
            if ($status == "awaiting_shipment") {
                // Mise à jour de l'état dans la base de données
                PackagingOrder::where('order_number', $order_number)->update([
                    'on_shipstation'    => 1,
                    'status_id'         => 1,
                    'label_void'        => 0
                ]);
    
                // Retourner la réponse avec un code de succès et le statut
                return response()->json([
                    'status' => $status,
                    'message' => __('messages/controller.order.status.awaiting_shipment'),
                ], 200); // 200 est un code HTTP de succès
            }
    
            // Retourner la réponse si la commande n'est pas "awaiting_shipment"
            return response()->json([
                'status' => $status,
                'message' => __('messages/controller.order.status.not_awaiting_shipment')
            ], 200);
            
        } catch (\Throwable $th) {
            // Si une exception survient, renvoie un message d'erreur avec le statut 500
            return response()->json([
                'error' => __('messages/controller.order.error.while_processing'),
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function packagingReport(Request $request)
    {
        try {
            $obj = (object) [
                "branch"            => $request->input('branch'),
                "packaging_sku"     => $request->input('packaging_sku'),
                "date_from"         => $request->input('date_from'),
                "date_to"           => $request->input('date_to')
            ];

            //$query = $this->packagingReportService->queryBillingPackaging($obj);
            $query = $this->packagingReportService->queryBillingPackaging($obj);

            $result = (clone $query)->paginate(10)->appends(request()->query());
            $branchs = (clone $query)->select('branch')->distinct()->pluck('branch');
            $packagings = (clone $query)->select('packaging_sku')->distinct()->pluck('packaging_sku');            

            return view('reports.packaging-report', compact('result', 'branchs', 'packagings'));
        } catch (\Throwable $th) {
            dd($th->getMessage());

            return redirect()->route('packedOrderList')->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()] ));
        }
    }

    public function billingPackagingReport(Request $request)
    {
        try {
            $date_from = $request->input('date_from') ?? null;
            $date_to = $request->input('date_to') ?? null;
            
            $obj = (object) [
                "branch"            => $request->input('branch'),
                "packaging_sku"     => $request->input('packaging_sku'),
                "date_from"         => $request->input('date_from'),
                "date_to"           => $request->input('date_to')
            ];

            $data = $this->packagingReportService->queryBillingPackaging($obj)->get();

            return $this->packagingReportService->billingPackagingsCsv($data, $date_from, $date_to);
        } catch (\Throwable $th) {
            return redirect()->route('packedOrderList')->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()] ));
        }
    }

    public function packagingMultiPack()
    {
        return view('packaging-order.multi-pack-select');
    }

    public function checkOrder($orderNumber)
    {
        $order = DB::connection('acctivate_db')
            ->table('tbOrders')
            ->select([
                'tbOrders.OrderNumber',
                'tbOrders.OrderDate',
                'tbBranch.BranchID',
                'tbCustomer.Name',
                'tbOrders.ShipToAddress1',
                'tbOrders.ShipToCity',
                'tbOrders.ShipToZip',
                'tbOrders.ShipToCountry',
                DB::raw("CASE 
                    WHEN tbOrders.OrderStatus = 'S' THEN 'Scheduled'
                    WHEN tbOrders.OrderStatus = 'B' THEN 'Booked'
                    ELSE tbOrders.OrderStatus
                END AS OrderStatus"),
                DB::raw("CASE 
                    WHEN tbOrderWorkFlowStatus.WorkFlowStatus = 'H' THEN 'Pick on Hold'
                    WHEN tbOrderWorkFlowStatus.WorkFlowStatus = 'I' THEN 'In Progress'
                    WHEN tbOrderWorkFlowStatus.WorkFlowStatus = 'X' THEN 'No Pick Required'
                    WHEN tbOrderWorkFlowStatus.WorkFlowStatus = 'P' THEN 'Picked'
                    ELSE tbOrderWorkFlowStatus.WorkFlowStatus
                END AS WorkFlowStatus"),
                'tbOrderWorkFlowStatus.Description',
            ])
            ->join('tbOrderWorkFlowStatus', 'tbOrders.GUIDOrderWorkFlowStatus', '=', 'tbOrderWorkFlowStatus.GUIDOrderWorkFlowStatus')
            ->join('tbCustomer', 'tbOrders.GUIDCustomer', '=', 'tbCustomer.GUIDCustomer')
            ->join('tbBranch', 'tbOrders.GUIDBranch', '=', 'tbBranch.GUIDBranch')
            ->where('tbOrders.OrderNumber', $orderNumber)
            ->whereNotIn('tbOrders.OrderStatus', ['X', 'C', 'K'])
            ->whereIn('tbOrderWorkFlowStatus.WorkFlowStatus', ['H', 'X', 'I', 'P'])
            ->where('tbBranch.BranchID', 'NOT LIKE', 'CA-%')
            ->first();

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        return response()->json($order);
    }

    public function cancelOrders(Request $request)
    {
        try {
            $orderNumbers = $request->input('order_numbers', []);

            if (!empty($orderNumbers)) {
                $uniqueOrderNumbers = collect($orderNumbers)->unique()->values()->all();

                PackagingOrder::whereIn('order_number', $uniqueOrderNumbers)->delete();

                LogHelper::handleLog('cancel', '', '', 'packed');
            }

            return redirect()->route('getAcctivateOrders')->with('warning', 'Orders cancelled successfully.');
        } catch (\Throwable $th) {
            return redirect()->route('getAcctivateOrders')->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()] ));
        }
    }

    public function voidedOrderList(Request $request)
    {
        try {
            $packaging_orders = [];
            //$client_ids = PackagingOrder::where('label_void', 1)->distinct()->pluck('client_id');

            $sortColumn = request()->query('sort_column', 'created_at'); 
            $sortDirection = request()->query('sort_direction', 'DESC');
            $rows = request()->query('per_page', 10);

            $obj = (object) [
                "client_id"         => $request->input('client_id'),
                "date_from"         => $request->input('date_from'),
                "date_to"           => $request->input('date_to'),
                "order_number"      => $request->input('order_number'),
                "sort_column"       => $sortColumn,
                "sort_direction"    => $sortDirection,
                "perPage"           => $request->input('perPage', 10),
                "status"            => false,
                "label_void"        => 1,
                "rows"              => $rows
            ];
    
            $packaging_orders = $this->packagingOrderModel->handleOrdersFilters($obj);
    
            return view('packaging-order.voided-list', compact('packaging_orders', 'sortColumn', 'sortDirection'));
        } catch (\Throwable $th) {
            return redirect()->route('packedOrderList')->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()] ));
        }
    }

    public function cancelOrder(Request $request)
    {
        try {
            $request->validate([
                'order_number' => 'required|string'
            ]);

            $order_number = $request->order_number;

            $order = $this->shipstationAPIservice->getOrder($order_number);
            $orders = $order['orders'] ?? [];

            foreach ($orders as $order) {
                $url = 'https://ssapi.shipstation.com/orders/createorders';

                $data = [
                    array_merge($order, ['orderStatus' => 'cancelled'])
                ];

                $response = $this->shipstationAPIservice->postAPIv1($url, $data);

                if ($response->failed()) {
                    throw new \Exception(
                        "Failed to cancel order on ShipStation: " . $response->body()
                    );
                }
            }

            PackagingOrder::where('order_number', $order_number)->update(['status' => 'cancelled']);

            return redirect()->route('voidedOrderList')->with('warning', 'Order cancelled successfully.');
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }
    
}