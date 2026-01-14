<?php

namespace App\Http\Controllers\Admin;

use App\Models\Bin;
use App\Models\Site;
use App\Models\BinType;
use App\Helpers\LogHelper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Picqer\Barcode\BarcodeGeneratorPNG;

class BinController extends Controller
{
    public function __construct() {}

    public function binsList(Request $request)
    {
        try {
            $sites = Site::where('active', 1)->get();
            $binTypes = BinType::where('active', 1)->get();
            $rows = $request->per_page ?? 10;
            $query = Bin::with(['location', 'binType', 'site']);

            if ($request->filled('search')) {
                $query->where('barcode', 'like', '%' . strtolower($request->search) . '%');
            }

            if ($request->filled('include_inactive')) {
                $query->whereIn('status', ["Stored", "Retired", "Destroyed", "Active"]);
            }else {
                $query->where('status', "Active");
            }

            if ($request->filled('min_uid')) {
                $query->whereRaw('CAST(uid AS INT) >= ?', [(int)$request->min_uid]);
            }

            if ($request->filled('max_uid')) {
                $query->whereRaw('CAST(uid AS INT) <= ?', [(int)$request->max_uid]);
            }

            $bins = $query->paginate($rows)->withQueryString();

            return view('bins.list', compact('bins', 'sites', 'binTypes'));
        } catch (\Throwable $th) {
            return redirect()->route('binsList')->with('error', $th->getMessage());
        }
    }

    public function binCreate()
    {
        try {
            $binTypes = BinType::where('active', 1)->get();
            $sites = Site::all();
            $warehouses = Warehouse::all();

            // 🔢 Récupérer le dernier UID
            $lastUID = Bin::orderBy('UID', 'desc')->value('UID');

            // ➕ Générer le nouveau UID
            if ($lastUID) {
                $newUID = str_pad((int)$lastUID + 1, 5, '0', STR_PAD_LEFT);
            } else {
                $newUID = '00001';
            }

            return view('bins.create', compact('binTypes', 'sites', 'newUID', 'warehouses'));
        } catch (\Exception $e) {
            return redirect()->route('binsList')->with('error', $e->getMessage());
        }
    }

    public function binStore(Request $request)
    {
        $request->validate([
            'BinType'    => 'required',
            'BinColor'   => 'required|string|max:20',
            'BinStatus'  => 'required|string|max:20',
            'site_id'    => 'required|integer|exists:sites,id',
            'quantity'   => 'nullable|integer|min:1|max:1000',
            'prefix'     => 'required|digits:2',
            // 'warehouse_id' => 'required|integer|exists:warehouses,id',
            'location_id'  => 'required|integer|exists:locations,id',
        ]);

        try {
            $createMultiple = $request->has('create_multiple');
            $quantity = $createMultiple ? max(1, (int)$request->input('quantity')) : 1;
            $prefix = $request->input('prefix');
            $lastUID = Bin::orderBy('UID', 'desc')->value('UID');
            $uidNumber = $lastUID ? (int)$lastUID : 0;
            $createdBarcodes = [];

            $generator = new BarcodeGeneratorPNG(); // Ou BarcodeGeneratorHTML ou SVG

            for ($i = 0; $i < $quantity; $i++) {
                $uidNumber++;
                $uid = str_pad($uidNumber, 6, '0', STR_PAD_LEFT);

                // Code-barres de type Code128 : ex. "12-000123"
                $barcodeData = "(" . $prefix . ")" . $uid;

                //$barcodeImage = base64_encode($generator->getBarcode($barcodeData, $generator::TYPE_CODE_128));

                $bin = Bin::create([
                    'UID'      => $uid,
                    'barcode'  => $barcodeData,
                    'color'    => $request->BinColor,
                    'status'   => $request->BinStatus,
                    'type_id'  => $request->BinType,
                    'site_id'  => $request->site_id,
                    // 'warehouse_id' => $request->warehouse_id,
                    'location_id' => $request->location_id,
                ]);

                LogHelper::handleLog('create', '', '', 'bin', $bin->UID);
                $createdBarcodes[] = $barcodeData;
            }

            $message = $createMultiple
                ? count($createdBarcodes) . ' Bins created: ' . implode(', ', $createdBarcodes)
                : $createdBarcodes[0] . ' ' . __('main.create_success');

            return redirect()->route('binsList')->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->route('binsList')->with('error', $e->getMessage());
        }
    }

    
    public function binEdit($id)
    {
        try {
            $bin = Bin::where('UID', $id)->first();
            $binTypes = BinType::where('active', 1)->get();
            $sites = Site::all();
            $site = $bin->site()->first();
            $location = $bin->location()->first();

            $locations = $site->locations()->get();

            return view('bins.edit', compact('bin', 'binTypes', 'sites', 'locations', 'site', 'location'));
        } catch (\Exception $e) {
            return redirect()->route('binsList')->with('error', $e->getMessage());
        }
    }

    public function binDetails($id)
    {
        try {
            $bin = Bin::where('UID', $id)->with(['site', 'location', 'binType'])->first();
            if (!$bin) {
                throw new \Exception(__('main.not_found'));
            }

            return view('bins.details', compact('bin'));
        } catch (\Exception $e) {
            return redirect()->route('binsList')->with('error', $e->getMessage());
        }
    }

    public function binUpdate(Request $request, $id)
    {

        try {
            $request->validate([
                'UID'               => 'required|string|max:20|unique:bins,UID,' . $id,
                'BinBarcode'        => 'required|string|max:20|unique:bins,barcode,' . $id,
                'BinType'           => 'required',
                'BinColor'          => 'required|string|max:20',
                'BinStatus'         => 'required|string|max:20',
                'site_id'           => 'required|integer|exists:sites,id',
                // 'warehouse_id'      => 'required|integer|exists:warehouses,id',
                'location_id'       => 'required|integer|exists:locations,id',
            ]);

            $bin = Bin::findOrFail($id);

            $bin->update([
                'barcode'           =>  $request->BinBarcode,
                'color'             =>  $request->BinColor,
                'status'            =>  $request->BinStatus,
                'type_id'           =>  $request->BinType,
                'site_id'           =>  $request->site_id,
                // 'warehouse_id'      =>  $request->warehouse_id,
                'location_id'       =>  $request->location_id,
            ]);
            LogHelper::handleLog('update', '', '', 'bin', $bin->UID);

            return redirect()->route('binsList')->with('success', $request->BinBarcode . ' ' . __('main.update_success'));
        } catch (\Throwable $th) {
            return redirect()->route('binsList')->with('error', $th->getMessage());
        }
    }

    public function binStatusToggle(Request $request)
    {
        try {
            $bin_id = $request->bin_id;

            if (!$bin_id) {
                return redirect()->route('binsList')->with('error',  __('messages/controller.admin.bin.error.id_missing_deletion'));
            }

            $bin = Bin::where('binUID', $bin_id)->first();

            if (!$bin) {
                return redirect()->route('binsList')->with('error', __('messages/controller.admin.bin.error.reference_not_found'));
            }

            $newActive = $bin->active ? 0 : 1;
            $bin->active = $newActive;

            if ($bin->save()) {
                $action = $newActive ? 'activate' : 'deactivate';
                $messageKey = $newActive
                    ? 'main.messages.activate_success'
                    : 'main.messages.deactivate_success';

                LogHelper::handleLog($action, '', '', 'bin', $bin->label);

                return redirect()
                    ->route('binsList', ['page' => $request->page ?? 1])
                    ->with('success', __($messageKey));
            }

            return redirect()->route('binsList')->with('error', __('messages/controller.main.unexpected', ['message' => $bin->label]));
        } catch (\Throwable $th) {
            return redirect()->route('binsList')->with('error', $th->getMessage());
        }
        
    }

    public function binsPrint($ids, Request $request)
    {
        try {
            $format = $request->input('format', 'sheet');
            $idArray = explode(',', $ids);

            $bins = Bin::whereIn('UID', $idArray)->get();

            return view('bins.print', compact('bins', 'format'));
        } catch (\Exception $e) {
            return redirect()->route('binsList')->with('error', $e->getMessage());
        }
    }

    public function binsSetStatus(Request $request)
    {
        try {
            $request->validate([
                'bin_ids' => 'required|array',
                'status' => 'required|string|in:Active,Stored,Retired,Destroyed'
            ]);

            Bin::whereIn('UID', $request->bin_ids)->update(['status' => $request->status]);

            return response()->json(['success' => true]);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()]);
        }

    }

}
