<?php
namespace App\Http\Controllers\Admin;

use App\Helpers\LogHelper;
use App\Models\Site;
use App\Models\Carrier;
use App\Models\Company;
use App\Models\warehouse;
use Illuminate\Http\Request;
use App\Services\WarehouseService;
use App\Http\Controllers\Controller;

class WarehouseController extends Controller
{
    private $warehouseService;

    public function __construct(WarehouseService $warehouseService)
    {
        $this->warehouseService = $warehouseService;
    }

    public function warehousesList(Request $request)
    {
        $rows = $request->per_page ?? 10;

        $query = $this->warehouseService->listQuery($request);

        $warehouses = $query->orderBy('name')->paginate($rows);
        $warehouses->appends($request->all());

        $sites = Site::where('active', 1)->get();

        return view('warehouses.list', compact('warehouses', 'sites'));
    }

    public function warehouseCreate()
    {
        $sites = Site::all();
        $companies = Company::All();
        $carriers = Carrier::All();

        return view('warehouses.create', compact('sites', 'companies', 'carriers'));
    }

    public function warehouseStore(Request $request)
    {
        try {   
            $request->validate([
                'name'       => 'required|string|max:50',
                'code'       => 'required|string|max:6',
                'site_id'    => 'required|integer'
            ]);

            $warehouse = $this->warehouseService->handleStore($request);

            if(!$warehouse->id){
                return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => 'Error on insert']));
            }

            //$this->warehouseService->handleCompaniesLink($request, $warehouse->id);
            $this->warehouseService->handleCarriersLink($request, $warehouse->id);

            LogHelper::handleLog('create', '', '', 'warehouse', $request->name);
           
            return redirect()->route('warehousesList')->with('success', 'Warehouse ' . __('main.create_success'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()]));
        }
    }

    public function warehouseEdit(Request $request)
    {
        $id = $request->query('id');
        
        //$warehouse = Warehouse::with('companies')->find($id);
        $warehouse = Warehouse::find($id);
        $sites = Site::all();
        //$companies = Company::all();
        //$carriers = Carrier::All();
        
        return view('warehouses.edit', compact('warehouse', 'sites'));
    }

    public function warehouseUpdate(Request $request, $id)
    {
        try {
            $request->validate([
                'name'       => 'required|string|max:50',
                'code'       => 'required|string|max:6',
                'site_id'    => 'required|integer'
            ]);
    
            $warehouse = Warehouse::find($id);
    
            if (!$warehouse) {
                return redirect()->route('warehousesList')->with('error', __('warehouses/main.not_found'));
            }

            $this->warehouseService->handleUpdate($request, $id);

            if(!$warehouse->id){
                return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => 'Error on update']));
            }
/* 
            $this->warehouseService->handleUpdateCompaniesLink($request, $id);
            $this->warehouseService->handleUpdateCarriersLink($request, $id); */

            LogHelper::handleLog('update', '', '', 'warehouse', $warehouse->name);

            return redirect()->route('warehousesList')->with('success', $warehouse->label . ' ' . __('main.update_success'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            return redirect()->route('warehousesList')->with('error', __('messages/controller.main.unexpected', ['message' => $e->getMessage()]));
        }
    }    

    public function warehouseActivate(Request $request)
    {
        try {
            $id = $request->warehouse_id;
    
            if (!$id) {
                return redirect()->route('warehousesList')->with('error',  __('messages/controller.packaging_reference.error.id_missing_deletion'));
            }
        
            $warehouse = Warehouse::find($id);

            if (!$warehouse) {
                return redirect()->route('warehousesList')->with('error', __('main.not_found'));
            }

            $message = $this->warehouseService->handleStatus($warehouse);

            return redirect()->route('warehousesList')->with('success', $warehouse->name . ' ' . $message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            return redirect()->route('warehousesList')->with('error', __('messages/controller.main.unexpected', ['message' => $e->getMessage()]));
        }
    }

    public function warehouseDetails($id)
    {
        try {
            //$warehouse = Warehouse::with('companies')->with('site')->with('carriers')->find($id);
            $warehouse = Warehouse::with('site')->find($id);

            return view('warehouses.details', compact('warehouse'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            return redirect()->route('warehousesList')->with('error', __('messages/controller.main.unexpected', ['message' => $e->getMessage()]));
        }
    }
    
}
