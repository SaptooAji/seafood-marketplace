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
     * Placeholder bank details: clearly not a real account (DATA-2).
     */
    public const string BANK_ACCOUNT_NO = '0000000000';

    public const string BANK_ACCOUNT_NAME = 'Toko contoh';

    /**
     * Run the database seeds: six demo stores ("Toko contoh") in North
     * Jakarta, each with its own owner who cannot sign in: no password, no
     * social account, and an `example.com` address (RFC 2606).
     *
     * This also runs in production, so the released marketplace is never
     * empty; it only ever creates rows marked `is_demo` and never changes a
     * real store. Stores, verification, and shipping methods go through the
     * same Actions as the app. Safe to run more than once.
     * `php artisan demo:remove` takes all of it out again.
     */
    public function run(CreateStore $createStore, VerifyStore $verifyStore, SaveShippingMethod $saveShippingMethod): void
    {
        foreach ($this->stores() as $slug => $demo) {
            $owner = self::owner($slug, $demo['owner'], $demo['data']->phone);

            $store = $this->ownedStore($owner) ?? $createStore->handle($owner, $demo['data']);

            // Not fillable on purpose: only this seeder marks a demo store.
            $store->forceFill(['is_demo' => true])->save();

            if ($store->status === StoreStatus::Pending) {
                $verifyStore->handle($store);
            }

            foreach ($demo['shipping'] as $method) {
                $saveShippingMethod->handle($store, $method);
            }
        }
    }

    /**
     * The owner of a demo store, created or updated by email, without a
     * password so nobody can sign in with it.
     */
    public static function owner(string $slug, string $name, string $phone): User
    {
        return User::query()->updateOrCreate(
            ['email' => $slug.'@example.com'],
            ['name' => $name, 'phone' => $phone, 'password' => null],
        );
    }

    private function ownedStore(User $owner): ?Store
    {
        return $owner->stores()->wherePivot('role', StoreRole::Owner->value)->first();
    }

    /**
     * The demo stores by slug. Every store gets pickup from CreateStore
     * (and existing ones from the default pickup migration); `shipping`
     * lists only the extra ways to receive an order.
     *
     * @return array<string, array{owner: string, data: StoreData, shipping: list<ShippingMethodData>}>
     */
    private function stores(): array
    {
        return [
            'ud-kepiting-pak-budi' => [
                'owner' => 'Budi Santoso',
                'data' => $this->store(
                    'UD Kepiting Pak Budi',
                    'Pengepul kepiting bakau dan rajungan dari tambak Muara Angke sejak 2009. Stok dicek setiap pagi sebelum jam tujuh.',
                    '081200000001',
                    'Jl. Dermaga Ujung No. 12, Muara Angke, Penjaringan',
                    '-6.1067000',
                    '106.7740000',
                ),
                'shipping' => [
                    new ShippingMethodData(ShippingMethodType::OwnDelivery, ShippingFeeType::PerKm, '20', null, 3_000, 10_000),
                ],
            ],
            'lapak-bu-sari' => [
                'owner' => 'Sari Wulandari',
                'data' => $this->store(
                    'Lapak Bu Sari',
                    'Udang, cumi, dan ikan dari kapal yang bersandar di Muara Baru. Bisa dibersihkan sesuai pesanan.',
                    '081200000002',
                    'Pasar Ikan Muara Baru, Los C No. 8, Penjaringan',
                    '-6.1050000',
                    '106.8050000',
                ),
                'shipping' => [
                    new ShippingMethodData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, '10', 10_000, null, null),
                ],
            ],
            'pantai-indah' => [
                'owner' => 'Hendra Wijaya',
                'data' => $this->store(
                    'Pantai Indah',
                    'Kerang hijau dan kerang dara dari bagan di Cilincing, dipanen setiap subuh.',
                    '081200000003',
                    'Jl. Pantai Cilincing No. 21, Cilincing',
                    '-6.1180000',
                    '106.9430000',
                ),
                'shipping' => [
                    new ShippingMethodData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, '12', 15_000, null, null),
                ],
            ],
            'bahari-kalibaru' => [
                'owner' => 'Rahmat Hidayat',
                'data' => $this->store(
                    'Bahari Kalibaru',
                    'Hasil tangkapan nelayan Kalibaru: kepiting, cumi, kakap, dan kerang.',
                    '081200000004',
                    'Jl. Kalibaru Timur No. 5, Cilincing',
                    '-6.1030000',
                    '106.9230000',
                ),
                'shipping' => [],
            ],
            'muara-baru-jaya' => [
                'owner' => 'Agus Prasetyo',
                'data' => $this->store(
                    'Muara Baru Jaya',
                    'Cumi dan kakap merah dari kapal besar, segar maupun beku. Melayani rumah makan dan katering.',
                    '081200000005',
                    'Jl. Muara Baru Raya No. 17, Penjaringan',
                    '-6.1095000',
                    '106.8000000',
                ),
                'shipping' => [
                    new ShippingMethodData(ShippingMethodType::OwnDelivery, ShippingFeeType::PerKm, '25', null, 2_500, 12_000),
                ],
            ],
            'tambak-marunda' => [
                'owner' => 'Siti Aminah',
                'data' => $this->store(
                    'Tambak Marunda',
                    'Kepiting bakau dan udang windu dari tambak sendiri di Marunda.',
                    '081200000006',
                    'Jl. Tambak Marunda No. 3, Cilincing',
                    '-6.1080000',
                    '106.9620000',
                ),
                'shipping' => [
                    new ShippingMethodData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, '15', 12_000, null, null),
                ],
            ],
        ];
    }

    private function store(string $name, string $description, string $phone, string $address, string $lat, string $lng): StoreData
    {
        return new StoreData(
            name: $name,
            description: $description,
            phone: $phone,
            address: $address,
            city: 'Jakarta Utara',
            lat: $lat,
            lng: $lng,
            bankName: 'BCA',
            bankAccountNo: self::BANK_ACCOUNT_NO,
            bankAccountName: self::BANK_ACCOUNT_NAME,
            stockReminderTime: null,
        );
    }
}
