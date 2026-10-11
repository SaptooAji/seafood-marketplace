<?php

declare(strict_types=1);

use App\Models\User;

it('revokes only the token used for the request', function () {
    $user = User::factory()->create();
    $phone = $user->createToken('Pixel 8');
    $tablet = $user->createToken('Galaxy Tab');

    $this->withToken($phone->plainTextToken)
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $phone->accessToken->id]);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $tablet->accessToken->id]);
});

it('rejects a logout without a token', function () {
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});
