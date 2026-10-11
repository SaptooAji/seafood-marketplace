<?php

declare(strict_types=1);

use App\Domain\Account\Enums\SocialProvider;
use App\Domain\Account\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('creates a user without a password', function () {
    $user = User::factory()->withoutPassword()->create();

    expect(DB::table('users')->where('id', $user->id)->value('password'))->toBeNull();
});

it('allows a user without a phone or avatar', function () {
    $user = User::factory()->create(['phone' => null, 'avatar' => null]);

    expect($user->fresh())
        ->phone->toBeNull()
        ->avatar->toBeNull();
});

it('stores phone and avatar on the user', function () {
    $user = User::factory()->create([
        'phone' => '081234567890',
        'avatar' => 'https://lh3.googleusercontent.com/a/photo.jpg',
    ]);

    expect($user->fresh())
        ->phone->toBe('081234567890')
        ->avatar->toBe('https://lh3.googleusercontent.com/a/photo.jpg');
});

it('links social accounts to their user', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create();

    expect($user->socialAccounts)->toHaveCount(1)
        ->and($user->socialAccounts->first()->is($account))->toBeTrue()
        ->and($account->user->is($user))->toBeTrue();
});

it('casts the provider to the SocialProvider enum', function () {
    $account = SocialAccount::factory()->create(['provider' => 'google']);

    expect($account->fresh()->provider)->toBe(SocialProvider::Google);
});

it('rejects two accounts with the same provider and provider id', function () {
    SocialAccount::factory()->create(['provider' => 'google', 'provider_id' => '1234567890']);

    SocialAccount::factory()->create(['provider' => 'google', 'provider_id' => '1234567890']);
})->throws(UniqueConstraintViolationException::class);

it('allows the same provider id on different providers', function () {
    SocialAccount::factory()->create(['provider' => 'google', 'provider_id' => '1234567890']);
    SocialAccount::factory()->create(['provider' => 'facebook', 'provider_id' => '1234567890']);

    expect(SocialAccount::query()->where('provider_id', '1234567890')->count())->toBe(2);
});

it('deletes social accounts when their user is deleted', function () {
    $user = User::factory()->create();
    SocialAccount::factory()->for($user)->count(2)->create();

    $user->delete();

    expect(SocialAccount::query()->count())->toBe(0);
});
