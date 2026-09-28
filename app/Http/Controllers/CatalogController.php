<?php

namespace App\Http\Controllers;

use App\Models\Product;
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
        $products = Product::with('allergens')->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name_ja', 'like', '%'.$search.'%')
                        ->orWhere('name_en', 'like', '%'.$search.'%')
                        ->orWhere('barcode', $search);
                });
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->orderBy('id')->paginate(12)->withQueryString();

        return view('discover', compact('products', 'search', 'category'));
    }

    public function lookup(Request $request): RedirectResponse
    {
        $validated = $request->validate(['barcode' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9-]+$/']]);
        $product = Product::where('is_active', true)->where('barcode', $validated['barcode'])->first();
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
