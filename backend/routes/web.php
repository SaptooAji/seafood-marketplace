<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Web\HomeController;
use App\Livewire\Catalog;
use App\Livewire\ProductPage;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::livewire('/produk', Catalog::class)->name('catalog');
Route::livewire('/produk/{slug}', ProductPage::class)->name('products.show');

Route::get('/masuk', [GoogleLoginController::class, 'redirectToGoogle'])->name('login');
Route::get('/auth/google/callback', [GoogleLoginController::class, 'handleGoogleCallback'])->name('auth.google.callback');
