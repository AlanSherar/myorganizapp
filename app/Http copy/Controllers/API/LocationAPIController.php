<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\LocationService;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class LocationAPIController extends Controller
{
    protected $service;

    public function __construct(LocationService $service)
    {
        $this->service = $service;
    }
    /**
     * Get all locations
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAll(Request $request)
    {
        try {
            $locations = $this->service->getAll($request);

            return response()->json([
                'success' => true,
                'data' => $locations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // /**
    //  * Get locations by warehouse ID
    //  *
    //  * @param int $warehouseId
    //  * @return \Illuminate\Http\JsonResponse
    //  */
    // public function getByWarehouseId($warehouseId)
    // {
    //     try {
    //         $locations = $this->service->getByWarehouseId($warehouseId);

    //         return response()->json([
    //             'success' => true,
    //             'data' => $locations
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ], Response::HTTP_INTERNAL_SERVER_ERROR);
    //     }
    // }

    /**
     * Get locations by site ID
     *
     * @param int $siteId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBySiteId($siteId)
    {
        try {
            $locations = $this->service->getBySiteId($siteId);

            return response()->json([
                'success' => true,
                'data' => $locations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

        /**
     * Get all locations
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function locationsBySite($site_id)
    {
        try {
            $locations = $this->service->getLocationsNoMain($site_id);

            return response()->json([
                'success' => true,
                'data' => $locations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
