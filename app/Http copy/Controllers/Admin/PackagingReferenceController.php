<?php
namespace App\Http\Controllers\Admin;

use App\Models\PackagingReference;
use Illuminate\Http\Request;
use App\Models\PackagingType;
use App\Models\PackagingVendor;
use App\Http\Controllers\Controller;

class PackagingReferenceController extends Controller
{

    public function packagingReferenceList(Request $request)
    {
        $rows = request()->query('per_page', 10);
        $query = PackagingReference::query();
        
        if ($request->filled('include_inactive')) {
            $query->whereIn('is_active', [0, 1]);
        }else {
            $query->where('is_active', 1);
        }
        
        $packagings = $query->paginate($rows);

        return view('packaging-reference.list', compact('packagings'));
    }

    public function packagingReferenceCreate()
    {
        $packagingTypes = PackagingType::all();
        $packagingProviders = PackagingVendor::all();
        
        return view('packaging-reference.create', compact('packagingProviders', 'packagingTypes'));
    }

    public function packagingReferenceStore(Request $request)
    {
        
        try {                   
            $request->validate([
                'sku'                   => 'required|string|max:50',
                'provider_id'           => 'required|integer|exists:packaging_vendors,id',
                'packaging_type_id'     => 'required|integer|exists:packaging_types,id',
                'width'                 => 'required|numeric|min:0',
                'height'                => 'required|numeric|min:0',
                'length'                => 'required|numeric|min:0',
                'upc'                   => 'required|string|max:50',
                'price'                 => 'required|numeric',
                'is_active'             => 'required'
            ]);

            PackagingReference::create([
                'provider_id'           => $request->provider_id,
                'type_id'               => $request->packaging_type_id,
                'width'                 => $request->width,
                'height'                => $request->height,
                'length'                => $request->length,
                'sku'                   => $request->sku,
                'upc'                   => $request->upc ?? null,
                'price'                 => $request->price,
                'is_active'             => 1,
                'image'                 => 'big_box.png'
            ]);
           
            return redirect()->route('packagingReferenceList')->with('success', $request->sku . ' ' . __('main.create_success'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->route('packagingReferenceList')->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()]));
        }
    }

    public function packagingReferenceEdit(Request $request)
    {
        $id = $request->query('id');
        
        $packagingSelected = PackagingReference::find($id);
        $packagingTypes = PackagingType::all();
        $packagingProviders = PackagingVendor::all();
        
        return view('packaging-reference.edit', compact('packagingProviders', 'packagingTypes', 'packagingSelected'));
    }

    public function packagingReferenceUpdate(Request $request, $id)
    {
        try {
            // Validation des données
            $validatedData = $request->validate([
                'provider_id'           => 'required|integer|exists:packaging_vendors,id',
                'packaging_type_id'     => 'required|integer|exists:packaging_types,id',
                'width'                 => 'required|numeric|min:0',
                'height'                => 'required|numeric|min:0',
                'length'                => 'required|numeric|min:0',
                'sku'                   => 'required|string|max:50',
                'upc'                   => 'nullable|string|max:50',
                'price'                 => 'required|numeric',
                'is_active'             => 'required'
            ]);
    
            // Trouver l'enregistrement de packaging par son ID
            $packaging = PackagingReference::find($id);
    
            // Vérifier si l'enregistrement existe
            if (!$packaging) {
                return redirect()->route('packagingReferenceList')->with('error', __('messages/controller.packaging_reference.error.reference_not_found'));
            }

            $active = $validatedData['is_active'] == "on" ? 1 : 0;

            // Mettre à jour l'enregistrement avec les données validées
            $packaging->update([
                'provider_id'           => $validatedData['provider_id'],
                'type_id'               => $validatedData['packaging_type_id'],
                'width'                 => $validatedData['width'],
                'height'                => $validatedData['height'],
                'length'                => $validatedData['length'],
                'sku'                   => $validatedData['sku'],
                'upc'                   => $validatedData['upc'] ?? null,
                'price'                 => $validatedData['price'],
                'is_active'             => $active,
                'image'                 => 'big_box.png'
            ]);
    
            // Retourner à la liste des packagings avec un message de succès
            return redirect()->route('packagingReferenceList')->with('success', $packaging->sku . ' ' . __('main.update_success'));
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            return redirect()->route('packagingReferenceList')->with('error', __('messages/controller.main.unexpected', ['message' => $e->getMessage()]));
        }
    }    

    public function packagingReferenceDelete(Request $request)
    {
        try {
            $id = $request->packaging_id;
    
            // Check if the ID is provided
            if (!$id) {
                return redirect()->route('packagingReferenceList')->with('error',  __('messages/controller.packaging_reference.error.id_missing_deletion'));
            }
        
            $packagingReference = PackagingReference::find($id);

            // Check if the entry exists
            if (!$packagingReference) {
                return redirect()->route('packagingReferenceList')->with('error', __('messages/controller.packaging_reference.error.reference_not_found'));
            }
            
            // Delete the entry
            $packagingReference->delete();
        
            // Redirect with a success message
            return redirect()->route('packagingReferenceList')->with('success',  $packagingReference->sku . ' ' . __('main.delete_success'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            return redirect()->route('packagingReferenceList')->with('error', __('messages/controller.main.unexpected', ['message' => $e->getMessage()]));
        }
    }
    
}
