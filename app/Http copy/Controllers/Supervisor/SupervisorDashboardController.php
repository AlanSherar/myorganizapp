<?php 
namespace App\Http\Controllers\Supervisor;

use App\Models\Site;
use Illuminate\Http\Request;
use App\Services\Dashboard\IndicatorCountService;
use App\Http\Controllers\Controller;
use App\Services\Dashboard\FiveDaysAgoService;
use Illuminate\Support\Facades\Auth;

class SupervisorDashboardController extends Controller
{
    private $indicatorCountService;
    private $fiveDaysAgoService;

    public function __construct(IndicatorCountService $indicatorCountService, FiveDaysAgoService $fiveDaysAgoService)
    {
        $this->indicatorCountService = $indicatorCountService;
        $this->fiveDaysAgoService = $fiveDaysAgoService;
    }

    public function dashboardIndicator(Request $request)
    {
        if(app()->environment('local')){
            return redirect()->route('getAcctivateOrders');
        }
        $site_id = $request->site_id ?? 1;

        if((int) Auth::user()->role_id != 1 && (int) Auth::user()->role_id != 4){
            return redirect()->route('getAcctivateOrders');
        }
        
        if ((int) Auth::user()->role_id == 4) {
            $site_id = (int) Auth::user()->site_id;
        }
        
        $site_selected = Site::find($site_id);
        $sites = Site::all();
        
        return view('indicator.index', compact('sites', 'site_selected'));
    }

    public function readyToPickCount()
    {
        try {
            $result = $this->indicatorCountService->getReadyToPickCountOrders();

            return [
                "count"                         => $result->total_orders,
                "units"                         => $result->total_units,
            ];
        } catch (\Throwable $th) {
            return redirect()->back()->with('error',  __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function ordersUnshippedCount()
    {
        try {
            $unshipped_label = [];
            $unshipped_orders_values = [];
            $unshipped_units_values = [];
            
            $result = $this->indicatorCountService->getUnshippedCountOrders();
            $result_five_days_ago = $this->fiveDaysAgoService->getUnshippedOrdersFiveDaysAgo();

            foreach ($result_five_days_ago as $key => $value) {
                $unshipped_label[] = $value->OrderDate;
                $unshipped_orders_values[] = $value->total_orders;
                $unshipped_units_values[] = $value->total_units;
            }

            return [
                "count"                         => $result->total_orders,
                "units"                         => $result->total_units,
                "indicator_label"               => $unshipped_label,
                "indicator_orders_values"       => $unshipped_units_values,
                "indicator_units_values"        => $unshipped_units_values
            ];
        } catch (\Throwable $th) {
            return redirect()->back()->with('error',  __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function ordersShippedCount($filterTime = 'week_to_date')
    {
        try {
            $shipped_label = [];
            $shipped_orders_values = [];
            $shipped_units_values = [];
            
            $result = $this->indicatorCountService->getShippedCountOrders($filterTime);
            $result_five_days_ago = $this->fiveDaysAgoService->getShippedOrdersFiveDaysAgo();

            foreach ($result_five_days_ago as $key => $value) {
                $shipped_label[] = $value->WorkFlowStatusDate;
                $shipped_orders_values[] = $value->total_orders;
                $shipped_units_values[] = $value->total_units;
            }

            return [
                "count"                         => $result->total_orders,
                "units"                         => $result->total_units,
                "indicator_label"               => $shipped_label,
                "indicator_orders_values"       => $shipped_orders_values,
                "indicator_units_values"        => $shipped_units_values
            ];
        } catch (\Throwable $th) {
            return redirect()->back()->with('error',  __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function timeWorkedCount($filterTime = 'week_to_date')
    {
        try {
            $label = [];
            $net_time = [];
            
            $result = $this->indicatorCountService->getTimeWorkedCount($filterTime);

            $result_five_days_ago = $this->fiveDaysAgoService->getTimeWorkedFiveDaysAgo();
            foreach ($result_five_days_ago as $key => $value) {
                $label[] = $value["date"];
                $net_time[] = $this->convertHHMMStringToFloat($value["net_time"]);
            }

            return [
                "count"                         => $result["net_worked_time"],
                "indicator_label"               => $label,
                "indicator_values"              => $net_time
            ];
        } catch (\Throwable $th) {
            return redirect()->back()->with('error',  __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    private function convertHHMMStringToFloat($hhmm)
    {
        // S'assurer que la string fait 4 caractères (ex: "640" devient "0640")
        $hhmm = str_pad($hhmm, 4, '0', STR_PAD_LEFT);

        $hours = intval(substr($hhmm, 0, 2));
        $minutes = intval(substr($hhmm, 2, 2));

        // Créer le float avec 2 décimales
        return floatval($hours . '.' . str_pad($minutes, 2, '0', STR_PAD_LEFT));
    }

    public function ordersOnHoldCount()
    {
        try {
            $result = $this->indicatorCountService->getOnHoldCountOrders();

            return [
                "count"                         => $result->total_orders,
                "units"                         => $result->total_units
            ];
        } catch (\Throwable $th) {
            return redirect()->back()->with('error',  __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function timeToShipCount($filterTime = 'week_to_date', $freight)
    {
        try {
            $result = $this->indicatorCountService->getCountTimeToShip($filterTime, $freight);

            $total = 0;
            $data = [];
            $label = ["0 to 24 hrs", "24 to 48 hrs", "48 to 72 hrs", "72 to 96 hrs", "96 + hrs"];
            $ranges = [];

            foreach ($result as $key => $value) {
                $data[$key] = (int) $value;
                $total = $total + (int) $value;
            }

            foreach ($data as $key => $value) {
                $ranges[] = round(($value / $total) * 100, 2);
            }


            return [
                "count_0_24"                    => $ranges[0],
                "count_24_48"                   => $ranges[1],
                "indicator_label"               => $label,
                "indicator_values"              => $ranges
            ];
        } catch (\Throwable $th) {
            return redirect()->back()->with('error',  __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

}
