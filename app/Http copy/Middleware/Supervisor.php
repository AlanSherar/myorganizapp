<?php

namespace App\Http\Middleware;

use AuthHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Supervisor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
       
        // if(Auth::user()->role_id == 4 || Auth::user()->role_id == 1) {
        //     return $next($request);
        // }

        if (!AuthHelper::authType(['OWNER']) || !AuthHelper::authRoleByName(['admin', 'supervisor'])) {
            
            if (AuthHelper::authType(['CLIENT'])) {
                return redirect()->route('salesOrderList')->with('error', __('messages.middleware.auth.error.unauthorized'));
            }

            return redirect()->route('index')->with('error', __('messages.middleware.auth.error.unauthorized'));
        }

        return $next($request);
    }
}
