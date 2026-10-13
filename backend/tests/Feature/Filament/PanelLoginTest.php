<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Enums\StoreRole;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * AUTH-3: a failed panel sign-in never reveals whether the email exists, has
 * a password, or may use the panel.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    // Allowed in both panels: an admin who is also an active store's owner.
    $this->member = User::factory()->create(['email' => 'budi@mail.com', 'password' => 'rahasia123']);
    $this->member->assignRole(PlatformRole::Admin);
    Store::factory()->active()->create()->users()->attach($this->member, ['role' => StoreRole::Owner->value]);

    User::factory()->create(['email' => 'tamu@mail.com', 'password' => 'rahasia123']);
    User::factory()->withoutPassword()->create(['email' => 'sari@gmail.com']);
});

/**
 * The error shown on the email field after signing in to the current panel.
 */
function panelLoginError(string $email, string $password): string
{
    return Livewire::test(Login::class)
        ->fillForm(['email' => $email, 'password' => $password])
        ->call('authenticate')
        ->assertHasFormErrors(['email'])
        ->errors()
        ->first('data.email');
}

it('shows the same message for every failed sign-in', function (string $panel) {
    Filament::setCurrentPanel($panel);

    $errors = [
        'unknown email' => panelLoginError('tidakada@mail.com', 'rahasia123'),
        'wrong password' => panelLoginError('budi@mail.com', 'salah12345'),
        'Google user without a password' => panelLoginError('sari@gmail.com', 'apasaja123'),
        'right password, no access to the panel' => panelLoginError('tamu@mail.com', 'rahasia123'),
    ];

    expect(array_unique($errors))->toHaveCount(1)
        ->and($errors['unknown email'])->not->toContain('tidakada@mail.com');

    $this->assertGuest();
})->with(['seller', 'admin']);
