<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Enums\Unit;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

/*
 * Kepiting (quality standard)
 *   └─ Bakau (Berat per ekor: number in g; Jenis kelamin: select)
 *
 * Kepiting Bakau Super from Toko Bahari, Jakarta Utara, with two active
 * variants (one fresh, one stale and sold out), one inactive, one deleted.
 */
beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 13)->setTime(0, 30));

    $this->kepiting = Category::factory()->create(['name' => 'Kepiting', 'quality_standard' => '**Cangkang keras**, kaki lengkap.']);
    $this->bakau = Category::factory()->childOf($this->kepiting)->create(['name' => 'Bakau']);
    $this->berat = CategoryAttribute::factory()->for($this->kepiting)->create(['name' => 'Berat per ekor', 'type' => AttributeType::Number, 'unit' => 'g']);
    $this->kelamin = CategoryAttribute::factory()->for($this->bakau)->create(['name' => 'Jenis kelamin', 'type' => AttributeType::Select, 'options' => ['Jantan', 'Betina'], 'unit' => null]);

    $this->store = Store::factory()->active()->create([
        'name' => 'Toko Bahari',
        'slug' => 'toko-bahari',
        'city' => 'Jakarta Utara',
        'phone' => '081234567890',
        'bank_name' => 'Bank Rahasia',
        'bank_account_no' => '9876543210',
        'bank_account_name' => 'Pemilik Rahasia',
    ]);

    $this->product = Product::factory()->for($this->store)->for($this->bakau)->create([
        'name' => 'Kepiting Bakau Super',
        'slug' => 'kepiting-bakau-super',
        'description' => 'Kepiting bakau hidup dari tambak Muara Angke, dipilih setiap pagi.',
        'seo_description' => 'Kepiting bakau hidup langsung dari Muara Angke.',
        'condition' => ProductCondition::Live,
        'shelf_life_days' => 2,
        'storage_instructions' => 'Simpan di tempat lembap dan sejuk.',
    ]);
    $this->product->attributeValues()->create(['category_attribute_id' => $this->berat->id, 'value' => '350']);
    $this->product->attributeValues()->create(['category_attribute_id' => $this->kelamin->id, 'value' => 'Jantan']);

    $this->fresh = ProductVariant::factory()->for($this->product)->create(['name' => 'Jantan 300-400 g', 'unit' => Unit::Kg, 'price' => 185_000, 'min_qty' => '0.500']);
    $this->fresh->forceFill(['stock' => '12.500', 'stock_confirmed_at' => now()->subHour()])->save();

    $this->stale = ProductVariant::factory()->for($this->product)->create(['name' => 'Jantan 500 g up', 'unit' => Unit::Kg, 'price' => 240_000, 'min_qty' => '1.000']);
    $this->stale->forceFill(['stock' => '0.000', 'stock_confirmed_at' => now()->subHours(30)])->save();

    ProductVariant::factory()->for($this->product)->create(['name' => 'Varian Nonaktif', 'price' => 99_000, 'is_active' => false]);
    ProductVariant::factory()->for($this->product)->create(['name' => 'Varian Terhapus', 'price' => 50_000])->delete();
});

/**
 * The product page of the shared fixture, or of another slug.
 */
function productPage(string $slug = 'kepiting-bakau-super'): TestResponse
{
    return test()->get('/produk/'.$slug);
}

/**
 * The decoded JSON-LD block of a page.
 *
 * @return array<string, mixed>
 */
function jsonLdOf(string $html): array
{
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    expect($matches)->toHaveKey(1);

    return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
}

describe('content', function () {
    it('shows the product with its active variants, prices, and stock', function () {
        productPage()
            ->assertOk()
            ->assertSeeLivewire('product-page')
            ->assertSee('Kepiting Bakau Super')
            ->assertSee('Kepiting bakau hidup dari tambak Muara Angke, dipilih setiap pagi.')
            ->assertSeeInOrder(['Jantan 300-400 g', 'Rp185.000', '/ '.Unit::Kg->label(), '12,5'])
            ->assertSeeInOrder(['Jantan 500 g up', 'Rp240.000'])
            ->assertDontSee('Varian Nonaktif')
            ->assertDontSee('Varian Terhapus');
    });

    it('marks a stale variant and shows when the stock was updated in WIB', function () {
        productPage()
            ->assertOk()
            ->assertSeeInOrder(['Jantan 300-400 g', 'Stok diperbarui 13 Oktober 2026, 06.30 WIB'])
            ->assertSeeInOrder(['Jantan 500 g up', 'Stok belum dikonfirmasi']);
    });

    it('shows the attributes with their units and the quality standard as markdown', function () {
        productPage()
            ->assertOk()
            ->assertSeeInOrder(['Berat per ekor', '350 g'])
            ->assertSeeInOrder(['Jenis kelamin', 'Jantan'])
            ->assertSee('<strong>Cangkang keras</strong>, kaki lengkap.', escape: false);
    });

    it('uses the quality standard of the category itself before its parent', function () {
        $this->bakau->update(['quality_standard' => 'Standar khusus bakau.']);

        productPage()->assertOk()->assertSee('Standar khusus bakau.')->assertDontSee('Cangkang keras');
    });

    it('shows the condition, shelf life, and storage', function () {
        productPage()
            ->assertOk()
            ->assertSee(ProductCondition::Live->label())
            ->assertSee(SalesMode::ReadyStock->label())
            ->assertSee('2 hari')
            ->assertSee('Simpan di tempat lembap dan sejuk.');
    });

    it('shows the pre-order terms', function () {
        $this->product->update(['sales_mode' => SalesMode::PreOrder, 'daily_quota' => '20.000', 'lead_time_days' => 3, 'cutoff_time' => '15:00']);

        productPage()
            ->assertOk()
            ->assertSee(SalesMode::PreOrder->label())
            ->assertSee('3 hari')
            ->assertSee('15:00')
            ->assertSee('Kuota harian 20');
    });

    it('shows the photos with the product name as alt text', function () {
        $second = ProductImage::factory()->for($this->product)->create(['sort_order' => 2]);
        $first = ProductImage::factory()->for($this->product)->create(['sort_order' => 1]);

        productPage()
            ->assertOk()
            ->assertSeeInOrder([$first->url(), $second->url()], escape: false)
            ->assertSee($first->thumbnailUrl(), escape: false)
            ->assertSee('alt="Kepiting Bakau Super', escape: false);
    });

    it('links the store and its WhatsApp', function () {
        productPage()
            ->assertOk()
            ->assertSee('Toko Bahari')
            ->assertSee('Jakarta Utara')
            ->assertSee('href="'.url('/toko/toko-bahari').'"', escape: false)
            ->assertSee('href="https://wa.me/6281234567890', escape: false)
            ->assertSee('rel="noopener noreferrer"', escape: false);
    });

    it('builds the WhatsApp number from the +62 form too', function () {
        $this->store->update(['phone' => '+6281234567890']);

        productPage()->assertOk()->assertSee('href="https://wa.me/6281234567890', escape: false);
    });

    it('keeps the cart button disabled until checkout exists', function () {
        productPage()
            ->assertOk()
            ->assertSee('Segera hadir')
            ->assertSee('disabled', escape: false);
    });
});

describe('not shown', function () {
    it('is a 404 for a product buyers may not see', function (Closure $hide) {
        $hide($this->product);

        productPage()->assertNotFound();
    })->with([
        'blocked' => [fn (Product $p) => $p->forceFill(['blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai.'])->save()],
        'inactive' => [fn (Product $p) => $p->update(['is_active' => false])],
        'deleted' => [fn (Product $p) => $p->delete()],
        'without an active variant' => [fn (Product $p) => $p->variants()->update(['is_active' => false])],
        'from a suspended store' => [fn (Product $p) => $p->store->forceFill(['status' => 'suspended'])->save()],
        'from a pending store' => [fn (Product $p) => $p->store->forceFill(['status' => 'pending'])->save()],
    ]);

    it('is a 404 for an unknown slug', function () {
        productPage('tidak-ada')->assertNotFound();
    });
});

describe('choosing a variant and quantity', function () {
    it('starts with the first active variant at its minimum quantity', function () {
        Livewire::test('product-page', ['slug' => 'kepiting-bakau-super'])
            ->assertSet('variantId', $this->fresh->id)
            ->assertSet('qty', '0.5');
    });

    it('switches to another active variant and its minimum quantity', function () {
        Livewire::test('product-page', ['slug' => 'kepiting-bakau-super'])
            ->set('variantId', $this->stale->id)
            ->assertSet('variantId', $this->stale->id)
            ->assertSet('qty', '1');
    });

    it('ignores a variant that is not an active variant of this product', function (Closure $variant) {
        Livewire::test('product-page', ['slug' => 'kepiting-bakau-super'])
            ->set('variantId', $variant()->id)
            ->assertSet('variantId', $this->fresh->id);
    })->with([
        'of another product' => [fn () => ProductVariant::factory()->create()],
        'inactive' => [fn () => ProductVariant::query()->where('name', 'Varian Nonaktif')->sole()],
        'deleted' => [fn () => ProductVariant::withTrashed()->where('name', 'Varian Terhapus')->sole()],
    ]);

    it('rejects a quantity it cannot sell', function (string $qty) {
        Livewire::test('product-page', ['slug' => 'kepiting-bakau-super'])
            ->set('qty', $qty)
            ->assertHasErrors('qty');
    })->with([
        'below the minimum' => ['0.4'],
        'zero' => ['0'],
        'negative' => ['-1'],
        'text' => ['satu'],
        'more than 3 decimals' => ['1.2345'],
        'too large' => ['100000'],
    ]);

    it('accepts a quantity at or above the minimum', function (string $qty) {
        Livewire::test('product-page', ['slug' => 'kepiting-bakau-super'])
            ->set('qty', $qty)
            ->assertHasNoErrors('qty');
    })->with(['0.5', '2', '1.25']);

    it('cannot swap the product from the browser', function () {
        $other = ProductVariant::factory()->create()->product;

        expect(fn () => Livewire::test('product-page', ['slug' => 'kepiting-bakau-super'])->set('product', $other->id))
            ->toThrow(Exception::class);
    });
});

describe('SEO', function () {
    it('has the title, meta description, canonical, and Open Graph tags', function () {
        $image = ProductImage::factory()->for($this->product)->create();

        productPage()
            ->assertOk()
            ->assertSee('<title>Kepiting Bakau Super - Toko Bahari, Jakarta Utara | '.config('app.name').'</title>', escape: false)
            ->assertSee('<meta name="description" content="Kepiting bakau hidup langsung dari Muara Angke.">', escape: false)
            ->assertSee('<meta property="og:description" content="Kepiting bakau hidup langsung dari Muara Angke.">', escape: false)
            ->assertSee('<link rel="canonical" href="'.url('/produk/kepiting-bakau-super').'">', escape: false)
            ->assertSee('<meta property="og:type" content="product">', escape: false)
            ->assertSee('<meta property="og:image" content="'.$image->url().'">', escape: false)
            ->assertDontSee('noindex');

        expect($image->url())->toStartWith('http');
    });

    it('leaves out og:image without photos', function () {
        productPage()->assertOk()->assertDontSee('og:image');
    });

    it('cuts the description when there is no seo description', function () {
        $this->product->update(['seo_description' => null, 'description' => str_repeat('Kepiting bakau segar dari Muara Angke. ', 10)]);

        productPage()->assertOk()->assertSee('<meta name="description" content="'.e($this->product->fresh()->metaDescription()).'">', escape: false);
    });

    it('describes the product in JSON-LD', function () {
        $image = ProductImage::factory()->for($this->product)->create();

        $data = jsonLdOf(productPage()->assertOk()->getContent());

        expect($data)
            ->{'@context'}->toBe('https://schema.org')
            ->{'@type'}->toBe('Product')
            ->name->toBe('Kepiting Bakau Super')
            ->sku->toBe((string) $this->product->id)
            ->category->toBe('Bakau')
            ->image->toBe([$image->url()])
            ->and($data['offers'])->toMatchArray([
                '@type' => 'AggregateOffer',
                'lowPrice' => 185000,
                'highPrice' => 240000,
                'priceCurrency' => 'IDR',
                'offerCount' => 2,
                'availability' => 'https://schema.org/InStock',
            ])
            ->and($data['seller'])->toBe(['@type' => 'Organization', 'name' => 'Toko Bahari']);
    });

    it('sets the availability from stock and sales mode', function (Closure $setUp, string $availability) {
        $setUp($this);

        expect(jsonLdOf(productPage()->assertOk()->getContent())['offers']['availability'])
            ->toBe('https://schema.org/'.$availability);
    })->with([
        'all active variants out of stock' => [fn ($t) => $t->fresh->forceFill(['stock' => '0.000'])->save(), 'OutOfStock'],
        'pre-order' => [fn ($t) => $t->product->update(['sales_mode' => SalesMode::PreOrder, 'daily_quota' => '20.000', 'lead_time_days' => 2, 'cutoff_time' => '15:00']), 'PreOrder'],
    ]);
});

describe('security', function () {
    it('escapes markup in the quality standard and drops unsafe links', function () {
        $this->bakau->update(['quality_standard' => "Cangkang keras.\n\n<script>alert(1)</script>\n\n[klik](javascript:alert(2))"]);

        productPage()
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertDontSee('javascript:alert(2)', escape: false)
            ->assertSee('Cangkang keras.');
    });

    it('keeps the JSON-LD valid when the text closes a script tag', function () {
        $this->product->update([
            'name' => 'Kepiting </script><script>alert(1)</script>',
            'description' => 'Segar & hidup </script><img src=x onerror=alert(2)>',
        ]);

        $html = productPage()->assertOk()->getContent();
        $data = jsonLdOf($html);

        expect($data['name'])->toBe('Kepiting </script><script>alert(1)</script>')
            ->and($html)->not->toContain('<script>alert(1)</script>')
            ->and($html)->not->toContain('<img src=x onerror=alert(2)>');
    });

    it('escapes the product name, description, and attribute values', function () {
        $this->product->update(['name' => '<b>Kepiting</b>', 'description' => '<img src=x onerror=alert(1)>']);
        $this->product->attributeValues()->where('category_attribute_id', $this->kelamin->id)->update(['value' => '<i>Jantan</i>']);

        productPage()
            ->assertOk()
            ->assertDontSee('<b>Kepiting</b>', escape: false)
            ->assertDontSee('<img src=x onerror=alert(1)>', escape: false)
            ->assertDontSee('<i>Jantan</i>', escape: false);
    });

    it('never shows bank details or store coordinates', function () {
        $html = productPage()->assertOk()->getContent();

        expect($html)
            ->not->toContain('Bank Rahasia')
            ->not->toContain('9876543210')
            ->not->toContain('Pemilik Rahasia')
            ->not->toContain((string) $this->store->lat)
            ->not->toContain((string) $this->store->lng);
    });
});

describe('layout', function () {
    it('leads with a breadcrumb to the category and its parent', function () {
        productPage()
            ->assertOk()
            ->assertSee('<nav aria-label="Breadcrumb"', escape: false)
            ->assertSeeInOrder([
                'href="'.route('home').'"',
                'href="'.e(route('catalog', ['kategori' => $this->kepiting->slug])).'"',
                'Kepiting',
                'href="'.e(route('catalog', ['kategori' => $this->bakau->slug])).'"',
                'Bakau',
                'aria-current="page"',
                'Kepiting Bakau Super',
            ], escape: false);
    });

    it('leaves the parent out of the breadcrumb for a root category', function () {
        $this->product->update(['category_id' => $this->kepiting->id]);

        $html = productPage()->assertOk()->getContent();
        $breadcrumb = Str::between($html, '<nav aria-label="Breadcrumb"', '</nav>');

        expect($breadcrumb)
            ->toContain(e(route('catalog', ['kategori' => $this->kepiting->slug])))
            ->not->toContain(e(route('catalog', ['kategori' => $this->bakau->slug])));
    });

    it('picks a photo with buttons that say which photo they show', function () {
        ProductImage::factory()->for($this->product)->count(3)->sequence(
            ['sort_order' => 1],
            ['sort_order' => 2],
            ['sort_order' => 3],
        )->create();

        $html = productPage()->assertOk()->getContent();

        expect(substr_count($html, 'aria-label="Foto '))->toBe(3)
            ->and($html)
            ->toMatch('#<button[^>]*aria-label="Foto 1 dari 3"[^>]*aria-pressed="true"#s')
            ->toMatch('#<button[^>]*aria-label="Foto 2 dari 3"[^>]*aria-pressed="false"#s')
            ->toContain('x-bind:aria-pressed');
    });

    it('shows a single photo without thumbnails', function () {
        ProductImage::factory()->for($this->product)->create();

        productPage()
            ->assertOk()
            ->assertDontSee('aria-label="Foto 1 dari 1"', escape: false)
            ->assertDontSee('aria-pressed', escape: false);
    });

    it('fills a product without photos with the empty state', function () {
        productPage()
            ->assertOk()
            ->assertSee('Belum ada foto')
            ->assertDontSee('aria-pressed', escape: false);
    });

    it('marks a stale variant with the warning badge', function () {
        $html = productPage()->assertOk()->getContent();

        expect($html)->toMatch('#<span[^>]*bg-warning-50[^>]*>\s*Stok belum dikonfirmasi\s*</span>#');
    });

    it('shows the condition and sales mode as badges above the name', function () {
        productPage()
            ->assertOk()
            ->assertSeeInOrder([ProductCondition::Live->label(), SalesMode::ReadyStock->label(), '<h1', 'Kepiting Bakau Super'], escape: false);
    });

    it('shows the price of the chosen variant next to the name', function () {
        Livewire::test('product-page', ['slug' => 'kepiting-bakau-super'])
            ->assertSeeHtmlInOrder(['<h1', 'Kepiting Bakau Super', 'Rp185.000', 'Pilih varian'])
            ->set('variantId', $this->stale->id)
            ->assertSeeHtmlInOrder(['<h1', 'Kepiting Bakau Super', 'Rp240.000', 'Pilih varian']);
    });

    it('links the store from the seller card', function () {
        productPage()
            ->assertOk()
            ->assertSeeInOrder(['Dijual oleh', 'Toko Bahari', 'Jakarta Utara', 'Lihat toko']);
    });

    it('opens every new tab with noopener noreferrer', function () {
        $html = productPage()->assertOk()->getContent();

        preg_match_all('#<a\s[^>]*target="_blank"[^>]*>#', $html, $links);

        expect($links[0])->not->toBeEmpty()
            ->each->toContain('rel="noopener noreferrer"');
    });
});
