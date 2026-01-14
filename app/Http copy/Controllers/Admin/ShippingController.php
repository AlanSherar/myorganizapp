<?php
namespace App\Http\Controllers\Admin;

use App\Models\Site;
use Illuminate\Http\Request;
use App\Models\ShippingQuote;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\ShippingService;
use App\Models\PackagingReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\CalculateShippingCostJob;
use App\Http\Controllers\Controller;

class ShippingController extends Controller
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

    public function shippingQuote(Request $request)
    {
        try {
            $rows = request()->query('per_page', 10);
            $sites = Site::where('active', 1)->get();
            $packagings = PackagingReference::where('is_active', 1)->get();
            $weights = config('shipping.weights');

            $shipping_quotes = ShippingQuote::select(
                'group_id',
                DB::raw('MIN(site_id) as site_id'),
                DB::raw('MIN(company_name) as company_name'),
                DB::raw('MIN(email) as email'),
                DB::raw('MIN(packaging_id) as packaging_id'),
                DB::raw('MIN(weight) as weight'),
                DB::raw('MIN(markup_rate) as markup_rate'),
                DB::raw('MAX(completed_at) as completed_at'),
                DB::raw('COUNT(*) as quotes_count')
            )
            ->groupBy('group_id')
            ->orderByDesc('completed_at', 'desc')
            ->paginate($rows);

            // If the request has a 'site' parameter, filter the sites accordingly
            if ($request->has('site_id') && $request->has('packaging_id') && ($request->has('weight') || $request->has('_weight'))) {
                $shippingData = $this->shippingService->setShippingData($request);

                $shippingResult = $this->shippingService->setShippingCostData($shippingData);

                dispatch(new CalculateShippingCostJob($shippingResult));

                return redirect()->route('shippingQuote')->with('warning', 'Estimate rate calculation in process...');
            }

            return view('shipping-quotes.index', compact('sites', 'packagings', 'weights', 'shipping_quotes'));
        } catch (\Throwable $th) {
            $oldInput = $request->all();
            session()->flashInput($oldInput);
            Log::error('Error in shippingQuote: ' . $th->getMessage());
            return redirect()->back()->withErrors(['error' => $th->getMessage()]);   
        }
    }

    public function shippingQuoteDetails($group_id)
    {
        try {
            $shipping_quote = ShippingQuote::where('group_id', $group_id)->get();

            $completedAt = $shipping_quote[0]->completed_at;
            $date = \Carbon\Carbon::parse($completedAt)->format('d/m/Y');

            return view('shipping-quotes.details', compact('shipping_quote', 'date'));
        } catch (\Throwable $th) {
            return redirect()->back()->withErrors(['error' => $th->getMessage()]);  
        }
    }

    public function shippingQuoteExport($group_id)
    {
        try {
            $shipping_quote = ShippingQuote::where('group_id', $group_id)->get();

            if ($shipping_quote->isEmpty()) {
                return redirect()->back()->with('error', 'No shipping quotes found for this group.');
            }

            $completedAt = $shipping_quote[0]->completed_at;
            $date = \Carbon\Carbon::parse($completedAt)->format('d/m/Y');

            $pdf = Pdf::loadView('shipping-quotes.pdf', compact('shipping_quote', 'date'));

            return $pdf->download('shipping_quotes_' . 
                $shipping_quote[0]->packaging->sku . 
                '_' . number_format($shipping_quote[0]->weight, 0) .'oz.pdf');
        } catch (\Throwable $th) {
            Log::error('error exporting PDF : ' . $th->getMessage());
            return redirect()->back()->with('error', 'Error exporting PDF.');
        }
    }

    public function shippingQuoteDelete($group_id)
    {
        try {
            ShippingQuote::where('group_id', $group_id)->delete();

            return redirect()->back()->with('success', 'The Shipping Quote ' . __('main.delete_success'));
        } catch (\Throwable $th) {
           return redirect()->back()->withErrors(['error' => $th->getMessage()]);  
        }
    }

}