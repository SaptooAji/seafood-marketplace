<?php

declare(strict_types=1);

use App\Enums\SocialProvider;

it('keeps the stored values unchanged', function () {
    expect(array_map(fn (SocialProvider $provider) => $provider->value, SocialProvider::cases()))
        ->toBe(['google', 'facebook']);
});

it('has a unique, non-empty label for every case', function () {
    $labels = array_map(fn (SocialProvider $provider) => $provider->label(), SocialProvider::cases());

    expect($labels)->each->not->toBeEmpty()
        ->and(array_unique($labels))->toHaveCount(count($labels));
});
