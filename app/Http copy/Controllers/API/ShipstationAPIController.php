<?php 

namespace App\Http\Controllers\API;

use App\Services\LabelService;
use App\Services\ShipStationAPIService;
use App\Services\PackagingOrderService;
use App\Http\Controllers\Controller;

class ShipstationAPIController extends Controller
{
    protected $shipStationAPIService;
    protected $packagingOrderService;
    protected $labelService;

    public function __construct(ShipStationAPIService $shipStationAPIService, 
        PackagingOrderService $packagingOrderService,
        LabelService $label_service)
    {
        $this->shipStationAPIService = $shipStationAPIService;
        $this->packagingOrderService = $packagingOrderService;
        $this->labelService = $label_service;
    }

    public function getOrder($orderNumber)
    {
        try {
            $order = $this->shipStationAPIService->getOrder($orderNumber);

            dd($order);

            return response()->json($order);
        } catch (\Exception $e) {
            return response()->json(['error' => __('messages/controller.main.error', ['message' => $e->getMessage()])], 500);
        }
    }

    public function updateOrderSize($orderNumber)
    {
        try {
            $order = $this->shipStationAPIService->updateOrder($orderNumber, []);

            dd($order);

            return response()->json($order);
        } catch (\Exception $e) {
            return response()->json(['error' => __('messages/controller.main.error', ['message' => $e->getMessage()])], 500);
        }
    }

    public function getShipmentList($orderNumber)
    {
        try {
            $result = $this->shipStationAPIService->handleShipmentList($orderNumber);

            dd($result);
            //dd($result);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => __('messages/controller.main.error', ['message' => $e->getMessage()])], 500);
        }
    }

    public function getShipmentOrderNumber($orderNumber)
    {
        try {
            $result = $this->shipStationAPIService->handleShipmentOrderNumber($orderNumber);

            dd($result->json());

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => __('messages/controller.main.error', ['message' => $e->getMessage()])], 500);
        }
    }

    public function getShipmentById($shipment_id)
    {
        try {
            $result = $this->shipStationAPIService->handleShipmentById($shipment_id);

            dd($result->json());

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => __('messages/controller.main.error', ['message' => $e->getMessage()])], 500);
        }
    }

    public function getLabelById($shipment_id)
    {
        try {
            $params = 'se-' . $shipment_id;
            $url = 'https://api.shipstation.com/v2/labels/?shipment_id=' . $params;
            $result = $this->shipStationAPIService->getAPIv2($url);
            dd($result->json());

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => __('messages/controller.main.error', ['message' => $e->getMessage()])], 500);
        }
    }

    public function getLabelTrackingNumber($trackingNumber)
    {
        try {
            $result = $this->shipStationAPIService->handleLabelTrackingNumber($trackingNumber);

            dd($result->json());

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => __('messages/controller.main.error', ['message' => $e->getMessage()])], 500);
        }
    }

    public function getRates($shipmentID)
    {
        try {
            $carrier_ids = [
                "se-913049", "se-913050", "se-1854772"
            ];

            $result = $this->shipStationAPIService->handleRates($shipmentID, $carrier_ids, []);

            dd($result->json());

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => __('messages/controller.main.error', ['message' => $e->getMessage()])], 500);
        }
    }

    public function getWarehousesList()
    {
        try {
            $result = $this->shipStationAPIService->handleWarehousesList();

            dd($result);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => __('messages/controller.main.error', ['message' => $e->getMessage()])], 500);
        }
    }

    public function getCarriersList()
    {
        try {
            $result = $this->shipStationAPIService->handleCarriersList();

            dd($result);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => __('messages/controller.main.error', ['message' => $e->getMessage()])], 500);
        }
    }

    public function getCarrier($carrier_code)
    {
        try {
            $result = $this->shipStationAPIService->handleCarrier($carrier_code);

            dd($result);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => __('messages/controller.main.error', ['message' => $e->getMessage()])], 500);
        }
    }

}
