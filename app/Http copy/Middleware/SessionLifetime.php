<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class SessionLifetime
{
    public function handle($request, Closure $next)
    {
        $expired_at = Session::get('expired_at');
        $timezone = Session::get('timezone');
        $lifetime = Session::get('lifetime') ?? 15;
        
        if ($timezone && $expired_at && now($timezone)->greaterThan($expired_at)) {
            Auth::logout();
            Session::flush();

            return redirect('/login')->with('message', __('messages.middleware.session.error.expired'));
        }

        Session::put('expired_at', now($timezone)->addMinutes($lifetime));

        return $next($request);
    }
}