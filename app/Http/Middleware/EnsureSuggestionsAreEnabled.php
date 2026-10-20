<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuggestionsAreEnabled
{
    /**
     * Hide the suggestion box pages and API while the feature is switched off.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('allerscan.suggestions'), 404);

        return $next($request);
    }
}
