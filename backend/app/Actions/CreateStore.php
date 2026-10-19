<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\ShippingMethodData;
use App\Data\StoreData;
use App\Enums\ShippingFeeType;
use App\Enums\ShippingMethodType;
use App\Enums\StoreRole;
use App\Events\StoreOpened;
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
    public function __construct(private SaveShippingMethod $saveShippingMethod) {}

    /**
     * Create a pending store with a unique slug, make the user its owner, and
     * give it an active pickup method, the default way to receive an order.
     * The store, the membership, and the method are saved together or not at
     * all; admins are told through StoreOpened once they are.
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

            $this->saveShippingMethod->handle($store, new ShippingMethodData(ShippingMethodType::Pickup, ShippingFeeType::Flat, null, 0, null, null));

            // Sent once the transaction commits, so admins only hear of saved stores.
            StoreOpened::dispatch($store);

            // Load the database defaults, such as the pending status.
            return $store->refresh();
        });
    }
}
