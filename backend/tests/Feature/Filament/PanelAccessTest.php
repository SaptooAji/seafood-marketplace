<?php

declare(strict_types=1);

dataset('panels', [
    'admin panel' => ['admin'],
    'seller panel' => ['seller'],
]);

it('shows the login page', function (string $panel) {
    $this->get("/{$panel}/login")->assertOk();
})->with('panels');

it('redirects guests to the login page of the same panel', function (string $panel) {
    $this->get("/{$panel}")->assertRedirect("/{$panel}/login");
})->with('panels');

it('gives each panel its own login URL', function () {
    expect(route('filament.admin.auth.login'))
        ->toEndWith('/admin/login')
        ->not->toBe(route('filament.seller.auth.login'));
});
