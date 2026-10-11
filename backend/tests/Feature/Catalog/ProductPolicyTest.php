<?php

declare(strict_types=1);

use App\Enums\StoreRole;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;

beforeEach(function () {
    $this->store = Store::factory()->active()->create();
    $this->product = Product::factory()->for($this->store)->create();

    $this->owner = User::factory()->create();
    $this->staff = User::factory()->create();
    $this->outsider = User::factory()->create();
    $this->otherOwner = User::factory()->create();

    $this->store->users()->attach($this->owner, ['role' => StoreRole::Owner->value]);
    $this->store->users()->attach($this->staff, ['role' => StoreRole::Staff->value]);
    Store::factory()->active()->create()->users()->attach($this->otherOwner, ['role' => StoreRole::Owner->value]);
});

it('lets members of the product’s store manage it', function (string $who, string $ability, bool $allowed) {
    expect($this->{$who}->can($ability, $this->product))->toBe($allowed);
})->with([
    'owner may view' => ['owner', 'view', true],
    'owner may update' => ['owner', 'update', true],
    'owner may delete' => ['owner', 'delete', true],
    'staff may view' => ['staff', 'view', true],
    'staff may update' => ['staff', 'update', true],
    'staff may delete' => ['staff', 'delete', true],
    'outsider may not view' => ['outsider', 'view', false],
    'outsider may not update' => ['outsider', 'update', false],
    'owner of another store may not update' => ['otherOwner', 'update', false],
    'owner of another store may not delete' => ['otherOwner', 'delete', false],
]);

it('lets members list and add products of their own store only', function (string $who, bool $allowed) {
    expect($this->{$who}->can('viewAny', [Product::class, $this->store]))->toBe($allowed)
        ->and($this->{$who}->can('create', [Product::class, $this->store]))->toBe($allowed);
})->with([
    'owner' => ['owner', true],
    'staff' => ['staff', true],
    'outsider' => ['outsider', false],
    'owner of another store' => ['otherOwner', false],
]);
