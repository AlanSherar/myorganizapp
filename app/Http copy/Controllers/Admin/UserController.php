<?php
namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Models\User;
use App\Models\Site;
use App\Models\Company;
use App\Models\UserType;
use Illuminate\Http\Request;
use App\Models\PackagingOrder;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;

class UserController extends Controller
{
    public function __construct()
    {
        
    }
    
    public function usersList(Request $request)
    {
        $site_id = $request->site_id;
        $per_page = $request->per_page ?? 10;
        if ($site_id == 'all' || !$site_id) {
            // Si "All" est sélectionné, récupère tous les utilisateurs
            $users = User::with(['type'])->paginate($per_page);
            $site_selected = null; // Pas de site spécifique sélectionnée
        } else {
            // Sinon, récupère les utilisateurs par site_id
            $users = User::where('site_id', $site_id)->with(['type'])->paginate($per_page);
            $site_selected = Site::find($site_id);
        }

        // Récupère toutes les sites
        $sites = Site::all();
        
        return view('users.list', compact('users', 'sites', 'site_selected'));
    }
    

    public function userCreate()
    {
        $roles = Role::all();
        $sites = Site::all();
        $companies = Company::all();
        $userTypes = UserType::all();

        return view('users.create', compact('roles', 'sites', 'companies', 'userTypes'));
    }

    public function userStore(Request $request)
    {
        $messages = [
            'password.regex' => __('messages/controller.password.requirements'),
        ];

        // Validation des données de la requête
        $request->validate([
            'username'          => 'required|string|max:255',
            'site'              => 'required|int',
            'email'             => 'required|email|unique:users,email|max:255',
            'clock_pin'         => 'required|string|unique:users,secret_pin',
            'role'              => 'required|int',
            'lifetime'          => 'required|int|min:5',
            'company_id'        => 'nullable|int|exists:companies,id',
            'type'              => 'required|string|exists:user_types,code',
            'password'          => [
                'required',
                'string',
                'min:8', // Minimum 8 caractères
                'confirmed', // La contraseña debe ser confirmada
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@#?!+_%$&]).{8,}$/'
            ]
        ], $messages);

        // Création de l'utilisateur dans la base de données
        User::create([
            'name'          => $request->username,
            'email'         => $request->email,
            'role_id'       => $request->role,
            'site_id'       => $request->site,
            'company_id'    => $request->company_id,
            'type'          => $request->type,
            'password'      => Hash::make($request->password), // Hacher le mot de passe
            'active'        => $request->active,
            'lifetime'      => $request->lifetime,
            'secret_pin'    => $request->clock_pin
        ]);

        return redirect()->route('usersList')->with('success', __('messages/controller.admin.user.create.success'));
    }

    public function userEdit($id)
    {
        $user = User::findOrFail($id);
        $roles = Role::all();
        $sites = Site::all();
        $companies = Company::all();
        $userTypes = UserType::all();

        return view('users.edit', compact('user', 'roles', 'sites', 'companies', 'userTypes'));
    }

    public function userUpdate(Request $request, $id)
    {
        $request->validate([
            'username'          => 'required|string|max:255',
            'site_id'           => 'required|int',
            'email'             => 'required|email|max:50',
            'role_id'           => 'required|int',
            'lifetime'          => 'required|int|min:5',
            'clock_pin'         => [
                'required',
                'string',
                Rule::unique('users', 'secret_pin')->ignore($id),
            ],
            'company_id'        => 'nullable|int|exists:companies,id',
            'type'              => 'required|string|exists:user_types,code',
            'password'          => [
                'nullable',
                'string',
                'min:8', 
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@#?!+_%$&]).{8,}$/'
            ],
        ]);

        $user = User::findOrFail($id);
        $user->update([
            'name'              => $request->username,
            'email'             => $request->email,
            'role_id'           => $request->role_id,
            'site_id'           => $request->site_id,
            'company_id'        => $request->company_id,
            'type'              => $request->type,
            'password'          => $request->password ? Hash::make($request->password) : $user->password,
            'active'            => $request->active,
            'lifetime'          => $request->lifetime,
            'secret_pin'        => $request->clock_pin
        ]);

        if (Auth::user()->email == $request->email) {
            Session::put('expired_at', now($request->timezone)->addMinutes((int) $request->lifetime));
            Session::put('timezone', $request->timezone);
        }

        return redirect()->route('usersList')->with('success', __('messages/controller.admin.user.update.success'));
    }

    public function userDelete(Request $request)
    {
        $user_id = $request->user_id;
    
        if (!$user_id) {
            return redirect()->route('userList')->with('error',  __('messages/controller.admin.user.error.id_missing_deletion'));
        }
    
        $user = User::find($user_id);
    
        if (!$user) {
            return redirect()->route('userList')->with('error', __('messages/controller.admin.user.error.reference_not_found'));
        }

        // Vérifier si le fournisseur est utilisé dans packaging_types
        //$isUsedInPackagingTypes = PackagingOrder::where('provider_id', $user_id)->exists();

        if (PackagingOrder::where('packed_by_user', $user_id)->exists()) {
            return redirect()->route('userList')->with(
                'error',
                __('messages/controller.admin.packaging_vendor.error.referenced')
            );
        }
    
        $user->update(['active' => 0]);
    
        return redirect()->route('usersList')->with('success', __('messages/controller.admin.user.delete.success'));
    }

    public function userDetails($id)
    {
        try {
            $user = User::findOrFail($id);
            return view('users.details', compact('user'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('usersList')->with('error', __('messages/controller.admin.user.details.not_found'));
        }
    }

    public function getUsers()
    {
            return User::All();
    }

}
