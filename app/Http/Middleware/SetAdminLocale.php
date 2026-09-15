<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Filament ships Serbian as sr_Latn / sr_Cyrl while the app uses "sr" — map it. */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->getLocale() === 'sr') {
            app()->setLocale('sr_Latn');
        }

        return $next($request);
    }
}
