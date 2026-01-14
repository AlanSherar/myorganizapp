<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Acctivate;
use App\Models\SaleOrder;
use App\Helpers\LogHelper;
use Illuminate\Http\Request;
use App\Models\PackagingOrder;
use App\Services\LabelService;
use App\Services\CompanyService;
use Hamcrest\Text\IsEmptyString;
use App\Models\TransactionProduct;
use Illuminate\Support\Facades\Log;
use App\Services\ShipstationService;
use Illuminate\Support\Facades\File;
use App\Services\PackagingOrderService;
use App\Services\ShipStationAPIService;
use App\Services\SkuCalculationService;
use App\Services\InventoryProductService;
use App\Services\TransactionProductService;
use App\Support\SaleOrder\SaleOrderSupport;

class ShipStationController extends Controller
{
    protected $shipStationAPIService;
    protected $packagingOrderService;
    protected $labelService;
    protected $shipstationService;
    protected $inventoryProductService;
    protected $acctivateModel;

    public function __construct(
        ShipStationAPIService $shipStationAPIService,
        PackagingOrderService $packagingOrderService,
        LabelService $label_service,
        ShipstationService $shipstationService,
        InventoryProductService $inventoryProductService,
        Acctivate $acctivateModel
    ) {
        $this->shipStationAPIService = $shipStationAPIService;
        $this->packagingOrderService = $packagingOrderService;
        $this->labelService = $label_service;
        $this->shipstationService = $shipstationService;
        $this->inventoryProductService = $inventoryProductService;
        $this->acctivateModel = $acctivateModel;
    }

    public function showCreateLabel($order_number)
    {
        try {
            $packaging_orders = PackagingOrder::with('companyById', 'companyByCode')
                ->where('order_number', $order_number)->get();
            $packaging_order = $packaging_orders[0];

            $company = $packaging_order->companyById ?? $packaging_order->companyByCode;
            
            if(!isset($company->id)){
                return redirect()->back()->with('error', 'Company not found on Logikli');
            }

            if((float) $company->credit <= 0 && $company->credit_applied == '1'){
                return redirect()->back()->with('error', 
                'Company ' . $company->name . " don't have enough credit for shipment");
            }

            $_warehouses = $this->shipStationAPIService->handleWarehousesList();
            $_carriers = $this->shipStationAPIService->handleCarriersList();
            $carriers_array = $_carriers["carriers"];
            $warehouses = $_warehouses["warehouses"];

            $instructions = $this->acctivateModel->getInstructionsByOrder($order_number);
            $instructions = $instructions && !empty($instructions->ShippingInstructions)
                ? $instructions->ShippingInstructions
                : null;

            $carriers = $this->labelService->handleDomesticCarriers($carriers_array);
            $order_data = Acctivate::getOrderDetails($order_number);
            if(count($order_data) > 0){
                $order_data = $order_data[0];
                
                if (count($packaging_orders) > 1) {
                    $carriers = $this->labelService->handleCarriersMultipack($carriers);
                }
                
                return view('shipstation.create-label', compact('carriers', 'warehouses', 'packaging_order', 'packaging_orders', 'instructions', 'order_data'));
            }
            return redirect()->back()->with('error', 'Order not found on Aactivate');
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function getRates(Request $request)
    {
        try {
            $carrier_ids = [];
            $service_codes = [];

            $order_number = $request->order_number;
            $weight = $request->weight;
            $transport_type = $request->transport_type;
            $carrier_id = $request->carrier;
            $warehouse = $request->warehouse;

            session()->put('warehouse', $warehouse);

            $this->packagingOrderService->handleUpdateWeight($weight);

            $packaging_order = PackagingOrder::where('order_number', $order_number)->first();
            $_packaging_order[] = $packaging_order;
            $params = $this->labelService->handlePackagesData($_packaging_order);
            $orderId = $this->shipStationAPIService->updateOrder($order_number, $params[0]);

            if ($orderId == "error") {
                return [
                    "error_message" => __('messages/controller.shipstation.error.not_found'),
                ];
            }

            if ($orderId == "error_update") {
                return [
                    "error_message" =>  __('messages/controller.shipstation.error.update')
                ];
            }

            if ($transport_type) {
                $services_carriers = $this->shipstationService->handleTypeServiceCarrier($transport_type);

                $carrier_ids = $services_carriers["carrier_ids"];
                $service_codes = $services_carriers["service_codes"];
            }

            if ($carrier_id) {
                $carrier_ids[] = $carrier_id;
            }

            $rates = $this->shipStationAPIService->handleRates($orderId, $carrier_ids, $service_codes);

            if ($rates->successful()) {
                $response = $rates->json()["rate_response"]["rates"];
                if (count($response) >= 1) {

                    usort($response, function ($a, $b) {
                        return $a['shipping_amount']['amount'] <=> $b['shipping_amount']['amount'];
                    });

                    return [
                        "rates"                 => $response,
                        "package_selected"      => $packaging_order,
                    ];
                }

                if (count($rates->json()["rate_response"]["errors"]) >= 1) {
                    return [
                        "error"                 => $rates->json()["rate_response"]["errors"],
                        "package_selected"      => $packaging_order,
                    ];
                }
            } else {
                return [
                    "rates"                 => $rates->json()
                ];
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getLabelMultiPackage(Request $request)
    {
        try {
            $order_number = $request->packaging_order_id;
            $packaging_order = PackagingOrder::where('order_number', $order_number)->get();
            $selectedRate = $request->selected_rate[0];
            $selectedRate = json_decode($selectedRate, true);

            // Debug pour voir les valeurs extraites
            $warehouse = session('warehouse');
            $warehouse = json_decode($warehouse, true);
            $carrier = session('carrier');

            // Vérifier si l'objet contient les clés nécessaires
            if (!$selectedRate || !isset($selectedRate['carrier_id'], $selectedRate['service_code'])) {
                return response()->json(['error' => 'Service data invalid'], 400);
            }

            // Extraire les valeurs
            $carrier_id = $selectedRate['carrier_id'];
            $service_code = $selectedRate['service_code'];
            $carrier_code = $selectedRate["carrier_code"];

            $carrier = [
                "carrier_id"    => $carrier_id,
                "service_code"  => $service_code
            ];

            $order = $this->shipStationAPIService->getOrder($order_number);
            $order_result = $this->shipstationService->checkStatusOrder($order_number);

            $result = $this->shipStationAPIService->handleCreateLabel($order_result, $carrier, $warehouse, $packaging_order);

            if ($result->successful()) {
                $result = $result->json(); // Retourner les données sous forme de tableau
            } else {
                $responseData = json_decode($result->body(), true);

                if (isset($responseData["errors"][0]["message"])) {
                    return redirect()->back()->with('error', $responseData["errors"][0]["message"]);
                }

                return redirect()->back()->with('error', 'An error occurred');
            }

            if (isset($result["shipment_id"]) && isset($result["tracking_number"])) {
                $tracking_number = $result["tracking_number"];
                $ship_date = $result["ship_date"];
                $orderId = $order["orders"][0]["orderId"];

                $carrierData = $this->shipStationAPIService->getCarrierData($carrier_code);
                $carrierData = $carrierData->json();

                if ($carrierData["requiresFundedAccount"]) {
                    return redirect()->back()->with('error', __('messages/controller.shipstation.error.not_funded'));
                }

                $shippingProviderID = $carrierData["shippingProviderId"];
                $params = [
                    "shippingAmount"    => $result["shipment_cost"]["amount"],
                    "carrierCode"       => $result["carrier_code"],
                    "serviceCode"       => $result["service_code"]
                    //"shipDate"          => new \DateTime()
                ];
                $orderUpdated = $this->shipStationAPIService->handleUpdateOrderData($order_number, $params, $shippingProviderID);

                if ($orderUpdated->successful()) {
                    $orderUpdated = $orderUpdated->json(); // Retourner les données sous forme de tableau
                } else {
                    $responseData = json_decode($orderUpdated->body(), true);

                    return redirect()->back()->with('error', $responseData["Message"]);
                }

                $obj = (object) [
                    "order_id"          => $orderId,
                    "shipe_date"        => $ship_date,
                    "tracking_number"   => $tracking_number,
                    "carrier_code"      => $carrier_code
                ];

                $orderShipped = $this->shipStationAPIService->handleMarkShipOrder($obj);

                if ($orderShipped->successful()) {
                    $orderShipped = $orderShipped->json(); // Retourner les données sous forme de tableau
                } else {
                    $responseData = json_decode($orderShipped->body(), true);

                    return redirect()->back()->with('error', $responseData["errors"][0]["message"]);
                }

                $markupPercent = 0;
                $sale_order = SaleOrder::where('order_number', $order_number)->first();
                
                if ($sale_order) {
                    SaleOrderSupport::updateOrderStatus('shipped', $order_number);
                    $markupPercent = $sale_order->company->markup ?? 0;
                }

                $this->packagingOrderService->handleUpdatePurchaseOrder($result, $order_number, $markupPercent);
                
                $skus = SkuCalculationService::calculateSkus($packaging_order, $sale_order);
                $transaction = TransactionProductService::transactionStoreConsumption($skus)['transaction'];
                if(!$transaction){
                    return redirect()->back()->with('error', 'Error creating transaction product consumption');
                }
                TransactionProductService::post($transaction);
                CompanyService::handleShippingCredit($order_number);

                $packaging_order = PackagingOrder::where('order_number', $order_number)->get();

                return view('shipstation.detail-ship', compact('packaging_order'));
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function getLabelSinglePackage(Request $request)
    {
        try {
            $request->validate([
                'packaging_order_id' => 'required|exists:packaging_orders,order_number', // Validate order number exists in the packaging_orders table
            ]);

            $selected_rate = $request->selected_rate;
            $rate_data = json_decode($selected_rate[0], true);
            $rate_id = $rate_data['rate_id'];
            $order_number = $request->packaging_order_id;
            $packaging_order = PackagingOrder::with('companyByCode', 'companyById')
                ->where('order_number', $order_number)->get();
            $_result = $this->shipStationAPIService->handleLabelByRateID($rate_id);
            $result = $this->shipstationService->handleRateLabelResponse($_result);
            $markupPercent = 0;
            $sale_order = SaleOrder::with('company', 'items.product')->where('order_number', $order_number)->first();

            if ($sale_order) {
                SaleOrderSupport::updateOrderStatus('shipped', $order_number);
                $markupPercent = optional($sale_order->company)->markup ?? 0;
            }else {
                $packagingOrder = $packaging_order->first();
                $markupPercent = $packagingOrder?->companyById?->markup
                    ?? $packagingOrder?->companyByCode?->markup
                    ?? 0;
            }
            
            $this->packagingOrderService->updateShipData($result, $order_number, $markupPercent);
            
            $skus = SkuCalculationService::calculateSkus($packaging_order, $sale_order);
            $transaction = TransactionProductService::transactionStoreConsumption($skus)['transaction'];
            TransactionProductService::post($transaction);
            CompanyService::handleShippingCredit($order_number);

            $packaging_order = PackagingOrder::where('order_number', $order_number)->get();

            return view('shipstation.detail-ship', compact('packaging_order'));
        } catch (\Exception $e) {
            Log::info("catch: " . $e->getMessage());
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function testView($order_number)
    {
        $packaging_order = PackagingOrder::where('order_number', $order_number)->get();

        return view('shipstation.detail-ship', compact('packaging_order'));
    }   

    public function voidLabel(Request $request)
    {
        try {
            $shipmentId = $request->code;

            $labelId = PackagingOrder::where('shipment_id', $shipmentId)->value('label_id');

            if ($labelId) {
                $result = $this->shipStationAPIService->handleVoidLabel($labelId);

                if (!$result["approved"]) {
                    return redirect()->back()->with('error', $result["message"]);
                }

                if ($result->successful()) {
                    $response = $result->json();

                    if ($response["approved"]) {
                        $order = PackagingOrder::where('shipment_id', $shipmentId)->first();

                        if ($order) {
                            PackagingOrder::where('shipment_id', $shipmentId)->update([
                                'label_void' => 1,
                                'status_id'  => 1
                            ]);

                            $orderNumber = $order->order_number;
                            SaleOrder::where('order_number', $orderNumber)->update([
                                'status' => 'voided'
                            ]);

                            LogHelper::handleLog('label_void', $orderNumber);
                        }

                        return redirect()->back()->with('success', 'Label voided');
                    }
                } else {
                    return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $result->json()["Message"]]));
                }
            } else {
                return redirect()->back()->with('error', __('messages/controller.shipstation.error.id_not_found'));
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function readyToShip(Request $request)
    {
        try {
            $orderNumber = $request->orderNumber;

            $payload = [
                'orderStatus'   => 'awaiting_shipment',
                'sortBy'        => 'CreateDate',
                'sortDir'       => 'DESC',
                'pageSize'      => 20,
                'orderNumber'   => $orderNumber
            ];


            $orders = $this->shipStationAPIService->handleOrdersList($payload);
            //dd($orders);
            if (isset($orders['error'])) {
                return redirect()->back()->with('error', $orders['message'] ?? __('messages/controller.shipstation.error.retrieving_orders'));
            }

            return view('shipstation.list', compact('orders'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }
}
