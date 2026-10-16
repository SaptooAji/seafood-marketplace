<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SalesMode;
use App\Enums\StoreStatus;
use App\Enums\Unit;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\View\Components\Layouts\App as Layout;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The product page at `/produk/{slug}`: photos, variants with price and stock
 * freshness, product details, the store, and the SEO tags with JSON-LD.
 *
 * Only products buyers may see are found; anything else is a 404. The
 * variant and quantity come from the browser, so both are checked again here.
 *
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read ProductVariant|null $selectedVariant
 * @property-read Collection<int, Product> $similarProducts
 * @property-read Collection<int, Store> $otherStores
 */
class ProductPage extends Component
{
    public const int MAX_QTY = 9999;

    public const int SIMILAR_PRODUCTS = 4;

    public const int OTHER_STORES = 3;

    /**
     * How far up the category tree to look for a quality standard.
     */
    private const int QUALITY_STANDARD_DEPTH = 10;

    #[Locked]
    public Product $product;

    public ?int $variantId = null;

    public string $qty = '';

    public function mount(string $slug): void
    {
        $this->product = Product::query()->visibleInCatalog()->where('slug', $slug)->firstOrFail();

        $this->choose($this->variants->first());
    }

    /**
     * A variant that is not an active variant of this product falls back to the first one.
     */
    public function updatedVariantId(): void
    {
        $variant = $this->variants->firstWhere('id', $this->variantId) ?? $this->variants->first();

        $this->choose($variant);
        $this->resetValidation('qty');
    }

    public function updatedQty(): void
    {
        $this->validateOnly('qty');
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
     * Up to four other products buyers can see from the same category,
     * filled up from the sibling categories, the same category first and
     * newest first. A root category has no siblings to fill up from.
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
            ->orderByRaw('category_id = ? desc', [$categoryId])
            ->latest()
            ->latest('id')
            ->limit(self::SIMILAR_PRODUCTS)
            ->get(['id', 'store_id', 'name', 'slug', 'created_at']);
    }

    /**
     * Up to three other active stores selling a product buyers can see in
     * the same category, each with its cheapest active variant there as
     * `from_price` and `from_unit`, cheapest first. Only public columns.
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
            ->selectRaw('distinct on (products.store_id) products.store_id, product_variants.price, product_variants.unit')
            ->orderBy('products.store_id')
            ->orderBy('product_variants.price')
            ->orderBy('product_variants.id');

        return Store::query()
            ->joinSub($cheapest, 'cheapest', 'cheapest.store_id', '=', 'stores.id')
            ->where('stores.status', StoreStatus::Active)
            ->select(['stores.id', 'stores.name', 'stores.slug', 'stores.city', 'stores.logo', 'cheapest.price as from_price', 'cheapest.unit as from_unit'])
            ->withCasts(['from_price' => 'integer', 'from_unit' => Unit::class])
            ->orderBy('cheapest.price')
            ->orderBy('stores.id')
            ->limit(self::OTHER_STORES)
            ->get();
    }

    /**
     * A decimal quantity as buyers read it: `12.500` becomes `12,5`.
     */
    public static function quantity(string $value): string
    {
        $trimmed = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;

        return str_replace('.', ',', $trimmed);
    }

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        $min = $this->selectedVariant->min_qty ?? '0.001';

        return [
            'qty' => ['required', 'numeric', 'decimal:0,3', 'min:'.$min, 'max:'.self::MAX_QTY],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'qty.required' => 'Isi jumlah yang ingin dibeli.',
            'qty.numeric' => 'Jumlah harus berupa angka.',
            'qty.decimal' => 'Jumlah paling banyak 3 angka di belakang koma.',
            'qty.min' => 'Jumlah minimal :min.',
            'qty.max' => 'Jumlah maksimal :max.',
        ];
    }

    public function render(): View
    {
        $product = $this->product->load([
            'store:id,name,slug,city,phone,logo',
            'category:id,name,slug,parent_id',
            'category.parent:id,name,slug',
            'images',
            'attributeValues' => fn (HasMany $values) => $values
                ->with('categoryAttribute:id,name,unit')
                ->orderBy('category_attribute_id'),
        ]);

        return view('livewire.product-page', [
            'product' => $product,
            'qualityStandard' => $this->qualityStandard(),
            'jsonLd' => $this->jsonLd($product),
            'whatsappUrl' => $product->store->whatsappUrl("Halo, saya tertarik dengan {$product->name}: ".route('products.show', $product->slug)),
        ])->layout(Layout::class, [
            'title' => "{$product->name} - {$product->store->name}, {$product->store->city}",
            'description' => $product->metaDescription(),
            'ogType' => 'product',
            'image' => $product->images->first()?->url(),
        ]);
    }

    private function choose(?ProductVariant $variant): void
    {
        $this->variantId = $variant?->id;
        $this->qty = $variant === null ? '' : str_replace(',', '.', self::quantity($variant->min_qty));
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
