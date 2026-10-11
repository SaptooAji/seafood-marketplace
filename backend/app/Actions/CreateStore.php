<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StoreData;
use App\Enums\StoreRole;
use App\Exceptions\UserAlreadyOwnsStore;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
                'slug' => $this->uniqueSlug($data->name),
            ]);

            $store->users()->attach($owner, ['role' => StoreRole::Owner->value]);

            // Load the database defaults, such as the pending status.
            return $store->refresh();
        });
    }

    /**
     * Slug the name, adding -2, -3, ... when it is taken. The slug never changes
     * afterwards, so store URLs stay stable.
     */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'toko';
        $slug = $base;

        for ($suffix = 2; Store::query()->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
