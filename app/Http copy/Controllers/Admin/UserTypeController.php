<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Models\User;
use App\Models\Site;
use Illuminate\Http\Request;
use App\Models\PackagingOrder;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Models\UserType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class UserTypeController extends Controller
{
    public function __construct() {}

    public function userTypesList(Request $request)
    {
        try {
            $userTypes = UserType::paginate(10);
            return view('user_types.list', compact('userTypes'));
        } catch (\Exception $e) {
            return redirect()->route('userTypesList')->with('error', $e->getMessage());
        }
    }

    public function userTypeCreate()
    {
        try {
            return view('user_types.create');
        } catch (\Exception $e) {
            return redirect()->route('userTypesList')->with('error', $e->getMessage());
        }
    }

    public function userTypeStore(Request $request)
    {
        try {
            // Validation des données de la requête
            $request->validate([
                'name'                  => 'required|string|max:50',
                'code'                  => 'required|string|max:10|unique:user_types,code',
                'description'           => 'nullable|string|max:255',
            ]);

            // Création de l'utilisateur dans la base de données
            UserType::create([
            'name'          => $request->name,
            'code'          => $request->code,
            'description'   => $request->description,
        ]);

        return redirect()->route('userTypesList')->with('success', __('messages/controller.admin.user_type.create.success'));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function userTypeEdit($id)
    {
        try {
            $userType = UserType::findOrFail($id);
            return view('user_types.edit', compact('userType'));
        } catch (\Exception $e) {
            return redirect()->route('userTypesList')->with('error', $e->getMessage());
        }
    }

    public function userTypeUpdate(Request $request, $id)
    {
        try {
            $request->validate([
                'name'                  => 'required|string|max:50',
                'code'                  => 'required|string|max:10|unique:user_types,code,' . $id,
                'description'           => 'nullable|string|max:255',
            ]);
    
            $userType = UserType::findOrFail($id);
            $userType->update([
                'name'          => $request->name,
                'code'          => $request->code,
                'description'   => $request->description,
            ]);

            return redirect()->route('userTypesList')->with('success', __('messages/controller.admin.user_type.update.success'));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function userTypeDetails($id)
    {
        try {
            $userType = UserType::findOrFail($id);
            return view('user_types.details', compact('userType'));
        } catch (\Exception $e) {
            return redirect()->route('userTypesList')->with('error', $e->getMessage());
        }
    }
}
