<?php

namespace App\Http\Controllers;

use App\Models\Allergen;
use App\Models\Product;
use App\Models\Suggestion;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    public function store(Request $request): RedirectResponse
    {
        $type = $request->validate(['type' => ['required', Rule::in(['product', 'allergen'])]])['type'];
        $validated = $request->validate($type === 'product' ? [
            'barcode' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]+$/'],
            'name_ja' => ['nullable', 'required_without:name_en', 'string', 'max:255'],
            'name_en' => ['nullable', 'required_without:name_ja', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['Food', 'Drinks', 'Snacks', 'Daily'])],
            'allergen_codes' => ['sometimes', 'array', 'max:100'],
            'allergen_codes.*' => ['string', 'distinct', 'exists:allergens,code'],
            'note' => ['nullable', 'string', 'max:500'],
        ] : [
            'name_ja' => ['nullable', 'required_without:name_en', 'string', 'max:100'],
            'name_en' => ['nullable', 'required_without:name_ja', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $user = $request->user();
        if ($type === 'product' && Product::where('barcode', $validated['barcode'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['barcode' => __('This product is already in our catalog. Try looking it up on the Scan page.')]);
        }
        if ($user->suggestions()->pending()->count() >= Suggestion::PENDING_LIMIT_PER_USER) {
            throw ValidationException::withMessages(['type' => __('You have many suggestions waiting for review. Please wait until our team has looked at them.')]);
        }
        $user->suggestions()->create($validated + ['type' => $type, 'status' => 'pending']);

        return redirect()->route('suggest')->with('account_message', __('Thank you. Our team will review your suggestion.'));
    }
}
