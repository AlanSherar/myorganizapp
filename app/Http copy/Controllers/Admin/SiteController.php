<?php

namespace App\Http\Controllers\Admin;

use App\Models\Site;
use App\Models\SiteOpen;
use App\Helpers\LogHelper;
use App\Models\SiteCutOff;
use Illuminate\Http\Request;
use App\Services\SiteService;
use App\Services\UserService;
use App\Support\Site\SiteSupport;
use App\Services\WarehouseService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class SiteController extends Controller
{
    public function __construct() {}

    public function sitesList(Request $request)
    {
        try {
            $rows = $request->per_page ?? 10;
            $sites = SiteService::listQuery($request)->with('supervisor')->orderBy('name')->paginate($rows)->appends($request->all());

            return view('sites.list', compact('sites'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function siteCreate()
    {
        try {
            $countries = getCountries();
            $timezones = getTimezones();
            $states = getStates();
            $supervisors = UserService::getUsersByRole(4);

            return view('sites.create', compact('countries', 'timezones', 'states', 'supervisors'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function siteStore(Request $request)
    {
        try {
            SiteSupport::validate($request);
            $site = Site::create($request->all());

            SiteCutOff::create([
                'site_id'       => $site->id,
                "sunday"        => $request->sunday_cut_off,
                "monday"        => $request->monday_cut_off,
                "tuesday"       => $request->tuesday_cut_off,
                "wednesday"     => $request->wednesday_cut_off,
                "thursday"      => $request->thursday_cut_off,
                "friday"        => $request->friday_cut_off,
                "saturday"      => $request->saturday_cut_off
            ]);

            SiteOpen::create([
                'site_id'       => $site->id,
                "sunday"        => (int)$request->sunday_open ?? 0,
                "monday"        => (int)$request->monday_open ?? 0,
                "tuesday"       => (int)$request->tuesday_open ?? 0,
                "wednesday"     => (int)$request->wednesday_open ?? 0,
                "thursday"      => (int)$request->thursday_open ?? 0,
                "friday"        => (int)$request->friday_open ?? 0,
                "saturday"      => (int)$request->saturday_open ?? 0
            ]);
            LogHelper::handleLog('create', '', '', 'site', $request->name);

            return redirect()->route('sitesList')->with('success', __('messages/controller.admin.site.create.success'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function siteEdit($id)
    {
        try {
            $site = Site::where('id', $id)->with('supervisor')->first();

            if (!$site) {
                return redirect()->route('sitesList')->with('error', __('messages/controller.admin.site.error.reference_not_found'));
            }
            
            $countries = getCountries();
            $timezones = getTimezones();
            $states = getStates();
            $supervisors = UserService::getUsersByRole(4);
            $defaultCutOff = SiteCutOff::where('site_id', $id)->first();
            $defaultOpen = SiteOpen::where('site_id', $id)->first();

            if (!$defaultCutOff) {
                $defaultCutOff = (object)[
                    'sunday'    => '14:00:00',
                    'monday'    => '14:00:00',
                    'tuesday'   => '14:00:00',
                    'wednesday' => '14:00:00',
                    'thursday'  => '14:00:00',
                    'friday'    => '14:00:00',
                    'saturday'  => '14:00:00',
                ];
            }

            if (!$defaultOpen) {
                $defaultOpen = (object)[
                    'sunday'    => 1,
                    'monday'    => 1,
                    'tuesday'   => 1,
                    'wednesday' => 1,
                    'thursday'  => 1,
                    'friday'    => 1,
                    'saturday'  => 1,
                ];
            }

            return view('sites.edit', compact('site', 'countries', 'states', 'timezones', 'supervisors', 'defaultCutOff', 'defaultOpen'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }   
    }

    public function siteUpdate(Request $request, $id)
    {
        $site = Site::find($id);
        
        if (!$site) {
            return redirect()->route('sitesList')->with('error', __('messages/controller.admin.site.error.id_code_already_exists'));
        }

        try {
            SiteSupport::validate($request);
            DB::beginTransaction();
            $site->update($request->all());

            SiteCutOff::upsert([
                "site_id"       => $id,
                "sunday"        => $request->sunday_cut_off,
                "monday"        => $request->monday_cut_off,
                "tuesday"       => $request->tuesday_cut_off,
                "wednesday"     => $request->wednesday_cut_off,
                "thursday"      => $request->thursday_cut_off,
                "friday"        => $request->friday_cut_off,
                "saturday"      => $request->saturday_cut_off
            ], 'site_id');

            SiteOpen::upsert([
                'site_id'       => $id,
                "sunday"        => (int)$request->sunday_open ?? 0,
                "monday"        => (int)$request->monday_open ?? 0,
                "tuesday"       => (int)$request->tuesday_open ?? 0,
                "wednesday"     => (int)$request->wednesday_open ?? 0,
                "thursday"      => (int)$request->thursday_open ?? 0,
                "friday"        => (int)$request->friday_open ?? 0,
                "saturday"      => (int)$request->saturday_open ?? 0
            ], 'site_id');

            DB::commit();
            LogHelper::handleLog('update', '', '', 'site', $request->name);

            return redirect()->route('siteDetails', ['id' => $id])->with('success', __('messages/controller.admin.site.update.success'));
        } catch (\Throwable $th) {
            return redirect()->route('siteDetails', ['id' => $id])->with('error', __('messages/controller.admin.site.error.reference_not_found'));
        }

    }

    public function siteStatusToggle($id)
    {
        try {
            $site_id = $id;

            if (!$site_id) {
                return redirect()->route('sitesList')->with('error',  __('messages/controller.admin.site.error.id_missing_deletion'));
            }

            $site = Site::find($site_id);

            if (!$site) {
                return redirect()->route('sitesList')->with('error', __('messages/controller.admin.site.error.reference_not_found'));
            }

            $newActive = $site->active == 1 ? 0 : 1;

            if ($site->update(['active' => $newActive])) {
                if ($newActive == 1) {
                    LogHelper::handleLog('activate', '', '', 'site', $site->name);

                    return redirect()->route('sitesList')->with('success', __('messages/controller.admin.site.status.success_activation'));
                }

                WarehouseService::deactivateAllWarehousesBySiteId($site_id);

                LogHelper::handleLog('deactivate', '', '', 'site', $site->name);

                return redirect()->route('sitesList')->with('success', __('messages/controller.admin.site.status.success_deactivation'));
            }
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function siteDetails($id)
    {
        try {
            $site = Site::where('id', $id)->with('supervisor')->first();
            $defaultCutOff = SiteCutOff::where('site_id', $id)->first();
            $defaultOpen = SiteOpen::where('site_id', $id)->first();

            if (!$defaultCutOff) {
                $defaultCutOff = (object)[
                    'sunday'    => '14:00:00',
                    'monday'    => '14:00:00',
                    'tuesday'   => '14:00:00',
                    'wednesday' => '14:00:00',
                    'thursday'  => '14:00:00',
                    'friday'    => '14:00:00',
                    'saturday'  => '14:00:00',
                ];
            }

            if (!$defaultOpen) {
                $defaultOpen = (object)[
                    'sunday'    => 1,
                    'monday'    => 1,
                    'tuesday'   => 1,
                    'wednesday' => 1,
                    'thursday'  => 1,
                    'friday'    => 1,
                    'saturday'  => 1,
                ];
            }

            if (!$site) {
                return redirect()->route('sitesList')->with('error', __('messages/controller.admin.site.error.reference_not_found'));
            }

            return view('sites.details', compact('site', 'defaultCutOff', 'defaultOpen'));
        } catch (\Throwable $th) {
            return redirect()->route('sitesList')->with('error', __('messages/controller.admin.site.error.reference_not_found'));
        }
    }
}
