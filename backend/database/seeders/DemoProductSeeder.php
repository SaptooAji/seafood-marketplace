<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\AddProductImage;
use App\Actions\ConfirmDailyStock;
use App\Actions\RecordStockMovement;
use App\Actions\SaveProduct;
use App\Actions\SaveVariant;
use App\Data\ProductData;
use App\Data\VariantData;
use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Enums\StockMovementType;
use App\Enums\StoreRole;
use App\Enums\Unit;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DemoProductSeeder extends Seeder
{
    public function __construct(
        private SaveProduct $saveProduct,
        private SaveVariant $saveVariant,
        private RecordStockMovement $recordStockMovement,
        private AddProductImage $addProductImage,
        private ConfirmDailyStock $confirmDailyStock,
    ) {}

    /**
     * Run the database seeds: products for the two active demo stores, each
     * with one photo from `database/seeders/images/` and variants stocked from
     * a supplier, confirmed today. Everything goes through the same Actions as
     * the app. The pending store gets none. Skipped in production; safe to run
     * more than once: a product that already exists is left as it is.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoProductSeeder skipped: demo products are never created in production.');

            return;
        }

        foreach ($this->products() as $storeName => $products) {
            $store = Store::query()->where('name', $storeName)->first();

            if ($store === null) {
                $this->command?->warn("DemoProductSeeder: store {$storeName} not found; run DemoStoreSeeder first.");

                continue;
            }

            /** @var User $owner */
            $owner = $store->users()->wherePivot('role', StoreRole::Owner->value)->firstOrFail();

            foreach ($products as $demo) {
                $product = $store->products()->withTrashed()->where('name', $demo['name'])->first()
                    ?? $this->createProduct($store, $owner, $demo);

                if (! $product->images()->exists()) {
                    $path = database_path('seeders/images/'.$demo['photo']);
                    $this->addProductImage->handle($product, new UploadedFile($path, $demo['photo'], 'image/jpeg', null, true));
                }
            }

            $this->confirmDailyStock->handle($store, [], true, $owner);
        }
    }

    /**
     * Save the product, its variants, and their first stock in, together.
     *
     * @param  array{name: string, category: string, condition: ProductCondition, pre_order: array{string, int, string}|null, shelf_life_days: int, storage: string, description: string, attributes: array<string, string>, photo: string, variants: list<array{VariantData, string, string, int}>}  $demo
     */
    private function createProduct(Store $store, User $owner, array $demo): Product
    {
        return DB::transaction(function () use ($store, $owner, $demo): Product {
            $category = Category::query()->where('slug', $demo['category'])->firstOrFail();

            $product = $this->saveProduct->handle($store, new ProductData(
                categoryId: $category->id,
                name: $demo['name'],
                description: $demo['description'],
                condition: $demo['condition'],
                salesMode: $demo['pre_order'] === null ? SalesMode::ReadyStock : SalesMode::PreOrder,
                dailyQuota: $demo['pre_order'][0] ?? null,
                leadTimeDays: $demo['pre_order'][1] ?? null,
                cutoffTime: $demo['pre_order'][2] ?? null,
                shelfLifeDays: $demo['shelf_life_days'],
                storageInstructions: $demo['storage'],
                isActive: true,
                attributes: $this->attributeValues($category, $demo['attributes']),
            ));

            foreach ($demo['variants'] as [$data, $qty, $supplier, $unitCost]) {
                $variant = $this->saveVariant->handle($product, $data, by: $owner);
                $this->recordStockMovement->handle($variant, StockMovementType::StockIn, $qty, by: $owner, supplierName: $supplier, unitCost: $unitCost);
            }

            return $product;
        });
    }

    /**
     * Attribute values keyed by attribute id, from values keyed by name.
     *
     * @param  array<string, string>  $byName
     * @return array<int, string>
     */
    private function attributeValues(Category $category, array $byName): array
    {
        return $category->effectiveAttributes()
            ->filter(fn (CategoryAttribute $attribute): bool => isset($byName[$attribute->name]))
            ->mapWithKeys(fn (CategoryAttribute $attribute): array => [$attribute->id => $byName[$attribute->name]])
            ->all();
    }

    /**
     * The demo products by store name. Each variant comes with its first stock
     * in: quantity, supplier, and cost per unit.
     *
     * @return array<string, list<array{name: string, category: string, condition: ProductCondition, pre_order: array{string, int, string}|null, shelf_life_days: int, storage: string, description: string, attributes: array<string, string>, photo: string, variants: list<array{VariantData, string, string, int}>}>>
     */
    private function products(): array
    {
        return [
            'UD Kepiting Pak Budi' => [
                [
                    'name' => 'Kepiting Bakau',
                    'category' => 'bakau',
                    'condition' => ProductCondition::Live,
                    'pre_order' => null,
                    'shelf_life_days' => 3,
                    'storage' => 'Simpan di tempat sejuk dan lembap, tutup dengan kain basah. Jangan direndam air tawar.',
                    'description' => 'Kepiting bakau hidup dari tambak Muara Angke, diikat dan dipilih satu per satu. Daging padat, cocok untuk saus padang atau lada hitam.',
                    'attributes' => ['Berat per ekor' => '350', 'Jenis kelamin' => 'Jantan', 'Grade' => 'Super'],
                    'photo' => 'kepiting-bakau.jpg',
                    'variants' => [
                        [new VariantData('Jantan 300-400 g', Unit::Kg, 185_000, '0.5', true), '15', 'Tambak H. Rasyid', 140_000],
                        [new VariantData('Jantan 500 g up', Unit::Kg, 240_000, '1', true), '8', 'Tambak H. Rasyid', 190_000],
                    ],
                ],
                [
                    'name' => 'Rajungan',
                    'category' => 'rajungan',
                    'condition' => ProductCondition::Fresh,
                    'pre_order' => null,
                    'shelf_life_days' => 1,
                    'storage' => 'Simpan dalam es atau kulkas, masak di hari yang sama.',
                    'description' => 'Rajungan segar tangkapan nelayan pagi ini, ukuran sedang. Manis untuk dikukus atau dibuat sup.',
                    'attributes' => ['Berat per ekor' => '150'],
                    'photo' => 'rajungan.jpg',
                    'variants' => [
                        [new VariantData('Ukuran sedang', Unit::Kg, 95_000, '1', true), '20', 'Nelayan Pak Darto', 70_000],
                    ],
                ],
                [
                    'name' => 'Kepiting Soka',
                    'category' => 'soka',
                    'condition' => ProductCondition::Frozen,
                    'pre_order' => null,
                    'shelf_life_days' => 90,
                    'storage' => 'Simpan di freezer -18 °C. Cairkan di kulkas sebelum dimasak.',
                    'description' => 'Kepiting cangkang lunak beku, bisa dimakan utuh. Praktis digoreng tepung.',
                    'attributes' => ['Berat per ekor' => '80'],
                    'photo' => 'kepiting-soka.jpg',
                    'variants' => [
                        [new VariantData('Pack 500 g', Unit::Pack, 85_000, '1', true), '30', 'CV Soka Mandiri', 60_000],
                    ],
                ],
            ],
            'Cumi Beku Bu Sri' => [
                [
                    'name' => 'Cumi Beku',
                    'category' => 'cumi-beku',
                    'condition' => ProductCondition::Frozen,
                    'pre_order' => ['20', 2, '15:00'],
                    'shelf_life_days' => 180,
                    'storage' => 'Simpan di freezer -18 °C. Jangan dibekukan ulang setelah dicairkan.',
                    'description' => 'Cumi beku sekali beku dari kapal, dibersihkan dan dikemas vakum. Pesan sebelum jam 15.00, dikirim dua hari kemudian.',
                    'attributes' => ['Ukuran' => 'Sedang', 'Tanggal beku' => now()->subDays(3)->toDateString()],
                    'photo' => 'cumi-beku.jpg',
                    'variants' => [
                        [new VariantData('Pack 1 kg', Unit::Pack, 75_000, '1', true), '40', 'KM Sinar Laut', 55_000],
                    ],
                ],
                [
                    'name' => 'Cumi Segar',
                    'category' => 'cumi-segar',
                    'condition' => ProductCondition::Fresh,
                    'pre_order' => null,
                    'shelf_life_days' => 1,
                    'storage' => 'Simpan dalam es, masak di hari yang sama.',
                    'description' => 'Cumi segar hasil tangkapan nelayan Cirebon, mata masih jernih. Enak dibakar atau ditumis.',
                    'attributes' => ['Ukuran' => 'Besar'],
                    'photo' => 'cumi-segar.jpg',
                    'variants' => [
                        [new VariantData('Ukuran besar', Unit::Kg, 70_000, '0.5', true), '12.5', 'Nelayan Pak Karim', 50_000],
                    ],
                ],
            ],
        ];
    }
}
