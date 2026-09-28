<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
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
    Route::get('/settings', fn () => view('settings', ['allergens' => Allergen::all()]))->name('settings');
});

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function () {
    Route::get('/admin/products', function () {
        abort_unless(auth()->user()->role === 'admin', 403);

        return view('products');
    })->name('products');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
});
