<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\BinService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class BinAPIController extends Controller
{
    protected $service;

    public function __construct(BinService $service)
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
            $bins = $this->service->getAll($request);

            return response()->json([
                'success' => true,
                'data' => $bins
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
    // public function getByWarehouseId(Request $request, $warehouseId)
    // {
    //     try {
    //         $locations = $this->service->getByWarehouseId($request, $warehouseId);

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

    public function getByLocationId(Request $request, $locationId)
    {
        try {
            $bins = $this->service->getByLocationId($request, $locationId);

            return response()->json([
                'success' => true,
                'data' => $bins
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
