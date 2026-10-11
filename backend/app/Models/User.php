<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlatformRole;
use App\Enums\StoreRole;
use App\Enums\StoreStatus;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'avatar'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Determine whether the user can access the given Filament panel.
     *
     * Only platform roles may enter the admin panel. The seller panel is open
     * to members of at least one active store, whatever their platform role.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->hasRole([PlatformRole::Admin, PlatformRole::SuperAdmin]),
            'seller' => $this->activeStores()->exists(),
            default => false,
        };
    }

    /**
     * The stores the user may switch between in the seller panel: active ones only.
     *
     * @return Collection<int, Store>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->activeStores()->get();
    }

    /**
     * Checked on every seller panel request, so a store that is suspended
     * while the user is signed in locks them out on the next request.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Store
            && $this->activeStores()->whereKey($tenant->getKey())->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the social sign-in accounts linked to the user.
     *
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Get the stores the user belongs to, with their `role` on the pivot.
     *
     * @return BelongsToMany<Store, $this>
     */
    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the active stores the user belongs to, as owner or staff.
     *
     * @return BelongsToMany<Store, $this>
     */
    public function activeStores(): BelongsToMany
    {
        return $this->stores()->where('stores.status', StoreStatus::Active->value);
    }

    /**
     * The store the seller API works on. In the MVP a user owns at most one
     * store, but a staff member may also open their own, so the owned store
     * comes first (`owner` sorts before `staff`).
     */
    public function currentStore(): ?Store
    {
        return $this->stores()->orderByPivot('role')->first();
    }

    /**
     * Get the user's role in the given store, or null when they are not a member.
     */
    public function roleIn(Store $store): ?StoreRole
    {
        return StoreMember::query()
            ->where('store_id', $store->id)
            ->where('user_id', $this->id)
            ->first()
            ?->role;
    }

    /**
     * Determine whether the user is the owner of any store.
     */
    public function ownsAnyStore(): bool
    {
        return $this->stores()->wherePivot('role', StoreRole::Owner->value)->exists();
    }
}
