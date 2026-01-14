<?php

namespace App\Http\Controllers\Admin;

use App\Models\Site;
use App\Models\Carrier;
use App\Models\Company;
use App\Helpers\LogHelper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\CompanyService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class CompanyController extends Controller
{

    private $companyService;

    public function __construct(CompanyService $companyService)
    {
        $this->companyService = $companyService;
    }

    public function companiesList(Request $request)
    {
        $rows = $request->per_page ?? 10;

        $query = Company::query();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . strtolower($request->search) . '%');
        }

        if ($request->filled('include_inactive')) {
            $query->whereIn('active', [0, 1]);
        } else {
            $query->where('active', 1);
        }

        $companies = $query->orderBy('name')->paginate($rows);
        $companies->appends($request->all());

        return view('companies.list', compact('companies'));
    }

    public function companyCreate()
    {
        $sites = Site::where('active', 1)->get();
        $carriers = Carrier::where('active', 1)->get();

        return view('companies.create', compact('sites', 'carriers'));
    }

    public function companyStore(Request $request)
    {
        try {
            $request->validate([
                'name'              => 'required|string|max:50|unique:companies,name',
                'code'              => 'required|string|max:6|unique:companies,code',
                'address'           => 'required|string|max:80',
                'address_2'         => 'nullable|string|max:80',
                'country'           => 'required|string|max:50',
                'state_province'    => 'nullable|string|max:10',
                'city'              => 'required|string|max:50',
                'zip_code'          => 'required|string|max:10',
                'phone'             => 'nullable|string|max:50',
                'email'             => 'required|email|max:80',
                'ein_tax_number'    => 'nullable|string|max:40',
                'contact_name'      => 'nullable|string|max:80',
                'logo'              => 'nullable|image|mimes:jpg,jpeg,png|max:3072', // max 3MB
                'markup'            => 'nullable|numeric',
                'credit'            => 'nullable|numeric',
                'credit_applied'    => 'nullable',
            ]);

            $is_active = $request->has('is_active') ? 1 : 0;
            $credit_applied = $request->credit_applied ? 1 : 0;
            // Gérer le logo
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('logos', 'public');
            }

            $company = Company::create([
                'name'              => $request->name,
                'code'              => $request->code,
                'active'            => $is_active,
                'address'           => $request->address,
                'address_2'         => $request->address_2,
                'country'           => $request->country,
                'state_province'    => $request->state_province,
                'city'              => $request->city,
                'zip_code'          => $request->zip_code,
                'phone'             => $request->phone,
                'email'             => $request->email,
                'ein_tax_number'    => $request->ein_tax_number,
                'contact_name'      => $request->contact_name,
                'markup'            => $request->markup,
                'credit'            => $request->credit,
                'credit_applied'    => $credit_applied,
                //'logo_path'         => $logoPath, // si tu as une colonne "logo_path" ou équivalent
            ]);

            $siteCarriersData = json_decode($request->input('site_carriers_data'), true);

            foreach ($siteCarriersData as $siteId => $carriers) {
                foreach ($carriers as $carrierData) {
                    DB::table('companies_carriers')->insert([
                        'company_id'     => $company->id,
                        'site_id'        => $siteId,
                        'carrier_id'     => $carrierData['id'],
                        'account_number' => $carrierData['account_number'] ?? null,
                    ]);
                }
            }

            LogHelper::handleLog('create', '', '', 'company', $request->name);

            return redirect()->route('companiesList')->with('success', $request->name . ' ' . __('main.create_success'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $e->getMessage()]));
        }
    }

    public function companyEdit($id)
    {
        try{
            $company = Company::find($id);
            $sites = Site::all();
            $carriers = Carrier::All();
            
            return view('companies.edit', compact('company', 'sites', 'carriers'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function companyUpdate(Request $request, $id)
    {
        try {
            // Validation des données
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('companies')->ignore($id),
                ],
                'code'              => 'required|string|max:6',
                'address'           => 'required|string|max:80',
                'address_2'         => 'nullable|string|max:80',
                'country'           => 'required|string|max:50',
                'state_province'    => 'nullable|string|max:30',
                'city'              => 'required|string|max:50',
                'zip_code'          => 'required|string|max:10',
                'phone'             => 'nullable|string|max:50',
                'email'             => 'required|email|max:80',
                'ein_tax_number'    => 'nullable|string|max:40',
                'contact_name'      => 'nullable|string|max:80',
                'logo'              => 'nullable|image|mimes:jpg,jpeg,png|max:3072', // max 3MB
                'markup'            => 'nullable|numeric',
                //'credit'            => 'nullable|numeric',
                'credit_applied'    => 'nullable',
            ]);

            // Trouver l'enregistrement de packaging par son ID
            $company = Company::find($id);

            // Vérifier si l'enregistrement existe
            if (!$company) {
                return redirect()->route('companiesList')->with('error', __('messages/controller.admin.packaging_reference.error.reference_not_found'));
            }

            $is_active = $request->active ? 1 : 0;
            $credit_applied = $request->credit_applied ? 1 : 0;

            // Mettre à jour l'enregistrement avec les données validées
            $company->update([
                'code'              => $request->code,
                'name'              => $request->name,
                'active'            => $is_active,
                'address'           => $request->address,
                'address_2'         => $request->address_2,
                'country'           => $request->country,
                'state_province'    => $request->state_province,
                'city'              => $request->city,
                'zip_code'          => $request->zip_code,
                'phone'             => $request->phone,
                'email'             => $request->email,
                'ein_tax_number'    => $request->ein_tax_number,
                'contact_name'      => $request->contact_name,
                'markup'            => $request->markup,
                //'credit'            => $request->credit,
                'credit_applied'    => $credit_applied,
                //'logo_path'         => $logoPath, // si tu as une colonne "logo_path" ou équivalent
            ]);

            $this->companyService->handleUpdateCarriersLink($request, $id);

            LogHelper::handleLog('update', '', '', 'company', $request->name);

            // Retourner à la liste des packagings avec un message de succès
            return redirect()->route('companiesList')->with('success', $request->name . ' ' . __('main.update_success'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            return redirect()->route('companiesList')->with('error', __('messages/controller.main.unexpected', ['message' => $e->getMessage()]));
        }
    }

    public function companyDelete(Request $request)
    {
        try {
            $id = $request->company_id;

            if (!$id) {
                return redirect()->route('companiesList')->with('error',  __('messages/controller.admin.packaging_reference.error.id_missing_deletion'));
            }

            $company = Company::find($id);

            if (!$company) {
                return redirect()->route('companiesList')->with('error', __('messages/controller.admin.packaging_reference.error.reference_not_found'));
            }

            if ($company->active == 1) {
                $company->update(['active' => 0]);

                LogHelper::handleLog('deactivate', '', '', 'company', $company->name);

                return redirect()->route('companiesList')->with('success', $company->name . ' ' . __('main.messages.deactivate_success'));
            }

            if ($company->active == 0) {
                $company->update(['active' => 1]);

                LogHelper::handleLog('activate', '', '', 'company', $company->name);

                return redirect()->route('companiesList')->with('success', $company->name . ' ' . __('main.messages.activate_success'));
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            return redirect()->route('companiesList')->with('error', __('messages/controller.main.unexpected', ['message' => $e->getMessage()]));
        }
    }

    public function companyDetail($id)
    {
        try {
            $company = Company::findOrFail($id);

            $carrierData = DB::table('companies_carriers')
                ->where('company_id', $id)
                ->get();

            $groupedCarriers = [];

            foreach ($carrierData as $entry) {
                $site = Site::find($entry->site_id);
                $carrier = Carrier::find($entry->carrier_id);

                if ($site && $carrier) {
                    $groupedCarriers[$site->name][] = [
                        'name' => $carrier->name,
                        'account_number' => $entry->account_number
                    ];
                }
            }

            return view('companies.details', compact('company', 'groupedCarriers'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Rediriger avec les erreurs de validation
            return redirect()->back()
                ->withErrors($e->validator)
                ->withInput();
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            return redirect()->route('companiesList')->with('error', __('messages/controller.main.unexpected', ['message' => $e->getMessage()]));
        }
    }

    public function companyCarriers(Request $request)
    {
        $siteId = $request->query('site_id');
        $companyId = $request->query('company_id');

        // Tous les carriers activés
        $carriers = DB::table('carriers')
            ->where('active', 1)
            ->select('id', 'name')
            ->get();

        $selectedCarrierIds = DB::table('companies_carriers')
            ->where('company_id', $companyId)
            ->where('site_id', $siteId)
            ->pluck('account_number', 'carrier_id')
            ->toArray();

        return response()->json([
            'carriers' => $carriers,
            'selectedCarrierIds' => $selectedCarrierIds
        ]);
    }

    public function updateCredit(Request $request, Company $company)
    {
        try {
            $validated = $request->validate([
                'credit' => ['required', 'integer', 'min:0'],
            ]);

            $company->increment('credit', $validated['credit']);

            LogHelper::handleLog('update_credit', '', '', 'added_credit', $company->name, $validated['credit']);

            return redirect()
                ->back()
                ->with(
                    'success',
                    'Credit updated successfully. New balance: $' . number_format($company->credit)
                );
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }

    }

    public function companiesShippings(Request $request)
    {
        try {
            $query = $this->companyService->handleCompaniesShippings();
            $companies = $query->get();
            //$rows = $request->per_page ?? 10;
            //$companies = $query->paginate($rows)->withQueryString();

            return view('reports.companies-shippings', compact('companies'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function companyShippings(Request $request, $id)
    {
        try {
            $company = Company::findOrFail($id);

            $query = $this->companyService->handleCompanyShippings($company);

            $rows = $request->per_page ?? 10;
            $orders = $query->paginate($rows)->withQueryString();

            return view('reports.company-shippings', compact('orders', 'company'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

}
