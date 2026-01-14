<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\LogHelper;
use App\Models\ProductType;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\ProductTypeService;

class ProductTypeController extends Controller
{
    private $service;
    public function __construct()
    {
        $this->service = new ProductTypeService();
    }

    public function list(Request $request)
    {
        $rows = request()->query('per_page', 10);

        $query = $this->service::listQuery($request);

        // Ejecutamos la consulta con paginación
        $productTypes = $query->paginate($rows);

        return view('productTypes.list', compact('productTypes', 'rows'));
    }

    public function create()
    {
        return view('productTypes.create');
    }

    public function store(Request $request)
    {
        // Validación de los datos de la solicitud
        $request->validate([
            'name'                  => 'required|string|max:80',
            'code'                  => 'required|string|max:6',
            'tangible'              => 'nullable|int|max:1',
            'predefined'            => 'nullable|int|max:1',
            'active'                => 'nullable|int|max:1',
        ]);

        // Verificamos si ya existe un tipo de producto con el mismo uid
        if (ProductType::where('name', $request->name)->first()) {
            return redirect()->route('productTypesList')->with('error', __(
                'main.messages.already_exists',
                [
                    'resource' => __('entity.product_type'),
                    'idKey' => __('main.name'),
                    'idValue' => $request->name
                ]
            ));
        }

        // Creación del tipo de producto en la base de datos
        $product_type = ProductType::create([
            'name'             =>  $request->name,
            'code'             =>  $request->code,
            'tangible'         =>  $request->tangible,
        ]);

        LogHelper::handleLog('create', '', '', 'product_type', $request->name);

        return redirect()->route('productTypesList')->with('success', __(
            'main.messages.create_success_info',
            [
                'resource' => __('entity.product_type'),
                'name' => $request->name
            ]
        ));
    }

    public function edit($id)
    {
        $productType = ProductType::findOrFail($id);

        return view('productTypes.edit', compact('productType'));
    }

    public function update(Request $request, $id)
    {

        // Verificamos que nos pasen id por parametro
        if (!$id) {
            return redirect()->route('productTypesList')->with('error', __('main.messages.id_missing'));
        }

        // Buscamos el tipo de producto por su ID o lanzamos un error si no se encuentra
        $productType = ProductType::findOrFail($id);

        if (!$productType) {
            return redirect()->route('productTypesList')->with('error', __('main.messages.reference_not_found', ['id' => $id]));
        }
        // Validamos comunes a todos los casos
        $validation = $request->validate([
            'code'                  => 'required|string|max:6',
            'predefined'            => 'nullable|int|max:1',
            'tangible'              => 'nullable|int|max:1',
            'active'                => 'nullable|int|max:1',
        ]);

        // Verificamos si ya existe un tipo de producto con el mismo name (excluyendo el actual)
        $existingProductType = ProductType::where('name', $request->name)->where('id', '!=', $id)->first();

        // Si existe un tipo de producto con el mismo name y no es el mismo ID, lanzamos un error
        if ($existingProductType) {
            return redirect()->route('productTypesList')->with('error', __(
                'main.messages.already_exists',
                [
                    'resource' => __('entity.product_type'),
                    'idKey' => __('main.name'),
                    'idValue' => $request->name,
                ]
            ));
        }

        // Verificamos si el tipo de producto es predefinido
        if ($productType->predefined) {

            // Si es predefinido, solo se pueden actualizar algunos campos
            $productType->update($validation);

            LogHelper::handleLog('update', '', '', 'product_type', $request->name);
        } else {
            // Si no es predefinido, se pueden actualizar otros campos

            // Validamos los campos específicos para no predefinidos
            $validation = $request->validate([
                'name'                  => 'required|string|max:20',
                'code'                  => 'required|string|max:6',
                'predefined'            => 'nullable|int|max:1',
                'tangible'              => 'nullable|int|max:1',
                'active'                => 'nullable|int|max:1',
            ]);

            // Actualizamos los campos
            $product_type = $productType->update($validation);

            LogHelper::handleLog('update', '', '', 'product_type', $request->name);
        }

        return redirect()->route('productTypesList')->with('success', __(
            'main.update_success_info',
            [
                'resource' => __('entity.product_type'),
                'name' => $request->name
            ]
        ));
    }

    public function statusToggle(Request $request)
    {
        $id = $request->id;

        if (!$id) {
            return redirect()->route('productTypesList')->with('error',  __('main.messages.id_missing'));
        }

        $productType = ProductType::find($id);

        if (!$productType) {
            return redirect()->route('productTypesList')->with('error', __(
                'main.messages.reference_not_found',
                [
                    'id' => $id,
                ]
            ));
        }

        if ($productType->predefined) {
            return redirect()->route('productTypesList')->with('error', __('main.messages.type_predefined', ['name' => $productType->name]));
        }

        $newActive = $productType->active == 1 ? 0 : 1;
        if ($productType->update(['active' => $newActive])) {
            if ($newActive == 1) {
                LogHelper::handleLog('activate', '', '', 'product_type', $productType->name);
                return redirect()->route('productTypesList')->with('success', __('main.messages.deactivate_success', ['resource' => __('entity.product_type'), 'id' => $productType->name]));
            }
            LogHelper::handleLog('deactivate', '', '', 'product_type', $productType->name);
            return redirect()->route('productTypesList')->with('success', __('main.messages.activate_success', ['resource' => __('entity.product_type'), 'id' => $productType->name]));
        }

        return redirect()->route('productTypesList')->with('error', __('unexpected_error', ['message' => $productType->name]));
    }
}
