<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\ShippingMethodData;
use App\Enums\ShippingFeeType;
use App\Enums\ShippingMethodType;
use App\Exceptions\InvalidShippingMethod;
use App\Models\Store;
use App\Models\StoreShippingMethod;

/**
 * Creates or updates one of a store's shipping methods. The rules for which
 * fee settings each shipping type may use live here and nowhere else.
 */
class SaveShippingMethod
{
    /**
     * Check the combination, then save it as the store's only row for that
     * type. Every fee column is written, so fields the new fee type does not
     * use are cleared.
     *
     * @throws InvalidShippingMethod naming the first field that does not fit
     */
    public function handle(Store $store, ShippingMethodData $data): StoreShippingMethod
    {
        $this->ensureValid($data);

        return $store->shippingMethods()->updateOrCreate(
            ['type' => $data->type],
            [
                'fee_type' => $data->feeType,
                'max_radius_km' => $data->maxRadiusKm,
                'flat_fee' => $data->flatFee,
                'fee_per_km' => $data->feePerKm,
                'min_fee' => $data->minFee,
                'is_active' => $data->isActive,
            ],
        );
    }

    /**
     * - own_delivery: flat or per_km, radius > 0. Flat needs flat_fee >= 0;
     *   per_km needs fee_per_km > 0 and min_fee >= 0. The other fee type's fields stay empty.
     * - pickup: flat with flat_fee = 0; no radius, fee_per_km, or min_fee.
     * - ojol, expedition: manual; no flat_fee, fee_per_km, or min_fee. A radius is optional.
     *
     * @throws InvalidShippingMethod
     */
    private function ensureValid(ShippingMethodData $data): void
    {
        match ($data->type) {
            ShippingMethodType::OwnDelivery => $this->ensureOwnDelivery($data),
            ShippingMethodType::Pickup => $this->ensurePickup($data),
            ShippingMethodType::Ojol, ShippingMethodType::Expedition => $this->ensureManual($data),
        };
    }

    private function ensureOwnDelivery(ShippingMethodData $data): void
    {
        $this->check('fee_type', in_array($data->feeType, [ShippingFeeType::Flat, ShippingFeeType::PerKm], true), 'Diantar sendiri memakai tarif flat atau per km.');
        $this->check('max_radius_km', $this->isPositive($data->maxRadiusKm), 'Radius maksimal harus lebih dari 0 km.');

        if ($data->feeType === ShippingFeeType::Flat) {
            $this->check('flat_fee', $data->flatFee !== null && $data->flatFee >= 0, 'Tarif flat wajib diisi dan tidak boleh negatif.');
            $this->check('fee_per_km', $data->feePerKm === null, 'Tarif per km harus kosong untuk tarif flat.');
            $this->check('min_fee', $data->minFee === null, 'Tarif minimum harus kosong untuk tarif flat.');

            return;
        }

        $this->check('fee_per_km', $data->feePerKm !== null && $data->feePerKm > 0, 'Tarif per km wajib diisi dan lebih dari 0.');
        $this->check('min_fee', $data->minFee !== null && $data->minFee >= 0, 'Tarif minimum wajib diisi dan tidak boleh negatif.');
        $this->check('flat_fee', $data->flatFee === null, 'Tarif flat harus kosong untuk tarif per km.');
    }

    private function ensurePickup(ShippingMethodData $data): void
    {
        $this->check('fee_type', $data->feeType === ShippingFeeType::Flat, 'Ambil di tempat memakai tarif flat.');
        $this->check('max_radius_km', $data->maxRadiusKm === null, 'Ambil di tempat tidak memakai radius.');
        $this->check('flat_fee', $data->flatFee === 0, 'Ambil di tempat selalu gratis.');
        $this->check('fee_per_km', $data->feePerKm === null, 'Ambil di tempat tidak memakai tarif per km.');
        $this->check('min_fee', $data->minFee === null, 'Ambil di tempat tidak memakai tarif minimum.');
    }

    /**
     * Ojol and expedition fees are entered by the seller per order, so no fee is stored.
     */
    private function ensureManual(ShippingMethodData $data): void
    {
        $this->check('fee_type', $data->feeType === ShippingFeeType::Manual, 'Ojek online dan ekspedisi memakai tarif manual.');
        $this->check('flat_fee', $data->flatFee === null, 'Tarif manual tidak menyimpan tarif flat.');
        $this->check('fee_per_km', $data->feePerKm === null, 'Tarif manual tidak menyimpan tarif per km.');
        $this->check('min_fee', $data->minFee === null, 'Tarif manual tidak menyimpan tarif minimum.');
    }

    /**
     * @throws InvalidShippingMethod when the condition does not hold
     */
    private function check(string $field, bool $condition, string $message): void
    {
        if (! $condition) {
            throw new InvalidShippingMethod($field, $message);
        }
    }

    /**
     * Compares the decimal string as a number without converting it to a float.
     */
    private function isPositive(?string $value): bool
    {
        return $value !== null && is_numeric($value) && $value > 0;
    }
}
