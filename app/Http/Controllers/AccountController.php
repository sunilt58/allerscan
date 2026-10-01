<?php

namespace App\Http\Controllers;

use App\Models\Allergen;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public const SAVED_PRODUCT_LIMIT = 50;

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'consent' => ['accepted'],
        ]);
        $user = new User;
        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => 'shopper',
        ])->save();
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('settings')->with('merge_preferences', true)
            ->with('account_message', __('Your account is ready. Your allergens and saved products now sync across your devices.'));
    }

    /**
     * Replace the shopper's synced allergens and saved products with the browser's lists.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'allergens' => ['present', 'array', 'max:100'],
            'allergens.*' => ['string', 'max:50'],
            'savedProducts' => ['present', 'array', 'max:'.self::SAVED_PRODUCT_LIMIT],
            'savedProducts.*' => ['integer', 'min:1'],
        ]);
        $user = $request->user();
        DB::transaction(function () use ($user, $validated) {
            // Unknown codes and deleted products are dropped rather than rejected, so an old browser list still saves.
            $user->allergens()->sync(Allergen::whereIn('code', $validated['allergens'])->pluck('id'));
            $user->savedProducts()->sync(Product::whereIn('id', $validated['savedProducts'])->pluck('id'));
        });

        return response()->json($user->shopperPreferences());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user->isAdmin(), 403, __('Team accounts are removed by another team member.'));
        $request->validateWithBag('accountDeletion', ['password' => ['required', 'current_password']]);
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('discover')->with('signed_out', true)
            ->with('account_message', __('Your account and its saved information were deleted.'));
    }
}
