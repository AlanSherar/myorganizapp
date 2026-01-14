<?php
namespace App\Http\Controllers\Fulfillment;

use App\Http\Controllers\Controller;
use App\Services\ShippingCreditService;

class PackagingOrderFulfillmentController extends Controller
{
    private $shippingCreditService;

    public function __construct(ShippingCreditService $shippingtCredit)
    {
        $this->shippingCreditService = $shippingtCredit;
    }
    
    public function shippingCredit()
    {
        try {
            $this->shippingCreditService->handleShippingCreditUnchecked();

            
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

}