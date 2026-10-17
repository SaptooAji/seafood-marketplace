<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Enums\ShippingMethodType;
use App\Enums\Unit;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Support\StockCheckTime;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Livewire\Features\SupportTesting\Testable;
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
            ->assertSee('Pesan sebelum 15.00 hari ini, dikirim Jumat')
            ->assertSee('Kuota 20 kg per hari');
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
        $this->stale->forceFill(['stock' => '3.000'])->save();

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
        'sold out' => [fn () => test()->stale],
    ]);

    it('cannot swap the product from the browser', function () {
        $other = ProductVariant::factory()->create()->product;

        expect(fn () => Livewire::test('product-page', ['slug' => 'kepiting-bakau-super'])->set('product', $other->id))
            ->toThrow(Exception::class);
    });
});

describe('SEO', function () {
    it('keeps a product of a demo store out of search results', function () {
        $this->store->forceFill(['is_demo' => true])->save();

        productPage()
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', escape: false);
    });

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

    it('shows the photos as a mosaic of the main photo and the next two', function () {
        $images = ProductImage::factory()->for($this->product)->count(4)->sequence(
            ['sort_order' => 1],
            ['sort_order' => 2],
            ['sort_order' => 3],
            ['sort_order' => 4],
        )->create();

        $html = productPage()->assertOk()->getContent();

        expect($html)
            ->toMatch('#<img[^>]*src="'.preg_quote($images[0]->url(), '#').'"[^>]*alt="Kepiting Bakau Super, foto 1 dari 4"#')
            ->toMatch('#<button[^>]*data-photo-tile[^>]*aria-label="Tampilkan foto 2 dari 4"#')
            ->toMatch('#<button[^>]*data-photo-tile[^>]*aria-label="Tampilkan foto 3 dari 4"#')
            ->toContain($images[1]->thumbnailUrl())
            ->toContain($images[2]->thumbnailUrl())
            ->and(substr_count($html, 'data-photo-tile'))->toBe(2);
    });

    it('moves between the photos with previous and next buttons', function () {
        ProductImage::factory()->for($this->product)->count(3)->sequence(['sort_order' => 1], ['sort_order' => 2], ['sort_order' => 3])->create();

        expect(productPage()->assertOk()->getContent())
            ->toMatch('#<button[^>]*aria-label="Foto sebelumnya"#')
            ->toMatch('#<button[^>]*aria-label="Foto berikutnya"#')
            ->toContain('1 dari 3');
    });

    it('gives two photos one tall tile', function () {
        ProductImage::factory()->for($this->product)->count(2)->sequence(['sort_order' => 1], ['sort_order' => 2])->create();

        expect(substr_count(productPage()->assertOk()->getContent(), 'data-photo-tile'))->toBe(1);
    });

    it('shows a single photo without tiles or buttons', function () {
        ProductImage::factory()->for($this->product)->create();

        productPage()
            ->assertOk()
            ->assertDontSee('data-photo-tile', escape: false)
            ->assertDontSee('Foto berikutnya');
    });

    it('fills a product without photos with the empty state', function () {
        productPage()
            ->assertOk()
            ->assertSee('Belum ada foto')
            ->assertDontSee('data-photo-tile', escape: false)
            ->assertDontSee('Foto berikutnya');
    });

    it('keeps the photo list out of inline scripts', function () {
        ProductImage::factory()->for($this->product)->count(2)->sequence(['sort_order' => 1], ['sort_order' => 2])->create();
        $this->product->update(['name' => 'Kepiting "Bakau" </script>']);

        expect(productPage()->assertOk()->getContent())
            ->toContain('data-photos="')
            ->not->toContain('Kepiting "Bakau" </script>');
    });

    it('marks a stale variant with the warning badge', function () {
        $html = productPage()->assertOk()->getContent();

        expect($html)->toMatch('#<span[^>]*bg-warning-50[^>]*>\s*Stok belum dikonfirmasi\s*</span>#');
    });

    it('shows the condition and sales mode as chips under the name', function () {
        productPage()
            ->assertOk()
            ->assertSeeInOrder(['<h1', 'Kepiting Bakau Super', ProductCondition::Live->label(), SalesMode::ReadyStock->label()], escape: false);
    });

    it('shows the price of the chosen variant next to the name', function () {
        $this->stale->forceFill(['stock' => '3.000'])->save();

        Livewire::test('product-page', ['slug' => 'kepiting-bakau-super'])
            ->assertSeeHtmlInOrder(['<h1', 'Kepiting Bakau Super', 'Rp185.000', 'Pilih ukuran'])
            ->set('variantId', $this->stale->id)
            ->assertSeeHtmlInOrder(['<h1', 'Kepiting Bakau Super', 'Rp240.000', 'Pilih ukuran']);
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

/**
 * Make the fixture product a pre-order product.
 *
 * @param  array<string, mixed>  $attributes
 */
function makeOrderPreOrder(array $attributes = []): void
{
    test()->product->update(['sales_mode' => SalesMode::PreOrder, 'daily_quota' => '20.000', 'lead_time_days' => 1, 'cutoff_time' => '15:00', ...$attributes]);
}

/**
 * Friday 16 October 2026 at the given time in WIB.
 */
function orderFridayAt(string $time): void
{
    test()->travelTo(CarbonImmutable::parse("2026-10-16 {$time}", StockCheckTime::TIMEZONE)->utc());
}

function orderBuilder(): Testable
{
    return Livewire::test('product-page', ['slug' => 'kepiting-bakau-super']);
}

describe('order steps', function () {
    it('numbers the steps of a ready stock product 1 to 3 without a delivery date', function () {
        orderBuilder()
            ->assertSeeHtmlInOrder(['data-step="1"', 'Pilih ukuran', 'data-step="2"', 'Berapa kilo?', 'data-step="3"', 'Diantar atau ambil sendiri?'])
            ->assertDontSeeHtml('data-step="4"')
            ->assertDontSee('Kapan mau diterima?')
            ->assertDontSee('Tanggal kirim');
    });

    it('adds the delivery date as step 3 of a pre-order product', function () {
        makeOrderPreOrder();

        orderBuilder()->assertSeeHtmlInOrder(['data-step="1"', 'Pilih ukuran', 'data-step="2"', 'Berapa kilo?', 'data-step="3"', 'Kapan mau diterima?', 'data-step="4"', 'Diantar atau ambil sendiri?']);
    });

    it('asks for the quantity in the unit of the chosen variant', function () {
        $this->fresh->update(['unit' => Unit::Piece]);

        orderBuilder()->assertSee('Berapa '.mb_strtolower(Unit::Piece->label()).'?');
    });

    it('lists the active variants with price and unit, and disables the sold-out ones', function () {
        $html = orderBuilder()
            ->assertSeeHtmlInOrder(['Jantan 300-400 g', 'Rp185.000', 'Jantan 500 g up', 'Rp240.000'])
            ->html();

        expect($html)
            ->toMatch('#<input[^>]*value="'.$this->stale->id.'"[^>]*disabled#')
            ->not->toMatch('#<input[^>]*value="'.$this->fresh->id.'"[^>]*disabled#');
    });

    it('starts with the first variant that has stock', function () {
        $this->fresh->forceFill(['stock' => '0.000'])->save();
        $this->stale->forceFill(['stock' => '4.000'])->save();

        orderBuilder()->assertSet('variantId', $this->stale->id)->assertSet('qty', '1');
    });
});

describe('quantity', function () {
    it('steps by the minimum quantity within the stock', function () {
        orderBuilder()
            ->call('decrement')
            ->assertSet('qty', '0.5')
            ->call('increment')
            ->assertSet('qty', '1')
            ->call('increment')
            ->assertSet('qty', '1.5')
            ->set('qty', '12.5')
            ->call('increment')
            ->assertSet('qty', '12.5');
    });

    it('brings a quantity it cannot sell back to the nearest valid one', function (string $typed, string $kept) {
        orderBuilder()->set('qty', $typed)->assertSet('qty', $kept)->assertHasNoErrors();
    })->with([
        'below the minimum' => ['0.4', '0.5'],
        'zero' => ['0', '0.5'],
        'negative' => ['-1', '0.5'],
        'above the stock' => ['20', '12.5'],
        'not a multiple, rounded down' => ['1.2', '1'],
        'not a multiple, rounded up' => ['1.3', '1.5'],
        'halfway, rounded up' => ['1.25', '1.5'],
        'with a comma' => ['1,5', '1.5'],
        'too large' => ['100000', '12.5'],
    ]);

    it('keeps the previous quantity for text that is not a number', function (string $typed) {
        orderBuilder()->set('qty', '2')->set('qty', $typed)->assertSet('qty', '2');
    })->with(['satu', '', '1e3', '1.2.3', '0x10']);

    it('keeps a valid quantity as it is', function (string $qty) {
        orderBuilder()->set('qty', $qty)->assertSet('qty', $qty);
    })->with(['0.5', '2', '12.5']);

    it('limits a pre-order product by the daily quota instead of the stock', function () {
        makeOrderPreOrder();

        orderBuilder()
            ->set('qty', '15')
            ->assertSet('qty', '15')
            ->set('qty', '25')
            ->assertSet('qty', '20');
    });

    it('lets a pre-order product without a daily quota go up to the maximum', function () {
        makeOrderPreOrder(['daily_quota' => null]);

        orderBuilder()->set('qty', '50')->assertSet('qty', '50');
    });

    it('shows "Stok habis" and disables the next steps when every variant is sold out', function () {
        $this->fresh->forceFill(['stock' => '0.000'])->save();
        StoreShippingMethod::factory()->for($this->store)->pickup()->create();

        $html = productPage()->assertOk()->assertSee('Stok habis')->getContent();

        expect($html)
            ->toMatch('#<button[^>]*wire:click="increment"[^>]*disabled#')
            ->toMatch('#<button[^>]*wire:click="decrement"[^>]*disabled#')
            ->toMatch('#<input[^>]*name="cara-terima"[^>]*disabled#');
    });
});

describe('delivery date', function () {
    it('offers the first three dates from the cutoff and lead time', function (string $time, array $dates, string $firstDay) {
        orderFridayAt($time);
        makeOrderPreOrder();

        $component = orderBuilder()->assertSet('deliveryDate', $dates[0])->assertSee($firstDay);

        foreach ($dates as $date) {
            $component->assertSeeHtml('data-delivery-date="'.$date.'"');
        }
    })->with([
        'before the cutoff' => ['10:00', ['2026-10-17', '2026-10-18', '2026-10-19'], 'Sabtu'],
        'after the cutoff' => ['16:00', ['2026-10-18', '2026-10-19', '2026-10-20'], 'Minggu'],
        'exactly at the cutoff' => ['15:00', ['2026-10-18', '2026-10-19', '2026-10-20'], 'Minggu'],
    ]);

    it('shows the daily quota on each date in the unit of the variant', function () {
        orderFridayAt('10:00');
        makeOrderPreOrder();

        expect(substr_count(orderBuilder()->html(), 'Kuota 20 kg per hari'))->toBe(3);
    });

    it('shows the dates without a quota line when there is no daily quota', function () {
        orderFridayAt('10:00');
        makeOrderPreOrder(['daily_quota' => null]);

        orderBuilder()->assertSeeHtml('data-delivery-date="2026-10-17"')->assertDontSee('Kuota');
    });

    it('ignores a date it does not offer', function (string $date) {
        orderFridayAt('10:00');
        makeOrderPreOrder();

        orderBuilder()
            ->set('deliveryDate', '2026-10-18')
            ->set('deliveryDate', $date)
            ->assertSet('deliveryDate', '2026-10-18');
    })->with([
        'too early' => ['2026-10-16'],
        'too late' => ['2026-10-20'],
        'not a date' => ['besok'],
        'empty' => [''],
    ]);

    it('shows the order deadline and first delivery day of a pre-order product', function (string $time, string $line) {
        orderFridayAt($time);
        makeOrderPreOrder();

        productPage()->assertOk()->assertSee($line);
    })->with([
        'before the cutoff' => ['10:00', 'Pesan sebelum 15.00 hari ini, dikirim Sabtu'],
        'after the cutoff' => ['16:00', 'Pesan sebelum 15.00 besok, dikirim Minggu'],
    ]);

    it('has no deadline line for a ready stock product', function () {
        productPage()->assertOk()->assertDontSee('Pesan sebelum');
    });
});

describe('receiving the order', function () {
    it('shows each active shipping method of the store with its fee', function () {
        StoreShippingMethod::factory()->for($this->store)->pickup()->create();
        StoreShippingMethod::factory()->for($this->store)->ownDeliveryFlat()->create();
        StoreShippingMethod::factory()->for($this->store)->expedition()->create(['is_active' => false]);

        orderBuilder()
            ->assertSeeInOrder([ShippingMethodType::Pickup->label(), 'Gratis'])
            ->assertSeeInOrder([ShippingMethodType::OwnDelivery->label(), 'Rp10.000'])
            ->assertDontSee(ShippingMethodType::Expedition->label());
    });

    it('says the fee is set at checkout for per-km and manual fees', function (Closure $method) {
        $method($this->store);

        orderBuilder()->assertSee('Dihitung saat checkout');
    })->with([
        'own delivery per km' => [fn (Store $store) => StoreShippingMethod::factory()->for($store)->ownDeliveryPerKm()->create()],
        'ojol' => [fn (Store $store) => StoreShippingMethod::factory()->for($store)->ojol()->create()],
    ]);

    it('asks the buyer to check with the seller when the store has no active method', function () {
        StoreShippingMethod::factory()->for($this->store)->pickup()->create(['is_active' => false]);

        orderBuilder()->assertSee('Tanyakan cara kirim ke penjual');
    });

    it('ignores a shipping method of another store or one that is inactive', function (Closure $method) {
        $own = StoreShippingMethod::factory()->for($this->store)->pickup()->create();

        orderBuilder()
            ->set('shippingMethodId', $own->id)
            ->set('shippingMethodId', $method()->id)
            ->assertSet('shippingMethodId', $own->id);
    })->with([
        'of another store' => [fn () => StoreShippingMethod::factory()->ownDeliveryFlat()->create()],
        'inactive' => [fn () => StoreShippingMethod::factory()->for(test()->store)->ownDeliveryFlat()->create(['is_active' => false])],
    ]);
});

describe('summary', function () {
    it('totals the price times the quantity', function () {
        orderBuilder()
            ->set('qty', '1.5')
            ->assertSeeHtml('data-order-total="277500"')
            ->assertSee('Rp277.500');
    });

    it('adds a flat delivery fee to the total', function () {
        $flat = StoreShippingMethod::factory()->for($this->store)->ownDeliveryFlat()->create();

        orderBuilder()
            ->set('qty', '1.5')
            ->set('shippingMethodId', $flat->id)
            ->assertSeeHtml('data-order-total="287500"')
            ->assertSee('Rp287.500');
    });

    it('leaves out a fee that is set at checkout', function () {
        $perKm = StoreShippingMethod::factory()->for($this->store)->ownDeliveryPerKm()->create();

        orderBuilder()
            ->set('qty', '1.5')
            ->set('shippingMethodId', $perKm->id)
            ->assertSeeHtml('data-order-total="277500"')
            ->assertSee('Dihitung saat checkout');
    });

    it('rounds a total that is not a whole rupiah', function () {
        $this->fresh->update(['price' => 33_333, 'min_qty' => '0.001']);

        // 33,333 x 0.005 = 166.665, rounded half up.
        orderBuilder()->set('qty', '0.005')->assertSeeHtml('data-order-total="167"');
    });

    it('sums up the variant, quantity, date, and way of receiving', function () {
        orderFridayAt('10:00');
        makeOrderPreOrder();
        $pickup = StoreShippingMethod::factory()->for($this->store)->pickup()->create();

        orderBuilder()
            ->set('qty', '2')
            ->set('shippingMethodId', $pickup->id)
            ->assertSeeHtmlInOrder(['aria-labelledby="ringkasan-judul"', 'Jantan 300-400 g', '2 kg', 'Sabtu, 17 Okt', ShippingMethodType::Pickup->label(), 'data-order-total="370000"', 'Segera hadir']);
    });

    it('keeps the cart button disabled', function () {
        expect(productPage()->assertOk()->getContent())->toMatch('#<button[^>]*disabled[^>]*>\s*<span>Tambah ke keranjang</span>\s*<span[^>]*>Segera hadir</span>#');
    });
});

describe('top band', function () {
    it('heads the page with the name, store, condition, and stock check', function () {
        productPage()
            ->assertOk()
            ->assertSeeInOrder(['<h1', 'Kepiting Bakau Super', 'Dari Toko Bahari, Jakarta Utara', ProductCondition::Live->label(), 'Dicek hari ini 06.30'], escape: false);
    });

    it('shows the stale stock marker instead of the check time', function () {
        $this->fresh->forceFill(['stock_confirmed_at' => now()->subHours(30)])->save();

        productPage()->assertOk()->assertSee('Stok belum dikonfirmasi')->assertDontSee('Dicek hari ini');
    });

    it('puts the description, quality standard, and details in tabs under the photos', function () {
        $html = productPage()->assertOk()->getContent();

        expect($html)
            ->toMatch('#role="tablist"#')
            ->toMatch('#role="tab"[^>]*aria-controls="tab-deskripsi"[^>]*>\s*Deskripsi#')
            ->toMatch('#role="tab"[^>]*aria-controls="tab-standar"[^>]*>\s*Standar kualitas#')
            ->toMatch('#role="tab"[^>]*aria-controls="tab-rincian"[^>]*>\s*Rincian#')
            ->toContain('id="tab-deskripsi"')
            ->toContain('role="tabpanel"')
            ->toContain('x-on:keydown.right')
            ->toContain('x-on:keydown.left');
    });

    it('shows only the tabs that have content', function () {
        $this->kepiting->update(['quality_standard' => null]);
        $this->product->update(['shelf_life_days' => null, 'storage_instructions' => null]);
        $this->product->attributeValues()->delete();

        expect(productPage()->assertOk()->getContent())
            ->toContain('aria-controls="tab-deskripsi"')
            ->not->toContain('aria-controls="tab-standar"')
            ->not->toContain('aria-controls="tab-rincian"');
    });
});
