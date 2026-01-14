<?php
namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Acctivate;
use App\Helpers\LogHelper;
use Illuminate\Http\Request;
use App\Models\PackagingOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use App\Services\PassportAPIService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Services\PackagingOrderService;
use App\Services\PackagingReportService;
use App\Support\Packaging\PackagingSupport;

class PassportController extends Controller
{
    protected $passportAPIService;
    protected $acctivateModel;
    protected $packagingOrderService;

    public function __construct(
        PassportAPIService $passportAPIService,  
        Acctivate $acctivateModel, 
        PackagingOrderService $packagingOrderService) {
            $this->passportAPIService = $passportAPIService;
            $this->acctivateModel = $acctivateModel;
            $this->packagingOrderService = $packagingOrderService;
    }

    public function passportCreateShip($order_number)
    {
        try {
            $packaging_orders = PackagingOrder::with('packedByUser')->where('order_number', $order_number)->get();
            $instructions = $this->acctivateModel->getInstructionsByOrder($order_number);
            $instructions = $instructions && !empty($instructions->ShippingInstructions)
                ? $instructions->ShippingInstructions
                : null;

            $site = Site::where('id', Auth::user()->id)->first();
            $order_data = Acctivate::getOrderDetails($order_number);
            $order_data = $order_data[0];

            return view('passport.create', compact('packaging_orders', 'instructions', 'site', 'order_data'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function passportGetRates(Request $request, $order_number)
    {
        try {
            $weight = $request->weight;
            $this->packagingOrderService->handleUpdateWeight($weight);
            $payload = $this->passportAPIService->setPayload($order_number);
            $payload["duty_paid"] =  true;

            $url = "https://api-stg.passportshipping.com/v3/rate";

            return $this->passportAPIService->passportApiRequest($url, $payload);
        } catch (\Throwable $th) {
            Log::info('rates passport: ' . $th->getMessage());
        }
        
    }

    public function passportPurchaseLabel(Request $request, $order_number)
    {
        $service_name = $request->service;
        $payload = $this->passportAPIService->setPayload($order_number);
        $payload["label_image_format"] =  "png";
        $payload["service_name"] = $service_name;
        $url = "https://api-stg.passportshipping.com/v3/ship";

        $response = $this->passportAPIService->passportApiRequest($url, $payload);

        if($response["success"]){
            $data = $response["data"];
            $cost = (double) $data['rate'] + (double) $data['tax'] ?? 0 + (double) $data['duty'] ?? 0 + (double) $data['insurance'] ?? 0;
            PackagingOrder::where('order_number', $order_number)->update([
                'shipped_by_user' => Auth::id(), 
                'status_id'       => 3,
                'updated_at'      => user_local_time(),
                'shipment_id'     => $data['code'] ?? null,
                'ship_date'       => user_local_time(),
                'shipment_cost'   => $cost ?? null,
                'label_download'  => $data['label'] ?? null,
                'service_code'    => $service_name,
                'tracking_number' => $data['code'] ?? null,
                'tracking_url'    => $data['tracking_url'],
                'label_id'        => $data["code"],
                'label_void'      => 0,
                'tax'             => $data['tax'] ?? 0,
                'duty'            => $data['duty'] ?? 0,
                'insurance'       => $data['insurance'] ?? 0
            ]);           

            LogHelper::handleLog('ship', $order_number);
        }

        return $response;
    }

    public function passportGetLabel($order_number)
    {
        $packaging_order = PackagingOrder::where('order_number', $order_number)->get();

        return view('passport.resume-ship', compact('packaging_order'));
    }

    public function passportVoidLabel(Request $request)
    {
        try {
            $code = $request->code;
            $order_number = $request->order_number;
            $url = "https://api-stg.passportshipping.com/v3/void/{$code}";

            $response = $this->passportAPIService->passportApiRequest($url);

            if(!$response["success"]){
                return redirect()->back()->with('error', $response["error"]["message"]);
            }

            PackagingOrder::where('shipment_id', $code)->update([
                'label_void' => 1,
                'status_id'  => 1
            ]);

            LogHelper::handleLog('label_void', $order_number);

            return redirect()->back()->with('success', 'Label voided');
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
        
    }

    public function passportPrintLabel(Request $request)
    {
        $url = $request->input('url');
        if (!$url) {
            return response('Missing URL', 400);
        }

        try {
            // Télécharge l'image du label
            $imageData = @file_get_contents($url);
            if ($imageData === false) {
                return response('Failed to download image', 500);
            }

            // Encode l’image en base64
            $base64 = base64_encode($imageData);

            // HTML minimal sans marges
            $html = '<!DOCTYPE html>
                <html>
                <head>
                    <meta charset="utf-8">
                    <style>
                        @page { margin: 0; }
                        img {
                            display: block;
                            width: 100%;
                            height: 99%;
                            margin: 0;
                            padding: 0;
                            page-break-before: avoid;
                            page-break-after: avoid;
                        }
                    </style>
                </head>
                <body>
                    <img src="data:image/png;base64,'.$base64.'" />
                </body>
                </html>';

            $widthPt  = 102 * 2.83465;  // ≈ 289.14 pt
            $heightPt = 152 * 2.83465;  // ≈ 430.86 pt

            while (ob_get_level()) {
                ob_end_clean();
            }

            $pdf = Pdf::setOptions(['isRemoteEnabled' => true])
                ->loadHTML($html)
                ->setPaper([0, 0, $widthPt, $heightPt]); // 102x152 mm exact

            return $pdf->stream('label.pdf');
        } catch (\Exception $e) {
            return response('Error generating PDF: ' . $e->getMessage(), 500);
        }
    }

    public function passportReportOrders(Request $request)
    {
        try {
            $obj = (object) [
                "branch"        => $request->input('branch'),
                "date_from"     => $request->input('date_from'),
                "date_to"       => $request->input('date_to'),
            ];

            $query = PackagingSupport::queryPassportOrders($obj);

            $result = (clone $query)->orderByDesc('created_at')->paginate(10)->appends(request()->query());
            $branchs = (clone $query)->select('branch')->distinct()->pluck('branch');

            return view('passport.report', compact('result', 'branchs'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function passportReportOrdersCSV(Request $request)
    {
        try {
            $date_from = $request->input('date_from') ?? null;
            $date_to = $request->input('date_to') ?? null;
            
            $obj = (object) [
                "branch"            => $request->input('branch'),
                "date_from"         => $request->input('date_from'),
                "date_to"           => $request->input('date_to')
            ];

            $query = PackagingSupport::queryPassportOrders($obj);
            $data = $query->get();

            return PackagingReportService::passportReportOrders($data, $date_from, $date_to);
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
            
    }

}
