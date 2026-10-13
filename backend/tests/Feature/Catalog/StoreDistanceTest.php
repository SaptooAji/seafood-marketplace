<?php

declare(strict_types=1);

use App\Models\Store;

const MONAS = ['-6.1754', '106.8272'];
const MUARA_ANGKE = ['-6.1065', '106.7765'];
const CIREBON = ['-6.7063', '108.5570'];

/**
 * An active store at the given point.
 *
 * @param  array{0: string, 1: string}  $point
 */
function storeAt(string $name, array $point): Store
{
    return Store::factory()->active()->create(['name' => $name, 'lat' => $point[0], 'lng' => $point[1]]);
}

describe('withDistanceFrom()', function () {
    it('measures the distance in km along the earth', function () {
        storeAt('Toko Muara Angke', MUARA_ANGKE);

        $store = Store::query()->withDistanceFrom(...MONAS)->sole();

        expect((float) $store->distance_km)->toBeGreaterThan(9.0)->toBeLessThan(10.0);
    });

    it('is zero at the store itself', function () {
        storeAt('Toko Monas', MONAS);

        expect((float) Store::query()->withDistanceFrom(...MONAS)->sole()->distance_km)->toBe(0.0);
    });

    it('keeps the other store columns', function () {
        storeAt('Toko Muara Angke', MUARA_ANGKE);

        expect(Store::query()->withDistanceFrom(...MONAS)->sole())
            ->name->toBe('Toko Muara Angke')
            ->lat->toBe('-6.1065000');
    });

    it('orders the nearest store first', function () {
        storeAt('Toko Cirebon', CIREBON);
        storeAt('Toko Muara Angke', MUARA_ANGKE);

        expect(Store::query()->withDistanceFrom(...MONAS)->orderBy('distance_km')->pluck('name')->all())
            ->toBe(['Toko Muara Angke', 'Toko Cirebon']);
    });
});

describe('within()', function () {
    it('keeps only the stores inside the radius', function () {
        storeAt('Toko Cirebon', CIREBON);
        storeAt('Toko Muara Angke', MUARA_ANGKE);

        expect(Store::query()->within(...[...MONAS, '10'])->pluck('name')->all())->toBe(['Toko Muara Angke'])
            ->and(Store::query()->within(...[...MONAS, '9'])->pluck('name')->all())->toBe([])
            ->and(Store::query()->within(...[...MONAS, '250'])->count())->toBe(2);
    });

    it('combines with the distance column', function () {
        storeAt('Toko Muara Angke', MUARA_ANGKE);

        $store = Store::query()->withDistanceFrom(...MONAS)->within(...[...MONAS, '10'])->sole();

        expect((float) $store->distance_km)->toBeLessThan(10.0);
    });
});
