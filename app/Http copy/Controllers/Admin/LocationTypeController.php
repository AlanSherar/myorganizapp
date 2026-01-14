<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\LogHelper;
use App\Models\LocationType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\LocationTypeService;

class LocationTypeController extends Controller
{
    public function __construct() {}

    public function locationTypesList(Request $request)
    {
        $rows = request()->query('per_page', 10);

        $query = LocationTypeService::listQuery($request);

        // Ejecutamos la consulta con paginación
        $locationTypes = $query->paginate($rows);

        return view('locationTypes.list', compact('locationTypes', 'rows'));
    }

    public function locationTypeCreate()
    {

        return view('locationTypes.create');
    }

    public function locationTypeStore(Request $request)
    {

        // Validación de los datos de la solicitud
        $request->validate([
            'uid'         => 'required|string|max:3',
            'name'            => 'required|string|max:20',
            'max_sku'          => 'nullable|int',
            'cost'             => ['nullable', 'numeric', 'regex:/^\d+(\.\d{1,3})?$/'],
        ]);

        // Verificamos si ya existe un tipo de ubicación con el mismo uid
        $existingLocationType = LocationType::where('uid', $request->uid)->first();
        if ($existingLocationType) {
            return redirect()->route('locationTypesList')->with('error', __('messages/controller.admin.location_type.error.uid_already_exists'));
        }

        // Creación del tipo de ubicación en la base de datos
        $location_type = LocationType::create([
            'uid'              =>  $request->uid,
            'name'             =>  $request->name,
            'max_sku'          =>  $request->max_sku,
            'cost'             =>  $request->cost,
        ]);

        LogHelper::handleLog('create', '', '', 'location_type', $request->name);

        return redirect()->route('locationTypesList')->with('success', __('messages/controller.admin.location_type.create.success'));
    }

    public function locationTypeEdit($id)
    {
        $locationType = LocationType::findOrFail($id);

        return view('locationTypes.edit', compact('locationType'));
    }

    public function locationTypeUpdate(Request $request, $id)
    {
        // Validamos comunes a todos los casos
        $request->validate([
            'max_sku'          => 'nullable|int',
            'cost'             => ['nullable', 'numeric', 'regex:/^\d+(\.\d{1,3})?$/'],
        ]);

        // Buscamos el tipo de ubicación por su ID o lanzamos un error si no se encuentra
        $locationType = LocationType::findOrFail($id);
        
        if (!$locationType) {
            return redirect()->route('locationTypesList')->with('error', __('messages/controller.admin.location_type.error.reference_not_found'));
        }
        
        // Verificamos si ya existe un tipo de ubicación con el mismo uid (excluyendo el actual)
        $existingLocationType = LocationType::where('uid', $request->uid)->where('id', '!=', $id)->first();

        // Si existe un tipo de ubicación con el mismo uid y no es el mismo ID, lanzamos un error
        if ($existingLocationType) {
            return redirect()->route('locationTypesList')->with('error', __('messages/controller.admin.location_type.error.uid_already_exists'));
        }

        
        // Verificamos si el tipo de ubicación es predefinido
        if ($locationType->predefined) {

            // Si es predefinido, solo se pueden actualizar los campos 'max_sku' y 'cost'
            $location_type = $locationType->update([
                'max_sku'          =>  $request->max_sku <= 0 ? null : $request->max_sku,
                'cost'             =>  $request->cost <= 0 ? null : $request->cost,
            ]);

            LogHelper::handleLog('update', '', '', 'location_type', $locationType->name);
        } else {
            // Si no es predefinido, se pueden actualizar otros campos

            // Validamos los campos específicos para no predefinidos
            $request->validate([
                'uid'              => 'required|string|max:3',
                'name'             => 'required|string|max:20',
                'active'           => 'required|int|max:1',
            ]);

            // Actualizamos los campos
            $locationType->update([
                'uid'              =>  $request->uid,
                'name'             =>  $request->name,
                'max_sku'          =>  $request->max_sku <= 0 ? null : $request->max_sku,
                'cost'             =>  $request->cost <= 0 ? null : $request->cost,
                'active'           =>  $request->active,
            ]);

            LogHelper::handleLog('update', '', '', 'location_type', $request->name);
        }

        return redirect()->route('locationTypesList')->with('success', __('messages/controller.admin.location_type.update.success'));
    }

    public function locationTypeStatusToggle(Request $request)
    {
        $location_type_id = $request->location_type_id;

        if (!$location_type_id) {
            return redirect()->route('locationTypesList')->with('error',  __('messages/controller.admin.location_type.error.id_missing_deletion'));
        }

        $locationType = LocationType::find($location_type_id);

        if (!$locationType) {
            return redirect()->route('locationTypesList')->with('error', __('messages/controller.admin.location_type.error.reference_not_found'));
        }
        if ($locationType->predefined) {
            return redirect()->route('locationTypesList')->with('error', __('messages/controller.admin.location_type.error.predefined'));
        }

        $newActive = $locationType->active == 1 ? 0 : 1;
        if ($location_type = $locationType->update(['active' => $newActive])) {
            if ($newActive == 1) {
                LogHelper::handleLog('activate', '', '', 'location_type', $locationType->name);
                return redirect()->route('locationTypesList')->with('success', __('messages/controller.admin.location_type.status.success_activation'));
            }
            LogHelper::handleLog('deactivate', '', '', 'location_type', $locationType->name);
            return redirect()->route('locationTypesList')->with('success', __('messages/controller.admin.location_type.status.success_deactivation'));
        }

        return redirect()->route('locationTypesList')->with('error', __('messages/controller.general.unexpected', ['message' => $locationType->name]));
    }

    public function getlocationTypes()
    {
        return LocationType::All();
    }
}
