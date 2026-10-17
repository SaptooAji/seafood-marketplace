<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Data\Recipe;
use App\Enums\SalesMode;
use App\Enums\ShippingFeeType;
use App\Enums\ShippingMethodType;
use App\Enums\StoreStatus;
use App\Enums\Unit;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Support\StockCheckTime;
use App\View\Components\Layouts\App as Layout;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The product page at `/produk/{slug}`: photos, product details, the store,
 * the SEO tags with JSON-LD, and the order steps: size, quantity, delivery
 * date (pre-order only), and way of receiving, with a running total.
 *
 * Only products buyers may see are found; anything else is a 404. Every
 * choice comes from the browser, so each is checked again here: a choice
 * that is not offered goes back to the previous one, and a quantity goes to
 * the nearest one that can be sold. The total is worked out here in whole
 * rupiah and thousandths of a unit, never floats (VAL-2).
 *
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read ProductVariant|null $selectedVariant
 * @property-read Collection<int, StoreShippingMethod> $shippingMethods
 * @property-read StoreShippingMethod|null $selectedShippingMethod
 * @property-read Collection<int, Product> $similarProducts
 * @property-read Collection<int, Store> $otherStores
 * @property-read int $otherStoreCount
 * @property-read Collection<int, Product> $sameStoreProducts
 * @property-read int $sameStoreCount
 * @property-read list<Recipe> $recipes
 */
class ProductPage extends Component
{
    public const int MAX_QTY = 9999;

    public const int SIMILAR_PRODUCTS = 4;

    public const int OTHER_STORES = 3;

    public const int SAME_STORE_PRODUCTS = 4;

    public const int RECIPES = 3;

    /**
     * How far up the category tree to look for a quality standard.
     */
    private const int QUALITY_STANDARD_DEPTH = 10;

    #[Locked]
    public Product $product;

    public ?int $variantId = null;

    /**
     * The quantity as a decimal string with a dot, such as `1.5`.
     */
    public string $qty = '';

    /**
     * The chosen delivery date of a pre-order product, `Y-m-d` in WIB.
     */
    public string $deliveryDate = '';

    public ?int $shippingMethodId = null;

    /**
     * The value before the browser changed it, to go back to when the new one
     * is not offered. Only lives for one request.
     */
    private mixed $previous = null;

    public function mount(string $slug): void
    {
        $this->product = Product::query()->visibleInCatalog()->where('slug', $slug)->firstOrFail();

        $this->choose($this->variants->first(fn (ProductVariant $variant): bool => $this->isAvailable($variant)) ?? $this->variants->first());
        $this->deliveryDate = ($this->deliveryDates()[0] ?? null)?->format('Y-m-d') ?? '';
    }

    public function updating(string $property): void
    {
        $this->previous = $this->{$property} ?? null;
    }

    /**
     * Only an active variant of this product that can be sold may be chosen;
     * it starts at its minimum quantity.
     */
    public function updatedVariantId(): void
    {
        $variant = $this->variants->firstWhere('id', $this->variantId);

        if ($variant === null || ! $this->isAvailable($variant)) {
            $this->variantId = is_int($this->previous) ? $this->previous : null;

            return;
        }

        $this->choose($variant);
    }

    /**
     * A number goes to the nearest quantity that can be sold; anything else
     * keeps the previous quantity.
     */
    public function updatedQty(): void
    {
        $thousandths = self::thousandths($this->qty);

        if ($thousandths === null) {
            $this->qty = is_string($this->previous) ? $this->previous : '';

            return;
        }

        $this->setQuantity($thousandths);
    }

    public function updatedDeliveryDate(): void
    {
        $offered = array_map(fn (CarbonImmutable $date): string => $date->format('Y-m-d'), $this->deliveryDates());

        if (! in_array($this->deliveryDate, $offered, true)) {
            $this->deliveryDate = is_string($this->previous) ? $this->previous : '';
        }
    }

    /**
     * Only an active shipping method of this store may be chosen (AC-2).
     */
    public function updatedShippingMethodId(): void
    {
        if ($this->shippingMethods->firstWhere('id', $this->shippingMethodId) === null) {
            $this->shippingMethodId = is_int($this->previous) ? $this->previous : null;
        }
    }

    public function increment(): void
    {
        $this->step(1);
    }

    public function decrement(): void
    {
        $this->step(-1);
    }

    /**
     * The active variants, cheapest first.
     *
     * @return Collection<int, ProductVariant>
     */
    #[Computed]
    public function variants(): Collection
    {
        return $this->product->variants()->where('is_active', true)->reorder('price')->orderBy('id')->get();
    }

    #[Computed]
    public function selectedVariant(): ?ProductVariant
    {
        return $this->variants->firstWhere('id', $this->variantId);
    }

    /**
     * The active shipping methods of the store, in the order they were added.
     * Only the columns the page needs.
     *
     * @return Collection<int, StoreShippingMethod>
     */
    #[Computed]
    public function shippingMethods(): Collection
    {
        return StoreShippingMethod::query()
            ->where('store_id', $this->product->store_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'type', 'fee_type', 'flat_fee']);
    }

    #[Computed]
    public function selectedShippingMethod(): ?StoreShippingMethod
    {
        return $this->shippingMethods->firstWhere('id', $this->shippingMethodId);
    }

    /**
     * The fee of a shipping method known before checkout: free for pickup,
     * the flat fee for a flat rate, and null when it depends on the distance
     * or is set by the seller.
     */
    private static function knownFee(StoreShippingMethod $method): ?int
    {
        return match (true) {
            $method->type === ShippingMethodType::Pickup => 0,
            $method->fee_type === ShippingFeeType::Flat => $method->flat_fee ?? 0,
            default => null,
        };
    }

    /**
     * Whether a variant can be ordered: a pre-order product is made to order,
     * a ready stock variant needs at least its minimum quantity in stock.
     */
    private function isAvailable(ProductVariant $variant): bool
    {
        return $this->maxThousandths($variant) >= self::thousandths($variant->min_qty);
    }

    /**
     * Up to four other products buyers can see from the same category,
     * filled up from the sibling categories, real stores first, then the
     * same category first and newest first. A root category has no siblings to fill up from.
     *
     * @return Collection<int, Product>
     */
    #[Computed]
    public function similarProducts(): Collection
    {
        $categoryId = $this->product->category_id;
        $parentId = $this->product->category->parent_id;

        return Product::query()
            ->visibleInCatalog()
            ->withCardDetails()
            ->whereKeyNot($this->product->id)
            ->when(
                $parentId === null,
                fn (Builder $query) => $query->where('category_id', $categoryId),
                fn (Builder $query) => $query->whereIn('category_id', Category::query()->select('id')->where('parent_id', $parentId)),
            )
            ->realStoresFirst()
            ->orderByRaw('category_id = ? desc', [$categoryId])
            ->latest()
            ->latest('id')
            ->limit(self::SIMILAR_PRODUCTS)
            ->get(['id', 'store_id', 'name', 'slug', 'created_at']);
    }

    /**
     * Up to three other active stores selling a product buyers can see in
     * the same category, each with its cheapest active variant there as
     * `from_price`, `from_unit`, `from_stock`, and `checked_at` (when that
     * stock was last confirmed), real stores first, then cheapest first.
     * Only public columns.
     *
     * @return Collection<int, Store>
     */
    #[Computed]
    public function otherStores(): Collection
    {
        // One row per store: its cheapest active variant in this category (Postgres DISTINCT ON).
        $cheapest = ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('product_variants.is_active', true)
            ->where('products.category_id', $this->product->category_id)
            ->where('products.store_id', '!=', $this->product->store_id)
            ->whereIn('products.id', Product::query()->visibleInCatalog()->select('id'))
            ->selectRaw('distinct on (products.store_id) products.store_id, product_variants.price, product_variants.unit, product_variants.stock, product_variants.stock_confirmed_at')
            ->orderBy('products.store_id')
            ->orderBy('product_variants.price')
            ->orderBy('product_variants.id');

        return Store::query()
            ->joinSub($cheapest, 'cheapest', 'cheapest.store_id', '=', 'stores.id')
            ->where('stores.status', StoreStatus::Active)
            ->select(['stores.id', 'stores.name', 'stores.slug', 'stores.city', 'stores.logo', 'stores.is_demo', 'cheapest.price as from_price', 'cheapest.unit as from_unit', 'cheapest.stock as from_stock', 'cheapest.stock_confirmed_at as checked_at'])
            ->withCasts(['from_price' => 'integer', 'from_unit' => Unit::class, 'checked_at' => 'datetime'])
            ->orderBy('stores.is_demo')
            ->orderBy('cheapest.price')
            ->orderBy('stores.id')
            ->limit(self::OTHER_STORES)
            ->get();
    }

    /**
     * How many other active stores sell a product buyers can see in the same
     * category, for "Lihat semua {n} lapak".
     */
    #[Computed]
    public function otherStoreCount(): int
    {
        return Store::query()
            ->where('status', StoreStatus::Active)
            ->whereIn('id', Product::query()
                ->visibleInCatalog()
                ->where('category_id', $this->product->category_id)
                ->where('store_id', '!=', $this->product->store_id)
                ->select('store_id'))
            ->count();
    }

    /**
     * Up to four other products buyers can see from the same store, in any
     * category, newest first.
     *
     * @return Collection<int, Product>
     */
    #[Computed]
    public function sameStoreProducts(): Collection
    {
        return Product::query()
            ->select(['id', 'store_id', 'name', 'slug', 'condition', 'created_at'])
            ->visibleInCatalog()
            ->withCardDetails()
            ->withLastCheckedAt()
            ->where('store_id', $this->product->store_id)
            ->whereKeyNot($this->product->id)
            ->latest()
            ->latest('id')
            ->limit(self::SAME_STORE_PRODUCTS)
            ->get();
    }

    /**
     * How many products buyers can see in the store, this one included, for
     * "Lihat semua {n} produk".
     */
    #[Computed]
    public function sameStoreCount(): int
    {
        return Product::query()->visibleInCatalog()->where('store_id', $this->product->store_id)->count();
    }

    /**
     * Up to three static recipes of the product's root category.
     *
     * @return list<Recipe>
     */
    #[Computed]
    public function recipes(): array
    {
        $category = $this->product->category;
        $root = $category->parent_id === null ? $category : $category->parent;

        return $root === null ? [] : array_slice(Recipe::inCategory($root->slug), 0, self::RECIPES);
    }

    /**
     * A decimal quantity as buyers read it: `12.500` becomes `12,5`.
     */
    public static function quantity(string $value): string
    {
        $trimmed = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;

        return str_replace('.', ',', $trimmed);
    }

    public function render(): View
    {
        $product = $this->product->load([
            'store:id,name,slug,city,phone,logo,is_demo',
            'category:id,name,slug,parent_id',
            'category.parent:id,name,slug',
            'images',
            'attributeValues' => fn (HasMany $values) => $values
                ->with('categoryAttribute:id,name,unit')
                ->orderBy('category_attribute_id'),
        ]);

        $selected = $this->selectedVariant;
        $dates = $this->deliveryDates();

        return view('livewire.product-page', [
            'product' => $product,
            'soldOut' => $selected === null || ! $this->isAvailable($selected),
            'availableIds' => $this->variants->filter(fn (ProductVariant $variant): bool => $this->isAvailable($variant))->pluck('id')->all(),
            'fees' => $this->shippingMethods->mapWithKeys(fn (StoreShippingMethod $method): array => [$method->id => self::knownFee($method)])->all(),
            'minQty' => $selected === null ? null : self::decimal(self::thousandths($selected->min_qty) ?? 0),
            'maxQty' => $selected === null ? null : self::decimal($this->maxThousandths($selected)),
            'deliveryDates' => $dates,
            'chosenDate' => collect($dates)->first(fn (CarbonImmutable $date): bool => $date->format('Y-m-d') === $this->deliveryDate),
            'deadline' => $dates === [] ? null : self::deadline($product->orderDeadlineFor($dates[0])),
            'checkedAt' => $selected === null || $selected->isStockStale() ? null : self::checkedAt($selected),
            'total' => $this->total(),
            'qualityStandard' => $this->qualityStandard(),
            'jsonLd' => $this->jsonLd($product),
            // A demo store is an example: it has no phone buyers may call.
            'whatsappUrl' => $product->store->is_demo ? null : $product->store->whatsappUrl("Halo, saya tertarik dengan {$product->name}: ".route('products.show', $product->slug)),
        ])->layout(Layout::class, [
            'title' => "{$product->name} - {$product->store->name}, {$product->store->city}",
            'description' => $product->metaDescription(),
            'ogType' => 'product',
            'image' => $product->images->first()?->url(),
            // Demo stores are labelled examples; keep them out of search results.
            'noindex' => $product->store->is_demo,
        ]);
    }

    private function choose(?ProductVariant $variant): void
    {
        $this->variantId = $variant?->id;
        $this->qty = $variant === null ? '' : self::decimal(self::thousandths($variant->min_qty) ?? 0);
    }

    /**
     * Move the quantity up or down by one minimum quantity.
     */
    private function step(int $direction): void
    {
        $variant = $this->selectedVariant;

        if ($variant === null) {
            return;
        }

        $this->setQuantity((self::thousandths($this->qty) ?? 0) + $direction * (self::thousandths($variant->min_qty) ?? 1));
    }

    /**
     * Set the quantity to the nearest multiple of the minimum quantity,
     * between the minimum and the most that can be ordered.
     */
    private function setQuantity(int $thousandths): void
    {
        $variant = $this->selectedVariant;

        if ($variant === null) {
            return;
        }

        $min = max(1, self::thousandths($variant->min_qty) ?? 1);
        $max = max($min, $this->maxThousandths($variant));
        $nearest = self::divideRounded(max(0, $thousandths), $min) * $min;

        $this->qty = self::decimal(min($max, max($min, $nearest)));
    }

    /**
     * The most of a variant one order can take, in thousandths, as a whole
     * multiple of its minimum quantity: the stock for ready stock, the daily
     * quota (or the maximum) for pre-order.
     */
    private function maxThousandths(ProductVariant $variant): int
    {
        $min = max(1, self::thousandths($variant->min_qty) ?? 1);

        $limit = $this->product->sales_mode === SalesMode::PreOrder
            ? ($this->product->daily_quota === null ? self::MAX_QTY * 1000 : self::thousandths((string) $this->product->daily_quota))
            : self::thousandths($variant->stock);

        return intdiv(min(self::MAX_QTY * 1000, $limit ?? 0), $min) * $min;
    }

    /**
     * The first three delivery dates of a pre-order product, earliest first;
     * none for ready stock.
     *
     * @return list<CarbonImmutable>
     */
    private function deliveryDates(): array
    {
        $earliest = $this->product->earliestDeliveryDate(now());

        return $earliest === null ? [] : [$earliest, $earliest->addDay(), $earliest->addDays(2)];
    }

    /**
     * The price times the quantity, rounded half up to whole rupiah, plus the
     * fee of the chosen shipping method when it is known now.
     */
    private function total(): ?int
    {
        $variant = $this->selectedVariant;
        $qty = self::thousandths($this->qty);

        if ($variant === null || $qty === null) {
            return null;
        }

        $method = $this->selectedShippingMethod;
        $fee = $method === null ? 0 : (self::knownFee($method) ?? 0);

        return self::divideRounded($variant->price * $qty, 1000) + $fee;
    }

    /**
     * When to order by: "15.00 hari ini", "15.00 besok", or "15.00 Sabtu".
     */
    private static function deadline(CarbonImmutable $deadline): string
    {
        $today = CarbonImmutable::now(StockCheckTime::TIMEZONE)->startOfDay();

        $day = match ((int) $today->diffInDays($deadline->startOfDay())) {
            0 => 'hari ini',
            1 => 'besok',
            default => $deadline->locale('id')->translatedFormat('l'),
        };

        return $deadline->format('H.i').' '.$day;
    }

    /**
     * "hari ini 06.30" for a stock checked today in WIB, "14 Okt" before.
     */
    private static function checkedAt(ProductVariant $variant): ?string
    {
        if ($variant->stock_confirmed_at === null) {
            return null;
        }

        $checked = StockCheckTime::format($variant->stock_confirmed_at);

        return $variant->stock_confirmed_at->setTimezone(StockCheckTime::TIMEZONE)->isSameDay(now(StockCheckTime::TIMEZONE))
            ? 'hari ini '.$checked
            : $checked;
    }

    /**
     * A decimal quantity typed by a buyer as whole thousandths: "1,5" and
     * "1.5" become 1500, extra decimals are cut. Null for anything that is
     * not a plain number.
     */
    private static function thousandths(string $value): ?int
    {
        if (preg_match('/^(-?)(\d{1,9})(?:[.,](\d+))?$/', trim($value), $parts) !== 1) {
            return null;
        }

        $amount = (int) $parts[2] * 1000 + (int) substr(($parts[3] ?? '').'000', 0, 3);

        return $parts[1] === '-' ? -$amount : $amount;
    }

    /**
     * Whole thousandths as the shortest decimal string: 1500 becomes "1.5".
     */
    private static function decimal(int $thousandths): string
    {
        $fraction = rtrim(sprintf('%03d', $thousandths % 1000), '0');

        return intdiv($thousandths, 1000).($fraction === '' ? '' : '.'.$fraction);
    }

    /**
     * A non-negative division rounded half up, in integers only.
     */
    private static function divideRounded(int $dividend, int $divisor): int
    {
        return intdiv(2 * $dividend + $divisor, 2 * $divisor);
    }

    /**
     * The category's quality standard as safe HTML, or the closest
     * ancestor's when the category has none. Raw HTML in the markdown is
     * escaped and `javascript:` links are dropped.
     */
    private function qualityStandard(): ?string
    {
        $categoryId = $this->product->category_id;

        for ($depth = 0; $categoryId !== null && $depth < self::QUALITY_STANDARD_DEPTH; $depth++) {
            $category = Category::query()->find($categoryId, ['id', 'parent_id', 'quality_standard']);

            if ($category === null) {
                return null;
            }

            if (filled($category->quality_standard)) {
                return Str::markdown((string) $category->quality_standard, [
                    'html_input' => 'escape',
                    'allow_unsafe_links' => false,
                ]);
            }

            $categoryId = $category->parent_id;
        }

        return null;
    }

    /**
     * The schema.org Product for search results, priced from the active variants.
     *
     * @return array<string, mixed>
     */
    private function jsonLd(Product $product): array
    {
        $variants = $this->variants;

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'description' => $product->description,
            'image' => $product->images->map(fn (ProductImage $image): string => $image->url())->all(),
            'sku' => (string) $product->id,
            'category' => $product->category->name,
            'offers' => [
                '@type' => 'AggregateOffer',
                'lowPrice' => $variants->min('price'),
                'highPrice' => $variants->max('price'),
                'priceCurrency' => 'IDR',
                'offerCount' => $variants->count(),
                'availability' => 'https://schema.org/'.$this->availability($variants),
            ],
            'seller' => [
                '@type' => 'Organization',
                'name' => $product->store->name,
            ],
        ];
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     */
    private function availability(Collection $variants): string
    {
        if ($this->product->sales_mode === SalesMode::PreOrder) {
            return 'PreOrder';
        }

        return $variants->contains(fn (ProductVariant $variant): bool => (float) $variant->stock > 0) ? 'InStock' : 'OutOfStock';
    }
}
