<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
| API routes for the Android app. Every route here is served under /api/v1
| (apiPrefix in bootstrap/app.php) and always responds with JSON.
*/

Route::post('auth/register', [AuthController::class, 'register'])->name('api.auth.register');
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('api.auth.login');
Route::post('auth/google', [AuthController::class, 'google'])->middleware('throttle:google-login')->name('api.auth.google');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::get('me', [AuthController::class, 'me'])->name('api.me');
});
