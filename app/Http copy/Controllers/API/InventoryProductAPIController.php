<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Models\ProductLotNumber;
use App\Models\ProductSerialNumber;
use App\Models\InventoryProduct;
use App\Services\ProductService;
use App\Models\ProductMainLocation;
use App\Http\Controllers\Controller;
use App\Support\Inventory\InventorySupport;

class InventoryProductAPIController extends Controller
{
    protected $service;

    public function __construct(ProductService $service)
    {
        $this->service = $service;
    }

    /**
     * Get all inventory products
     *
     * @return \Illuminate\Http\Response
     */
    public function getAll(Request $request)
    {
        try {
            $query = InventorySupport::setQuery($request);
            $main_location_status = false;
            $main_location_id = false;
            $main_location_label = false;

            if(isset($request->product_id) && $request->product_id && isset($request->site_id) && $request->site_id){
                $total_available = InventoryProduct::getTotalAvailable($request->product_id, $request->site_id);
                $main_location = InventorySupport::getMainLocation($request);
                if ($main_location && $main_location->id) {
                    $main_location_status = true;
                    $main_location_id = $main_location->main_location_id;
                    $main_location_label = $main_location->mainLocation->label;
                }
            }

            $inventories = $query->with('product', 'product.company', 'warehouse', 'location', 'bin')->get();

            if ($request->filled('search')) {
                // $search = '%' . $request->search . '%';
                $search = $request->search;
                $inventories = $inventories->filter(function ($product) use ($search) {
                    return str_contains(strtolower($product->product->name), strtolower($search)) ||
                        str_contains(strtolower($product->product->barcode), strtolower($search)) ||
                        str_contains(strtolower($product->product->sku), strtolower($search));
                });
            }

            $inventories = $inventories->values()->all();

            return response()->json([
                'success' => true,
                'data' => $inventories,
                'total_available' => $total_available ?? null,
                'main_location' => $main_location_status,
                'main_location_id' => $main_location_id,
                'main_location_label' => $main_location_label,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving products: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

        /**
     * Get all inventory products
     *
     * @return \Illuminate\Http\Response
     */
    public function getInventoryByProduct(Request $request)
    {
        try {
            $query = InventorySupport::setQuery($request);

            $main_location = InventorySupport::getMainLocation($request);
            $total_available = InventoryProduct::getTotalAvailable($request->product_id, $request->site_id);

            if ($main_location && $main_location->id) {
                $inventories = InventorySupport::getInventoryByMainLocation($query, $main_location);

                return InventorySupport::setInventoryByMainLocation($inventories, $main_location, $total_available);
            }

            return InventorySupport::getInventoryProduct($query, $total_available);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving products: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getLotNumbers(Request $request)
    {
        try {
            $inventory_id = $request->inventory_id;
            $product_id = $request->product_id;
            $per_page = $request->per_page ?? 10;

            // Group by lot_number to show aggregated quantity and allow pagination on groups
            $query = ProductLotNumber::select(
                'lot_number',
                'expiration_date',
                'inventory_id',
                'product_id',
                DB::raw('count(*) as quantity_on_hand')
            )
            ->groupBy('lot_number', 'expiration_date', 'inventory_id', 'product_id');

            if ($inventory_id) {
                $query->where('inventory_id', $inventory_id);
            }

            if ($product_id) {
                $query->where('product_id', $product_id);
            }

            $lotNumbers = $query->paginate($per_page);

            return response()->json($lotNumbers);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving lot numbers: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function getSerialNumbers(Request $request)
    {
        try {
            $inventory_id = $request->inventory_id;
            $product_id = $request->product_id;
            $per_page = $request->per_page ?? 10;

            $query = ProductSerialNumber::with(['inventory.site', 'inventory.warehouse', 'inventory.location', 'inventory.bin']);

            if ($inventory_id) {
                $query->where('inventory_id', $inventory_id);
            }

            if ($product_id) {
                $query->where('product_id', $product_id);
            }

            $serialNumbers = $query->paginate($per_page);

            return response()->json($serialNumbers);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving serial numbers: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}