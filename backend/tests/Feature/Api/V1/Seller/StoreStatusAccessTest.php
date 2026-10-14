<?php

declare(strict_types=1);

use App\Actions\ReactivateStore;
use App\Actions\SuspendStore;
use App\Enums\StoreRole;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
 * AC-7: a suspended store cannot use the seller API, as the /seller panel
 * already refuses it. Its members may still see the store, so the app can
 * show why, and may still open a store of their own.
 */

/**
 * Seller API routes that a suspended store may still use.
 */
const STORE_STATUS_OPEN_ROUTES = ['api.seller.store.show', 'api.seller.store.store'];

/**
 * Tables the seller API writes to.
 */
const STORE_STATUS_TABLES = [
    'stores', 'store_members', 'store_shipping_methods', 'products', 'product_variants',
    'product_images', 'product_attribute_values', 'stock_movements',
];

beforeEach(function () {
    Storage::fake('public');
    // The description suggestion reaches Gemini only when the store is let through.
    config(['services.gemini.key' => 'AIzaFAKE-status-test-key', 'services.gemini.model' => 'gemini-test-flash', 'services.gemini.fallback_models' => []]);
    Http::preventStrayRequests();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [[
            'content' => ['role' => 'model', 'parts' => [['text' => json_encode([
                'description' => 'Kakap merah segar tangkapan pagi.',
                'seo_description' => 'Kakap merah segar.',
            ])]]],
            'finishReason' => 'STOP',
        ]],
    ])]);
});

/**
 * Sign in the owner of a store in the given state, with one product (a
 * variant and a photo) and one shipping method.
 *
 * @return array{store: Store, category: Category, product: Product, variant: ProductVariant, image: ProductImage, method: StoreShippingMethod}
 */
function storeStatusSeller(StoreStatus $status): array
{
    $user = User::factory()->create();
    $store = Store::factory()->create(['status' => $status]);
    $store->users()->attach($user, ['role' => StoreRole::Owner->value]);
    Sanctum::actingAs($user);

    $category = Category::factory()->create();
    $product = Product::factory()->for($store)->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create();
    $image = ProductImage::factory()->for($product)->create(['path' => 'products/'.$product->id.'/ikan.webp']);
    Storage::disk('public')->put($image->path, 'photo');
    Storage::disk('public')->put($image->thumbnailPath(), 'thumbnail');
    $method = StoreShippingMethod::factory()->for($store)->pickup()->create();

    return compact('store', 'category', 'product', 'variant', 'image', 'method');
}

/**
 * A valid request for every seller API route a suspended store may not use:
 * [method, URL, data] from the seller's fixtures.
 *
 * @return array<string, array{Closure}>
 */
function storeStatusRequests(): array
{
    $productData = fn (array $f) => [
        'category_id' => $f['category']->id,
        'name' => 'Kakap Merah Segar',
        'description' => 'Kakap merah tangkapan pagi.',
        'condition' => 'fresh',
        'sales_mode' => 'ready_stock',
    ];
    $variantData = ['name' => '500 g', 'unit' => 'kg', 'price' => 45_000, 'min_qty' => 1];

    return [
        'api.seller.store.update' => [fn (array $f) => ['PUT', '/api/v1/seller/store', [
            'name' => 'Toko Ganti Nama',
            'phone' => '081234567890',
            'address' => 'Jl. Pelabuhan No. 5',
            'city' => 'Jakarta Utara',
            'lat' => -6.2087634,
            'lng' => 106.845599,
            'bank_name' => 'BCA',
            'bank_account_no' => '1234567890',
            'bank_account_name' => 'Budi Santoso',
        ]]],
        'api.seller.stock.today' => [fn (array $f) => ['GET', '/api/v1/seller/stock/today', []]],
        'api.seller.stock.confirm' => [fn (array $f) => ['POST', '/api/v1/seller/stock/confirm', [
            'variants' => [['id' => $f['variant']->id, 'stock' => 7]],
        ]]],
        'api.seller.shipping-methods.index' => [fn (array $f) => ['GET', '/api/v1/seller/shipping-methods', []]],
        'api.seller.shipping-methods.store' => [fn (array $f) => ['POST', '/api/v1/seller/shipping-methods', [
            'type' => 'own_delivery', 'fee_type' => 'flat', 'max_radius_km' => 10, 'flat_fee' => 10_000,
        ]]],
        'api.seller.shipping-methods.update' => [fn (array $f) => ['PUT', "/api/v1/seller/shipping-methods/{$f['method']->id}", [
            'fee_type' => 'flat', 'flat_fee' => 0, 'is_active' => false,
        ]]],
        'api.seller.products.description-suggestion' => [fn (array $f) => ['POST', '/api/v1/seller/products/description-suggestion', $productData($f)]],
        'api.seller.products.index' => [fn (array $f) => ['GET', '/api/v1/seller/products', []]],
        'api.seller.products.store' => [fn (array $f) => ['POST', '/api/v1/seller/products', [
            ...$productData($f),
            'variants' => [[...$variantData, 'initial_stock' => 10]],
        ]]],
        'api.seller.products.show' => [fn (array $f) => ['GET', "/api/v1/seller/products/{$f['product']->id}", []]],
        'api.seller.products.update' => [fn (array $f) => ['PUT', "/api/v1/seller/products/{$f['product']->id}", $productData($f)]],
        'api.seller.products.destroy' => [fn (array $f) => ['DELETE', "/api/v1/seller/products/{$f['product']->id}", []]],
        'api.seller.products.images.store' => [fn (array $f) => ['POST', "/api/v1/seller/products/{$f['product']->id}/images", [
            'photo' => UploadedFile::fake()->image('kakap.jpg', 640, 480),
        ]]],
        'api.seller.products.images.reorder' => [fn (array $f) => ['PUT', "/api/v1/seller/products/{$f['product']->id}/images/order", [
            'image_ids' => [$f['image']->id],
        ]]],
        'api.seller.products.images.destroy' => [fn (array $f) => ['DELETE', "/api/v1/seller/products/{$f['product']->id}/images/{$f['image']->id}", []]],
        'api.seller.products.variants.store' => [fn (array $f) => ['POST', "/api/v1/seller/products/{$f['product']->id}/variants", [
            ...$variantData, 'initial_stock' => 5,
        ]]],
        'api.seller.variants.update' => [fn (array $f) => ['PUT', "/api/v1/seller/variants/{$f['variant']->id}", [
            ...$variantData, 'price' => 99_000,
        ]]],
        'api.seller.variants.destroy' => [fn (array $f) => ['DELETE', "/api/v1/seller/variants/{$f['variant']->id}", []]],
        'api.seller.stock-movements.index' => [fn (array $f) => ['GET', '/api/v1/seller/stock-movements', []]],
        'api.seller.stock-movements.store' => [fn (array $f) => ['POST', '/api/v1/seller/stock-movements', [
            'variant_id' => $f['variant']->id, 'type' => 'stock_in', 'qty' => 5,
        ]]],
    ];
}

/**
 * Send the request the dataset builds from the fixtures.
 *
 * @param  array<string, mixed>  $fixtures
 */
function sendStoreStatusRequest(Closure $request, array $fixtures): TestResponse
{
    [$method, $url, $data] = $request($fixtures);

    // Multipart, so the photo upload is sent as a file.
    return $method === 'POST' && isset($data['photo'])
        ? test()->post($url, $data, ['Accept' => 'application/json'])
        : test()->json($method, $url, $data);
}

/**
 * Every row of the tables the seller API writes to, and every stored file.
 *
 * @return array<string, mixed>
 */
function storeStatusSnapshot(): array
{
    $rows = collect(STORE_STATUS_TABLES)
        ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->get()->map(fn (object $row) => (array) $row)->sortBy(fn (array $row) => json_encode($row))->values()->all()])
        ->all();

    return [...$rows, 'files' => Storage::disk('public')->allFiles()];
}

it('covers every seller API route', function () {
    $routes = collect(Route::getRoutes()->getRoutesByName())
        ->keys()
        ->filter(fn (string $name) => str_starts_with($name, 'api.seller.'))
        ->sort()
        ->values()
        ->all();

    $covered = collect(storeStatusRequests())->keys()->merge(STORE_STATUS_OPEN_ROUTES)->sort()->values()->all();

    expect($covered)->toBe($routes);
});

it('refuses a suspended store and changes nothing', function (Closure $request) {
    $fixtures = storeStatusSeller(StoreStatus::Suspended);
    $before = storeStatusSnapshot();

    sendStoreStatusRequest($request, $fixtures)
        ->assertForbidden()
        ->assertJsonPath('message', 'Toko sedang ditangguhkan. Hubungi admin untuk mengaktifkannya kembali.');

    expect(storeStatusSnapshot())->toBe($before);
    Http::assertNothingSent();
})->with(storeStatusRequests());

it('refuses staff of a suspended store too', function () {
    $user = User::factory()->create();
    Store::factory()->suspended()->create()->users()->attach($user, ['role' => StoreRole::Staff->value]);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/seller/products')->assertForbidden();
});

it('lets a pending or active store through', function (Closure $request, StoreStatus $status) {
    $fixtures = storeStatusSeller($status);

    sendStoreStatusRequest($request, $fixtures)->assertSuccessful();
})->with(storeStatusRequests())->with([
    'pending' => [StoreStatus::Pending],
    'active' => [StoreStatus::Active],
]);

it('still shows a suspended store to its members', function () {
    storeStatusSeller(StoreStatus::Suspended);

    $this->getJson('/api/v1/seller/store')
        ->assertOk()
        ->assertJsonPath('data.status.value', 'suspended');
});

it('lets staff of a suspended store open their own store', function () {
    $user = User::factory()->create();
    Store::factory()->suspended()->create()->users()->attach($user, ['role' => StoreRole::Staff->value]);
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/seller/store', [
        'name' => 'Toko Sendiri',
        'phone' => '081234567890',
        'address' => 'Jl. Pelabuhan No. 7',
        'city' => 'Jakarta Utara',
        'lat' => -6.2,
        'lng' => 106.8,
        'bank_name' => 'BCA',
        'bank_account_no' => '1234567890',
        'bank_account_name' => 'Rina',
    ])->assertCreated()->assertJsonPath('data.status.value', 'pending');

    // The store they own now comes first, so the seller API works on it.
    $this->getJson('/api/v1/seller/products')->assertOk();
});

it('still refuses an owner of a suspended store who opens another', function () {
    storeStatusSeller(StoreStatus::Suspended);

    $this->postJson('/api/v1/seller/store', [
        'name' => 'Toko Kedua',
        'phone' => '081234567890',
        'address' => 'Jl. Pelabuhan No. 9',
        'city' => 'Jakarta Utara',
        'lat' => -6.2,
        'lng' => 106.8,
        'bank_name' => 'BCA',
        'bank_account_no' => '1234567890',
        'bank_account_name' => 'Budi',
    ])->assertConflict();

    expect(Store::query()->count())->toBe(1);
});

it('follows the admin suspending and reactivating the store', function () {
    $store = storeStatusSeller(StoreStatus::Active)['store'];
    $this->getJson('/api/v1/seller/products')->assertOk();

    // Checked on every request, so the token already issued is refused at
    // once and works again without signing in.
    app(SuspendStore::class)->handle($store);
    $this->getJson('/api/v1/seller/products')->assertForbidden();

    app(ReactivateStore::class)->handle($store->refresh());
    $this->getJson('/api/v1/seller/products')->assertOk();
});
