<?php
namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\Site;
use App\Models\priceCard;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\ShippingService;
use App\Models\PackagingReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Jobs\CalculatePriceCard;

class PriceCardController extends Controller
{
    protected $shippingService;
    /**
     * Create a new controller instance.
     *
     * @param ShippingService $shippingService
     */
    
    public function __construct(ShippingService $shippingService)
    {
        $this->shippingService = $shippingService;
    }

    /**
     * Display the shipping index page with available sites, packagings, weights, and order ranges.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */

    public function priceCard(Request $request)
    {
        try {
            $weights = config('shipping.weights');
            $ranges = config('shipping.unit_ranges');

            $sites = Site::where('active', 1)->get();
            $packagings = PackagingReference::where('is_active', 1)->get();
            $rows = request()->query('per_page', 10);
            $prices_card_list = $this->shippingService->getShippingsData($rows);

            if ($request->has('site_id') && $request->has('packaging_id') && $request->has('ranges')) {
                $shippingData = $this->shippingService->setShippingData($request);
                $shippingResult = $this->shippingService->setPriceCardData($shippingData);

                //$this->testProcess($shippingResult);
                dispatch(new CalculatePriceCard($shippingResult));

                return redirect()->route('priceCard')->with('warning', 'Estimate rate calculation in process...');
            }

            return view('price-card.index', compact('sites', 'packagings', 'weights', 'ranges', 'prices_card_list'));
        } catch (\Throwable $th) {
            $oldInput = $request->all();
            session()->flashInput($oldInput);
            Log::error('Error in priceCard: ' . $th->getMessage());
            return redirect()->back()->withErrors(['error' => $th->getMessage()]);   
        }
    }

    private function testProcess($shippingResult)
    {
        try {
            $services = [];
            $value_selected = null;

            foreach ($shippingResult as $zip_code => $weights) {
                foreach ($weights as $weight => $carrier_codes) {
                    foreach ($carrier_codes as $carrier_code => $service_codes) {
                        foreach ($service_codes as $service_code => $value) {
                            if (str_contains(strtolower($service_code), 'ground') || $service_code == 'amazon_shipping_standard') {
                                sleep(1);
                                $_shipping_costs = $this->shippingService->getRatesByZipCode($value, $zip_code, $carrier_code, $weight, $service_code);
                                
                                if(isset($_shipping_costs["Message"]) && $_shipping_costs["Message"] == "An error has occurred."){
                                        continue;
                                }
                                
                                $service_selected = $this->shippingService->getServiceSelected($_shipping_costs, $service_code);
                                $service_selected["carrier_code"] = $carrier_code;
                                $service_selected["carrier_id"] = $value["carrier_id"];
                                $services[] = $service_selected;
                                $value_selected = $value;
                            }
                        }
                    }

                    if(count($services) > 0){
                        $service_priceless_selected = $this->shippingService->getServicePriceLess($services);
                        $service_pricehigh_selected = $this->shippingService->getServicePriceHigh($services);
                        
                        if(!is_null($service_priceless_selected) && !is_null($service_pricehigh_selected)){
                            $value_selected["weight"] = $weight;
                            
                            $value_selected["carrier_code_priceless"] = $service_priceless_selected["carrier_code"];
                            $value_selected["serviceName_priceless"] = $service_priceless_selected["serviceName"];
                            $value_selected["serviceCode_priceless"] = $service_priceless_selected["serviceCode"];
                            $value_selected["shipmentCost_priceless"] = $service_priceless_selected["shipmentCost"];
                            $value_selected["carrier_id_priceless"] = $service_priceless_selected["carrier_id"];
                            
                            $value_selected["carrier_code_pricehigh"] = $service_pricehigh_selected["carrier_code"];
                            $value_selected["serviceName_pricehigh"] = $service_pricehigh_selected["serviceName"];
                            $value_selected["serviceCode_pricehigh"] = $service_pricehigh_selected["serviceCode"];
                            $value_selected["shipmentCost_pricehigh"] = $service_pricehigh_selected["shipmentCost"];
                            $value_selected["carrier_id_pricehigh"] = $service_pricehigh_selected["carrier_id"];
                            
                            $insertData = $this->shippingService->setInsertData($value_selected);
                            $this->shippingService->insertResult($insertData);
                            unset($services);
                            unset($value_selected);
                        }
                    }
                }
            }
        } catch (\Throwable $th) {
            Log::error('Error in priceCard: ' . $th->getMessage());
            return redirect()->back()->withErrors(['error' => $th->getMessage()]);   
        }
    }

    public function priceCardDetails($group_id)
    {
        $price_card_details = PriceCard::where('group_id', $group_id)->orderBy('weight')->get();

        $completedAt = $price_card_details[0]->completed_at;
        $date = \Carbon\Carbon::parse($completedAt)->format('d/m/Y');

        return view('price-card.details', compact('price_card_details', 'date'));
    }

    public function priceCardExport($group_id)
    {
        try {
            $price_card_details = PriceCard::where('group_id', $group_id)->get();

            $completedAt = $price_card_details[0]->completed_at;
            $date = \Carbon\Carbon::parse($completedAt)->format('d/m/Y');

            $pdf = Pdf::loadView('price-card.pdf', compact('price_card_details', 'date'));

            return $pdf->download('price_list_' . $price_card_details[0]->packaging->sku . '.pdf');
        } catch (\Throwable $th) {
            Log::error('error exporting PDF : ' . $th->getMessage());
            return redirect()->back()->with('error', 'Error exporting PDF.');
        }
    }

    public function priceCardDelete($group_id)
    {
        try {
            PriceCard::where('group_id', $group_id)->delete();

            return redirect()->back()->with('success', 'The Price Card ' . __('main.delete_success'));
        } catch (\Throwable $th) {
           return redirect()->back()->withErrors(['error' => $th->getMessage()]);  
        }
    }
}
