<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\CreateStore;
use App\Actions\SaveShippingMethod;
use App\Actions\VerifyStore;
use App\Data\ShippingMethodData;
use App\Data\StoreData;
use App\Enums\ShippingFeeType;
use App\Enums\ShippingMethodType;
use App\Enums\StoreRole;
use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoStoreSeeder extends Seeder
{
    /**
     * Run the database seeds: three stores, each with its own owner. Stores,
     * verification, and shipping methods go through the same Actions as the
     * app, so the demo data obeys the app's rules. Skipped in production;
     * safe to run more than once.
     */
    public function run(CreateStore $createStore, VerifyStore $verifyStore, SaveShippingMethod $saveShippingMethod): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoStoreSeeder skipped: demo stores are never created in production.');

            return;
        }

        foreach ($this->stores() as $demo) {
            $owner = DemoUserSeeder::demoUser($demo['owner'][0], $demo['owner'][1], $demo['data']->phone);

            $store = $this->ownedStore($owner) ?? $createStore->handle($owner, $demo['data']);

            if ($demo['active'] && $store->status === StoreStatus::Pending) {
                $verifyStore->handle($store);
            }

            foreach ($demo['shipping'] as $method) {
                $saveShippingMethod->handle($store, $method);
            }
        }
    }

    private function ownedStore(User $owner): ?Store
    {
        return $owner->stores()->wherePivot('role', StoreRole::Owner->value)->first();
    }

    /**
     * @return list<array{owner: array{string, string}, active: bool, data: StoreData, shipping: list<ShippingMethodData>}>
     */
    private function stores(): array
    {
        $pickup = new ShippingMethodData(ShippingMethodType::Pickup, ShippingFeeType::Flat, null, 0, null, null);
        $expedition = new ShippingMethodData(ShippingMethodType::Expedition, ShippingFeeType::Manual, null, null, null, null);

        return [
            [
                'owner' => ['Budi Santoso', 'budi@seafood.test'],
                'active' => true,
                'data' => new StoreData(
                    name: 'UD Kepiting Pak Budi',
                    description: 'Kepiting bakau dan rajungan segar langsung dari tambak Muara Angke.',
                    phone: '081234567801',
                    address: 'Jl. Dermaga Ujung No. 12, Muara Angke, Penjaringan',
                    city: 'Jakarta Utara',
                    lat: '-6.1067000',
                    lng: '106.7740000',
                    bankName: 'BCA',
                    bankAccountNo: '1234567801',
                    bankAccountName: 'Budi Santoso',
                    stockReminderTime: '05:30',
                ),
                'shipping' => [
                    new ShippingMethodData(ShippingMethodType::OwnDelivery, ShippingFeeType::PerKm, '20', null, 3_000, 10_000),
                    $pickup,
                    new ShippingMethodData(ShippingMethodType::Ojol, ShippingFeeType::Manual, null, null, null, null),
                ],
            ],
            [
                'owner' => ['Sri Wahyuni', 'sri@seafood.test'],
                'active' => true,
                'data' => new StoreData(
                    name: 'Cumi Beku Bu Sri',
                    description: 'Cumi segar dan beku hasil tangkapan nelayan Cirebon.',
                    phone: '081234567802',
                    address: 'Jl. Pelabuhan Kejawanan No. 3, Lemahwungkuk',
                    city: 'Cirebon',
                    lat: '-6.7276000',
                    lng: '108.5719000',
                    bankName: 'BRI',
                    bankAccountNo: '1234567802',
                    bankAccountName: 'Sri Wahyuni',
                    stockReminderTime: null,
                ),
                'shipping' => [
                    new ShippingMethodData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, '10', 15_000, null, null),
                    $expedition,
                ],
            ],
            [
                'owner' => ['Joko Susilo', 'joko@seafood.test'],
                'active' => false,
                'data' => new StoreData(
                    name: 'Tiram Segar Mas Joko',
                    description: 'Tiram segar dari pesisir Kenjeran.',
                    phone: '081234567803',
                    address: 'Jl. Pantai Kenjeran No. 21, Bulak',
                    city: 'Surabaya',
                    lat: '-7.2310000',
                    lng: '112.7940000',
                    bankName: 'Mandiri',
                    bankAccountNo: '1234567803',
                    bankAccountName: 'Joko Susilo',
                    stockReminderTime: '06:00',
                ),
                'shipping' => [$pickup],
            ],
        ];
    }
}
