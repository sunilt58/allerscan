<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSuggestionRequest;
use App\Models\Allergen;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SuggestionController extends Controller
{
    public function index(Request $request): View
    {
        return view('suggest', [
            'allergens' => Allergen::all(),
            'suggestions' => $request->user()?->suggestions()->with('product')->latest('id')->limit(30)->get() ?? collect(),
            'barcode' => preg_match('/^[A-Za-z0-9-]{1,50}$/', (string) $request->query('barcode')) ? $request->query('barcode') : '',
        ]);
    }

    public function store(StoreSuggestionRequest $request): RedirectResponse
    {
        $request->user()->suggestions()->create($request->suggestion());

        return redirect()->route('suggest')->with('account_message', __('Thank you. Our team will review your suggestion.'));
    }
}
