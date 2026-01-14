<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Models\User;
use App\Models\Site;
use Illuminate\Http\Request;
use App\Models\PackagingOrder;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class RoleController extends Controller
{
    public function __construct() {}

    public function rolesList(Request $request)
    {
        try {
            $roles = Role::paginate(10);
            return view('roles.list', compact('roles'));
        } catch (\Exception $e) {
            return redirect()->route('rolesList')->with('error', $e->getMessage());
        }
    }


    public function roleCreate()
    {
        try {
            return view('roles.create');
        } catch (\Exception $e) {
            return redirect()->route('rolesList')->with('error', $e->getMessage());
        }
    }

    public function roleStore(Request $request)
    {
        try {
            // Validation des données de la requête
            $request->validate([
                'name'          => 'required|string|max:255',
                'label'          => 'required|string|max:255',
            ]);

            // Création de l'utilisateur dans la base de données
            Role::create([
                'name'          => $request->name,
                'label'         => $request->label,
            ]);

            return redirect()->route('rolesList')->with('success', __('messages/controller.admin.role.create.success'));
        } catch (\Illuminate\Database\QueryException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function roleEdit($id)
    {
        try {
            $role = Role::findOrFail($id);
            return view('roles.edit', compact('role'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('rolesList')->with('error', $e->getMessage());
        }
    }

    public function roleUpdate(Request $request, $id)
    {
        try {
            $request->validate([
                'name'          => 'required|string|max:255',
                'label'          => 'required|string|max:255',
            ]);

            $role = Role::findOrFail($id);
            $role->update([
                'name'          => $request->name,
                'label'         => $request->label,
            ]);
            return redirect()->route('rolesList')->with('success', __('messages/controller.admin.role.update.success'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }


    public function roleDetails($id)
    {
        try {
            $role = Role::findOrFail($id);
            return view('roles.details', compact('role'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('rolesList')->with('error', __('messages/controller.admin.role.details.not_found'));
        }
    }
}
