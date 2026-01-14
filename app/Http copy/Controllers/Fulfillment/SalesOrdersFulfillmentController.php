<?php

namespace App\Http\Controllers\Fulfillment;

use App\Models\Company;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\SaleOrder;
use Illuminate\Http\Request;
use App\Models\PackagingOrder;
use App\Services\LabelService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\SalesOrdersService;
use Illuminate\Support\Facades\Auth;
use App\Services\ShipStationAPIService;
use App\Support\SaleOrder\SaleOrderSupport;
use App\Support\Shipstation\ShipstationSupport;
use Illuminate\Support\Facades\Http;
use setasign\Fpdi\Tcpdf\Fpdi;

class SalesOrdersFulfillmentController extends Controller
{
    protected $salesOrdersService;
    protected $ShipStationAPIService;
    protected $labelService;

    public function __construct(SalesOrdersService $salesOrdersService, 
        ShipStationAPIService $ShipStationAPIService,
        LabelService $labelService)
    {
        $this->salesOrdersService = $salesOrdersService;
        $this->ShipStationAPIService = $ShipStationAPIService;
        $this->labelService = $labelService;
    }

    public function ordersToPackList(Request $request)
    {
        try {
            $sortColumn = $request->sort_column ?? 'created_at'; 
            $sortDirection = $request->sort_direction ?? 'DESC';
            $orders = SalesOrdersService::getOrdersToPackList('picked', $request);

            if(!$orders || $orders->isEmpty()){
                return view('sales-orders.orders-to-pack-list', compact('orders', 'sortColumn', 'sortDirection'))->with('error', "Order not found");
            }
            $branchIds = $orders->pluck('BranchID')->unique()->toArray() ?? [];

            return view('sales-orders.orders-to-pack-list', compact('orders', 'sortColumn', 'sortDirection', 'branchIds')); 
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function selectOrdersToPack(Request $request)
    {
        try {
            $orders = $request->selected_orders ?? [];
            $_selected_orders = array_filter($orders, fn ($val) => !is_null($val) && $val !== '');

            $selected_orders = SaleOrder::with('items')->whereIn('order_number', $_selected_orders)->get();

            return view('sales-orders.set_pack_order', compact('selected_orders')); 
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function storeSaleOrders(Request $request)
    {
        try {
            DB::beginTransaction();

            $widths  = $request->input('width', []);
            $heights = $request->input('height', []);
            $lengths = $request->input('length', []);
            $pounds  = $request->input('pounds', []);
            $ounces  = $request->input('ounces', []);

            $data = SaleOrderSupport::setStoreSaleOrders( $widths,
                $heights,
                $lengths,
                $pounds,
                $ounces);

            $order_numbers = array_keys($data);

            $selected_orders = SaleOrder::select('order_number', 'client_id', 'company_id', 'shipping_country')
                ->whereIn('order_number', $order_numbers)
                ->get()
                ->keyBy('order_number')
                ->toArray();

            $merged = [];

            foreach ($data as $orderNumber => $values) {
                $merged[$orderNumber] = array_merge(
                    $selected_orders[$orderNumber] ?? null,
                    $values
                );
            }

            $response = SalesOrdersService::_storeSaleOrders($merged);
            
            
            if(isset($response["error"])){
                DB::rollBack();
                return redirect()->route('ordersToPackList')->with('error', $response["message"]); 
            }
            
            $orders_to_create = SaleOrder::with('packaging', 'items.product')->whereIn('order_number', $order_numbers)->get()->toArray();
            
            $orders_to_cancel_pack = [];
            foreach ($orders_to_create as $key => $order) {
                
                $payload = ShipstationSupport::buildOrderPayload($order);
                $url = "https://ssapi.shipstation.com/orders/createorder";
                
                $response = $this->ShipStationAPIService->postAPIv1($url, $payload);

                $json = $response->json();

                if(!isset($json["orderId"]) || !$json["orderId"]) {
                    $orders_to_cancel_pack[] = $order["order_number"];
                }
            }

            if(count($orders_to_cancel_pack) > 0){
                DB::rollBack();

                return redirect()->route('ordersToPackList')
                    ->with('error', 
                        "ShipStation error: The following orders failed: " 
                        . implode(", ", $orders_to_cancel_pack)
                    );
            }

            // 👍 Tout OK → commit
            DB::commit();

            $orderLabel = count($order_numbers) > 1 ? 'Orders' : 'Order';

            return redirect()->route('ordersToPackList')->with('success', "{$orderLabel} packed successfully.");
        } catch (\Throwable $th) {
            return redirect()->route('ordersToPackList')->with('error', $th->getMessage());
        }
    }

    public function ordersToShipList(Request $request)
    {
        try {
            $sortColumn = $request->sort_column ?? 'created_at'; 
            $sortDirection = $request->sort_direction ?? 'DESC';
            $orders = SalesOrdersService::getOrdersToPackList('packed', $request);

            if(!$orders || $orders->isEmpty()){
                return view('sales-orders.orders-to-ship-list', compact('orders', 'sortColumn', 'sortDirection'))->with('error', "Order not found");
            }
            $branchIds = $orders->pluck('BranchID')->unique()->toArray() ?? [];

            return view('sales-orders.orders-to-ship-list', compact('orders', 'sortColumn', 'sortDirection', 'branchIds')); 
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function orderToShip($order_number)
    {
        try {
            $_warehouses = $this->ShipStationAPIService->handleWarehousesList();
            $warehouses = $_warehouses["warehouses"];

            $_carriers = $this->ShipStationAPIService->handleCarriersList();
            $carriers_array = $_carriers["carriers"];
            
            $order_data = SaleOrder::with('items', 'items.product', 'packaging')->where('order_number', $order_number)->first();
            $mark_up = Company::where('id', Auth::user()->company_id)->value('markup');

            if (count($order_data->packaging) > 1) {
                $carriers = $this->labelService->handleCarriersMultipack($carriers_array);
            } else{
                $carriers = $this->labelService->handleDomesticCarriers($carriers_array);
            }

            return view('sales-orders.order-to-ship', compact('warehouses', 'carriers', 'order_data', 'mark_up'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function ordersShippedList(Request $request)
    {
        try {
            $sortColumn = $request->sort_column ?? 'created_at'; 
            $sortDirection = $request->sort_direction ?? 'DESC';
            $orders = SalesOrdersService::getOrdersToPackList('shipped', $request);

            if(!$orders || $orders->isEmpty()){
                return view('sales-orders.orders-shipped-list', compact('orders', 'sortColumn', 'sortDirection'))->with('error', "Order not found");
            }
            $branchIds = $orders->pluck('BranchID')->unique()->toArray() ?? [];

            return view('sales-orders.orders-shipped-list', compact('orders', 'sortColumn', 'sortDirection', 'branchIds')); 
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function ordersCompletedList(Request $request)
    {
        try {
            $sortColumn = $request->sort_column ?? 'created_at'; 
            $sortDirection = $request->sort_direction ?? 'DESC';
            $orders = SalesOrdersService::getOrdersToPackList('completed', $request);

            if(!$orders || $orders->isEmpty()){
                return view('sales-orders.completed-orders-list', compact('orders', 'sortColumn', 'sortDirection'))->with('error', "Order not found");
            }
            $branchIds = $orders->pluck('BranchID')->unique()->toArray() ?? [];

            return view('sales-orders.completed-orders-list', compact('orders', 'sortColumn', 'sortDirection', 'branchIds')); 
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function orderShippedLabel($order_number)
    {
        try {
            $order = SaleOrder::with('items', 'packaging')->where('order_number', $order_number)->first();

            return view('sales-orders.order-shipped-label', compact('order')); 
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function printPackagingLabel($order_number)
    {
        try {
            $packagings = PackagingOrder::where('order_number', $order_number)
                ->select('width', 'length', 'height', 'sscc_code', 'item_number', 'packaging_total', 'weight_unit', 'weight_value', 'label_download')
                ->get();

            if ($packagings->isEmpty()) {
                return response()->json([
                    'error' => true,
                    'message' => "Aucun packaging trouvé pour l'ordre {$order_number}."
                ], 404);
            }

            $order = SaleOrder::with('company')->where('order_number', $order_number)->first();

            // Télécharger le PDF ShipStation
            $label_download = $packagings[0]->label_download;
            $shipPdf = Http::get($label_download);

            if (!$shipPdf->successful()) {
                return response()->json([ 'error' => true, 'message' => 'Shipment label not found' ], 500);
            }

            $shipPdfPath = storage_path("app/ship_tmp.pdf");
            file_put_contents($shipPdfPath, $shipPdf->body());

            // GENERATE FINAL PDF
            $pdf = new FPDI();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);

            foreach ($packagings as $index => $packaging) {

                // 1️⃣ Générer un PDF SSCC pour CE packaging seul
                $sscc = PDF::loadView('sales-orders.print-packaging-label', [
                    'pkg' => $packaging,
                    'order' => $order,
                ])->setPaper([0, 0, 287, 430], 'portrait')->output();

                $ssccPath = storage_path("app/sscc_tmp_{$index}.pdf");
                file_put_contents($ssccPath, $sscc);

                // ➜ Ajouter page SSCC(i)
                $pageCount = $pdf->setSourceFile($ssccPath);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $tpl = $pdf->importPage($i);
                    $size = $pdf->getTemplateSize($tpl);
                    $pdf->AddPage('P', [$size['width'], $size['height']]);
                    $pdf->useTemplate($tpl);
                }

                // ➜ Ajouter page SHIP(i)
                $shipPages = $pdf->setSourceFile($shipPdfPath);
                if ($shipPages >= ($index+1)) {
                    $tpl = $pdf->importPage($index+1);
                    $size = $pdf->getTemplateSize($tpl);
                    $pdf->AddPage('P', [$size['width'], $size['height']]);
                    $pdf->useTemplate($tpl);
                }
            }

            // Output
            return response($pdf->Output('label_order_' . $order_number . '.pdf'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"packagings_label_{$order_number}.pdf\"",
            ]);

        } catch (\Throwable $th) {
            return response()->json([ 'error' => true, 'message' => $th->getMessage() ], 500);
        }
    }

    public function unpackOrder($orderNumber)
    {
        try {
            DB::beginTransaction();

            PackagingOrder::where('order_number', $orderNumber)->delete();

            SaleOrder::where('order_number', $orderNumber)->update(['status' => 'picked', 'last_status' => 'picked']);

            DB::commit();

            return redirect()->back()->with('success', 'Order unpacked successfully. Packaging removed.');
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

}
