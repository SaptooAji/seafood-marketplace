<?php

declare(strict_types=1);

use App\Actions\AddProductImage;
use App\Enums\AttributeType;
use App\Enums\StockMovementType;
use App\Enums\StoreRole;
use App\Enums\Unit;
use App\Exceptions\ProductDescriptionUnavailable;
use App\Filament\Seller\Resources\Products\Pages\CreateProduct;
use App\Filament\Seller\Resources\Products\Pages\EditProduct;
use App\Filament\Seller\Resources\Products\Pages\ListProducts;
use App\Filament\Seller\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Filament\Seller\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Text;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
 * Kepiting (Berat per ekor: number, required)
 *   └─ Bakau (Jenis kelamin: select Jantan/Betina, required)
 * Cumi (Ukuran: select Kecil/Besar, required)
 *   └─ Cumi Segar
 *
 * The user owns an active store, which is the current tenant.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->kepiting = Category::factory()->create(['name' => 'Kepiting']);
    $this->bakau = Category::factory()->childOf($this->kepiting)->create(['name' => 'Bakau']);
    $this->cumi = Category::factory()->create(['name' => 'Cumi']);
    $this->cumiSegar = Category::factory()->childOf($this->cumi)->create(['name' => 'Cumi Segar']);

    $this->berat = CategoryAttribute::factory()->for($this->kepiting)->create([
        'name' => 'Berat per ekor', 'type' => AttributeType::Number, 'unit' => 'g', 'is_required' => true,
    ]);
    $this->kelamin = CategoryAttribute::factory()->for($this->bakau)->create([
        'name' => 'Jenis kelamin', 'type' => AttributeType::Select, 'options' => ['Jantan', 'Betina'], 'is_required' => true,
    ]);
    $this->ukuran = CategoryAttribute::factory()->for($this->cumi)->create([
        'name' => 'Ukuran', 'type' => AttributeType::Select, 'options' => ['Kecil', 'Besar'], 'is_required' => true,
    ]);

    $this->user = User::factory()->create();
    $this->store = Store::factory()->active()->create();
    $this->store->users()->attach($this->user, ['role' => StoreRole::Owner->value]);

    $this->actingAs($this->user);
    Filament::setCurrentPanel('seller');
    Filament::setTenant($this->store);
    Filament::bootCurrentPanel();
});

/**
 * A ready stock Kepiting Bakau as the create form takes it, unless overridden.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function panelProductForm(array $overrides = []): array
{
    $test = test();

    return array_merge([
        'category_id' => $test->bakau->id,
        'name' => 'Kepiting Bakau Super',
        'description' => 'Kepiting bakau hidup dari tambak Muara Angke.',
        'condition' => 'live',
        'sales_mode' => 'ready_stock',
        'shelf_life_days' => 2,
        'storage_instructions' => 'Simpan di tempat lembap dan sejuk.',
        'is_active' => true,
        'attributes' => [
            $test->berat->id => '350',
            $test->kelamin->id => 'Jantan',
        ],
    ], $overrides);
}

/**
 * A Kepiting Bakau of the store with its required attribute values.
 *
 * @param  array<string, mixed>  $attributes
 */
function panelBakauProduct(Store $store, array $attributes = []): Product
{
    $test = test();
    $product = Product::factory()->for($store)->for($test->bakau)->create($attributes);
    $product->attributeValues()->create(['category_attribute_id' => $test->berat->id, 'value' => '350']);
    $product->attributeValues()->create(['category_attribute_id' => $test->kelamin->id, 'value' => 'Jantan']);

    return $product;
}

/**
 * A photo row with its two files on the fake public disk.
 */
function panelStoredPhoto(Product $product, int $sortOrder): ProductImage
{
    $image = ProductImage::factory()->for($product)->create([
        'path' => 'products/'.$product->id.'/'.fake()->uuid().'.webp',
        'sort_order' => $sortOrder,
    ]);
    Storage::disk('public')->put($image->path, 'photo');
    Storage::disk('public')->put($image->thumbnailPath(), 'thumbnail');

    return $image;
}

describe('access', function () {
    it('opens the product pages for members of the store', function (StoreRole $role) {
        $user = User::factory()->create();
        $this->store->users()->attach($user, ['role' => $role->value]);
        $product = panelBakauProduct($this->store);

        $this->actingAs($user)->get("/seller/{$this->store->slug}/products")->assertOk();
        $this->actingAs($user)->get("/seller/{$this->store->slug}/products/create")->assertOk();
        $this->actingAs($user)->get("/seller/{$this->store->slug}/products/{$product->id}/edit")->assertOk();
    })->with([
        'owner' => [StoreRole::Owner],
        'staff' => [StoreRole::Staff],
    ]);

    it('returns 404 for the edit page of another store’s product', function () {
        $other = outsideTenant(fn () => panelBakauProduct(Store::factory()->active()->create()));

        $this->get("/seller/{$this->store->slug}/products/{$other->id}/edit")->assertNotFound();
    });

    it('returns 404 for a deleted product', function () {
        $product = panelBakauProduct($this->store);
        $product->delete();

        $this->get("/seller/{$this->store->slug}/products/{$product->id}/edit")->assertNotFound();
    });
});

describe('list', function () {
    it('lists only the products of the tenant store', function () {
        $own = Product::factory()->for($this->store)->count(2)->create();
        $others = outsideTenant(fn () => Product::factory()->count(2)->create());

        Livewire::test(ListProducts::class)
            ->assertCanSeeTableRecords($own)
            ->assertCanNotSeeTableRecords($others);
    });

    it('shows the category and the status of each product', function () {
        $blocked = Product::factory()->for($this->store)->for($this->bakau)->blocked()->create();

        Livewire::test(ListProducts::class)
            ->assertTableColumnStateSet('category.name', 'Bakau', $blocked)
            ->assertSee('Diblokir');
    });
});

describe('create', function () {
    it('creates a product of the tenant store with its attributes', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm(panelProductForm())
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->sole();
        expect($product)
            ->store_id->toBe($this->store->id)
            ->name->toBe('Kepiting Bakau Super')
            ->slug->toBe('kepiting-bakau-super')
            ->and($product->attributeValues()->pluck('value', 'category_attribute_id')->all())
            ->toEqual([$this->berat->id => '350', $this->kelamin->id => 'Jantan']);
    });

    it('lets staff create products', function () {
        $staff = User::factory()->create();
        $this->store->users()->attach($staff, ['role' => StoreRole::Staff->value]);
        $this->actingAs($staff);

        Livewire::test(CreateProduct::class)
            ->fillForm(panelProductForm())
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Product::query()->sole()->store_id)->toBe($this->store->id);
    });

    it('creates a pre-order product with its fields', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm(panelProductForm(['sales_mode' => 'pre_order', 'daily_quota' => '20', 'lead_time_days' => 2, 'cutoff_time' => '15:00']))
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Product::query()->sole())
            ->daily_quota->toBe('20.000')
            ->lead_time_days->toBe(2);
    });

    it('rejects input on the field at fault and saves nothing', function (Closure $overrides, Closure $field) {
        Livewire::test(CreateProduct::class)
            ->fillForm(panelProductForm($overrides()))
            ->call('create')
            ->assertHasFormErrors([$field()]);

        expect(Product::withTrashed()->count())->toBe(0);
    })->with([
        'no name' => [fn () => ['name' => ''], fn () => 'name'],
        'no category' => [fn () => ['category_id' => null], fn () => 'category_id'],
        'an empty required attribute' => [fn () => ['attributes' => [test()->berat->id => '', test()->kelamin->id => 'Jantan']], fn () => 'attributes.'.test()->berat->id],
        'a select attribute outside its options' => [fn () => ['attributes' => [test()->berat->id => '350', test()->kelamin->id => 'Banci']], fn () => 'attributes.'.test()->kelamin->id],
        'a pre-order without a quota' => [fn () => ['sales_mode' => 'pre_order', 'lead_time_days' => 2, 'cutoff_time' => '15:00'], fn () => 'daily_quota'],
        'an seo description longer than 160 characters' => [fn () => ['seo_description' => str_repeat('a', 161)], fn () => 'seo_description'],
    ]);

    it('saves the seo description', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm(panelProductForm(['seo_description' => 'Kepiting bakau hidup dari Muara Angke.']))
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Product::query()->sole()->seo_description)->toBe('Kepiting bakau hidup dari Muara Angke.');
    });

    it('offers only leaf categories', function () {
        Livewire::test(CreateProduct::class)
            ->assertFormFieldExists('category_id', function (Select $field): bool {
                $ids = array_keys($field->getOptions());
                sort($ids);

                return $ids === [$this->bakau->id, $this->cumiSegar->id];
            });
    });

    it('offers the condition and the sales mode as buttons, with no condition picked yet', function () {
        Livewire::test(CreateProduct::class)
            ->assertFormFieldExists('condition', fn (ToggleButtons $field): bool => array_keys($field->getOptions()) === ['live', 'fresh', 'frozen', 'marinated'])
            ->assertFormFieldExists('sales_mode', fn (ToggleButtons $field): bool => array_keys($field->getOptions()) === ['ready_stock', 'pre_order'])
            ->assertSchemaStateSet(['condition' => null, 'sales_mode' => 'ready_stock'], 'form');
    });

    it('swaps the attribute fields when the category changes', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm(['category_id' => $this->bakau->id])
            ->assertFormFieldExists('attributes.'.$this->berat->id)
            ->assertFormFieldExists('attributes.'.$this->kelamin->id)
            ->assertFormFieldDoesNotExist('attributes.'.$this->ukuran->id)
            ->fillForm(['category_id' => $this->cumiSegar->id])
            ->assertFormFieldExists('attributes.'.$this->ukuran->id)
            ->assertFormFieldDoesNotExist('attributes.'.$this->berat->id);
    });

    it('shows the pre-order fields only for pre-order', function () {
        Livewire::test(CreateProduct::class)
            ->fillForm(['sales_mode' => 'ready_stock'])
            ->assertFormFieldHidden('daily_quota')
            ->assertFormFieldHidden('lead_time_days')
            ->assertFormFieldHidden('cutoff_time')
            ->fillForm(['sales_mode' => 'pre_order'])
            ->assertFormFieldVisible('daily_quota')
            ->assertFormFieldVisible('lead_time_days')
            ->assertFormFieldVisible('cutoff_time');
    });

    it('has no field for the stock, the store, or a block', function (string $field) {
        Livewire::test(CreateProduct::class)->assertFormFieldDoesNotExist($field);
    })->with(['stock', 'store_id', 'blocked_at', 'block_reason']);
});

describe('edit', function () {
    it('fills the form with the product and its attribute values', function () {
        $product = panelBakauProduct($this->store, ['name' => 'Kepiting Bakau']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormSet([
                'name' => 'Kepiting Bakau',
                'category_id' => $this->bakau->id,
                'attributes.'.$this->berat->id => '350',
                'attributes.'.$this->kelamin->id => 'Jantan',
            ]);
    });

    it('saves the changes and keeps the slug', function () {
        $product = panelBakauProduct($this->store, ['name' => 'Kepiting Bakau', 'slug' => 'kepiting-bakau']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm([
                'name' => 'Kepiting Bakau Jumbo',
                'attributes' => [$this->berat->id => '500', $this->kelamin->id => 'Betina'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($product->fresh())
            ->name->toBe('Kepiting Bakau Jumbo')
            ->slug->toBe('kepiting-bakau')
            ->and($product->attributeValues()->pluck('value', 'category_attribute_id')->all())
            ->toEqual([$this->berat->id => '500', $this->kelamin->id => 'Betina']);
    });

    it('keeps the seo description when saving other changes', function () {
        $product = panelBakauProduct($this->store, ['seo_description' => 'Ringkasan dari aplikasi.']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormSet(['seo_description' => 'Ringkasan dari aplikasi.'])
            ->fillForm(['name' => 'Kepiting Bakau Jumbo'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($product->fresh()->seo_description)->toBe('Ringkasan dari aplikasi.');
    });

    it('shows the block reason and does not let a blocked product be turned on', function () {
        $product = panelBakauProduct($this->store, ['is_active' => false, 'blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai produk.']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertSee('Foto tidak sesuai produk.')
            ->fillForm(['is_active' => true])
            ->call('save')
            ->assertHasFormErrors(['is_active']);

        expect($product->fresh()->is_active)->toBeFalse();
    });

    it('soft deletes the product', function () {
        $product = panelBakauProduct($this->store);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction(DeleteAction::class);

        $this->assertSoftDeleted($product);
    });
});

describe('variants', function () {
    beforeEach(function () {
        $this->product = panelBakauProduct($this->store);
        $this->variant = ProductVariant::factory()->for($this->product)->create([
            'name' => 'Jantan 300-400 g', 'unit' => Unit::Kg, 'price' => 185_000, 'min_qty' => '0.500', 'stock' => '10.000',
        ]);
    });

    it('lists the variants with their stock', function () {
        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->assertCanSeeTableRecords([$this->variant])
            ->assertTableColumnStateSet('stock', '10.000', $this->variant);
    });

    it('adds a variant with its opening stock in the stock book', function () {
        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'name' => 'Jantan 500 g up',
                'unit' => 'kg',
                'price' => 240_000,
                'min_qty' => '1',
                'initial_stock' => '7.5',
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $variant = $this->product->variants()->where('name', 'Jantan 500 g up')->sole();
        expect($variant)
            ->price->toBe(240_000)
            ->stock->toBe('7.500')
            ->and(StockMovement::query()->sole())
            ->variant_id->toBe($variant->id)
            ->type->toBe(StockMovementType::Adjustment)
            ->created_by->toBe($this->user->id);
    });

    it('rejects a variant name already used in the product', function () {
        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'name' => 'jantan 300-400 G',
                'unit' => 'kg',
                'price' => 240_000,
                'min_qty' => '1',
            ])
            ->assertHasActionErrors(['name']);

        expect($this->product->variants()->count())->toBe(1);
    });

    it('changes the price and leaves the stock alone', function () {
        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->callAction(TestAction::make('edit')->table($this->variant), data: [
                'name' => 'Jantan 300-400 g',
                'unit' => 'kg',
                'price' => 199_000,
                'min_qty' => '0.5',
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        expect($this->variant->fresh())
            ->price->toBe(199_000)
            ->stock->toBe('10.000')
            ->and(StockMovement::query()->count())->toBe(0);
    });

    it('has no stock field when editing a variant', function () {
        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->mountAction(TestAction::make('edit')->table($this->variant))
            ->assertFormFieldDoesNotExist('stock')
            ->assertFormFieldDoesNotExist('initial_stock');
    });

    it('soft deletes a variant', function () {
        Livewire::test(VariantsRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->callAction(TestAction::make('delete')->table($this->variant));

        $this->assertSoftDeleted($this->variant);
    });
});

describe('photos', function () {
    beforeEach(function () {
        $this->product = panelBakauProduct($this->store);
    });

    it('uploads a photo as WebP with its thumbnail', function () {
        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'photo' => UploadedFile::fake()->image('tuna.jpg', 1200, 900),
            ])
            ->assertHasNoActionErrors();

        $image = $this->product->images()->sole();
        expect($image->path)->toStartWith('products/'.$this->product->id.'/')->toEndWith('.webp');
        Storage::disk('public')->assertExists([$image->path, $image->thumbnailPath()]);
    });

    it('rejects a file that is not an image and stores nothing', function (Closure $file) {
        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->callAction(TestAction::make('create')->table(), data: ['photo' => $file()])
            ->assertHasActionErrors(['photo']);

        expect($this->product->images()->count())->toBe(0)
            ->and(Storage::disk('public')->allFiles('products'))->toBe([]);
    })->with([
        'a PDF' => [fn () => UploadedFile::fake()->create('katalog.pdf', 100, 'application/pdf')],
        'text named as a JPG' => [fn () => UploadedFile::fake()->createWithContent('tuna.jpg', 'not an image')],
    ]);

    it('refuses a photo below the standard on the photo field and stores nothing', function (Closure $file) {
        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->callAction(TestAction::make('create')->table(), data: ['photo' => $file()])
            // The wording is checked on the Action and the API; here, that it lands on the field.
            ->assertHasActionErrors(['photo']);

        expect($this->product->images()->count())->toBe(0)
            ->and(Storage::disk('public')->allFiles('products'))->toBe([]);
    })->with('photos below the standard');

    it('offers square and 4:3 crops in the photo editor and says what a good photo is', function () {
        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->mountAction(TestAction::make('create')->table())
            ->assertFormFieldExists('photo', function (FileUpload $field): bool {
                $helper = $field->getChildSchema($field::BELOW_CONTENT_SCHEMA_KEY)?->getComponents()[0] ?? null;

                return $field->hasImageEditor()
                    && array_keys($field->getImageEditorAspectRatioOptionsForJs()) === ['1:1', '4:3', '3:4']
                    && $helper instanceof Text
                    && $helper->getContent() === 'Foto terang, produk di tengah, sisi terpendek minimal 600 piksel.';
            });
    });

    it('rejects a ninth photo', function () {
        foreach (range(1, 8) as $sortOrder) {
            panelStoredPhoto($this->product, $sortOrder);
        }

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'photo' => UploadedFile::fake()->image('tuna.jpg', 800, 600),
            ])
            ->assertHasActionErrors(['photo']);

        expect($this->product->images()->count())->toBe(8);
    });

    it('shows the upload limit on the photo field and stores nothing', function () {
        config(['catalog.max_photos' => 100]);
        foreach (range(1, 10) as $_) {
            app(AddProductImage::class)->handle($this->product, UploadedFile::fake()->image('tuna.jpg', 800, 600), $this->user);
        }

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'photo' => UploadedFile::fake()->image('tuna.jpg', 800, 600),
            ])
            ->assertHasActionErrors(['photo']);

        expect($this->product->images()->count())->toBe(10)
            ->and(Storage::disk('public')->allFiles('products'))->toHaveCount(20);
    });

    it('deletes a photo with its files and moves the later photos up', function () {
        $first = panelStoredPhoto($this->product, 1);
        $second = panelStoredPhoto($this->product, 2);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->callAction(TestAction::make('delete')->table($first));

        $this->assertModelMissing($first);
        expect($second->fresh()->sort_order)->toBe(1);
        Storage::disk('public')->assertMissing([$first->path, $first->thumbnailPath()]);
    });

    it('reorders the photos', function () {
        $a = panelStoredPhoto($this->product, 1);
        $b = panelStoredPhoto($this->product, 2);
        $c = panelStoredPhoto($this->product, 3);

        Livewire::test(ImagesRelationManager::class, ['ownerRecord' => $this->product, 'pageClass' => EditProduct::class])
            ->call('reorderTable', [(string) $c->id, (string) $a->id, (string) $b->id]);

        expect($this->product->images()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id]);
    });
});

/**
 * Gemini answers with the given response, or with a suggestion.
 */
function fakePanelGemini(mixed $response = null): void
{
    Http::fake(['https://generativelanguage.googleapis.com/*' => $response ?? Http::response([
        'candidates' => [[
            'content' => ['role' => 'model', 'parts' => [['text' => json_encode([
                'description' => "Kepiting bakau jantan hidup, sekitar 350 g per ekor.\n\nSiap dikirim hari ini.",
                'seo_description' => 'Kepiting bakau jantan hidup 350 g per ekor.',
            ])]]],
            'finishReason' => 'STOP',
        ]],
    ])]);
}

describe('description suggestion', function () {
    beforeEach(function () {
        Http::preventStrayRequests();

        config([
            'services.gemini.key' => 'AIzaFAKE-panel-test-key-789',
            'services.gemini.model' => 'gemini-test-flash',
            'services.gemini.fallback_models' => [],
            'services.gemini.daily_limit' => 500,
        ]);

        $this->suggestAction = TestAction::make('suggestDescription')->schemaComponent('description', 'form');
    });

    it('fills the description and SEO summary without saving', function () {
        fakePanelGemini();

        Livewire::test(CreateProduct::class)
            ->fillForm(panelProductForm(['description' => null]))
            ->callAction($this->suggestAction)
            ->assertSchemaStateSet([
                'description' => "Kepiting bakau jantan hidup, sekitar 350 g per ekor.\n\nSiap dikirim hari ini.",
                'seo_description' => 'Kepiting bakau jantan hidup 350 g per ekor.',
            ], 'form');

        expect(Product::query()->count())->toBe(0);
    });

    it('fills the edit form and leaves the saved product alone', function () {
        fakePanelGemini();
        $product = panelBakauProduct($this->store, ['description' => 'Deskripsi lama.']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->callAction($this->suggestAction)
            ->assertSchemaStateSet(['seo_description' => 'Kepiting bakau jantan hidup 350 g per ekor.'], 'form');

        expect($product->fresh()->description)->toBe('Deskripsi lama.');
    });

    it('lets staff use it', function () {
        fakePanelGemini();
        $staff = User::factory()->create();
        $this->store->users()->attach($staff, ['role' => StoreRole::Staff->value]);
        $this->actingAs($staff);

        Livewire::test(CreateProduct::class)
            ->fillForm(panelProductForm(['description' => null]))
            ->callAction($this->suggestAction)
            ->assertSchemaStateSet(['seo_description' => 'Kepiting bakau jantan hidup 350 g per ekor.'], 'form');
    });

    it('asks for confirmation only when a description is already written', function (?string $description, bool $confirms) {
        Livewire::test(CreateProduct::class)
            ->fillForm(panelProductForm(['description' => $description]))
            ->assertActionExists($this->suggestAction, fn (Action $action): bool => $action->isConfirmationRequired() === $confirms);
    })->with([
        'empty' => [null, false],
        'written' => ['Deskripsi saya sendiri.', true],
    ]);

    it('keeps the form as it is when the confirmation is cancelled', function () {
        fakePanelGemini();

        Livewire::test(CreateProduct::class)
            ->fillForm(panelProductForm(['description' => 'Deskripsi saya sendiri.']))
            ->mountAction($this->suggestAction)
            ->unmountAction()
            ->assertSchemaStateSet(['description' => 'Deskripsi saya sendiri.', 'seo_description' => null], 'form');

        Http::assertNothingSent();
    });

    it('shows the button only when an API key is set', function (string $key, bool $shown) {
        config(['services.gemini.key' => $key]);

        $page = Livewire::test(CreateProduct::class);

        $shown ? $page->assertSee('Buat deskripsi dengan AI') : $page->assertDontSee('Buat deskripsi dengan AI');
    })->with([
        'with a key' => ['AIzaFAKE-panel-test-key-789', true],
        'without a key' => ['', false],
    ]);

    it('shows the general message and keeps the form when Gemini fails', function () {
        fakePanelGemini(Http::response(['error' => ['message' => 'API key AIzaFAKE-panel-test-key-789 not valid']], 400));

        Livewire::test(CreateProduct::class)
            ->fillForm(panelProductForm(['description' => 'Deskripsi saya sendiri.']))
            ->callAction($this->suggestAction)
            ->assertNotified((new ProductDescriptionUnavailable)->getMessage())
            ->assertSchemaStateSet(['description' => 'Deskripsi saya sendiri.', 'seo_description' => null], 'form');
    });

    it('names the missing fields and marks them before asking', function (array $overrides, string $message, array $fields) {
        fakePanelGemini();

        Livewire::test(CreateProduct::class)
            ->fillForm(panelProductForm(['description' => null, ...$overrides]))
            ->callAction($this->suggestAction)
            ->assertNotified($message)
            ->assertHasFormErrors($fields)
            ->assertSchemaStateSet(['description' => null], 'form');

        Http::assertNothingSent();
    })->with([
        'no category' => [['category_id' => null], 'Lengkapi kategori sebelum meminta saran deskripsi.', ['category_id']],
        'no name' => [['name' => '  '], 'Lengkapi nama sebelum meminta saran deskripsi.', ['name']],
        'no condition' => [['condition' => null], 'Lengkapi kondisi sebelum meminta saran deskripsi.', ['condition']],
        'no sales mode' => [['sales_mode' => null], 'Lengkapi mode jual sebelum meminta saran deskripsi.', ['sales_mode']],
        'two missing' => [['name' => '', 'condition' => null], 'Lengkapi nama dan kondisi sebelum meminta saran deskripsi.', ['name', 'condition']],
        'three missing' => [['category_id' => null, 'name' => '', 'condition' => null], 'Lengkapi kategori, nama, dan kondisi sebelum meminta saran deskripsi.', ['category_id', 'name', 'condition']],
    ]);

    it('clears the marks once the suggestion is made', function () {
        fakePanelGemini();

        Livewire::test(CreateProduct::class)
            ->fillForm(panelProductForm(['description' => null, 'condition' => null]))
            ->callAction($this->suggestAction)
            ->assertHasFormErrors(['condition'])
            ->fillForm(['condition' => 'live'])
            ->callAction($this->suggestAction)
            ->assertHasNoFormErrors();
    });

    it('shows the limit message and keeps the form after 3 requests in a minute', function () {
        fakePanelGemini();
        $page = Livewire::test(CreateProduct::class)->fillForm(panelProductForm(['description' => null]));

        $page->callAction($this->suggestAction)->callAction($this->suggestAction)->callAction($this->suggestAction);
        $page->fillForm(['description' => 'Deskripsi saya sendiri.'])
            ->callAction($this->suggestAction)
            ->assertNotified('Terlalu sering meminta saran deskripsi. Tunggu sebentar lalu coba lagi.')
            ->assertSchemaStateSet(['description' => 'Deskripsi saya sendiri.'], 'form');

        Http::assertSentCount(3);
    });

    it('counts the characters of the SEO summary', function (?string $summary, string $hint) {
        Livewire::test(CreateProduct::class)
            ->fillForm(['seo_description' => $summary])
            ->assertFormFieldExists('seo_description', fn (Textarea $field): bool => $field->getHint() === $hint);
    })->with([
        'empty' => [null, '0/160'],
        'written' => ['Kepiting bakau hidup', '20/160'],
        'multibyte' => ['Kepiting “super”', '16/160'],
    ]);
});
