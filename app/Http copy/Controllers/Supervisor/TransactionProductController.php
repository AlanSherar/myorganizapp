<?php

namespace App\Http\Controllers\Supervisor;

use App\Helpers\LogHelper;
use Carbon\Carbon;
use App\Models\Product;
use App\Models\MovementType;
use Illuminate\Http\Request;
use App\Models\TransactionProduct;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use App\Models\TransactionProductDetail;
use App\Services\InventoryProductService;
use App\Models\ProductLotNumber;
use App\Services\TransactionProductService;
use App\Models\ProductSerialNumber;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class TransactionProductController extends Controller
{
    protected $transactionProductService;
    protected $inventoryProductService;

    public function __construct(
        TransactionProductService $transactionProductService,
        InventoryProductService $inventoryProductService
    ) {
        $this->transactionProductService = $transactionProductService;
        $this->inventoryProductService = $inventoryProductService;
    }

    public function transactionProductList(Request $request)
    {
        try {
            $filters = $this->transactionProductService->getFiltersValues();

            $transactions = $this->transactionProductService->getTransactionProductList($request);

            $users = User::where('active', 1)->get();

            return view('transaction-product.list', compact('transactions', 'filters', 'users'));
        } catch (\Throwable $th) {
            return redirect()->route('transactionProductList')->with('error', $th->getMessage());
        }
    }

    public function transactionProductCreate(Request $request)
    {
        try {
            $selected_movement = $request['movement_type'];
            $transaction_date = $request['transaction_date'];
            $movement_types = MovementType::all();
            $sites = Site::where('active', 1)->get();

            // $filters = $this->transactionProductService->getFiltersValues();


            return view('transaction-product.create', compact('selected_movement', 'transaction_date', 'movement_types', 'sites'));
        } catch (\Throwable $th) {
            return redirect()->route('transactionProductList')->with('error', $th->getMessage());
        }
    }

    // changed by "v1 feedback"
    public function transactionProductSearch(Request $request)
    {
        try {
            $filters = $this->transactionProductService->getFiltersValues();

            $products = $this->transactionProductService->getProducts($request);

            return view('transaction-product.search', compact('products', 'filters'));
        } catch (\Throwable $th) {
            return redirect()->route('transactionProductList')->with('error', $th->getMessage());
        }
    }

    public function transactionProductConfirm(Request $request)
    {
        try {
            $filters = $this->transactionProductService->getFiltersValues();

            $validated = $request->validate([
                'product_ids' => 'required|array',
                'product_ids.*' => 'exists:products,id',
            ]);

            $products = Product::whereIn('id', $validated['product_ids'])->with('control_type')->get();

            return view('transaction-product.confirm', compact('products', 'filters'));
        } catch (\Throwable $th) {
            return redirect()->route('transactionProductList')->with('error', $th->getMessage());
        }
    }

    public function transactionProductStore(Request $request)
    {
        try {

            $request->validate([
                'movement_type' => 'required|exists:movement_types,label',
                'transaction_date' => 'required|date',
            ]);
            // Maybe all the transaction creation logic should be called separately first and then the logic of the details
            // But maybe to use DB::transaction, this could all be in a service method called and then the details in a separate method inside
            $result = null;
            switch ($request->movement_type) {
                case 'Receipt':
                    $result = $this->transactionProductService->transactionStoreReceipt($request);
                    break;
                case 'Transfer':
                    $result = $this->transactionProductService->transactionStoreTransfer($request);
                    break;
                case 'Adjustment':
                    $result = TransactionProductService::transactionStoreAdjustment($request);
                    break;
                default:
                    // throw new \Exception('Movement type not supported');
                    throw new \Exception('Movement type not supported');
                    break;
            }

            if (!$result['ok']) {
                throw new \Exception($result['message']);
            }

            LogHelper::handleLog('create', '', '', $request->movement_type . ' Transaction', $result['transaction']->id);

            return redirect()->route('transactionProductList')->with('success', $result['message']);
        } catch (\Throwable $th) {
            return redirect()->back()->withInput()->with('error', 'Error saving transaction: ' . $th->getMessage());
        }
    }

    public function transactionProductEdit($id)
    {
        try {
            $transaction = TransactionProduct::where('id', $id)->with(['movementType'])->first();

            if (!$transaction) {
                return redirect()->route('transactionProductList')->with('error', 'Transaction not found');
            }
            if ($transaction->posted) {
                return redirect()->route('transactionProductList')->with('error', 'Transaction already posted');
            }

            $transaction_id = $id;

            $details = TransactionProductDetail::where('transaction_id', $id)->with(['product', 'product.company', 'locationTo', 'binTo', 'locationFrom', 'binFrom'])->get();
            /* if (count($details) == 0) {
                return redirect()->route('transactionProductList')->with('error', 'product not found');
            } */
            
            foreach($details as $detail){
                $detail->product->is_lot = $detail->product?->is_lot();
                $detail->product->is_serial = $detail->product?->is_serial();
            }
            
            // if transaction is full location
            if ($transaction->full_location) {
                // filter details and only include each detail once for each location_from
                $details = $details->unique('location_from_id');
            }

            foreach ($details as $detail) {
                // we specify 'from' because for edition we want to have the inventory from for tracking stock
                $detail->inventory = InventoryProductService::getInventoryProductByTransaction($detail, $transaction, 'from');
            }

            $selected_movement = $transaction->movementType->label;
            $transaction->details = $details;
            $isFullLocation = $transaction->full_location ? true : false;

            $sites = Site::where('active', 1)->get();
            // dd($details);
            return view('transaction-product.edit', compact('details', 'transaction_id', 'transaction', 'sites', 'isFullLocation', 'selected_movement'));
        } catch (\Throwable $th) {
            return redirect()->route('transactionProductList')->with('error', $th->getMessage());
        }
    }

    public function transactionProductUpdate(Request $request, $id)
    {
        try {
            $request->validate([
                'transaction_date' => 'required|date',
            ]);
            $transaction = TransactionProduct::where('id', $id)->with('movementType', 'details')->first();
            // Exists transaction
            if (!$transaction) {
                throw new \Exception('Transaction not found');
            }
            
            
            if ($transaction->posted) {
                throw new \Exception('Transaction already posted');
            }

            $updated = $this->transactionProductService->transactionUpdate($request, $transaction);

            if (!$updated['ok']) {
                throw new \Exception($updated['message']);
            }

            return redirect()->route('transactionProductDetail', ['id' => $transaction->id])->with('success', $updated['message']);
        } catch (\Throwable $th) {
            return redirect()->back()->withInput()->with('error', $th->getMessage());
        }
    }

    public function transactionProductDetail($id)
    {
        try {
            $transaction = TransactionProduct::with(['siteTo', 'warehouseTo', 'siteFrom', 'warehouseFrom', 'movementType', 'postedBy', 'canceledBy', 'updatedBy'])->findOrFail($id);

            if (!$transaction) {
                return redirect()->route('transactionProductList')->with('error', __('transaction-product/main.transaction_not_found'));
            }

            $detailsIds = $transaction->details->pluck('id');

            // if ($transaction->posted) {
            //     foreach ($details as $detail) {
            //         $detail['inventory'] = InventoryProductService::getInventoryProductByTransaction($detail, $transaction);
            //     }
            // }

            return view('transaction-product.details', compact('detailsIds', 'transaction'));
        } catch (\Throwable $th) {
            return redirect()->route('transactionProductList')->with('error', $th->getMessage());
        }
    }

    public function getTransactionProductPost($id)
    {
        try {
            $transaction = $this->transactionProductService->getTransactionWithRelations($id);

            return view('transaction-product.post', [
                'transaction' => $transaction,
                'products' => $transaction->details
            ]);
        } catch (\Throwable $th) {
            return redirect()->route('transactionProductList')->with('error', 'Transaction not found or error occurred: ' . $th->getMessage());
        }
    }

    public function transactionProductPost($id)
    {
        try {
            $transaction = $this->transactionProductService->getTransactionWithRelations($id);

            if (!$transaction->id) {
                return redirect()->route('transactionProductList')->with('error', __('transaction-product/main.transaction_not_found'));
            }

            if ($transaction->posted) {
                return redirect()->route('transactionProductList')->with('error', __('transaction-product/main.transaction_already_posted'));
            }

            DB::beginTransaction();

            // post transaction
            $postTransactionResponse = TransactionProductService::post($transaction);

            if (!$postTransactionResponse['ok']) {
                throw new \Exception($postTransactionResponse['message']);
            }

            DB::commit();

            return redirect()->route('transactionProductList')->with('success', __('transaction-product/main.transaction_posted_successfully'));
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->route('transactionProductList')->with('error', $th->getMessage());
        }
    }

    public function getTransactionProductUnpost($id)
    {
        try {
            $transaction = $this->transactionProductService->getTransactionWithRelations($id);

            if (!$transaction->id) {
                return redirect()->route('transactionProductList')->with('error', __('transaction-product/main.transaction_not_found'));
            }
            if (!$transaction->posted) {
                return redirect()->route('transactionProductList')->with('error', __('transaction-product/main.transaction_not_posted'));
            }
            $details = $transaction->details;

            switch ($transaction->movementType->name) {
                case 'transfer':
                    throw new \Exception(__('transaction-product/main.transfer_transactions_cannot_be_unposted'));
                case 'receipt':
                    $type = 'to';
                    break;
                case 'adjustment':
                    $type = 'from';
                    break;
                default:
                    throw new \Exception('Movement type not supported');
            }

            foreach ($details as $detail) {
                $detail['inventory'] = InventoryProductService::getInventoryProductByTransaction($detail, $transaction, $type);
            }

            return view('transaction-product.unpost', [
                'transaction' => $transaction,
                //'inventory' => $inventory,
                'products' => $details
            ]);
        } catch (\Throwable $th) {
            return redirect()->route('transactionProductList')->with('error', $th->getMessage());
        }
    }

    public function transactionProductUnpost($id)
    {
        try {
            DB::beginTransaction();
            $transaction = $this->transactionProductService->getTransactionWithRelations($id);

            if (!$transaction->id) {
                throw new \Exception(__('transaction-product/main.transaction_not_found'));
            }
            if (!$transaction->posted) {
                throw new \Exception(__('transaction-product/main.transaction_not_posted'));
            }
            if ($transaction->movementType->name == 'transfer') {
                throw new \Exception(__('transaction-product/main.transfer_transactions_cannot_be_unposted'));
            }

            $unpostInventoryResponse = $this->inventoryProductService->unpostTransaction($transaction);

            if (!$unpostInventoryResponse['ok']) {
                throw new \Exception($unpostInventoryResponse['message']);
            }

            $this->transactionProductService->unpost($transaction);

            DB::commit();
            return redirect()->route('inventoryProductList')->with('success', 'Transaction canceled successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            return redirect()->route('transactionProductList')->with('error', $th->getMessage());
        }
    }

    public function movementList()
    {
        try {
            $movements = MovementType::select('label', 'description')->whereNotIn('name', ["consumption", "pick"])->get();

            return response()->json($movements);
        } catch (\Throwable $th) {
            return redirect()->route('transactionProductList')->with('error', $th->getMessage());
        }
    }
}
