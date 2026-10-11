<?php

declare(strict_types=1);

use App\Actions\LoginWithGoogle;
use App\Data\GoogleProfile;
use App\Enums\SocialProvider;
use App\Exceptions\UnverifiedSocialEmail;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function googleProfile(array $overrides = []): GoogleProfile
{
    $attributes = array_merge([
        'id' => '109876543210987654321',
        'email' => 'budi@gmail.com',
        'name' => 'Budi Santoso',
        'avatar' => 'https://lh3.googleusercontent.com/a/budi.jpg',
        'emailVerified' => true,
    ], $overrides);

    return new GoogleProfile(...$attributes);
}

function loginWithGoogle(GoogleProfile $profile): User
{
    return app(LoginWithGoogle::class)->handle($profile);
}

it('creates a user and a Google account for a new email', function () {
    $user = loginWithGoogle(googleProfile());

    expect(User::query()->count())->toBe(1)
        ->and($user->email)->toBe('budi@gmail.com')
        ->and($user->name)->toBe('Budi Santoso')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(DB::table('users')->where('id', $user->id)->value('password'))->toBeNull()
        ->and($user->socialAccounts)->toHaveCount(1)
        ->and($user->socialAccounts->first()->provider)->toBe(SocialProvider::Google)
        ->and($user->socialAccounts->first()->provider_id)->toBe('109876543210987654321');
});

it('returns the existing user when the Google account is already linked', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->for($user)->create([
        'provider' => SocialProvider::Google,
        'provider_id' => '109876543210987654321',
    ]);

    $result = loginWithGoogle(googleProfile());

    expect($result->is($user))->toBeTrue()
        ->and(User::query()->count())->toBe(1)
        ->and(SocialAccount::query()->count())->toBe(1);
});

it('links the Google account to an existing user with the same email', function () {
    $user = User::factory()->create(['email' => 'budi@gmail.com']);

    $result = loginWithGoogle(googleProfile());

    expect($result->is($user))->toBeTrue()
        ->and(User::query()->count())->toBe(1)
        ->and($user->socialAccounts()->count())->toBe(1);
});

it('updates the avatar on every login but keeps the existing name', function () {
    $user = User::factory()->create(['name' => 'Budi Santoso', 'avatar' => 'https://lh3.googleusercontent.com/a/lama.jpg']);
    SocialAccount::factory()->for($user)->create([
        'provider' => SocialProvider::Google,
        'provider_id' => '109876543210987654321',
    ]);

    loginWithGoogle(googleProfile([
        'name' => 'Budi dari Google',
        'avatar' => 'https://lh3.googleusercontent.com/a/baru.jpg',
    ]));

    expect($user->fresh())
        ->avatar->toBe('https://lh3.googleusercontent.com/a/baru.jpg')
        ->name->toBe('Budi Santoso');
});

it('rejects a Google email that is not verified', function () {
    expect(fn () => loginWithGoogle(googleProfile(['emailVerified' => false])))
        ->toThrow(UnverifiedSocialEmail::class);

    expect(User::query()->count())->toBe(0)
        ->and(SocialAccount::query()->count())->toBe(0);
});

it('matches an existing user email regardless of letter case', function () {
    $user = User::factory()->create(['email' => 'budi@gmail.com']);

    $result = loginWithGoogle(googleProfile(['email' => 'Budi@Gmail.com']));

    expect($result->is($user))->toBeTrue()
        ->and(User::query()->count())->toBe(1);
});

it('finds the user by Google id even after the user changed their email', function () {
    $user = User::factory()->create(['email' => 'budi.baru@mail.com']);
    SocialAccount::factory()->for($user)->create([
        'provider' => SocialProvider::Google,
        'provider_id' => '109876543210987654321',
    ]);

    $result = loginWithGoogle(googleProfile(['email' => 'budi@gmail.com']));

    expect($result->is($user))->toBeTrue()
        ->and(User::query()->count())->toBe(1);
});
