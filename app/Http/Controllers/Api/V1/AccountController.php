<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AccountController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Replace the account's allergens and saved products with the app's lists.
     */
    public function updatePreferences(Request $request): UserResource
    {
        $validated = $request->validate([
            'allergens' => ['present', 'array', 'max:100'],
            'allergens.*' => ['string', 'max:50'],
            'saved_products' => ['present', 'array', 'max:'.User::SAVED_PRODUCT_LIMIT],
            'saved_products.*' => ['integer', 'min:1'],
        ]);
        $request->user()->syncShopperPreferences($validated['allergens'], $validated['saved_products']);

        return new UserResource($request->user());
    }

    public function savedProducts(Request $request): AnonymousResourceCollection
    {
        return ProductResource::collection(
            $request->user()->savedProducts()->with('allergens')->active()->orderBy('products.id')->get(),
        );
    }

    public function destroy(Request $request): Response
    {
        $user = $request->user();
        abort_if($user->isAdmin(), 403, __('Team accounts are removed by another team member.'));
        $request->validate(['password' => ['required', 'current_password:sanctum']]);
        $user->delete();

        return response()->noContent();
    }
}
