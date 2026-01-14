<?php
namespace App\Http\Controllers\Admin;

use App\Models\PackagingReference;
use Illuminate\Http\Request;
use App\Models\PackagingType;
use App\Models\PackagingVendor;
use App\Http\Controllers\Controller;

class PackagingTypeController extends Controller
{

    public function packagingTypeList()
    {
        $packagingsType = PackagingType::with('packagingProvider')->get();
        return view('packaging-type.list', compact('packagingsType'));
    }

    public function packagingTypeCreate()
    {
        $packagingTypes = PackagingType::all();
        $packagingProviders = PackagingVendor::all();
        
        return view('packaging-type.create', compact('packagingProviders', 'packagingTypes'));
    }

    public function packagingTypeStore(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'packaging_type'    => 'required|string',
                'provider_id'       => 'required',
            ]);
        
            PackagingType::create([
                'name'          => $validatedData['packaging_type'],
                'provider_id'   => $validatedData['provider_id']

            ]);
        
            return redirect()->route('packagingTypeList')->with('success', __('messages/controller.packaging_type.create.success'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Rediriger avec un message d'erreur générique en cas de problème inattendu
            return redirect()->route('packagingTypeList')->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()]));
        }   
    }

    public function packagingTypeEdit(Request $request)
    {
        $id = $request->query('id');
        
        $packagingTypeSelected = PackagingType::find($id);
        $packagingProviders = PackagingVendor::all();
        
        return view('packaging-type.edit', compact('packagingProviders', 'packagingTypeSelected'));
    }

    public function packagingTypeUpdate(Request $request, $id)
    {        
        try {
            $validatedData = $request->validate([
                'packaging_type'    => 'required|string',
                'provider_id'       => 'required'
            ]);
            
            $packagingSelected = PackagingType::find($id);

            $packagingSelected->update([
                'name'          => $validatedData['packaging_type'],
                'provider_id'   => $validatedData['provider_id']
            ]);
        
            return redirect()->route('packagingTypeList')->with('success', __('messages/controller.packaging_type.update.success'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Rediriger avec un message d'erreur générique en cas de problème inattendu
            return redirect()->route('packagingTypeList')->with('error',  __('messages/controller.main.unexpected', ['message' => $e->getMessage()]));
        }        
    }

    public function packagingTypeDelete(Request $request)
    {
        $id = $request->type_id;
    
        // Check if the ID is provided
        if (!$id) {
            return redirect()->route('packagingTypeList')->with('error', __('messages/controller.packaging_type.error.id_missing_deletion'));
        }
    
        $packagingType = PackagingType::find($id);
    
        // Check if the entry exists
        if (!$packagingType) {
            return redirect()->route('packagingTypeList')->with('error', __('messages/controller.packaging_type.error.reference_not_found'));
        }

        $isUsedInPackaging = PackagingReference::where('type_id', $id)->exists();

        if ($isUsedInPackaging) {
            return redirect()->route('packagingTypeList')->with(
                'error',
                __('messages/controller.packaging_type.error.referenced')
            );
        }
    
        // Delete the entry
        $packagingType->delete();
    
        // Redirect with a success message
        return redirect()->route('packagingTypeList')->with('success', __('messages/controller.packaging_type.delete.success'));
    }
}