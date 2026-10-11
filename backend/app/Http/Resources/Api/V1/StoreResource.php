<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\StoreRole;
use App\Enums\StoreStatus;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A store as its members see it. The bank account is shown to the owner only.
 *
 * @mixin Store
 */
class StoreResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'verified_at' => $this->verified_at?->toIso8601String(),
            // Only a rejected store has a reason the seller needs to act on.
            'rejection_reason' => $this->status === StoreStatus::Rejected ? $this->rejection_reason : null,
            // The database returns `06:00:00`; send it back in the `H:i` form it was entered in.
            'stock_reminder_time' => $this->stock_reminder_time !== null ? substr($this->stock_reminder_time, 0, 5) : null,
            'bank' => $this->when($request->user()?->roleIn($this->resource) === StoreRole::Owner, fn () => [
                'name' => $this->bank_name,
                'account_no' => $this->bank_account_no,
                'account_name' => $this->bank_account_name,
            ]),
        ];
    }
}
