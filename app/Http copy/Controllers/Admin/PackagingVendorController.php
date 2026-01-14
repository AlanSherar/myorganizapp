<?php

namespace App\Http\Controllers\Admin;

use App\Models\PackagingReference;
use Illuminate\Http\Request;
use App\Models\PackagingType;
use App\Models\PackagingVendor;
use App\Http\Controllers\Controller;

class PackagingVendorController extends Controller
{
    public function packagingProviderList()
    {
        $packagings = PackagingVendor::All();

        return view('packaging-provider.list', compact('packagings'));
    }

    public function packagingProviderCreate()
    {
        return view('packaging-provider.create');
    }

    public function packagingProviderStore(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'provider_name' => 'required|string|unique:packaging_vendors,name',
            ]);

            PackagingVendor::create([
                'name' => $validatedData['provider_name']
            ]);

            return redirect()->route('packagingProviderList')->with('success', __('messages/controller.packaging_vendor.create.success'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Rediriger avec un message d'erreur générique en cas de problème inattendu
            return redirect()->route('packagingProviderList')->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()]));
        }
    }

    public function packagingProviderEdit(Request $request)
    {
        $id = $request->query('id');
        $packagingSelected = PackagingVendor::find($id);

        return view('packaging-provider.edit', compact('packagingSelected'));
    }

    public function packagingProviderUpdate(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'provider_name' => 'required|string|unique:packaging_vendors,name,' . $request->id,
            ]);

            $id = $request->id;
            $packagingSelected = PackagingVendor::find($id);

            $packagingSelected->update([
                'name' => $validatedData['provider_name']
            ]);

            return redirect()->route('packagingProviderList')->with('success', __('messages/controller.packaging_vendor.update.success'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Rediriger avec un message d'erreur générique en cas de problème inattendu
            return redirect()->route('packagingProviderList')->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()]));
        }
    }

    public function packagingProviderDelete(Request $request)
    {
        $id = $request->provider_id;

        if (!$id) {
            return redirect()->route('packagingProviderList')->with('error', __('messages/controller.packaging_vendor.error.id_missing_deletion'));
        }

        $packagingVendor = PackagingVendor::find($id);

        if (!$packagingVendor) {
            return redirect()->route('packagingProviderList')->with('error', __('messages/controller.packaging_vendor.error.reference_not_found'));
        }

        // Vérifier si le fournisseur est utilisé dans packaging_types
        $isUsedInPackagingTypes = PackagingType::where('provider_id', $id)->exists();

        // Vérifier si le fournisseur est utilisé dans packaging
        $isUsedInPackaging = PackagingReference::where('provider_id', $id)->exists();

        if ($isUsedInPackagingTypes || $isUsedInPackaging) {
            return redirect()->route('packagingProviderList')->with(
                'error',
                __('messages/controller.packaging_vendor.error.referenced')
            );
        }

        $packagingVendor->delete();

        return redirect()->route('packagingProviderList')->with('success', __('messages/controller.packaging_vendor.delete.success'));
    }
}
