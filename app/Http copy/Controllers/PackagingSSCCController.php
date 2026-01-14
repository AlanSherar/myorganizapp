<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PackagingOrder;

class PackagingSSCCController extends Controller
{

    public function packagingSSCCList(Request $request)
    {
        $rows = $request->query('per_page', 10);

        $orders = PackagingOrder::orderBy('created_at', 'desc')->paginate($rows)->withQueryString();

        return view('packaging-sscc.list', compact('orders'));
    }

    public function packagingSSCCSearch(Request $request)
    {
        try {
            $request->validate([
                'sscc_number' => 'required|string|size:18',
            ]);
            $sscc_number = $request->query('sscc_number');

            if (isset($sscc_number) && !is_numeric($sscc_number)) {
                return redirect()->back()->with('error', __('messages/controller.sscc.error.required'));
            }

            $orders = PackagingOrder::where('sscc_code', 'like', '%' . $sscc_number . '%')->paginate();

            if ($orders->isEmpty()) {
                return redirect()->back()->with('error', __('messages/controller.sscc.error.not_found'));
            }

            return view('packaging-sscc.list', compact('orders'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            return redirect()->route('packagingSSCCList')->with('error', __('messages/controller.main.unexpected', ['message' =>  $e->getMessage()]));
        }
    }
}
