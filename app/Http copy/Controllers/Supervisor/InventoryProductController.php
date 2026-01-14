<?php

namespace App\Http\Controllers\Supervisor;

use App\Models\Site;
use App\Models\Product;
use App\Models\Acctivate;
use App\Models\ControlType;
use Illuminate\Http\Request;
use App\Models\InventoryProduct;
use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use App\Services\InventoryProductService;
use App\Services\TransactionProductService;

class InventoryProductController extends Controller
{
    protected $transactionProductService;
    protected $inventoryProductService;
    protected $acctivateModel;

    public function __construct(
        TransactionProductService $transactionProductService,
        InventoryProductService $inventoryProductService,
        Acctivate $acctivateModel,
    ) {
        $this->transactionProductService = $transactionProductService;
        $this->inventoryProductService = $inventoryProductService;
        $this->acctivateModel = $acctivateModel;
    }

    public function inventoryProductList(Request $request)
    {
        try {
            $sites = Site::where('active', 1)->get();
            $companies = Company::where('active', 1)->get();
            $product_types = $this->inventoryProductService->getProductsTypes();
            $response = ["total_products" => 0, "total_on_hand" => 0, "inventory" => collect()];
            $display = $request->get('display_results') ?? 'by_locations';

            if($display == 'by_locations'){
                $response = $this->inventoryProductService->getInventoryProductListByLocations($request);
            }else{
                $response = $this->inventoryProductService->getInventoryProductListByWarehouses($request);
            }

            $total_products = $response["total_products"];
            $total_on_hand = $response["total_on_hand"];
            $total_available = $response["total_available"];
            $inventory = $response["inventory"];

            $products_ids = $inventory->pluck('product_id')->unique()->values()->all();
            $ordersNumberShipped = $this->acctivateModel->setOrdersNumbersShipped($products_ids);

            if (count($ordersNumberShipped) > 0) {
                $this->inventoryProductService->refreshQuantityOrdersShipped($ordersNumberShipped);

                if($display == 'by_locations'){
                    $response = $this->inventoryProductService->getInventoryProductListByLocations($request);
                }else{
                    $response = $this->inventoryProductService->getInventoryProductListByWarehouses($request);
                }

                $total_products = $response["total_products"];
                $total_on_hand = $response["total_on_hand"];
                $total_available = $response["total_available"];
                $inventory = $response["inventory"];

                return view('inventory.list', compact('inventory', 'sites', 'total_products', 'total_on_hand', 'total_available', 'companies', 'product_types', 'display'));
            }

            return view('inventory.list', compact('inventory', 'sites', 'total_products', 'total_on_hand', 'total_available', 'companies', 'product_types', 'display'));
        } catch (\Throwable $th) {
            return redirect()->route('inventoryProductList')->with('error', $th->getMessage());
        }
    }
    
    public function inventoryProductConfirm($id)
    {
        try {
            $product = Product::where('id', $id)->first();

            return view('inventory.confirm', compact('product'));
        } catch (\Throwable $th) {
            return redirect()->route('inventoryProductList')->with('error', $th->getMessage());
        }
    }

    public function inventoryProductEdit($id)
    {
        try {
            $product = Product::where('id', (int)$id)->first();

            $filters = $this->transactionProductService->getFiltersValues();
            $inventory = InventoryProduct::where('product_id', $id)->first();
            $standard = ControlType::where('code', 'STNDRD')->where('id', (string) $product->control_type_id)->exists();

            return view('inventory.edit', compact('product', 'filters', 'standard', 'inventory'));
        } catch (\Throwable $th) {
            return redirect()->route('inventoryProductList')->with('error', $th->getMessage());
        }
    }

    public function inventoryProductUpdate(Request $request, $id)
    {
        try {
            $request->validate([
                'product_id'        => 'required|exists:products,id',
                'site_id'           => 'required|exists:sites,id',
                'warehouse_id'      => 'required|exists:warehouses,id',
                'location_id'       => 'required|exists:locations,id',
                'bin_id'            => 'nullable|exists:bins,id',
                'quantity_on_hand'  => 'required|numeric|min:-1000000',
                'expiration_date'   => 'nullable|date',
                'lot_number'        => 'nullable|string|max:50',
                'serial_number'     => 'nullable|string|max:50',
            ]);

            $inventory = InventoryProduct::where('product_id', (int) $id)->first();

            $inventory->update([
                'product_id'        => $request->product_id,
                'site_id'           => $request->site_id,
                'warehouse_id'      => $request->warehouse_id,
                'location_id'       => $request->location_id ?? null,
                'bin_id'            => $request->bin_id ?? null,
                'quantity_on_hand'  => (int) $request->quantity_on_hand,
                'expiration_date'   => $request->expiration_date,
                'lot_number'        => $request->lot_number,
                'serial_number'     => $request->serial_number,
            ]);

            return redirect()->route('inventoryProductList')->with('success', __('main.update_success'));
        } catch (\Throwable $th) {
            return redirect()->route('inventoryProductList')->with('error', $th->getMessage());
        }
    }

    public function exportInventory(Request $request)
    {
        try {
            $request->validate([
                'type' => 'required|in:location,warehouse',
            ]);
            
            $type = $request->input('type');
            $inventory = collect();
            $site_id = $request->site_id ?? Auth::user()->site_id;

            if($type == 'location'){
                $query = $this->inventoryProductService->setInventoryListQuery($request->query(), $site_id);
                $inventory = $query->get();
            }else{
                $warehouses = $this->inventoryProductService
                    ->setInventoryByWarehouseQuery($request, $site_id)
                    ->get();
                $inventoryProducts = InventoryProduct::where('site_id', $site_id)->get();

                $availableByWarehouse = $inventoryProducts
                    ->groupBy('warehouse_id')
                    ->map(fn ($rows) => $rows->sum->quantity_available);
                $inventory = $warehouses->transform(function ($row) use ($availableByWarehouse) {
                    $row->quantity_available = $availableByWarehouse[$row->warehouse_id] ?? 0;
                    return $row;
                });
            }

            if (!$inventory) {
                return redirect()->back()->with('error', 'No inventory data to export.');
            }

            $fileName = 'inventory_export_' . $type . '_' . now()->format('m/d/Y H:i') . '.csv';

            return response()->view('inventory.export.export_' . $type, compact('inventory'))
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', "attachment; filename={$fileName}");
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }


}