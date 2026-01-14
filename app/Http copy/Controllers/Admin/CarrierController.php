<?php
namespace App\Http\Controllers\Admin;

use App\Models\Carrier;
use App\Models\Warehouse;
use App\Helpers\LogHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class CarrierController extends Controller
{

    public function carriersList(Request $request)
    {
        $rows = $request->per_page ?? 10;

        $query = Carrier::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . strtolower($request->search) . '%');
        }

        if ($request->filled('include_inactive')) {
            $query->whereIn('active', [0, 1]);
        }else {
            $query->where('active', 1);
        }

        $carriers = $query->orderBy('name')->paginate($rows);
        $carriers->appends($request->all());

        return view('carriers.list', compact('carriers'));
    }

    public function carrierCreate()
    {
        return view('carriers.create');
    }

    public function carrierStore(Request $request)
    {
        try {       
            $request->validate([
                'name'              => 'required|string|max:50',
                'phone'             => 'nullable|string|max:50',
                'type'              => 'required|string|max:80',
                'email'             => 'nullable|email|max:80',
                'scac'              => 'required|max:80',
                'contact_name'      => 'nullable|string|max:80',
                'logo'              => 'nullable|image|mimes:jpg,jpeg,png|max:3072',
                'services_data'     => 'nullable|json',
            ]);

            // Traiter les services
            $services = $request->services_data ? json_decode($request->services_data, true) : [];

            $is_active = $request->has('is_active') ? 1 : 0;

            // Gérer le logo
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('logos', 'public');
            }

            $carrier = Carrier::create([
                'name'              => $request->name,
                'active'            => $is_active,
                'type'              => $request->type,
                'phone'             => $request->phone,
                'email'             => $request->email,
                'scac'              => $request->scac ?? '',
                'contact_name'      => $request->contact_name,
                'logo'              => $logoPath
            ]);

            // Enregistrer les services
            if (!empty($services)) {
                foreach ($services as $service) {
                    $carrier->services()->create([
                        'name' => $service['service_name'],
                        'scac' => $service['service_scac'],
                        'type' => $service['service_type'],
                    ]);
                }
            }

            LogHelper::handleLog('create', '', '', 'carrier', $request->name);
           
            return redirect()->route('carriersList')->with('success', $request->name . ' ' . __('main.create_success'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('carriersList')->withInput()->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()]));
        }
    }

    public function carrierEdit(Request $request)
    {
        $id = $request->query('id');
        
        $carrier = Carrier::find($id);

        return view('carriers.edit', compact('carrier'));
    }

    public function carrierUpdate(Request $request, $id)
    {
        try {
            // Validation des données
            $request->validate([
                'name'              => 'required|string|max:50',
                'type'              => 'required|string|max:50',
                'scac'              => 'required|string|max:50',
                'phone'             => 'nullable|string|max:50',
                'email'             => 'nullable|email|max:80',
                'contact_name'      => 'nullable|string|max:80',
                'logo'              => 'nullable|image|mimes:jpg,jpeg,png|max:3072',
                'services_data'     => 'nullable|json',
            ]);
    
            // Trouver l'enregistrement de packaging par son ID
            $carrier = Carrier::find($id);
    
            // Vérifier si l'enregistrement existe
            if (!$carrier) {
                return redirect()->route('carriersList')->with('error', __('messages/controller.admin.packaging_reference.error.reference_not_found'));
            }

            $is_active = $request->active ? 1 : 0;

            // Mettre à jour l'enregistrement avec les données validées
            $logoPath = $carrier->logo;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('logos', 'public');
            }

            $carrier->update([
                'name'              => $request->name,
                'type'              => $request->type,
                'active'            => $is_active,
                'phone'             => $request->phone,
                'email'             => $request->email,
                'scac'              => $request->scac,
                'contact_name'      => $request->contact_name,
                'logo'              => $logoPath
            ]);
    
            // Sync services
            $services = $request->services_data ? json_decode($request->services_data, true) : [];
            $carrier->services()->delete();
            if (!empty($services) && is_array($services)) {
                foreach ($services as $service) {
                    if (!empty($service['service_name'])) {
                        $carrier->services()->create([
                            'name' => $service['service_name'],
                            'scac' => $service['service_scac'],
                            'type' => $service['service_type'],
                        ]);
                    }
                }
            }

            LogHelper::handleLog('update', '', '', 'carrier', $request->name);
    
            // Retourner à la liste des packagings avec un message de succès
            return redirect()->route('carriersList')->with('success', $request->name . ' ' . __('main.update_success'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            return redirect()->route('carriersList')->with('error', __('messages/controller.main.unexpected', ['message' => $e->getMessage()]));
        }
    }    

    public function carrierDelete(Request $request)
    {
        try {
            $id = $request->carrier_id;
    
            // Check if the ID is provided
            if (!$id) {
                return redirect()->route('carriersList')->with('error',  __('messages/controller.admin.packaging_reference.error.id_missing_deletion'));
            }
        
            $carrier = Carrier::find($id);
            $name = $carrier->name;

            // Check if the entry exists
            if (!$carrier) {
                return redirect()->route('carriersList')->with('error', __('messages/controller.admin.packaging_reference.error.reference_not_found'));
            }
            
            if($carrier->active == 1){   
                $carrier->update([
                    'active'        => 0
                ]);

                LogHelper::handleLog('delete', '', '', 'carrier', $name);

                return redirect()->route('carriersList')->with('success', $name . ' ' . __('main.messages.deactivate_success'));
            }
            
            if($carrier->active == 0){   
                $carrier->update([
                    'active'        => 1
                ]);

                LogHelper::handleLog('delete', '', '', 'carrier', $name);

                return redirect()->route('carriersList')->with('success', $name . ' ' . __('main.messages.activate_success'));
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            return redirect()->route('carriersList')->with('error', __('messages/controller.main.unexpected', ['message' => $e->getMessage()]));
        }
    }

    public function carrierDetail($id)
    {
        try {
            $carrier = Carrier::find($id);

            return view('carriers.details', compact('carrier'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            return redirect()->route('carriersList')->with('error', __('messages/controller.main.unexpected', ['message' => $e->getMessage()]));
        }
    }

}
