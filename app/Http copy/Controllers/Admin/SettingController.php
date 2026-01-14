<?php 
namespace App\Http\Controllers\Admin;

use App\Models\Setting;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SettingController extends Controller
{

    public function __construct()
    {
    }

    public function settingsView()
    {
        try {
            $settings = Setting::all();

            return view('settings.index', compact('settings'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }

    }

    public function createSetting(Request $request)
    {
        try {
            $request->validate([
                'key' => 'required|string|unique:settings,key',
                'value' => 'required|string',
            ]);

            $key = $request->key;
            $key = trim($key); // Enlève les espaces en début et fin
            $key = strtolower($key); // Met tout en minuscules
            $key = str_replace(' ', '_', $key); // Remplace les espaces par des underscores

            $value = $request->value;

            Setting::insert([
                'key'       => $key,
                'value'     => $value
            ]);

            return redirect()->back()->with('success', __('messages/controller.admin.setting.create.success'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function settingDelete(Request $request)
    {
        try {
            $id = $request->id;
            Setting::where('id', $id)->delete();

            return redirect()->back()->with('success',  __('messages/controller.admin.setting.delete.success'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

    public function settingUpdate(Request $request)
    {
        try {
            $request->validate([
                'key'       => 'required|string|max:255',
                'value'     => 'required|string|max:255',
            ]);

            //$value = trim($request->value); // Enlève les espaces en début et fin
            //$value = strtolower($value); // Met tout en minuscules
            //$value = str_replace(' ', '_', $value); // Remplace les espaces par des underscores

            Setting::where('id', $request->id)->update([
                'key'           => $request->key,
                'value'         => $request->value
            ]);   

            return redirect()->back()->with('success', __('messages/controller.admin.setting.update.success'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', __('messages/controller.main.error', ['message' => $th->getMessage()]));
        }
    }

}
