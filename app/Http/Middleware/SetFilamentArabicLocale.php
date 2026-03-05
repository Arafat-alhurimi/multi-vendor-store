<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetFilamentArabicLocale
{
    public function handle(Request $request, Closure $next)
    {
        app()->setLocale(config('app.locale', 'ar'));

        return $next($request);
    }
}
