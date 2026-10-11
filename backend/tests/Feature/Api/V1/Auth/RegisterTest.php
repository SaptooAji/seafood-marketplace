<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * @param  array<string, mixed>  $overrides  Use null to drop a field from the payload.
 * @return array<string, mixed>
 */
function registerPayload(array $overrides = []): array
{
    return array_filter(array_merge([
        'name' => 'Budi Santoso',
        'email' => 'budi@mail.com',
        'phone' => '081234567890',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
        'device_name' => 'Pixel 8',
    ], $overrides), fn (mixed $value) => $value !== null);
}

it('registers a user and returns a token with the user', function () {
    $response = $this->postJson('/api/v1/auth/register', registerPayload())
        ->assertCreated()
        ->assertExactJsonStructure(['token', 'user' => ['id', 'name', 'email', 'phone', 'avatar']])
        ->assertJsonPath('user.email', 'budi@mail.com')
        ->assertJsonPath('user.phone', '081234567890');

    $user = User::query()->sole();

    expect($user->password)->not->toBe('rahasia123')
        ->and(Hash::check('rahasia123', $user->password))->toBeTrue()
        ->and($user->tokens()->sole()->name)->toBe('Pixel 8')
        ->and($response->json('user.id'))->toBe($user->id);
});

it('returns a token that works for GET /me', function () {
    $token = $this->postJson('/api/v1/auth/register', registerPayload())->json('token');

    $this->withToken($token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'budi@mail.com');
});

it('stores the email in lower case', function () {
    $this->postJson('/api/v1/auth/register', registerPayload(['email' => 'Budi@Mail.com']))
        ->assertCreated()
        ->assertJsonPath('user.email', 'budi@mail.com');

    expect(User::query()->sole()->email)->toBe('budi@mail.com');
});

it('accepts Indonesian mobile numbers', function (string $phone) {
    $this->postJson('/api/v1/auth/register', registerPayload(['phone' => $phone]))
        ->assertCreated();
})->with([
    '08 prefix' => '081234567890',
    '+628 prefix' => '+6281234567890',
]);

it('rejects an email that is already registered', function (string $email) {
    User::factory()->create(['email' => 'budi@mail.com']);

    $this->postJson('/api/v1/auth/register', registerPayload(['email' => $email]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect(User::query()->count())->toBe(1);
})->with([
    'same case' => 'budi@mail.com',
    'different case' => 'BUDI@MAIL.COM',
]);

it('rejects invalid registration data', function (array $overrides, string $field) {
    $this->postJson('/api/v1/auth/register', registerPayload($overrides))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(User::query()->count())->toBe(0);
})->with([
    'password of 7 characters' => [['password' => '1234567', 'password_confirmation' => '1234567'], 'password'],
    'password confirmation mismatch' => [['password_confirmation' => 'berbeda123'], 'password'],
    'phone with letters' => [['phone' => 'abc'], 'phone'],
    'phone too short' => [['phone' => '12345'], 'phone'],
    'phone with 9 digits' => [['phone' => '081234567'], 'phone'],
    'phone with 16 digits' => [['phone' => '0812345678901234'], 'phone'],
    'landline number' => [['phone' => '0215551234'], 'phone'],
    'missing phone' => [['phone' => null], 'phone'],
    'missing device name' => [['device_name' => null], 'device_name'],
]);
