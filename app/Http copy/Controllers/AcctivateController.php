<?php 
namespace App\Http\Controllers;

use App\Models\Acctivate;
use Illuminate\Http\Request;
use App\Jobs\ShippingCreditJob;
use App\Jobs\CalculateShippingCostJob;
use App\Services\ShippingCreditService;

class AcctivateController extends Controller
{
    private $acctivateModel;
    private $shippingCreditService;

    public function __construct(Acctivate $acctivate, ShippingCreditService $shippingCreditService)
    {
        $this->acctivateModel = $acctivate;
        $this->shippingCreditService = $shippingCreditService;
    }

    public function getAcctivateOrders(Request $request)
    {
        try {
            $sortColumn = request()->query('sort_column', 'tbOrders.OrderDate'); 
            $sortDirection = request()->query('sort_direction', 'DESC');
            $branch_id = request()->query('branch_id', '');
            $date_from = request()->query('date_from', '');
            $date_to = request()->query('date_to', '');
            $order_number = request()->query('order_number', '');
            $rows = request()->query('per_page', 10);

            $obj = (object) [
                "sort_column"       => $sortColumn,
                "sort_direction"    => $sortDirection,
                "branch_id"         => $branch_id,
                "date_from"         => $date_from,
                "date_to"           => $date_to,
                "order_number"      => $order_number,
                "rows"              => $rows
            ];

            $orders = $this->acctivateModel->getAcctivateOrders($obj);

            if($orders->isEmpty()){
                return redirect()->back()->with('error', "Order not found");
            }
            $branchIds = $orders->pluck('BranchID')->unique()->toArray();

            $data = $this->shippingCreditService->getDataToUpdate();

            if($data || !empty($data)){
                dispatch(new ShippingCreditJob($data))->onQueue('shipping_credit');
                //$this->shippingCreditService->handleShippingCreditUnchecked($data);
            }

            return view('acctivate.list', compact('orders', 'sortColumn', 'sortDirection', 'branchIds'));   
        } catch (\Throwable $th) {
            return redirect()->route('getAcctivateOrders')->with('error', $th->getMessage());
        }
    }

}
