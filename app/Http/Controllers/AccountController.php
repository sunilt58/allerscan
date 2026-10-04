<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterAccountRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function store(RegisterAccountRequest $request): RedirectResponse
    {
        $user = User::createShopper($request->validated());
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
            'savedProducts' => ['present', 'array', 'max:'.User::SAVED_PRODUCT_LIMIT],
            'savedProducts.*' => ['integer', 'min:1'],
        ]);
        $user = $request->user();
        $user->syncShopperPreferences($validated['allergens'], $validated['savedProducts']);

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
