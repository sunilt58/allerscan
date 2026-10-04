<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AllergenResource;
use App\Http\Resources\ProductResource;
use App\Models\Allergen;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function allergens(): AnonymousResourceCollection
    {
        return AllergenResource::collection(Allergen::orderBy('id')->get());
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['Food', 'Drinks', 'Snacks', 'Daily'])],
        ]);
        $search = trim($filters['q'] ?? '');

        return ProductResource::collection(
            Product::with('allergens')->active()
                ->when($search !== '', fn ($query) => $query->matching($search))
                ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
                ->orderBy('id')->paginate(20)->withQueryString(),
        );
    }

    public function barcode(string $barcode): ProductResource|JsonResponse
    {
        $product = Product::with('allergens')->active()->where('barcode', $barcode)->first();

        return $product
            ? new ProductResource($product)
            : response()->json(['message' => __('Product not found in our catalog. Try searching by name.')], 404);
    }

    public function show(Product $product): ProductResource
    {
        abort_unless($product->is_active, 404);

        return new ProductResource($product->load('allergens'));
    }
}
