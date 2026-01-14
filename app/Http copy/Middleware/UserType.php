<?php

namespace App\Http\Middleware;

use AuthHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class UserType
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$types): Response
    {

        if(AuthHelper::authType(['CLIENT']) && !in_array('CLIENT', $types)) {
            return redirect()->route('saleOrdersList');
        }

        if(!AuthHelper::authType($types)) {
            
            return redirect()->route('index')->with('error', __('messages.middleware.auth.error.unauthorized'));
        }

        // if(!AuthHelper::authType(['OWNER'])) {
        //     return redirect()->route('index')->with('error', __('messages.middleware.auth.error.unauthorized'));
        // }

        return $next($request);
    }
}
