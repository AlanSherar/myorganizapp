<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Site;
use App\Models\User;
use App\Models\ClockTime;
use App\Helpers\LogHelper;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\ClockTimeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;


class AuthController extends Controller
{
    protected $clockTimeService;

    public function __construct(ClockTimeService $_clockTimeService)
    {
        $this->clockTimeService = $_clockTimeService;
    }

    public function loginForm()
    {
        //Session::flush();
        if(Auth::check()){
            return redirect()->route('index');
        }

        return view('layouts.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6'
        ]);

        // Vérifier si l'utilisateur existe avec le username (email ou autre)
        $user = User::with('site')->where('email', $request->email)->first();

        if (!$user) {
            return redirect()->route('loginForm')->with('error', __('messages/controller.login.error.user_not_found'));
        }

        /*if ($user->connected == 1) {
            return redirect()->route('loginForm')->with('error', 'User is already connected');
        }*/

        if ($user->active == 0) {
            return redirect()->route('loginForm')->with('error', __('messages/controller.login.error.user_not_active'));
        }

        // Vérifier si le mot de passe correspond
        if (Hash::check($request->password, $user->password)) {
            Auth::login($user);
            LogHelper::handleLog('login');
            $user->update([
                'connected' => 1,
                'last_login_at' => user_local_time()
            ]);

            // Get site based on site_id
            $site = $user->site;
                        
            // SET LIFETIME USER'S SESSION
            $lifetime = (int) $user->lifetime;
            $timezone = $site->timezone;

            if (!Session::has('expired_at')) {
                Session::put('expired_at', now($timezone)->addMinutes($lifetime));
                Session::put('timezone', $timezone);
                Session::put('lifetime', $lifetime);
            }

            // SET LANGUAGE USER'S SESSION
            $locale = $user->language ?? $site->language ?? config('app.locale') ;

            session()->put('locale', $locale);
            config(['app.locale' => $locale]);

            // CLOCK TIME USER'S SESSION
            $clock_time_status = $this->clockTimeService->getClockStatus($user);

            session()->put('clock_time_status', $clock_time_status);

            if($user->role_id == 1 || $user->role_id == 4){
                return redirect()->route('dashboardIndicator')->with('success',  __('messages/controller.login.success'));
            }
            
            return redirect()->route('getAcctivateOrders')->with('success',  __('messages/controller.login.success'));
        }

        // Si le mot de passe est incorrect
        return redirect()->route('loginForm')->with('error', __('messages/controller.login.failed'));
    }

    // Méthode pour gérer la déconnexion
    public function logout()
    {
        try {
            $user = Auth::user();

            if ($user) {
                // Recharger l'utilisateur depuis la base (optionnel mais OK)
                $user = User::find($user->id);

                if ($user) {
                    LogHelper::handleLog('logout', '', '', 'conexion', $user->name);

                    $user->connected = 0;
                    $user->save();
                }
            }
        } catch (\Throwable $e) {
            
        } finally {
            // Toujours exécuter le logout, même en cas d'erreur
            Session::flush();
            Auth::logout();
        }

        return redirect()->route('loginForm')->with('success', __('messages/controller.logout.success'));
    }

    public function sendTokenForm()
    {
        try {
            return view('layouts.send-token');
        } catch (\Throwable $th) {
            return redirect()->route('loginForm')->with('error', __('messages/controller.token.send.failed'));
        }
    }

    public function sendToken(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email|exists:users,email',
            ]);

            // Générer un token unique
            $token = Str::random(60);

            // Supprimer les anciens tokens pour cet email
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            // Stocker le token en base (hashé pour plus de sécurité)
            DB::table('password_reset_tokens')->insert([
                'email' => $request->email,
                'token' => $token,
                'created_at' => now(),
            ]);

            // Construire le lien de réinitialisation
            $resetLink = url('/reset-password', ['token' => $token]);

            // Envoyer l'email
            Mail::send([], [], function ($message) use ($request, $resetLink) {
                $message->to($request->email)
                    ->subject(__('messages/controller.password.reset.email.subject'))
                    ->html(__('messages/controller.password.reset.email.html', ['resetLink' => $resetLink]));
            });


            return redirect()->route('loginForm')->with('success', __('messages/controller.password.reset.link_sent'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function resetPassword(Request $request, $token)
    {
        try {
            // Vérification si le token est vide
            if (!$token) {
                return redirect()->route('sendTokenForm')->with('warning', __('messages/controller.token.missing'));
            }


            // Récupérer l'email et le token haché de la base de données
            $emailRecord = DB::table('password_reset_tokens')->where('token', $token)->first();

            // Vérifier si le token existe et s'il correspond au token haché
            if (!$emailRecord->email) {
                return redirect()->route('sendTokenForm')->with('error', __('messages/controller.token.invalid'));
            }

            // Passer l'email à la vue de réinitialisation du mot de passe
            return view('layouts.reset-password', ['email' => $emailRecord->email]);
        } catch (\Throwable $th) {
            return redirect()->route('loginForm')->with('error', __('messages/controller.login.error'));
        }
    }

    public function updatePassword(Request $request)
    {
        $messages = [
            'password.regex' => __('messages/controller.password.requirements'),
        ];

        $request->validate([
            'email' => 'required|email',
            'password'          => [
                'required',
                'string',
                'min:8', // Minimum 8 caractères
                'confirmed', // Le mot de passe doit être confirmé
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@#?!+_%$&]).{8,}$/'
            ],
        ], $messages);

        try {
            // Vérifier si le token est valide
            $resetRequest = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$resetRequest) {
                return redirect()->route('login')->with('error', __('messages/controller.token.invalid'));
            }

            // Mettre à jour le mot de passe de l'utilisateur
            User::where('email', $request->email)->update([
                'password' => Hash::make($request->password),
            ]);

            // Supprimer l'entrée de la table password_reset_tokens
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return redirect()->route('login')->with('success', __('messages/controller.password.reset-success'));
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }
}