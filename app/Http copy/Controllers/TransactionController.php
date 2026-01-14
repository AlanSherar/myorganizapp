<?php

namespace App\Http\Controllers;

use ArrayObject;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Site;
use App\Models\Acctivate;
use App\Helpers\LogHelper;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\TransactionService;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TransactionCSVReport;
use App\QueryBuilder\AactivateQueryBuilder;

class TransactionController extends Controller
{
    private $acctivateModel;
    private $AactivateQueryBuilder;
    private $transactionService;

    public function __construct(
        Acctivate $acctivate,
        AactivateQueryBuilder $AactivateQueryBuilder,
        TransactionService $transactionService
    ) {
        $this->AactivateQueryBuilder = $AactivateQueryBuilder;
        $this->acctivateModel = $acctivate;
        $this->AactivateQueryBuilder = $AactivateQueryBuilder;
        $this->transactionService = $transactionService;
    }

    public function transferProducts(Request $request)
    {
        try {
            $warehouse_from_id = $request->warehouse_from_id;
            $warehouse_destination_id = $request->warehouse_destination_id;

            $location_from_description = $request->location_from_description;
            $location_destination_description = $request->location_destination_input ? $request->location_destination_input : $request->location_destination_description;

            $GUIDWarehouse = $request->warehouse_from;
            $GUIDWHLocation = $request->location_from;

            $location_input = $request->location_input;

            $warehouses = $this->acctivateModel->getWarehouses();

            $data = null;
            $obj = null;

            if ($GUIDWarehouse && $GUIDWHLocation) {
                if (!$location_destination_description) {
                    return redirect()
                        ->back()
                        ->with('error', __('messages/controller.transaction.error.location_to_required'))
                        ->withInput();
                }

                $products = $this->AactivateQueryBuilder->productByLocationQuery($GUIDWarehouse, $GUIDWHLocation);
                $data = $products->paginate(10);

                if ($data->isEmpty()) {
                    return redirect()
                        ->back()
                        ->with('error', __('messages/controller.transaction.error.no_products'))
                        ->withInput();
                }

                $items_quantity = (clone $products)->sum(DB::raw('ISNULL(ROUND(LSI.OnHand, 0), ROUND(LocationSummary.OnHand, 0))'));
                $products_quantity = $products->count();

                $obj = (object) [
                    "warehouse_from"            => $warehouse_from_id,
                    "warehouse_destination"     => $warehouse_destination_id,
                    "location_from"             => $location_from_description,
                    "location_destination"      => $location_destination_description,
                    "items_quantity"            => $items_quantity,
                    "products_quantity"         => $products_quantity,
                    "products"                  => $data
                ];

            }
            
            return view('transaction.products', compact('warehouses', 'data', 'obj'));            
        } catch (\Throwable $th) {
            dd($th->getMessage());
            return redirect()->route('transferProducts')->with('error', __('messages/controller.main.error', ['messages' => $th->getMessage()]));
        }
    }

    public function getLocationsWarehouse($GUIDWarehouse)
    {
        try {
            $locations = $this->acctivateModel->getLocationsByWarehouse($GUIDWarehouse);

            if ($locations->isEmpty()) {
                return redirect()->route('transferProducts')->with('error', __('messages/controller.error.no_locations'));
            }

            return response()->json($locations);
        } catch (\Throwable $th) {
            return response()->json(['error' => __('messages/controller.main.error', ['messages' => $th->getMessage()])], 500);
        }
    }

    public function handleCsvTransaction(Request $request)
    {
        try {
            $title = $request->title ?? '';
            $_params = $request->params;

            $params = json_decode($_params, true);
            $object = new ArrayObject($params, ArrayObject::ARRAY_AS_PROPS);
            $location_from_description = $object->location_from_description ?? null;

            $site_id = Auth::user()->site_id;
            $timezzone = Site::find($site_id)->timezone;
            $timezzone = $timezzone ?? 'America/Chicago';
            $date = Carbon::now($timezzone)->format('m-d-Y_H\hi\m');
            $title = $title . '_from_' . strtoupper($location_from_description) . ' (' . $date . ')';
            $date = Carbon::now($timezzone)->format('m-d-Y');

            $GUIDWarehouse = $object->warehouse_from ?? null;
            $GUIDWHLocation = $object->location_from ?? null;

            if (empty($GUIDWarehouse)) {
                return redirect()->back()->with('error', __('messages/controller.error.warehouse_from_required'));
            }

            if (empty($GUIDWHLocation)) {
                return redirect()->back()->with('error', __('messages/controller.error.location_from_required'));
            }

            $products = $this->AactivateQueryBuilder->productByLocationQuery($GUIDWarehouse, $GUIDWHLocation);
            $_data = $products->get()->toArray();
            $quantity_items = (clone $products)->sum(DB::raw('ISNULL(ROUND(LSI.OnHand, 0), ROUND(LocationSummary.OnHand, 0))'));
            $quantity_product = $products->count();

            if (empty($_data)) {
                return redirect()->back()->with('error', __('messages/controller.transaction.error.no_products'));
            }

            $data = $this->transactionService->handleCSVRows($_data, $date, $object);

            $this->transactionService->storeTransactionData($_data, $object, $quantity_product, $quantity_items);

            LogHelper::handleLog('transaction');

            return Excel::download(new TransactionCSVReport([], $data), $title . '.csv');
        } catch (\Throwable $th) {
            dd($th->getMessage());
            //return redirect()->route('transferProducts')->with('error', 'An error occurred: ' . $th->getMessage());
        }
    }

    public function getTransactionsList(Request $request)
    {
        try {
            $sortColumn = request()->query('sort_column', 'created_at');
            $sortDirection = request()->query('sort_direction', 'DESC');

            $location_from = $request->location_from ?? '';
            $location_destination = $request->location_destination ?? '';
            $user_id = $request->user_id ?? '';
            $date_from = $request->date_from ?? '';
            $date_to = $request->date_to ?? '';

            $obj = (object) [
                "sort_column"               => $sortColumn,
                "sort_direction"            => $sortDirection,
                "location_from"             => $location_from,
                "location_destination"      => $location_destination,
                "user_id"                   => $user_id,
                "date_from"                 => $date_from,
                "date_to"                   => $date_to
            ];

            $transactions = $this->transactionService->getTransactions($obj)->paginate(10);

            //$transactions->appends(request()->query());
            $users_id = $this->transactionService->getTransactions($obj)->pluck('user_id')->unique()->toArray();
            $users = User::whereIn('id', $users_id)->get();

            return view('transaction.list', compact('transactions', 'users'));
        } catch (\Throwable $th) {
            dd($th->getMessage());
            return redirect()->route('transferProducts')->with('error', __('messages/controller.main.error', ['messages' => $th->getMessage()]));
        }
    }

    public function getTransactionDetails($transaction_group_id)
    {
        try {
            // Récupère uniquement les product_id des transactions correspondantes
            $productIds = Transaction::where('transaction_group_id', $transaction_group_id)
                ->pluck('product_id'); // pluck permet d'extraire uniquement la colonne product_id

            // Si aucune transaction n'est trouvée, renvoie une erreur
            if ($productIds->isEmpty()) {
                return response()->json(['error' => __('messages/controller.error.not_found')], 404);
            }

            // Retourne la liste des product_id sous forme de réponse JSON
            return response()->json($productIds);
        } catch (\Throwable $th) {
            // En cas d'erreur, renvoie un message d'erreur générique
            return response()->json(['error' => __('messages/controller.main.error', ['messages' =>  $th->getMessage()])], 500);
        }
    }

    public function locationProducts(Request $request)
    {
        try {
            $location_input = $request->location_input;
            $rows = $request->per_page ?? 10;

            $obj = null;
            
            if($location_input){
                $products = $this->AactivateQueryBuilder->productByLocationInput($location_input);
                $products_quantity = $products->count();
                $items_quantity = (clone $products)->sum(DB::raw('ISNULL(ROUND(LSI.OnHand, 0), ROUND(LocationSummary.OnHand, 0))'));
                $data = $products->paginate($rows)->withQueryString();

                $obj = (object) [
                    "items_quantity"            => $items_quantity,
                    "products_quantity"         => $products_quantity,
                    "products"                  => $data,
                    "location_from"             => $location_input
                ];
             
                if ($data->isEmpty()) {
                    return redirect()
                        ->back()
                        ->with('error', __('messages/controller.transaction.error.no_products'))
                        ->withInput();
                }
            }
            
            return view('transaction.products-location', compact('obj'));
        } catch (\Throwable $th) {
            dd($th->getMessage());
        }
    }

    public function handleCsvProductsLocation(Request $request)
    {
        try {
            $title = $request->title ?? '';
            $_params = $request->params;
            $params = json_decode($_params, true);
            $object = new ArrayObject($params, ArrayObject::ARRAY_AS_PROPS);
            $location_input = $object->location_input ?? null;

            $site_id = Auth::user()->site_id;
            $timezzone = Site::find($site_id)->timezone;
            $timezzone = $timezzone ?? 'America/Chicago';
            $date = Carbon::now($timezzone)->format('m-d-Y_H\hi\m');
            $title = $title . '_from_' . $location_input . ' (' . $date . ')';

            $_data = $this->AactivateQueryBuilder->productByLocationInput($location_input)->get()->toArray();

            if (empty($_data)) {
                return redirect()->back()->with('error', __('messages/controller.transaction.error.no_products'));
            }

            $data = $this->transactionService->handleCSVProductsLocationRows($_data);

            return Excel::download(new TransactionCSVReport([], $data), $title . '.csv');
        } catch (\Throwable $th) {
            dd($th->getMessage());
            //return redirect()->route('transferProducts')->with('error', 'An error occurred: ' . $th->getMessage());
        }
    }
}
