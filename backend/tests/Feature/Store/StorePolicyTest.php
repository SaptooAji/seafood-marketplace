<?php

declare(strict_types=1);

use App\Enums\StoreRole;
use App\Models\Store;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->staff = User::factory()->create();
    $this->outsider = User::factory()->create();

    $this->store = Store::factory()->withOwner($this->owner)->create();
    $this->store->users()->attach($this->staff, ['role' => StoreRole::Staff->value]);
});

it('decides who may view and update a store', function (string $who, string $ability, bool $allowed) {
    expect($this->{$who}->can($ability, $this->store))->toBe($allowed);
})->with([
    'owner may view' => ['owner', 'view', true],
    'owner may update' => ['owner', 'update', true],
    'staff may view' => ['staff', 'view', true],
    'staff may not update' => ['staff', 'update', false],
    'outsider may not view' => ['outsider', 'view', false],
    'outsider may not update' => ['outsider', 'update', false],
]);

it('does not let the owner of one store update another store', function () {
    $otherStore = Store::factory()->withOwner()->create();

    expect($this->owner->can('update', $otherStore))->toBeFalse()
        ->and($this->owner->can('view', $otherStore))->toBeFalse();
});

it('decides who may create a store', function (string $who, bool $allowed) {
    expect($this->{$who}->can('create', Store::class))->toBe($allowed);
})->with([
    'user without a store may create' => ['outsider', true],
    'staff of another store may create' => ['staff', true],
    'owner of a store may not create' => ['owner', false],
]);
