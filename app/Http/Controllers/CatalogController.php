<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\UnregisteredProductLookup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['Food', 'Drinks', 'Snacks', 'Daily'])],
        ]);
        $search = trim($filters['q'] ?? '');
        $category = $filters['category'] ?? '';
        $products = Product::with('allergens')->active()
            ->when($search !== '', fn ($query) => $query->matching($search))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->orderBy('id')->paginate(12)->withQueryString();

        return view('discover', compact('products', 'search', 'category'));
    }

    /**
     * A barcode that is not in the catalog starts AI research, when it is turned on and within today's limits.
     * Archived products are not researched: the team took them out on purpose.
     */
    public function lookup(Request $request, UnregisteredProductLookup $lookups): RedirectResponse
    {
        $validated = $request->validate(['barcode' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]+$/']]);
        $product = Product::where('is_active', true)->where('barcode', $validated['barcode'])->first();
        if (! $product && ! Product::where('barcode', $validated['barcode'])->exists() && $lookups->lookupFor($validated['barcode'], $request)) {
            return redirect()->route('scan.pending', ['barcode' => $validated['barcode']]);
        }
        if (! $product) {
            return redirect()->route('scan')->withInput()->withErrors([
                'barcode' => __('Product not found in our catalog. Try searching by name.'),
            ]);
        }

        return redirect()->route('catalog.show', $product);
    }

    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        return view('product', ['product' => $product->load('allergens')]);
    }

    public function saved(Request $request): View
    {
        $validated = $request->validate([
            'ids' => ['sometimes', 'array', 'max:50'],
            'ids.*' => ['integer', 'min:1', 'distinct'],
        ]);
        $products = Product::with('allergens')->where('is_active', true)
            ->whereIn('id', $validated['ids'] ?? [])->orderBy('id')->get();

        return view('partials.saved-products', compact('products'));
    }
}
