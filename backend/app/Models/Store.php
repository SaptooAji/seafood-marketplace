<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StoreStatus;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A seller's store. Users join it through store_members with a store role.
 *
 * `status`, `verified_at`, and `rejection_reason` are not fillable: they only
 * change through the admin verification actions, never from seller input.
 *
 * @property StoreStatus $status
 * @property Carbon|null $verified_at
 */
#[Fillable([
    'name', 'slug', 'description', 'phone', 'address', 'city', 'lat', 'lng', 'logo',
    'bank_name', 'bank_account_no', 'bank_account_name', 'stock_reminder_time',
])]
class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * The bank account number is encrypted at rest, so it cannot be searched.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'verified_at' => 'datetime',
            'bank_account_no' => 'encrypted',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    /**
     * Get the memberships of this store.
     *
     * @return HasMany<StoreMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(StoreMember::class);
    }

    /**
     * Get the users who belong to this store, with their `role` on the pivot.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_members')
            ->withPivot('role')
            ->withTimestamps();
    }
}
