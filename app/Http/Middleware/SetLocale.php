<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Use the visitor's chosen language from the locale cookie, falling back to the default (Japanese).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->cookie('locale');

        app()->setLocale(in_array($locale, config('app.supported_locales'), true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
