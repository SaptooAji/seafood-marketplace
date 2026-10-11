<?php

declare(strict_types=1);

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

const TOKENINFO_URL = 'https://oauth2.googleapis.com/tokeninfo*';
const WEB_CLIENT_ID = 'web-client-id.apps.googleusercontent.com';

/**
 * A tokeninfo response as Google sends it: every value is a string.
 *
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function tokenInfo(array $overrides = []): array
{
    return array_merge([
        'iss' => 'https://accounts.google.com',
        'aud' => WEB_CLIENT_ID,
        'azp' => 'android-client-id.apps.googleusercontent.com',
        'sub' => '109876543210987654321',
        'email' => 'budi@gmail.com',
        'email_verified' => 'true',
        'name' => 'Budi Santoso',
        'picture' => 'https://lh3.googleusercontent.com/a/budi.jpg',
        'iat' => (string) now()->subMinute()->timestamp,
        'exp' => (string) now()->addHour()->timestamp,
    ], $overrides);
}

/**
 * @param  array<string, string|null>  $overrides  Use null to drop a field from the payload.
 * @return array<string, string>
 */
function googleLoginPayload(array $overrides = []): array
{
    return array_filter(array_merge([
        'id_token' => 'google-id-token',
        'device_name' => 'Pixel 8',
    ], $overrides), fn (?string $value) => $value !== null);
}

beforeEach(function () {
    config(['services.google.allowed_client_ids' => [WEB_CLIENT_ID]]);
});

it('signs in a new Google user and returns a working token', function () {
    Http::fake([TOKENINFO_URL => Http::response(tokenInfo())]);

    $response = $this->postJson('/api/v1/auth/google', googleLoginPayload())
        ->assertOk()
        ->assertExactJsonStructure(['token', 'user' => ['id', 'name', 'email', 'phone', 'avatar']])
        ->assertJsonPath('user.email', 'budi@gmail.com');

    $user = User::query()->sole();

    expect($user->socialAccounts()->sole())
        ->provider->toBe(SocialProvider::Google)
        ->provider_id->toBe('109876543210987654321')
        ->and($user->tokens()->sole()->name)->toBe('Pixel 8');

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://oauth2.googleapis.com/tokeninfo')
        && str_contains($request->url().$request->body(), 'google-id-token'));

    $this->withToken($response->json('token'))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('returns the same user who signed in with Google on the web', function () {
    $user = User::factory()->withoutPassword()->create(['email' => 'budi@gmail.com']);
    SocialAccount::factory()->for($user)->create([
        'provider' => SocialProvider::Google,
        'provider_id' => '109876543210987654321',
    ]);
    Http::fake([TOKENINFO_URL => Http::response(tokenInfo())]);

    $this->postJson('/api/v1/auth/google', googleLoginPayload())
        ->assertOk()
        ->assertJsonPath('user.id', $user->id);

    expect(User::query()->count())->toBe(1)
        ->and(SocialAccount::query()->count())->toBe(1);
});

it('accepts both issuer forms Google uses', function (string $issuer) {
    Http::fake([TOKENINFO_URL => Http::response(tokenInfo(['iss' => $issuer]))]);

    $this->postJson('/api/v1/auth/google', googleLoginPayload())->assertOk();
})->with([
    'with https' => 'https://accounts.google.com',
    'without https' => 'accounts.google.com',
]);

it('rejects a token that Google says is invalid', function () {
    Http::fake([TOKENINFO_URL => Http::response(['error' => 'invalid_token'], 400)]);

    $this->postJson('/api/v1/auth/google', googleLoginPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('id_token');

    expect(User::query()->count())->toBe(0);
});

it('rejects a token whose claims are not acceptable', function (array $claims) {
    Http::fake([TOKENINFO_URL => Http::response(tokenInfo($claims))]);

    $this->postJson('/api/v1/auth/google', googleLoginPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('id_token');

    expect(User::query()->count())->toBe(0)
        ->and(SocialAccount::query()->count())->toBe(0);
})->with([
    'audience of another app' => fn () => ['aud' => 'other-app.apps.googleusercontent.com'],
    'expired' => fn () => ['exp' => (string) now()->subSecond()->timestamp],
    'unverified email' => fn () => ['email_verified' => 'false'],
    'unknown issuer' => fn () => ['iss' => 'https://evil.example.com'],
]);

it('rejects a request without the required fields', function (string $field) {
    Http::fake();

    $this->postJson('/api/v1/auth/google', googleLoginPayload([$field => null]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    Http::assertNothingSent();
})->with(['id_token', 'device_name']);

it('blocks the eleventh Google sign-in attempt from one IP within a minute', function () {
    Http::fake([TOKENINFO_URL => Http::response(['error' => 'invalid_token'], 400)]);

    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/v1/auth/google', googleLoginPayload())->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/google', googleLoginPayload())->assertTooManyRequests();

    Http::assertSentCount(10);
});

it('limits Google sign-in attempts per IP, not for every IP', function () {
    Http::fake([TOKENINFO_URL => Http::response(['error' => 'invalid_token'], 400)]);

    foreach (range(1, 10) as $attempt) {
        $this->postJson('/api/v1/auth/google', googleLoginPayload());
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->postJson('/api/v1/auth/google', googleLoginPayload())
        ->assertUnprocessable();
});

it('returns 503 when Google cannot be reached', function (Closure $googleResponse) {
    Http::fake([TOKENINFO_URL => $googleResponse]);

    $this->postJson('/api/v1/auth/google', googleLoginPayload())
        ->assertServiceUnavailable()
        ->assertJsonStructure(['message']);

    expect(User::query()->count())->toBe(0);
})->with([
    'connection timeout' => fn () => Http::failedConnection(),
    'server error' => fn () => fn () => Http::response(['error' => 'internal'], 500),
]);
