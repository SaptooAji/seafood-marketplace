<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StoreData;
use App\Enums\StoreRole;
use App\Exceptions\UserAlreadyOwnsStore;
use App\Models\Store;
use App\Models\User;
use App\Support\UniqueSlug;
use Illuminate\Support\Facades\DB;

/**
 * Opens a new store for a seller, waiting for admin verification.
 */
class CreateStore
{
    /**
     * Create a pending store with a unique slug and make the user its owner.
     * The store and the membership are saved together or not at all.
     *
     * @throws UserAlreadyOwnsStore when the user already owns a store
     */
    public function handle(User $owner, StoreData $data): Store
    {
        if ($owner->ownsAnyStore()) {
            throw new UserAlreadyOwnsStore;
        }

        return DB::transaction(function () use ($owner, $data): Store {
            $store = Store::query()->create([
                ...$data->toAttributes(),
                'slug' => UniqueSlug::for(Store::query(), $data->name, 'toko'),
            ]);

            $store->users()->attach($owner, ['role' => StoreRole::Owner->value]);

            // Load the database defaults, such as the pending status.
            return $store->refresh();
        });
    }
}
