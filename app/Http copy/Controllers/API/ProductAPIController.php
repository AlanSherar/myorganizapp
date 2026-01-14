<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AltID;
use App\Models\Product;
use App\Models\KitComponent;
use App\Models\ProductLabel;
use App\Models\ProductType;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductAPIController extends Controller
{
    protected $service;

    public function __construct(ProductService $service)
    {
        $this->service = $service;
    }
    /**
     * Get all products
     *
     * @return \Illuminate\Http\Response
     */
    public function getAll(Request $request)
    {
        try {
            $rows = $request->rows ?? 15;
            $products = $this->service::listQuery($request)->with(['company', 'control_type', 'type'])->orderBy('name')->paginate($rows);

            return response()->json([
                'success' => true,
                'data' => $products->items(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'next_page' => $products->nextPageUrl(),
                ],
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving products',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get product by ID
     *
     * @param Request $request
     * @param string|null $id
     * @return \Illuminate\Http\Response
     */
    public function getByID(Request $request, $id = null)
    {
        try {
            if (!$id) {
                return response()->json([
                    'success' => false,
                    'message' => __('main.messages.id_missing')
                ], Response::HTTP_BAD_REQUEST);
            }

            $product = $this->service->getByID($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => __('main.messages.reference_not_found', ['id' => $id])
                ], Response::HTTP_NOT_FOUND);
            }

            $product->load([
                'company',
                'control_type',
                'type',
                'main_locations.site',
                'main_locations.warehouse',
                'main_locations.mainLocation',
                'main_locations.bin',
            ]);

            return response()->json([
                'success' => true,
                'data' => $product
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('main.messages.unexpected_error'),
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get product by search term
     *
     * @param Request $request
     * @param string|null $search
     * @return \Illuminate\Http\Response
     */
    public function getBySearch(Request $request)
    {
        $search = $request->search;
        try {
            if (!$search) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search parameter is required'
                ], Response::HTTP_BAD_REQUEST);
            }

            $products = $this->service->listQuery($request)->get();

            if (!$products) {
                return response()->json([
                    'success' => false,
                    'message' => __('main.messages.reference_not_found', ['id' => $search])
                ], Response::HTTP_NOT_FOUND);
            }

            $products->load(['company', 'control_type', 'type']);

            return response()->json([
                'success' => true,
                'data' => $products
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving products',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get products by name
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getByName(Request $request, $name = null)
    {
        try {

            if (!$name) {
                return response()->json([
                    'success' => false,
                    'message' => 'Name parameter is required'
                ], Response::HTTP_BAD_REQUEST);
            }
            $products = Product::with(['company', 'control_type', 'type'])
                ->where('name', 'LIKE', '%' . str_replace(['%', '_'], ['\%', '\_'], $name) . '%')
                ->get();
            $products->load('company', 'control_type', 'type');
            return response()->json([
                'success' => true,
                'data' => $products
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving products',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * Get products by type
     *
     * @param Request $request
     * @param string|null $type
     * @return \Illuminate\Http\Response
     */
    public function getByType(Request $request, $type = null)
    {
        try {
            if (!$type) {
                return response()->json([
                    'success' => false,
                    'message' => 'Type parameter is required'
                ], Response::HTTP_BAD_REQUEST);
            }

            // recupero el product_type que es igual al type que me pasan al endpoint
            $product_type = ProductType::where('name', $type)->first();
            // verifico que exista
            if (!$product_type) {
                return response()->json([
                    'success' => false,
                    'message' => 'Type not found'
                ], Response::HTTP_NOT_FOUND);
            }

            // busco los productos que tienen un type igual a ese //
            $products = Product::with(['company', 'control_type', 'type'])
                ->where('type_id', $product_type->id)
                ->select('sku', 'name', 'barcode')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $products
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving products',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    // getAllComponentsByKitID
    public function getAllComponentsByKitID(Request $request, $id)
    {
        try {
            if (!isset($id) || empty($id)) {
                return response()->json([
                    'success' => false,
                    'message' => __('main.messages.id_missing')
                ], Response::HTTP_BAD_REQUEST);
            }
            // in the future this should work with SKU, barcodes, db_ids, any type of unique identificator
            // so i will use service->getProductByGeneralID($id) 
            // and get the product independently of the type of id used
            $product = $this->service->getProductByGeneralID($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => __('main.messages.reference_not_found', ['id' => $id]),
                ], Response::HTTP_NOT_FOUND);
            }
            $productID = $product->id;

            $products = $this->service->getAllComponentsByKitID($productID);

            if (!$products) {
                return response()->json([
                    'success' => false,
                    'message' => __('main.unexpected_error'),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            //$products->load('company', 'control_type', 'type');
            $products->load('component.company', 'component.control_type', 'component.type', 'component.barcode');

            return response()->json([
                'success' => true,
                'data' => $products
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving products',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
