<?php

declare(strict_types=1);

use App\Actions\SaveShippingMethod;
use App\Data\ShippingMethodData;
use App\Enums\PlatformRole;
use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Enums\StockMovementType;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\SocialAccount;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\StoreShippingMethod;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

/*
 * The demo data (#186): six labelled demo stores ("Toko contoh") with their
 * products, used in the release so the marketplace never looks empty. The
 * admin accounts stay dev only.
 */

const DEMO_STORE_SLUGS = ['ud-kepiting-pak-budi', 'lapak-bu-sari', 'pantai-indah', 'bahari-kalibaru', 'muara-baru-jaya', 'tambak-marunda'];

const DEMO_REMOVE_QUESTION = 'Hapus semua toko contoh beserta produk, foto, dan akun pemiliknya?';

beforeEach(function () {
    Storage::fake('public');
});

/**
 * Row counts of everything the seeders create, to compare runs.
 *
 * @return array<string, int>
 */
function seededCounts(): array
{
    return [
        'roles' => Role::query()->count(),
        'users' => User::query()->count(),
        'categories' => Category::query()->count(),
        'attributes' => CategoryAttribute::query()->count(),
        'stores' => Store::query()->count(),
        'members' => StoreMember::query()->count(),
        'shipping methods' => StoreShippingMethod::query()->count(),
        'products' => Product::withTrashed()->count(),
        'variants' => ProductVariant::withTrashed()->count(),
        'images' => ProductImage::query()->count(),
        'stock movements' => StockMovement::query()->count(),
        'photo files' => count(Storage::disk('public')->allFiles()),
    ];
}

/**
 * A real active store with an owner and one product with a photo file,
 * created before the seeders run.
 */
function realStoreBeforeSeeding(array $attributes = []): Store
{
    $owner = User::factory()->create(['email' => 'pemilik@mail.com']);
    $store = Store::factory()->active()->withOwner($owner)->create($attributes);
    $product = Product::factory()->for($store)->create();
    ProductVariant::factory()->for($product)->create();
    $image = ProductImage::factory()->for($product)->create();
    Storage::disk('public')->put($image->path, 'foto toko asli');

    return $store;
}

/**
 * The files in database/seeders/images, relative to that folder.
 *
 * @return list<string>
 */
function seederImageFiles(): array
{
    $root = database_path('seeders/images');

    return collect(File::allFiles($root))
        ->map(fn (SplFileInfo $file): string => str_replace('\\', '/', $file->getRelativePathname()))
        ->sort()
        ->values()
        ->all();
}

describe('categories', function () {
    it('creates the seafood category tree with Udang and Kerang', function () {
        $this->seed(CategorySeeder::class);

        $children = fn (string $slug): array => Category::query()->where('slug', $slug)->sole()
            ->children()->pluck('slug')->sort()->values()->all();

        expect($children('kepiting'))->toBe(['bakau', 'rajungan', 'soka'])
            ->and($children('cumi'))->toBe(['cumi-beku', 'cumi-segar'])
            ->and($children('udang'))->toBe(['vaname', 'windu'])
            ->and($children('kerang'))->toBe(['kerang-dara', 'kerang-hijau'])
            ->and(Category::query()->whereIn('slug', ['tiram', 'ikan'])->whereNull('parent_id')->count())->toBe(2);
    });

    it('gives every root category a required attribute and a quality standard', function () {
        $this->seed(CategorySeeder::class);

        $roots = Category::query()->whereNull('parent_id')->get();

        expect($roots)->toHaveCount(6);

        foreach ($roots as $root) {
            expect($root->quality_standard)->not->toBeEmpty()
                ->and($root->attributes()->where('is_required', true)->exists())->toBeTrue();
        }
    });

    it('does not duplicate the new categories when run twice', function () {
        $this->seed(CategorySeeder::class);
        $first = [Category::query()->count(), CategoryAttribute::query()->count()];

        $this->seed(CategorySeeder::class);

        expect([Category::query()->count(), CategoryAttribute::query()->count()])->toBe($first);
    });
});

describe('admin accounts', function () {
    it('creates one super admin and one admin with the demo password outside production', function () {
        config(['demo.password' => 'rahasia-demo']);

        $this->seed();

        $superAdmins = User::role(PlatformRole::SuperAdmin)->get();
        $admins = User::role(PlatformRole::Admin)->get();

        expect(Role::query()->count())->toBe(2)
            ->and($superAdmins)->toHaveCount(1)
            ->and($admins)->toHaveCount(1)
            ->and(Hash::check('rahasia-demo', $superAdmins->sole()->password))->toBeTrue()
            ->and(Hash::check('rahasia-demo', $admins->sole()->password))->toBeTrue();
    });
});

describe('demo stores', function () {
    it('creates six active demo stores in North Jakarta', function () {
        $this->seed();

        $stores = Store::query()->demo()->get();

        expect($stores->pluck('slug')->sort()->values()->all())->toBe(collect(DEMO_STORE_SLUGS)->sort()->values()->all());

        foreach ($stores as $store) {
            expect($store->status)->toBe(StoreStatus::Active)
                ->and($store->verified_at)->not->toBeNull()
                ->and($store->is_demo)->toBeTrue()
                ->and($store->city)->toBe('Jakarta Utara')
                ->and($store->description)->not->toBeEmpty()
                ->and($store->shippingMethods()->count())->toBeGreaterThan(0);
        }
    });

    it('gives every demo store its own owner who cannot sign in', function () {
        $this->seed();

        foreach (Store::query()->demo()->get() as $store) {
            $owner = $store->owners()->sole();

            expect($owner->email)->toEndWith('@example.com')
                ->and($owner->password)->toBeNull()
                ->and(SocialAccount::query()->where('user_id', $owner->id)->exists())->toBeFalse()
                ->and($owner->hasAnyRole(PlatformRole::cases()))->toBeFalse();
        }
    });

    it('fills the bank details with placeholder values', function () {
        $this->seed();

        foreach (Store::query()->demo()->get() as $store) {
            expect($store->bank_account_no)->toBe('0000000000')
                ->and($store->bank_account_name)->toBe('Toko contoh');
        }
    });

    it('creates shipping methods that pass the SaveShippingMethod rules', function () {
        $this->seed();

        foreach (StoreShippingMethod::query()->with('store')->get() as $method) {
            app(SaveShippingMethod::class)->handle($method->store, new ShippingMethodData(
                type: $method->type,
                feeType: $method->fee_type,
                maxRadiusKm: $method->max_radius_km,
                flatFee: $method->flat_fee,
                feePerKm: $method->fee_per_km,
                minFee: $method->min_fee,
                isActive: $method->is_active,
            ));
        }

        expect(StoreShippingMethod::query()->count())->toBeGreaterThan(0);
    });
});

describe('demo products', function () {
    it('shows at least 40 products in the catalog, 6 to 10 per store', function () {
        $this->seed();

        expect(Product::query()->visibleInCatalog()->count())->toBeGreaterThanOrEqual(40);

        foreach (Store::query()->demo()->withCount('products')->get() as $store) {
            expect($store->products_count)->toBeGreaterThanOrEqual(6)->toBeLessThanOrEqual(10);
        }
    });

    it('covers live, fresh, and frozen products and both sales modes', function () {
        $this->seed();

        $conditions = Product::query()->distinct()->pluck('condition')->all();

        expect($conditions)->toContain(ProductCondition::Live)
            ->toContain(ProductCondition::Fresh)
            ->toContain(ProductCondition::Frozen)
            ->and(Product::query()->where('sales_mode', SalesMode::ReadyStock)->exists())->toBeTrue();
    });

    it('has at least 8 pre-order products with a quota, lead time, and cutoff', function () {
        $this->seed();

        $preOrders = Product::query()->where('sales_mode', SalesMode::PreOrder)->get();

        expect($preOrders->count())->toBeGreaterThanOrEqual(8);

        foreach ($preOrders as $product) {
            expect($product->daily_quota)->not->toBeNull()
                ->and($product->lead_time_days)->not->toBeNull()
                ->and($product->cutoff_time)->not->toBeNull();
        }
    });

    it('sells products in the new Udang and Kerang categories', function () {
        $this->seed();

        foreach (['udang', 'kerang'] as $root) {
            $category = Category::query()->where('slug', $root)->sole();

            expect(Product::query()->visibleInCatalog()->whereIn('category_id', $category->descendantIds())->exists())->toBeTrue();
        }
    });

    it('gives every product 1 to 3 photos with their files', function () {
        $this->seed();

        foreach (Product::query()->with('images')->get() as $product) {
            expect($product->images->count())->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(3);

            foreach ($product->images as $image) {
                Storage::disk('public')->assertExists([$image->path, $image->thumbnailPath()]);
            }
        }
    });

    it('confirms the stock on the day the seeder runs', function () {
        $this->travelTo(now()->setDate(2026, 11, 2)->setTime(5, 45));

        $this->seed();

        foreach (ProductVariant::query()->where('is_active', true)->get() as $variant) {
            expect($variant->stock_confirmed_at?->isSameDay(now()))->toBeTrue();
        }
    });

    it('stocks every variant through the stock book from a supplier', function () {
        $this->seed();

        foreach (ProductVariant::query()->get() as $variant) {
            $stockIn = $variant->stockMovements()->where('type', StockMovementType::StockIn->value)->get();

            expect($stockIn)->not->toBeEmpty()
                ->and($stockIn->every(fn (StockMovement $movement): bool => filled($movement->supplier_name)))->toBeTrue()
                ->and($variant->stockMovements()->latest('id')->first()?->stock_after)->toBe($variant->stock);
        }
    });

    it('only creates products in demo stores', function () {
        $this->seed();

        expect(Product::query()->whereHas('store', fn ($store) => $store->real())->count())->toBe(0);
    });
});

describe('running again and in production', function () {
    it('can run twice without duplicating anything', function () {
        $this->seed();
        $first = seededCounts();

        $this->seed();

        expect(seededCounts())->toBe($first);
    });

    it('creates the demo stores in production but no admin accounts', function () {
        $this->app->detectEnvironment(fn () => 'production');

        // db:seed asks for confirmation in production; --force answers it.
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        expect(Role::query()->count())->toBe(2)
            ->and(User::role([PlatformRole::SuperAdmin, PlatformRole::Admin])->count())->toBe(0)
            ->and(Store::query()->demo()->count())->toBe(6)
            ->and(Store::query()->real()->count())->toBe(0)
            ->and(Product::query()->visibleInCatalog()->count())->toBeGreaterThanOrEqual(40);
    });

    it('leaves a real store that existed before untouched', function () {
        $this->travelTo(now()->subDay());
        $store = realStoreBeforeSeeding(['name' => 'UD Kepiting Pak Budi', 'slug' => 'ud-kepiting-pak-budi-asli']);
        $this->travelBack();
        $before = $store->fresh()->getAttributes();

        $this->seed();

        expect($store->fresh()->getAttributes())->toBe($before)
            ->and($store->fresh()->is_demo)->toBeFalse()
            ->and($store->products()->count())->toBe(1);
    });
});

describe('demo owners cannot sign in', function () {
    it('rejects an API login with a demo owner email and any password', function (string $password) {
        $this->seed();
        $email = Store::query()->demo()->firstOrFail()->owners()->sole()->email;

        $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password, 'device_name' => 'Pixel 8'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    })->with([
        'the demo password' => ['password'],
        'any other password' => ['rahasia12345'],
    ]);
});

describe('demo:remove', function () {
    it('removes the demo stores with their products, photos, and owners', function () {
        $this->seed();
        $ownerIds = Store::query()->demo()->get()->flatMap(fn (Store $store) => $store->owners()->pluck('users.id'))->all();

        $this->artisan('demo:remove')
            ->expectsConfirmation(DEMO_REMOVE_QUESTION, 'yes')
            ->assertSuccessful();

        expect(Store::query()->demo()->count())->toBe(0)
            ->and(Product::withTrashed()->count())->toBe(0)
            ->and(ProductImage::query()->count())->toBe(0)
            ->and(Storage::disk('public')->allFiles())->toBe([])
            ->and(User::query()->whereKey($ownerIds)->count())->toBe(0);
    });

    it('never touches a real store, even one with the same name as a demo store', function () {
        $this->seed();
        $store = realStoreBeforeSeeding(['name' => 'UD Kepiting Pak Budi', 'slug' => 'ud-kepiting-pak-budi-asli']);
        $image = $store->products()->sole()->images()->sole();

        $this->artisan('demo:remove')
            ->expectsConfirmation(DEMO_REMOVE_QUESTION, 'yes')
            ->assertSuccessful();

        expect(Store::query()->whereKey($store->id)->exists())->toBeTrue()
            ->and($store->products()->count())->toBe(1)
            ->and(User::query()->where('email', 'pemilik@mail.com')->exists())->toBeTrue();
        Storage::disk('public')->assertExists($image->path);
    });

    it('keeps everything when the confirmation is declined', function () {
        $this->seed();
        $before = seededCounts();

        $this->artisan('demo:remove')
            ->expectsConfirmation(DEMO_REMOVE_QUESTION, 'no')
            ->assertSuccessful();

        expect(seededCounts())->toBe($before);
    });

    it('skips the question with --force', function () {
        $this->seed();

        $this->artisan('demo:remove', ['--force' => true])->assertSuccessful();

        expect(Store::query()->demo()->count())->toBe(0);
    });
});

describe('photo credits', function () {
    it('credits every seeder photo and lists no photo that is missing', function () {
        $credited = collect(config('photo-credits'))
            ->pluck('file')
            ->filter(fn (string $file): bool => ! str_starts_with($file, 'public/'))
            ->sort()
            ->values()
            ->all();

        expect(seederImageFiles())->toBe($credited);
    });

    it('gives every credit a title, author, source, and license', function () {
        foreach (config('photo-credits') as $credit) {
            expect($credit)->toHaveKeys(['file', 'title', 'author', 'source_url', 'license', 'license_url'])
                ->and($credit['source_url'])->toStartWith('https://commons.wikimedia.org/')
                ->and($credit['license'])->not->toBeEmpty();
        }
    });
});
