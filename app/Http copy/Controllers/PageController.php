<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use \App\Models\User;

class PageController extends Controller
{

    public function __construct()
    {
    }

    public function index()
    {
        try {
            if (!Auth::check()) {
                // Si l'utilisateur n'est pas connecté, redirige vers le formulaire de connexion
                return redirect()->route('loginForm');
            }
            
            // Si l'utilisateur est connecté, on vérifie son rôle
            if (Auth::user()->role_id == "1" || Auth::user()->role_id == "4") {
                return redirect()->route('dashboardIndicator');
            } 

            return redirect()->route('getAcctivateOrders');
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
        
    public function setLanguage(Request $request)
    {
        try {
            // Récupérer la langue du formulaire
            $locale = $request->input('locale');
            
            if (!in_array($locale, ['en', 'es'])) {
                abort(400);
            }

            // Guardar en la sesión
            session(['locale' => $locale]);
            // Save in the users language  

            $user = Auth::user();
            $user = User::find($user->id);

            //$user->update(['language' => $locale]);
            $user->language = $locale;
            $user->save();

            return redirect()->back()->with('success', __($locale));
        } catch (\Throwable $th) {
            dd($th->getMessage());
        }
    }
}
