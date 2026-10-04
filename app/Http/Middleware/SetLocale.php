<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Use the visitor's chosen language from the locale cookie, or for the app API the Accept-Language header,
     * falling back to the default (Japanese).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->is('api/*')
            ? ($request->hasHeader('Accept-Language') ? $request->getPreferredLanguage(config('app.supported_locales')) : null)
            : $request->cookie('locale');

        app()->setLocale(in_array($locale, config('app.supported_locales'), true) ? $locale : config('app.locale'));

        return $next($request);
    }
}
