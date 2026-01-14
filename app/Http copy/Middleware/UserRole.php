<?php

namespace App\Http\Middleware;

use AuthHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {

        // if(AuthHelper::authType(['CLIENT']) && !in_array('CLIENT', $types)) {
        //     return redirect()->route('saleOrdersList');
        // }

        if(!AuthHelper::authRoleByName($roles)) {
            
            return redirect()->route('index')->with('error', __('messages.middleware.auth.error.unauthorized'));
        }

        // if(!AuthHelper::authType(['OWNER'])) {
        //     return redirect()->route('index')->with('error', __('messages.middleware.auth.error.unauthorized'));
        // }

        return $next($request);
    }
}
