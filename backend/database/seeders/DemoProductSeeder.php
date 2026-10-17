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
use Illuminate\Support\Facades\RateLimiter;

/**
 * @phpstan-type DemoVariant array{VariantData, string, string, int}
 * @phpstan-type DemoProduct array{name: string, category: string, condition: ProductCondition, pre_order: array{string, int, string}|null, shelf_life_days: int, storage: string, description: string, attributes: array<string, string>, photos: list<string>, variants: list<DemoVariant>}
 */
class DemoProductSeeder extends Seeder
{
    private const string LIVE_CRAB = 'Simpan di tempat sejuk dan lembap, tutup dengan kain basah. Jangan direndam air tawar.';

    private const string LIVE_SHELL = 'Simpan di wadah terbuka di bagian bawah kulkas, tutup kain basah. Masak paling lambat besok.';

    private const string ON_ICE = 'Simpan dalam es atau kulkas, masak di hari yang sama.';

    private const string FROZEN = 'Simpan di freezer -18 °C. Cairkan di kulkas sebelum dimasak, jangan dibekukan ulang.';

    public function __construct(
        private SaveProduct $saveProduct,
        private SaveVariant $saveVariant,
        private RecordStockMovement $recordStockMovement,
        private AddProductImage $addProductImage,
        private ConfirmDailyStock $confirmDailyStock,
    ) {}

    /**
     * Run the database seeds: the products of the six demo stores, each with
     * one to three photos from `database/seeders/images/` and variants stocked
     * from a supplier, confirmed on the day the seeder runs. Everything goes
     * through the same Actions as the app.
     *
     * Runs in production too (see DemoStoreSeeder), and only ever adds to
     * demo stores. Safe to run more than once: a product that already exists
     * is left as it is.
     */
    public function run(): void
    {
        foreach ($this->products() as $slug => $products) {
            $store = Store::query()->demo()->whereHas('users', fn ($user) => $user->where('email', $slug.'@example.com'))->first();

            if ($store === null) {
                $this->command?->warn("DemoProductSeeder: demo store {$slug} not found; run DemoStoreSeeder first.");

                continue;
            }

            /** @var User $owner */
            $owner = $store->users()->wherePivot('role', StoreRole::Owner->value)->firstOrFail();

            foreach ($products as $demo) {
                $product = $store->products()->withTrashed()->where('name', $demo['name'])->first()
                    ?? $this->createProduct($store, $owner, $demo);

                if (! $product->images()->exists()) {
                    $this->addPhotos($product, $owner, $demo['photos']);
                }
            }

            $this->confirmDailyStock->handle($store, [], true, $owner);
        }
    }

    /**
     * Upload the photos the way a seller would. The per-minute upload limit
     * is meant for people, so it is reset before each photo here.
     *
     * @param  list<string>  $photos
     */
    private function addPhotos(Product $product, User $owner, array $photos): void
    {
        foreach ($photos as $photo) {
            RateLimiter::clear('product-photo:user:'.$owner->id);

            $path = database_path('seeders/images/'.$photo);
            $this->addProductImage->handle($product, new UploadedFile($path, basename($photo), 'image/jpeg', null, true), $owner);
        }
    }

    /**
     * Save the product, its variants, and their first stock in, together.
     *
     * @param  DemoProduct  $demo
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
     * One demo product.
     *
     * @param  array{string, int, string}|null  $preOrder  daily quota, lead time in days, and cutoff time
     * @param  array<string, string>  $attributes
     * @param  list<string>  $photos  paths under database/seeders/images
     * @param  list<DemoVariant>  $variants
     * @return DemoProduct
     */
    private function product(string $name, string $category, ProductCondition $condition, ?array $preOrder, int $shelfLifeDays, string $storage, string $description, array $attributes, array $photos, array $variants): array
    {
        return [
            'name' => $name,
            'category' => $category,
            'condition' => $condition,
            'pre_order' => $preOrder,
            'shelf_life_days' => $shelfLifeDays,
            'storage' => $storage,
            'description' => $description,
            'attributes' => $attributes,
            'photos' => $photos,
            'variants' => $variants,
        ];
    }

    /**
     * One variant with its first stock in: quantity, supplier, and cost per unit.
     *
     * @return DemoVariant
     */
    private function variant(string $name, Unit $unit, int $price, string $minQty, string $stock, string $supplier, int $unitCost): array
    {
        return [new VariantData($name, $unit, $price, $minQty, true), $stock, $supplier, $unitCost];
    }

    /**
     * The demo products by store slug.
     *
     * @return array<string, list<DemoProduct>>
     */
    private function products(): array
    {
        $live = ProductCondition::Live;
        $fresh = ProductCondition::Fresh;
        $frozen = ProductCondition::Frozen;
        $frozenOn = now()->subDays(3)->toDateString();

        return [
            'ud-kepiting-pak-budi' => [
                $this->product('Kepiting Bakau', 'bakau', $live, null, 3, self::LIVE_CRAB,
                    'Kepiting bakau hidup dari tambak Muara Angke, diikat dan dipilih satu per satu. Daging padat, cocok untuk saus padang atau lada hitam.',
                    ['Berat per ekor' => '300', 'Jenis kelamin' => 'Jantan', 'Grade' => 'A'],
                    ['kepiting-bakau/1-hidup-di-keranjang.jpg', 'kepiting-bakau/2-dipegang-nelayan.jpg'],
                    [$this->variant('Jantan 3-4 ekor per kg', Unit::Kg, 185_000, '0.5', '12', 'Tambak H. Rasyid', 140_000),
                        $this->variant('Betina 3-4 ekor per kg', Unit::Kg, 195_000, '0.5', '8', 'Tambak H. Rasyid', 150_000)]),
                $this->product('Kepiting Bakau Jumbo', 'bakau', $live, null, 3, self::LIVE_CRAB,
                    'Kepiting bakau jantan ukuran jumbo, dua ekor per kilo. Untuk acara dan tamu.',
                    ['Berat per ekor' => '500', 'Jenis kelamin' => 'Jantan', 'Grade' => 'Super'],
                    ['kepiting-bakau/2-dipegang-nelayan.jpg', 'kepiting-bakau/1-hidup-di-keranjang.jpg'],
                    [$this->variant('2 ekor per kg', Unit::Kg, 240_000, '1', '5', 'Tambak H. Rasyid', 190_000)]),
                $this->product('Kepiting Bakau Betina Bertelur', 'bakau', $live, ['8', 1, '14:00'], 3, self::LIVE_CRAB,
                    'Kepiting bakau betina bertelur, dipilih dari tambak pagi hari. Pesan sebelum jam 14.00, dikirim besok pagi.',
                    ['Berat per ekor' => '350', 'Jenis kelamin' => 'Betina', 'Grade' => 'Super'],
                    ['kepiting-bakau/1-hidup-di-keranjang.jpg'],
                    [$this->variant('3 ekor per kg', Unit::Kg, 230_000, '1', '8', 'Tambak H. Rasyid', 180_000)]),
                $this->product('Kepiting Bakau Rebus', 'bakau', $fresh, null, 1, self::ON_ICE,
                    'Kepiting bakau yang sudah direbus pagi ini, tinggal dipanaskan dan diberi bumbu.',
                    ['Berat per ekor' => '300', 'Jenis kelamin' => 'Jantan'],
                    ['kepiting-bakau/3-rebus-di-piring.jpg'],
                    [$this->variant('3-4 ekor per kg', Unit::Kg, 200_000, '0.5', '6', 'Dapur UD Pak Budi', 160_000)]),
                $this->product('Rajungan', 'rajungan', $fresh, null, 1, self::ON_ICE,
                    'Rajungan segar tangkapan nelayan pagi ini, ukuran sedang. Manis untuk dikukus atau dibuat sup.',
                    ['Berat per ekor' => '150'],
                    ['rajungan/1-rajungan-hidup.jpg', 'rajungan/2-rajungan-di-pasar.jpg'],
                    [$this->variant('Ukuran sedang', Unit::Kg, 120_000, '0.5', '18', 'Nelayan Pak Darto', 90_000)]),
                $this->product('Rajungan Pilihan', 'rajungan', $fresh, ['10', 1, '15:00'], 1, self::ON_ICE,
                    'Rajungan ukuran besar, disortir per ekor. Pesan sebelum jam 15.00, dikirim besok pagi.',
                    ['Berat per ekor' => '200'],
                    ['rajungan/2-rajungan-di-pasar.jpg'],
                    [$this->variant('Ukuran besar', Unit::Kg, 140_000, '1', '10', 'Nelayan Pak Darto', 105_000)]),
                $this->product('Kepiting Soka', 'soka', $frozen, null, 90, self::FROZEN,
                    'Kepiting cangkang lunak beku, bisa dimakan utuh. Praktis digoreng tepung.',
                    ['Berat per ekor' => '80'],
                    ['kepiting-soka/1-soka-goreng.jpg', 'kepiting-soka/2-soka-tepung.jpg'],
                    [$this->variant('Pack 500 g', Unit::Pack, 85_000, '1', '30', 'CV Soka Mandiri', 60_000)]),
            ],
            'lapak-bu-sari' => [
                $this->product('Udang Vaname Size 40', 'vaname', $fresh, ['40', 1, '15:00'], 1, self::ON_ICE,
                    'Udang vaname tambak, sekitar 40 ekor per kilo. Pesan sebelum jam 15.00, dikirim besok pagi.',
                    ['Isi per kg' => '40'],
                    ['udang/1-udang-vaname.jpg', 'udang/2-udang-vaname.jpg'],
                    [$this->variant('40 ekor per kg', Unit::Kg, 95_000, '0.5', '40', 'Tambak Karawang', 72_000)]),
                $this->product('Udang Vaname Size 30', 'vaname', $fresh, null, 1, self::ON_ICE,
                    'Udang vaname besar, sekitar 30 ekor per kilo. Cocok untuk udang bakar madu.',
                    ['Isi per kg' => '30'],
                    ['udang/2-udang-vaname.jpg', 'udang/3-udang-vaname.jpg'],
                    [$this->variant('30 ekor per kg', Unit::Kg, 110_000, '0.5', '15', 'Tambak Karawang', 85_000)]),
                $this->product('Udang Vaname Beku', 'vaname', $frozen, null, 90, self::FROZEN,
                    'Udang vaname beku per kilo, dibekukan sehari setelah panen.',
                    ['Isi per kg' => '50'],
                    ['udang/3-udang-vaname.jpg'],
                    [$this->variant('Kemasan 1 kg', Unit::Pack, 88_000, '1', '25', 'CV Beku Segar', 66_000)]),
                $this->product('Cumi Segar', 'cumi-segar', $fresh, ['25', 1, '15:00'], 1, self::ON_ICE,
                    'Cumi segar dari kapal yang bersandar subuh, mata masih jernih. Pesan sebelum jam 15.00, dikirim besok pagi.',
                    ['Ukuran' => 'Sedang'],
                    ['cumi-segar/1-cumi-di-pasar.jpg', 'cumi-segar/2-cumi-di-lapak.jpg'],
                    [$this->variant('Ukuran sedang', Unit::Kg, 68_000, '0.5', '25', 'KM Sinar Laut', 50_000)]),
                $this->product('Cumi Beku', 'cumi-beku', $frozen, null, 180, self::FROZEN,
                    'Cumi beku sekali beku dari kapal, dibersihkan dan dikemas vakum.',
                    ['Ukuran' => 'Sedang', 'Tanggal beku' => $frozenOn],
                    ['cumi-beku/1-cumi-di-atas-es.jpg'],
                    [$this->variant('Pack 1 kg', Unit::Pack, 60_000, '1', '18', 'KM Sinar Laut', 44_000)]),
                $this->product('Kerang Hijau', 'kerang-hijau', $live, null, 2, self::LIVE_SHELL,
                    'Kerang hijau hidup dari bagan Cilincing, sudah dibersihkan dari lumut.',
                    ['Isi per kg' => '35'],
                    ['kerang-hijau/1-kerang-hijau.jpg'],
                    [$this->variant('Per kg', Unit::Kg, 27_000, '1', '40', 'Bagan Pak Usman', 18_000)]),
                $this->product('Kakap Merah', 'ikan', $fresh, null, 1, self::ON_ICE,
                    'Kakap merah utuh, insang masih merah. Bisa disiangi dan dipotong sesuai pesanan.',
                    ['Jenis ikan' => 'Kakap merah', 'Berat per ekor' => '800'],
                    ['kakap-merah/2-kakap-merah.jpg', 'kakap-merah/1-kakap-merah.jpg'],
                    [$this->variant('Utuh, 700-900 g per ekor', Unit::Kg, 78_000, '1', '12', 'KM Bintang Timur', 58_000)]),
            ],
            'pantai-indah' => [
                $this->product('Kerang Hijau', 'kerang-hijau', $live, ['60', 1, '16:00'], 2, self::LIVE_SHELL,
                    'Kerang hijau dipanen subuh dari bagan sendiri. Pesan sebelum jam 16.00, dikirim besok pagi.',
                    ['Isi per kg' => '30'],
                    ['kerang-hijau/2-kerang-hijau.jpg', 'kerang-hijau/1-kerang-hijau.jpg'],
                    [$this->variant('Karung 10 kg', Unit::Kg, 26_000, '10', '60', 'Bagan Pantai Indah', 17_000)]),
                $this->product('Kerang Hijau Kupas', 'kerang-hijau', $fresh, null, 1, self::ON_ICE,
                    'Daging kerang hijau yang sudah dikupas, siap ditumis atau dibuat sate.',
                    ['Isi per kg' => '120'],
                    ['kerang-hijau/2-kerang-hijau.jpg'],
                    [$this->variant('Pack 500 g', Unit::Pack, 45_000, '1', '20', 'Bagan Pantai Indah', 32_000)]),
                $this->product('Kerang Dara', 'kerang-dara', $live, null, 2, self::LIVE_SHELL,
                    'Kerang dara hidup, cangkang tertutup rapat. Enak direbus dengan serai.',
                    ['Isi per kg' => '60'],
                    ['kerang-hijau/3-cangkang-kerang.jpg'],
                    [$this->variant('Per kg', Unit::Kg, 35_000, '1', '25', 'Bagan Pantai Indah', 24_000)]),
                $this->product('Udang Vaname Size 30', 'vaname', $fresh, ['10', 1, '15:00'], 1, self::ON_ICE,
                    'Udang vaname besar dari tambak Marunda. Pesan sebelum jam 15.00, dikirim besok pagi.',
                    ['Isi per kg' => '30'],
                    ['udang/1-udang-vaname.jpg'],
                    [$this->variant('30 ekor per kg', Unit::Kg, 112_000, '1', '10', 'Tambak Marunda', 86_000)]),
                $this->product('Rajungan', 'rajungan', $fresh, null, 1, self::ON_ICE,
                    'Rajungan tangkapan jaring nelayan Cilincing, ukuran campur.',
                    ['Berat per ekor' => '150'],
                    ['rajungan/1-rajungan-hidup.jpg'],
                    [$this->variant('Ukuran campur', Unit::Kg, 118_000, '1', '9', 'Nelayan Cilincing', 88_000)]),
                $this->product('Cumi Segar', 'cumi-segar', $fresh, null, 1, self::ON_ICE,
                    'Cumi segar ukuran kecil, cocok untuk cumi goreng tepung.',
                    ['Ukuran' => 'Kecil'],
                    ['cumi-segar/2-cumi-di-lapak.jpg'],
                    [$this->variant('Ukuran kecil', Unit::Kg, 62_000, '0.5', '14', 'Nelayan Cilincing', 45_000)]),
                $this->product('Fillet Kakap Merah', 'ikan', $fresh, null, 1, self::ON_ICE,
                    'Fillet kakap merah tanpa tulang, dipotong pagi ini.',
                    ['Jenis ikan' => 'Kakap merah', 'Berat per ekor' => '800'],
                    ['kakap-merah/3-fillet-kakap.jpg'],
                    [$this->variant('Pack 500 g', Unit::Pack, 65_000, '1', '12', 'KM Bintang Timur', 48_000)]),
            ],
            'bahari-kalibaru' => [
                $this->product('Kepiting Bakau', 'bakau', $live, null, 3, self::LIVE_CRAB,
                    'Kepiting bakau hidup dari nelayan Kalibaru, campur jantan dan betina.',
                    ['Berat per ekor' => '300', 'Grade' => 'B'],
                    ['kepiting-bakau/1-hidup-di-keranjang.jpg'],
                    [$this->variant('3-4 ekor per kg', Unit::Kg, 190_000, '0.5', '15', 'Nelayan Kalibaru', 145_000)]),
                $this->product('Cumi Segar Besar', 'cumi-segar', $fresh, ['15', 1, '15:00'], 1, self::ON_ICE,
                    'Cumi segar ukuran besar untuk dibakar. Pesan sebelum jam 15.00, dikirim besok pagi.',
                    ['Ukuran' => 'Besar'],
                    ['cumi-segar/1-cumi-di-pasar.jpg'],
                    [$this->variant('Ukuran besar', Unit::Kg, 72_000, '0.5', '15', 'KM Harapan Kalibaru', 53_000)]),
                $this->product('Udang Windu', 'windu', $fresh, null, 1, self::ON_ICE,
                    'Udang windu tambak, kulit tebal dan daging manis.',
                    ['Isi per kg' => '25'],
                    ['udang/3-udang-vaname.jpg'],
                    [$this->variant('25 ekor per kg', Unit::Kg, 150_000, '0.5', '8', 'Tambak Kalibaru', 115_000)]),
                $this->product('Kerang Hijau', 'kerang-hijau', $live, null, 2, self::LIVE_SHELL,
                    'Kerang hijau hidup, dijual per kilo.',
                    ['Isi per kg' => '35'],
                    ['kerang-hijau/2-kerang-hijau.jpg'],
                    [$this->variant('Per kg', Unit::Kg, 28_000, '1', '30', 'Bagan Kalibaru', 19_000)]),
                $this->product('Kakap Merah Utuh', 'ikan', $fresh, ['20', 2, '14:00'], 1, self::ON_ICE,
                    'Kakap merah utuh dari kapal besar. Pesan sebelum jam 14.00, dikirim lusa pagi.',
                    ['Jenis ikan' => 'Kakap merah', 'Berat per ekor' => '1200'],
                    ['kakap-merah/1-kakap-merah.jpg'],
                    [$this->variant('Utuh, 1-1,5 kg per ekor', Unit::Kg, 82_000, '1', '20', 'KM Harapan Kalibaru', 62_000)]),
                $this->product('Kepiting Soka', 'soka', $frozen, null, 90, self::FROZEN,
                    'Kepiting soka beku isi 6 sampai 8 ekor per pack.',
                    ['Berat per ekor' => '70'],
                    ['kepiting-soka/2-soka-tepung.jpg'],
                    [$this->variant('Pack 500 g', Unit::Pack, 82_000, '1', '20', 'CV Soka Mandiri', 60_000)]),
                $this->product('Rajungan', 'rajungan', $fresh, null, 1, self::ON_ICE,
                    'Rajungan segar ukuran sedang dari nelayan Kalibaru.',
                    ['Berat per ekor' => '150'],
                    ['rajungan/2-rajungan-di-pasar.jpg'],
                    [$this->variant('Ukuran sedang', Unit::Kg, 116_000, '1', '6', 'Nelayan Kalibaru', 86_000)]),
            ],
            'muara-baru-jaya' => [
                $this->product('Cumi Segar', 'cumi-segar', $fresh, null, 1, self::ON_ICE,
                    'Cumi segar dari kapal besar, ukuran sedang. Bisa dibersihkan.',
                    ['Ukuran' => 'Sedang'],
                    ['cumi-segar/1-cumi-di-pasar.jpg', 'cumi-segar/2-cumi-di-lapak.jpg'],
                    [$this->variant('Ukuran sedang', Unit::Kg, 63_000, '1', '25', 'KM Jaya Abadi', 46_000)]),
                $this->product('Cumi Beku', 'cumi-beku', $frozen, ['30', 2, '15:00'], 180, self::FROZEN,
                    'Cumi beku untuk rumah makan, kemasan 1 kg. Pesan sebelum jam 15.00, dikirim lusa.',
                    ['Ukuran' => 'Besar', 'Tanggal beku' => $frozenOn],
                    ['cumi-beku/1-cumi-di-atas-es.jpg'],
                    [$this->variant('Pack 1 kg', Unit::Pack, 58_000, '5', '30', 'KM Jaya Abadi', 42_000)]),
                $this->product('Kakap Merah', 'ikan', $fresh, null, 1, self::ON_ICE,
                    'Kakap merah segar, sisik melekat kuat. Untuk dibakar atau digulai.',
                    ['Jenis ikan' => 'Kakap merah', 'Berat per ekor' => '900'],
                    ['kakap-merah/2-kakap-merah.jpg', 'kakap-merah/1-kakap-merah.jpg'],
                    [$this->variant('Utuh, 800 g-1 kg per ekor', Unit::Kg, 80_000, '1', '18', 'KM Jaya Abadi', 60_000)]),
                $this->product('Udang Vaname Size 40', 'vaname', $fresh, null, 1, self::ON_ICE,
                    'Udang vaname sekitar 40 ekor per kilo, panen kemarin sore.',
                    ['Isi per kg' => '40'],
                    ['udang/1-udang-vaname.jpg'],
                    [$this->variant('40 ekor per kg', Unit::Kg, 97_000, '1', '8', 'Tambak Indramayu', 74_000)]),
                $this->product('Kerang Hijau', 'kerang-hijau', $live, null, 2, self::LIVE_SHELL,
                    'Kerang hijau hidup, dikirim bersama pesanan ikan.',
                    ['Isi per kg' => '35'],
                    ['kerang-hijau/1-kerang-hijau.jpg'],
                    [$this->variant('Per kg', Unit::Kg, 27_000, '1', '20', 'Bagan Muara Baru', 18_000)]),
                $this->product('Fillet Kakap Merah Beku', 'ikan', $frozen, null, 90, self::FROZEN,
                    'Fillet kakap merah beku tanpa kulit, untuk katering dan rumah makan.',
                    ['Jenis ikan' => 'Kakap merah', 'Berat per ekor' => '900'],
                    ['kakap-merah/3-fillet-kakap.jpg'],
                    [$this->variant('Pack 1 kg', Unit::Pack, 115_000, '1', '15', 'KM Jaya Abadi', 88_000)]),
            ],
            'tambak-marunda' => [
                $this->product('Kepiting Bakau Super', 'bakau', $live, ['12', 1, '15:00'], 3, self::LIVE_CRAB,
                    'Kepiting bakau super dari tambak sendiri, dipilih per ekor. Pesan sebelum jam 15.00, dikirim besok pagi.',
                    ['Berat per ekor' => '400', 'Jenis kelamin' => 'Jantan', 'Grade' => 'Super'],
                    ['kepiting-bakau/2-dipegang-nelayan.jpg', 'kepiting-bakau/1-hidup-di-keranjang.jpg'],
                    [$this->variant('2-3 ekor per kg', Unit::Kg, 225_000, '1', '12', 'Tambak Marunda', 175_000)]),
                $this->product('Kepiting Bakau', 'bakau', $live, null, 3, self::LIVE_CRAB,
                    'Kepiting bakau hidup ukuran sedang dari tambak Marunda.',
                    ['Berat per ekor' => '300', 'Grade' => 'A'],
                    ['kepiting-bakau/1-hidup-di-keranjang.jpg'],
                    [$this->variant('3-4 ekor per kg', Unit::Kg, 182_000, '0.5', '14', 'Tambak Marunda', 138_000)]),
                $this->product('Udang Windu', 'windu', $fresh, ['20', 1, '15:00'], 1, self::ON_ICE,
                    'Udang windu panen pagi dari tambak sendiri. Pesan sebelum jam 15.00, dikirim besok pagi.',
                    ['Isi per kg' => '25'],
                    ['udang/2-udang-vaname.jpg'],
                    [$this->variant('25 ekor per kg', Unit::Kg, 145_000, '1', '20', 'Tambak Marunda', 110_000)]),
                $this->product('Udang Vaname Size 50', 'vaname', $fresh, null, 1, self::ON_ICE,
                    'Udang vaname kecil, sekitar 50 ekor per kilo. Hemat untuk sambal udang.',
                    ['Isi per kg' => '50'],
                    ['udang/3-udang-vaname.jpg'],
                    [$this->variant('50 ekor per kg', Unit::Kg, 82_000, '1', '16', 'Tambak Marunda', 62_000)]),
                $this->product('Kepiting Soka', 'soka', $frozen, null, 90, self::FROZEN,
                    'Kepiting soka dari tambak sendiri, dibekukan saat masih lunak.',
                    ['Berat per ekor' => '80'],
                    ['kepiting-soka/1-soka-goreng.jpg'],
                    [$this->variant('Pack 500 g', Unit::Pack, 88_000, '1', '15', 'Tambak Marunda', 64_000)]),
                $this->product('Kerang Dara', 'kerang-dara', $live, null, 2, self::LIVE_SHELL,
                    'Kerang dara hidup dari pesisir Marunda.',
                    ['Isi per kg' => '60'],
                    ['kerang-hijau/3-cangkang-kerang.jpg'],
                    [$this->variant('Per kg', Unit::Kg, 34_000, '1', '18', 'Nelayan Marunda', 23_000)]),
                $this->product('Rajungan', 'rajungan', $fresh, null, 1, self::ON_ICE,
                    'Rajungan segar dari nelayan Marunda, ukuran sedang.',
                    ['Berat per ekor' => '150'],
                    ['rajungan/1-rajungan-hidup.jpg'],
                    [$this->variant('Ukuran sedang', Unit::Kg, 119_000, '1', '7', 'Nelayan Marunda', 88_000)]),
            ],
        ];
    }
}
