<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('Incorrect email or password.')]);
        }
        $request->session()->regenerate();

        // The browser's allergens and saved products are merged into the account on the next page.
        return redirect()->intended(route('discover'))->with('merge_preferences', true);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Tells the browser to forget the account's allergens and saved products on a shared device.
        return redirect()->route('login')->with('signed_out', true);
    }
}
