<?php 
namespace App\Http\Controllers\Supervisor;

use App\Models\User;
use Illuminate\Http\Request;
use App\Services\ClockTimeService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\IndicatorService;
use App\Services\Dashboard\IndicatorTotalUnits;
use App\Services\Dashboard\IndicatorCountService;

class DashboardIndicatorController extends Controller
{
    private $indicatorService;
    private $totalUnitsService;
    private $clockTimeService;
    private $indicatorCountService;

    public function __construct(
        IndicatorService $indicatorService, 
        ClockTimeService $clockTimeService,
        IndicatorTotalUnits $totalUnitsService,
        IndicatorCountService $indicatorCountService)
    {
        $this->indicatorService = $indicatorService;
        $this->totalUnitsService = $totalUnitsService;
        $this->clockTimeService = $clockTimeService;
        $this->indicatorCountService = $indicatorCountService;
    }

    public function ordersOnHold(Request $request)
    {
        try {
            $sortColumn = request()->query('sort_column', 'tbOrders.EntryDate'); 
            $sortDirection = request()->query('sort_direction', 'ASC');

            $obj = (object) [
                "client_id"         => $request->input('client_id'),
                "date_from"         => $request->input('date_from'),
                "date_to"           => $request->input('date_to'),
                "order_number"      => $request->input('order_number'),
                "time_filter"       => $request->input('time_filter'),
                "sort_column"       => $sortColumn,
                "sort_direction"    => $sortDirection,
                "perPage"           => $request->input('perPage', 10),
            ];

            $totalUnits = $this->totalUnitsService->getOrdersOnHoldTotalUnits($obj);
            $result = $this->indicatorService->getOrdersOnHold($obj)->appends(request()->query());
            $client_ids = $result->pluck('BranchID')->unique();

            $ordersCount = $result->total();
            $ordersUnits = $totalUnits;

            return view('indicator.orders-on-hold', compact('result', 'client_ids', 'ordersUnits', 'ordersCount', 'sortColumn', 'sortDirection'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function readyToPick(Request $request)
    {
        try {
            $sortColumn = request()->query('sort_column', 'tbOrders.EntryDate'); 
            $sortDirection = request()->query('sort_direction', 'DESC');

            $obj = (object) [
                "client_id"         => $request->input('client_id'),
                "date_from"         => $request->input('date_from'),
                "date_to"           => $request->input('date_to'),
                "time_filter"       => $request->input('time_filter'),
                "hours_ago"         => $request->input('hours_ago_cus') ?? $request->input('hours_ago'),
                "order_number"      => $request->input('order_number'),
                "sort_column"       => $sortColumn,
                "sort_direction"    => $sortDirection,
                "perPage"           => $request->input('perPage', 10),
            ];

            $totalUnits = $this->totalUnitsService->getReadyToPickTotalUnits($obj);
            $result = $this->indicatorService->getReadyToPickOrders($obj)->appends(request()->query());
            $client_ids = $result->pluck('BranchID')->unique();

            $ordersCount = $result->total();
            $ordersUnits = $totalUnits;

            return view('indicator.ready-to-pick', compact('result', 'client_ids', 'ordersUnits', 'ordersCount', 'sortColumn', 'sortDirection'));
        } catch (\Throwable $th) {
            return response()->json(['error' => __('messages/controller.main.error', ['message' => $th->getMessage()])], 500);
        }
    }

    public function orderUnshipped(Request $request)
    {
        try {
            $sortColumn = request()->query('sort_column', 'tbOrders.EntryDate'); 
            $sortDirection = request()->query('sort_direction', 'ASC');

            $obj = (object) [
                "client_id"         => $request->input('client_id'),
                "date_from"         => $request->input('date_from'),
                "date_to"           => $request->input('date_to'),
                "time_filter"       => $request->input('time_filter'),
                "order_number"      => $request->input('order_number'),
                "sort_column"       => $sortColumn,
                "sort_direction"    => $sortDirection,
                "perPage"           => $request->input('perPage', 10),
            ];

            $totalUnits = $this->totalUnitsService->getUnshippedTotalUnits($obj);
            $result = $this->indicatorService->getUnshippedOrders($obj)->appends(request()->query());
            $client_ids = $result->pluck('BranchID')->unique();

            $ordersCount = $result->total();
            $ordersUnits = $totalUnits;

            return view('indicator.unshipped-orders', compact('result', 'client_ids', 'ordersUnits', 'ordersCount', 'sortColumn', 'sortDirection'));
        } catch (\Throwable $th) {
            return response()->json(['error' => __('messages/controller.main.error', ['message' => $th->getMessage()])], 500);
        }
    }

    public function orderShipped(Request $request)
    {
        try {
            $sortColumn = request()->query('sort_column', 'tbOrders.WorkFlowStatusDate'); 
            $sortDirection = request()->query('sort_direction', 'DESC');

            $obj = (object) [
                "client_id"         => $request->input('client_id'),
                "date_from"         => $request->input('date_from'),
                "date_to"           => $request->input('date_to'),
                "time_filter"       => $request->input('time_filter'),
                "order_number"      => $request->input('order_number'),
                "hours_ago"         => $request->input('hours_ago_cus') ?? $request->input('hours_ago'),
                "sort_column"       => $sortColumn,
                "sort_direction"    => $sortDirection,
                "perPage"           => $request->input('perPage', 10),
            ];

            $totalUnits = $this->totalUnitsService->getShippedTotalUnits($obj);
            $result = $this->indicatorService->getShippedOrders($obj)->appends(request()->query());
            $client_ids = $result->pluck('BranchID')->unique();

            $ordersCount = $result->total();
            $ordersUnits = $totalUnits;

            return view('indicator.shipped-orders', compact('result', 'client_ids', 'ordersUnits', 'ordersCount', 'sortColumn', 'sortDirection'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    } 

    public function timeWorked(Request $request)
    {
        try {
            $sortColumn = request()->query('sort_column', 'clock_in'); 
            $sortDirection = request()->query('sort_direction', 'DESC');

            $obj = (object) [
                "user_id"           => $request->input('user_id'),
                "date_from"         => $request->input('date_from'),
                "date_to"           => $request->input('date_to'),
                "time_filter"       => $request->input('time_filter'),
                "sort_column"       => $sortColumn,
                "sort_direction"    => $sortDirection,
                "perPage"           => $request->input('perPage', 10),
            ];

            $count = $this->indicatorCountService->getCountTimeWorked($obj);
            $result = $this->indicatorService->getTimeWorked($obj);

            $users_ids = $result->pluck('user_id')->unique()->toArray();
            $users_ids = array_values($users_ids);

            $employes = User::whereIn('id', $users_ids)->get();

            if($result){
                return view('indicator.time-worked', compact('result', 'employes', 'sortColumn', 'sortDirection', 'count'));
            }
            
            return redirect()->back()->with('error', __('messages/controller.main.not_found'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    } 

    public function timeToShip(Request $request)
    {
        try {
            $sortColumn = request()->query('sort_column', 'time_to_ship'); 
            $sortDirection = request()->query('sort_direction', 'DESC');

            $obj = (object) [
                "client_id"         => $request->input('client_id'),
                "date_from"         => $request->input('date_from'),
                "date_to"           => $request->input('date_to'),
                "order_number"      => $request->input('order_number'),
                "time_filter"       => $request->input('time_filter'),
                "time_range"        => $request->input('time_range'),
                "hours_ago"         => $request->input('hours_ago'),
                "sort_column"       => $sortColumn,
                "sort_direction"    => $sortDirection,
                "perPage"           => $request->input('perPage', 10),
            ];

            //$totalUnits = $this->totalUnitsService->getShippedTotalUnits($obj);
            //$result = $this->indicatorService->getTimeToShip($obj)->appends(request()->query());
            $totalUnits = $this->totalUnitsService->getTimeToShipUnits($obj);
            $result = $this->indicatorService->getTimeToShip($obj);

            $client_ids = $result->pluck('BranchID')->unique();

            $ordersCount = $result->total();
            $ordersUnits = $totalUnits;

            return view('indicator.time-to-ship', compact('result', 'client_ids', 'ordersUnits', 'ordersCount', 'sortColumn', 'sortDirection'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    } 
    
}
