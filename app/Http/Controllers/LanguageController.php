<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class LanguageController extends Controller
{
    /**
     * Remember the chosen display language and return to the previous page.
     */
    public function __invoke(string $locale): RedirectResponse
    {
        return redirect()->back(fallback: route('discover'))
            ->withCookie(cookie()->forever('locale', $locale, httpOnly: false));
    }
}
