<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\SuggestionController;
use App\Models\Allergen;
use Illuminate\Support\Facades\Route;

Route::middleware('cache.headers:no_store;private')->group(function () {
    Route::get('/', [CatalogController::class, 'index'])->name('discover');
    Route::view('/scan', 'scan')->name('scan');
    Route::get('/scan/lookup', [CatalogController::class, 'lookup'])->name('scan.lookup');
    Route::get('/item/{product}', [CatalogController::class, 'show'])->name('catalog.show');
    Route::view('/saved', 'saved')->name('saved');
    Route::get('/saved/items', [CatalogController::class, 'saved'])->name('saved.items');
    Route::view('/privacy', 'privacy')->name('privacy');
    Route::get('/suggest', [SuggestionController::class, 'index'])->name('suggest');
    Route::get('/settings', fn () => view('settings', ['allergens' => Allergen::all()]))->name('settings');
});

Route::get('/language/{locale}', LanguageController::class)->whereIn('locale', config('app.supported_locales'))->name('language');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AccountController::class, 'store'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function () {
    Route::middleware('can:manage-catalog')->prefix('admin')->group(function () {
        Route::view('/products', 'admin.products')->name('products');
        Route::view('/suggestions', 'admin.suggestions')->name('admin.suggestions');
    });
    Route::post('/suggest', [SuggestionController::class, 'store'])->middleware('throttle:10,1')->name('suggest.store');
    Route::put('/account/preferences', [AccountController::class, 'updatePreferences'])->middleware('throttle:60,1')->name('account.preferences');
    Route::delete('/account', [AccountController::class, 'destroy'])->middleware('throttle:10,1')->name('account.destroy');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
