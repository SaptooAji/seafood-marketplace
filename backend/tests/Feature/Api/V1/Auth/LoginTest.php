<?php

declare(strict_types=1);

use App\Models\User;

/**
 * @param  array<string, mixed>  $overrides  Use null to drop a field from the payload.
 * @return array<string, mixed>
 */
function loginPayload(array $overrides = []): array
{
    return array_filter(array_merge([
        'email' => 'budi@mail.com',
        'password' => 'rahasia123',
        'device_name' => 'Pixel 8',
    ], $overrides), fn (mixed $value) => $value !== null);
}

beforeEach(function () {
    $this->user = User::factory()->create([
        'email' => 'budi@mail.com',
        'password' => 'rahasia123',
    ]);
});

it('returns a new token with the user for valid credentials', function () {
    $this->postJson('/api/v1/auth/login', loginPayload())
        ->assertOk()
        ->assertExactJsonStructure(['token', 'user' => ['id', 'name', 'email', 'phone', 'avatar']])
        ->assertJsonPath('user.id', $this->user->id);

    expect($this->user->tokens()->sole()->name)->toBe('Pixel 8');
});

it('matches the email regardless of letter case', function () {
    $this->postJson('/api/v1/auth/login', loginPayload(['email' => 'Budi@Mail.com']))
        ->assertOk()
        ->assertJsonPath('user.id', $this->user->id);
});

it('rejects a wrong password on the email field', function () {
    $this->postJson('/api/v1/auth/login', loginPayload(['password' => 'salah12345']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect($this->user->tokens()->count())->toBe(0);
});

it('rejects a Google user without a password with the same message as a wrong password', function () {
    User::factory()->withoutPassword()->create(['email' => 'sari@gmail.com']);

    $wrongPassword = $this->postJson('/api/v1/auth/login', loginPayload(['password' => 'salah12345']))
        ->assertUnprocessable()
        ->json('errors.email.0');

    $this->postJson('/api/v1/auth/login', loginPayload(['email' => 'sari@gmail.com', 'password' => 'apasaja123']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', $wrongPassword);
});

it('rejects a login without a device name', function () {
    $this->postJson('/api/v1/auth/login', loginPayload(['device_name' => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('device_name');
});

it('blocks the sixth login attempt within a minute', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', loginPayload(['password' => 'salah12345']))
            ->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', loginPayload())
        ->assertTooManyRequests();
});

it('limits login attempts per email, not for every email on the same IP', function () {
    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/login', loginPayload(['password' => 'salah12345']));
    }

    $this->postJson('/api/v1/auth/login', loginPayload(['email' => 'lain@mail.com']))
        ->assertUnprocessable();
});

it('answers an unknown email, a wrong password, and a Google user without a password identically', function () {
    User::factory()->withoutPassword()->create(['email' => 'sari@gmail.com']);

    $responses = collect([
        'unknown email' => loginPayload(['email' => 'tidakada@mail.com']),
        'wrong password' => loginPayload(['password' => 'salah12345']),
        'Google user without a password' => loginPayload(['email' => 'sari@gmail.com', 'password' => 'apasaja123']),
    ])->map(fn (array $payload) => $this->postJson('/api/v1/auth/login', $payload));

    $responses->each(fn ($response) => $response->assertUnprocessable());

    expect($responses->map(fn ($response) => $response->json())->unique()->all())->toHaveCount(1)
        ->and($responses['unknown email']->json('errors.email.0'))->toBe(__('auth.failed'));
});
