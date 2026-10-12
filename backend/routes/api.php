<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\Seller\ProductController;
use App\Http\Controllers\Api\V1\Seller\ShippingMethodController;
use App\Http\Controllers\Api\V1\Seller\StockMovementController;
use App\Http\Controllers\Api\V1\Seller\StoreController;
use Illuminate\Support\Facades\Route;

/*
| API routes for the Android app. Every route here is served under /api/v1
| (apiPrefix in bootstrap/app.php) and always responds with JSON.
*/

Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:register')->name('api.auth.register');
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('api.auth.login');
Route::post('auth/google', [AuthController::class, 'google'])->middleware('throttle:google-login')->name('api.auth.google');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::get('me', [AuthController::class, 'me'])->name('api.me');

    Route::get('categories', CategoryController::class)->name('api.categories.index');

    Route::post('seller/store', [StoreController::class, 'store'])->name('api.seller.store.store');
    Route::get('seller/store', [StoreController::class, 'show'])->name('api.seller.store.show');
    Route::put('seller/store', [StoreController::class, 'update'])->name('api.seller.store.update');

    Route::get('seller/shipping-methods', [ShippingMethodController::class, 'index'])->name('api.seller.shipping-methods.index');
    Route::post('seller/shipping-methods', [ShippingMethodController::class, 'store'])->name('api.seller.shipping-methods.store');
    Route::put('seller/shipping-methods/{shippingMethod}', [ShippingMethodController::class, 'update'])
        ->whereNumber('shippingMethod')
        ->name('api.seller.shipping-methods.update');

    Route::apiResource('seller/products', ProductController::class)
        ->names('api.seller.products')
        ->whereNumber('product');

    Route::get('seller/stock-movements', [StockMovementController::class, 'index'])->name('api.seller.stock-movements.index');
    Route::post('seller/stock-movements', [StockMovementController::class, 'store'])->name('api.seller.stock-movements.store');
});
