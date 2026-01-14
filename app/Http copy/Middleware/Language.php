<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class Language
{
    public function handle($request, Closure $next)
    {
        $locale = Session::get('locale', config('app.locale'));

        if (!in_array($locale, ['en', 'es'])) {
            $locale = config('app.locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
