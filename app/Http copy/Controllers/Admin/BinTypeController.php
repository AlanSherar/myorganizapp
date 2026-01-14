<?php
namespace App\Http\Controllers\Admin;

use App\Models\Bin;
use App\Models\BinType;
use App\Helpers\LogHelper;
use App\Models\LocationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class BinTypeController extends Controller
{
    public function __construct() {}

    public function binsTypesList(Request $request)
    {
        try {
            $rows = $request->per_page ?? 10;

            $query = BinType::query();

            if ($request->filled('search')) {
                $query->where('type_name', 'like', '%' . strtolower($request->search) . '%');
            }

            if ($request->filled('include_inactive')) {
                $query->whereIn('active', [0, 1]);
            }else {
                $query->where('active', 1);
            }

            $binsTypes = $query->orderBy('type_name')->paginate($rows)->withQueryString();

            return view('binsTypes.list', compact('binsTypes'));
        } catch (\Exception $e) {
            return redirect()->route('dashboard')->with('error', $e->getMessage());
        }
    }

    public function binTypeCreate()
    {

        return view('binsTypes.create');
    }

    public function binTypeStore(Request $request)
    {
        try {
            // Validation des champs du formulaire
            $request->validate([
                'type_name'        => 'required|string|max:20',
                'useful_width'     => 'required|numeric|min:0',
                'useful_height'    => 'required|numeric|min:0',
                'useful_length'    => 'required|numeric|min:0',
                'max_weight_lbs'   => 'nullable|numeric|min:0',
                'max_units'        => 'nullable|integer|min:0',
                'storage_price'    => 'nullable|numeric|min:0|regex:/^\d+(\.\d{1,2})?$/',
                'active'           => 'nullable|in:1',
            ]);

            // Création du BinType
            $binType = BinType::create([
                'type_name'       => $request->type_name,
                'useful_width'    => $request->useful_width,
                'useful_height'   => $request->useful_height,
                'useful_length'   => $request->useful_length,
                'max_weight_lbs'  => $request->max_weight_lbs,
                'max_units'       => $request->max_units,
                'storage_price'   => $request->storage_price,
                'active'          => $request->has('active'), // true si coché
            ]);

            // Log (si nécessaire)
            LogHelper::handleLog('create', '', '', 'bin_type', $request->type_name);

            return redirect()->route('binsTypesList')->with('success', $request->type_name . ' ' . __('main.create_success'));
        } catch (\Throwable $th) {
            return redirect()->route('binsTypesList')->with('error', $th->getMessage());
        }

    }

    public function binTypeEdit($id)
    {
        $binType = BinType::findOrFail($id);

        return view('binsTypes.edit', compact('binType'));
    }

    public function binTypeUpdate(Request $request, $id)
    {
        try {
            // Validation des champs
            $request->validate([
                'type_name'         => 'required|string|max:20',
                'useful_width'      => 'required|numeric|min:0',
                'useful_height'     => 'required|numeric|min:0',
                'useful_length'     => 'required|numeric|min:0',
                'max_weight_lbs'    => 'nullable|numeric|min:0',
                'max_units'         => 'nullable|integer|min:0',
                'storage_price'     => ['nullable', 'numeric', 'regex:/^\d+(\.\d{1,2})?$/'],
                'active'            => 'nullable|boolean',
            ]);

            // Récupération du bin type
            $binType = BinType::findOrFail($id);

            // Mise à jour
            $binType->update([
                'type_name'         => $request->type_name,
                'useful_width'      => $request->useful_width,
                'useful_height'     => $request->useful_height,
                'useful_length'     => $request->useful_length,
                'max_weight_lbs'    => $request->max_weight_lbs ?: null,
                'max_units'         => $request->max_units ?: null,
                'storage_price'     => $request->storage_price ?: null,
                'active'            => $request->has('active') ? 1 : 0,
            ]);

            LogHelper::handleLog('update', '', '', 'bin_type', $binType->type_name);

            return redirect()->route('binsTypesList')->with('success', $binType->type_name . ' ' . __('main.update_success'));
        } catch (\Throwable $th) {
            // Gestion d’erreur
            return redirect()->route('binsTypesList')->with('error', 'An error occurred during the update.');
        }
    }

    public function binTypeStatusToggle(Request $request)
    {
        $bin_type_id = $request->id;

        if (!$bin_type_id) {
            return redirect()->route('binsTypesList')->with('error',  __('messages/controller.admin.bin_type.error.id_missing_deletion'));
        }

        $binType = BinType::find($bin_type_id);

        if (!$binType) {
            return redirect()->route('binsTypesList')->with('error', __('messages/controller.admin.bin_type.error.reference_not_found'));
        }

        $newActive = $binType->active ? 0 : 1;
        $binType->active = $newActive;

        if ($newActive === 0) {
            $usedInBins = Bin::where('type_id', $bin_type_id)->exists();

            if ($usedInBins) {
                return redirect()->route('binsTypesList')
                    ->with('error', __('binsTypes/main.deactivation_in_use'));
            }
        }

        if ($binType->save()) {
            $action = $newActive ? 'activate' : 'deactivate';
            $messageKey = $newActive
                ? $binType->type_name . ' ' . __('main.messages.activate_success')
                : $binType->type_name . ' ' . __('main.messages.deactivate_success');
            $type = $newActive ? 'success' : 'warning';

            LogHelper::handleLog($action, '', '', 'bin_type', $binType->type_name);

            return redirect()
                ->route('binsTypesList', ['page' => $request->page ?? 1])
                ->with($type, __($messageKey));
        }

        return redirect()->route('binsTypesList')->with('error', __('messages/controller.general.unexpected', ['message' => $binType->name]));
    }

}
