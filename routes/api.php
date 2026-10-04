<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\SuggestionController;
use Illuminate\Support\Facades\Route;

/*
 * Smartphone app API. Responses are JSON; send `Accept: application/json` and, for messages in English,
 * `Accept-Language: en` (Japanese is the default). Signed-in requests send `Authorization: Bearer <token>`.
 */
Route::prefix('v1')->name('api.v1.')->middleware('throttle:60,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login');

    Route::get('/allergens', [CatalogController::class, 'allergens'])->name('allergens');
    Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
    Route::get('/products/barcode/{barcode}', [CatalogController::class, 'barcode'])->where('barcode', '[A-Za-z0-9-]{1,50}')->name('products.barcode');
    Route::get('/products/{product}', [CatalogController::class, 'show'])->whereNumber('product')->name('products.show');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/me', [AccountController::class, 'show'])->name('me');
        Route::put('/me/preferences', [AccountController::class, 'updatePreferences'])->name('me.preferences');
        Route::get('/me/saved-products', [AccountController::class, 'savedProducts'])->name('me.saved-products');
        Route::delete('/me', [AccountController::class, 'destroy'])->name('me.destroy');
        Route::get('/me/suggestions', [SuggestionController::class, 'index'])->name('me.suggestions');
        Route::post('/suggestions', [SuggestionController::class, 'store'])->middleware('throttle:10,1')->name('suggestions.store');
    });
});
