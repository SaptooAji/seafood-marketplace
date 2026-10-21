<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Enums\StoreRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

const ME_URL = '/api/v1/me';

beforeEach(function () {
    $this->user = User::factory()->create([
        'name' => 'Budi Santoso',
        'email' => 'budi@mail.com',
        'phone' => '081111111111',
        'avatar' => 'https://example.com/budi.jpg',
    ]);
    Sanctum::actingAs($this->user);
});

/**
 * @param  array<string, mixed>  $overrides  Use null to drop a field from the payload.
 * @return array<string, mixed>
 */
function profilePayload(array $overrides = []): array
{
    return array_filter(array_merge([
        'name' => 'Budi S',
        'phone' => '081234567890',
    ], $overrides), fn (mixed $value) => $value !== null);
}

describe('PUT /me', function () {
    it('updates the name and phone and returns the profile', function () {
        $this->putJson(ME_URL, profilePayload())
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $this->user->id,
                'name' => 'Budi S',
                'email' => 'budi@mail.com',
                'phone' => '081234567890',
                'avatar' => 'https://example.com/budi.jpg',
            ]]);

        expect($this->user->fresh())
            ->name->toBe('Budi S')
            ->phone->toBe('081234567890');
    });

    it('trims spaces around the name', function () {
        $this->putJson(ME_URL, profilePayload(['name' => '  Budi S  ']))
            ->assertOk()
            ->assertJsonPath('data.name', 'Budi S');

        expect($this->user->fresh()->name)->toBe('Budi S');
    });

    it('rejects invalid input and changes nothing', function (array $overrides, string $field) {
        $this->putJson(ME_URL, profilePayload($overrides))
            ->assertUnprocessable()
            ->assertOnlyJsonValidationErrors([$field]);

        expect($this->user->fresh())
            ->name->toBe('Budi Santoso')
            ->phone->toBe('081111111111');
    })->with([
        'empty name' => [['name' => ''], 'name'],
        'missing name' => [['name' => null], 'name'],
        'name of 256 characters' => [['name' => str_repeat('a', 256)], 'name'],
        'name sent as an array' => [['name' => ['Budi']], 'name'],
        'phone 12345' => [['phone' => '12345'], 'phone'],
        'empty phone' => [['phone' => ''], 'phone'],
        'missing phone' => [['phone' => null], 'phone'],
    ]);

    it('accepts a name of 255 characters', function () {
        $this->putJson(ME_URL, profilePayload(['name' => str_repeat('a', 255)]))->assertOk();

        expect($this->user->fresh()->name)->toHaveLength(255);
    });

    it('requires a token', function () {
        app('auth')->forgetGuards();

        $this->putJson(ME_URL, profilePayload())->assertUnauthorized();
    });
});

describe('security', function () {
    it('ignores every field other than the name and phone', function () {
        $passwordHash = $this->user->password;

        $this->putJson(ME_URL, profilePayload([
            'email' => 'penyerang@mail.com',
            'password' => 'rahasia-baru-123',
            'password_confirmation' => 'rahasia-baru-123',
            'avatar' => 'https://evil.example/x.jpg',
            'roles' => [PlatformRole::SuperAdmin->value],
            'role' => PlatformRole::Admin->value,
            'email_verified_at' => null,
        ]))->assertOk();

        $user = $this->user->fresh();
        expect($user)
            ->name->toBe('Budi S')
            ->phone->toBe('081234567890')
            ->email->toBe('budi@mail.com')
            ->password->toBe($passwordHash)
            ->avatar->toBe('https://example.com/budi.jpg')
            ->email_verified_at->not->toBeNull()
            ->and(Hash::check('rahasia-baru-123', $user->password))->toBeFalse()
            ->and($user->roles()->count())->toBe(0);
    });

    it('changes only the account of the token owner', function () {
        $other = User::factory()->create(['name' => 'Siti', 'phone' => '082222222222']);

        $this->putJson(ME_URL, profilePayload(['id' => $other->id]))
            ->assertOk()
            ->assertJsonPath('data.id', $this->user->id);

        expect($other->fresh())
            ->name->toBe('Siti')
            ->phone->toBe('082222222222')
            ->and($this->user->fresh()->name)->toBe('Budi S');
    });

    it('stores a name with HTML as typed and prints it escaped on the web', function () {
        $name = '<script>alert(1)</script>';

        $this->putJson(ME_URL, profilePayload(['name' => $name]))
            ->assertOk()
            ->assertJsonPath('data.name', $name);

        expect($this->user->fresh()->name)->toBe($name);

        $this->actingAs($this->user->fresh(), 'web')
            ->get('/')
            ->assertOk()
            ->assertDontSee($name, escape: false)
            ->assertSee(e($name), escape: false);
    });
});

describe('store members', function () {
    it('lets a member of a suspended store update their profile', function (StoreRole $role) {
        Store::factory()->suspended()->create()->users()->attach($this->user, ['role' => $role->value]);

        $this->putJson(ME_URL, profilePayload())->assertOk();

        expect($this->user->fresh()->name)->toBe('Budi S');
    })->with([
        'owner' => [StoreRole::Owner],
        'staff' => [StoreRole::Staff],
    ]);
});
