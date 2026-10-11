<?php

declare(strict_types=1);

use App\Models\User;

it('returns the signed-in user', function () {
    $user = User::factory()->create([
        'name' => 'Budi Santoso',
        'email' => 'budi@mail.com',
        'phone' => '081234567890',
        'avatar' => 'https://example.com/budi.jpg',
    ]);

    $this->withToken($user->createToken('Pixel 8')->plainTextToken)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $user->id,
            'name' => 'Budi Santoso',
            'email' => 'budi@mail.com',
            'phone' => '081234567890',
            'avatar' => 'https://example.com/budi.jpg',
        ]]);
});

it('rejects a request without a token', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});
