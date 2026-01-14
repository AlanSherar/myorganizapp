<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WarehouseAPIController extends Controller
{
    protected $service;

    public function __construct(WarehouseService $service)
    {
        $this->service = $service;
    }
    
    /**
     * Get warehouses by site ID
     *
     * @param int $siteId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBySiteId($siteId)
    {
        try {
            $warehouses = $this->service->getBySiteId($siteId);

            return response()->json([
                'success' => true,
                'data' => $warehouses
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
