<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleLoginController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::get('/masuk', [GoogleLoginController::class, 'redirectToGoogle'])->name('login');
Route::get('/auth/google/callback', [GoogleLoginController::class, 'handleGoogleCallback'])->name('auth.google.callback');
