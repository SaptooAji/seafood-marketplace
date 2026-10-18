<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\SalesMode;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Support\StockCheckTime;
use App\View\Components\Layouts\App as Layout;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every pre-order product at `/pre-order`, with the earliest day it can be
 * delivered and its daily capacity, real stores first, then the earliest
 * day, then the newest. Filtered by how soon it ships (`?paling_lambat=`), by
 * root category (`?kategori=`), and by distance once the buyer shares a
 * location; unknown values are ignored. Under the list, ready stock checked
 * today for buyers who do not want to wait.
 *
 * The earliest day comes from Product::earliestDeliveryDate(), so the pre-
 * order products are read once with only the columns it needs, then sorted,
 * counted, and paged in PHP; the cards of one page are loaded after that.
 * The number of queries does not grow with the products.
 *
 * Like the catalog, the buyer's location lives only in the component state,
 * rounded to about 110 m, never in the URL.
 *
 * @property-read list<array{product: Product, earliest: CarbonImmutable, root: string|null, isDemo: bool, distance: float|null}> $rows
 * @property-read list<array{slug: string, name: string}> $roots
 */
class PreOrders extends Component
{
    use WithPagination;

    public const int PER_PAGE = 24;

    /**
     * Ready stock products under the list.
     */
    public const int READY_TODAY = 8;

    /**
     * Deadline options in the URL => the last day, counted from today.
     */
    public const array DEADLINES = [
        'besok' => 1,
        'lusa' => 2,
        '3-hari' => 3,
        'minggu-ini' => 7,
    ];

    #[Url(as: 'paling_lambat', except: '')]
    public string $deadline = '';

    #[Url(as: 'kategori', except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $radius = '';

    /**
     * The buyer's latitude, rounded; deliberately not in the URL.
     */
    public ?string $lat = null;

    /**
     * The buyer's longitude, rounded; deliberately not in the URL.
     */
    public ?string $lng = null;

    /**
     * The categories, read once per request.
     *
     * @var Collection<int, Category>|null
     */
    private ?Collection $categories = null;

    public function mount(): void
    {
        $this->deadline = $this->offeredDeadline($this->deadline);
        $this->category = $this->offeredCategory($this->category);
    }

    /**
     * Take the buyer's location from the browser to show distances and
     * offer the radius. A value that is not a point on earth is ignored.
     */
    public function locate(mixed $lat, mixed $lng): void
    {
        if (! is_numeric($lat) || ! is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return;
        }

        $this->lat = number_format((float) $lat, 3, '.', '');
        $this->lng = number_format((float) $lng, 3, '.', '');
        unset($this->rows);
        $this->resetPage();
    }

    /**
     * Keep the products deliverable by the chosen day; choosing it again
     * shows every pre-order.
     */
    public function chooseDeadline(string $value): void
    {
        $value = $this->offeredDeadline($value);
        $this->deadline = $value === $this->deadline ? '' : $value;
        $this->resetPage();
    }

    /**
     * Keep the products of one root category, or of all with an empty slug.
     */
    public function chooseCategory(string $slug): void
    {
        $this->category = $this->offeredCategory($slug);
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->deadline = '';
        $this->category = '';
        $this->radius = '';
        $this->resetPage();
    }

    public function updatedRadius(): void
    {
        $this->resetPage();
    }

    /**
     * Every pre-order product buyers can see that has an earliest delivery
     * day, with its root category slug, whether its store is a demo store,
     * and its distance once the buyer is located. One query.
     *
     * @return list<array{product: Product, earliest: CarbonImmutable, root: string|null, isDemo: bool, distance: float|null}>
     */
    #[Computed]
    public function rows(): array
    {
        $query = Product::query()
            ->visibleInCatalog()
            ->where('sales_mode', SalesMode::PreOrder)
            ->select(['products.id', 'products.category_id', 'products.sales_mode', 'products.lead_time_days', 'products.cutoff_time', 'products.created_at'])
            ->addSelect(['store_is_demo' => Store::query()->select('is_demo')->whereColumn('stores.id', 'products.store_id')]);

        if ($this->isLocated()) {
            $query->joinSub(Store::query()->select('stores.id')->withDistanceFrom((string) $this->lat, (string) $this->lng), 'store_distances', 'store_distances.id', '=', 'products.store_id')
                ->addSelect('store_distances.distance_km');
        }

        $rootSlugs = $this->rootSlugs();
        $now = now();
        $rows = [];

        foreach ($query->get() as $product) {
            $earliest = $product->earliestDeliveryDate($now);

            if ($earliest !== null) {
                $rows[] = [
                    'product' => $product,
                    'earliest' => $earliest,
                    'root' => $rootSlugs[$product->category_id] ?? null,
                    'isDemo' => (bool) $product->getAttribute('store_is_demo'),
                    'distance' => $this->isLocated() ? (float) $product->getAttribute('distance_km') : null,
                ];
            }
        }

        usort($rows, fn (array $a, array $b): int => [$a['isDemo'], $a['earliest'], $b['product']->created_at, $b['product']->id]
            <=> [$b['isDemo'], $b['earliest'], $a['product']->created_at, $a['product']->id]);

        return $rows;
    }

    /**
     * The root categories with a pre-order product, in admin order.
     *
     * @return list<array{slug: string, name: string}>
     */
    #[Computed]
    public function roots(): array
    {
        $used = array_flip(array_filter(array_column($this->rows, 'root')));

        return array_values(array_filter(
            $this->rootCategories(),
            fn (array $root): bool => isset($used[$root['slug']]),
        ));
    }

    public function render(): View
    {
        $today = CarbonImmutable::now(StockCheckTime::TIMEZONE)->startOfDay();
        $nearby = array_values(array_filter($this->rows, fn (array $row): bool => $this->isNearby($row)));

        $byCategory = array_values(array_filter($nearby, fn (array $row): bool => $this->category === '' || $row['root'] === $this->category));
        $byDeadline = array_values(array_filter($nearby, fn (array $row): bool => $this->isDeliverableBy($row, $this->deadline, $today)));
        $matching = array_values(array_filter($byCategory, fn (array $row): bool => $this->isDeliverableBy($row, $this->deadline, $today)));

        $deadlineOptions = [];

        foreach (self::DEADLINES as $value => $days) {
            $deadlineOptions[] = [
                'value' => $value,
                'label' => match ($value) {
                    'besok' => 'Besok, '.$today->addDay()->locale('id')->translatedFormat('l'),
                    'minggu-ini' => 'Minggu ini',
                    default => $today->addDays($days)->locale('id')->translatedFormat('l'),
                },
                'count' => count(array_filter($byCategory, fn (array $row): bool => $this->isDeliverableBy($row, $value, $today))),
            ];
        }

        $categoryOptions = [['slug' => '', 'name' => 'Semua', 'count' => count($byDeadline)]];

        foreach ($this->roots as $root) {
            $categoryOptions[] = [...$root, 'count' => count(array_filter($byDeadline, fn (array $row): bool => $row['root'] === $root['slug']))];
        }

        return view('livewire.pre-orders', [
            'preOrders' => $this->page($matching),
            'hasAny' => $this->rows !== [],
            'filtered' => $this->deadline !== '' || $this->category !== '' || $this->radiusKm() !== null,
            'orderBy' => $this->orderByForTomorrow($nearby, $today),
            'deadlineOptions' => $deadlineOptions,
            'categoryOptions' => $categoryOptions,
            'ready' => $this->readyToday($today),
            'located' => $this->isLocated(),
            'radii' => Catalog::RADII,
            'radiusKm' => $this->radiusKm(),
        ])->layout(Layout::class, [
            'title' => 'Semua pre order',
            'description' => 'Pesan hasil laut dari lapak terdekat dan terima di hari pilihan Anda: kepiting, udang, cumi, dan kerang, dengan jadwal kirim paling cepat setiap produk.',
            'noindex' => $this->deadline !== '' || $this->category !== '' || $this->radius !== '',
            'fullWidth' => true,
        ]);
    }

    /**
     * The cards of the current page, loaded with what a card shows.
     *
     * @param  list<array{product: Product, earliest: CarbonImmutable, root: string|null, isDemo: bool, distance: float|null}>  $rows
     * @return LengthAwarePaginator<int, array{product: Product, earliest: CarbonImmutable, distance: float|null}>
     */
    private function page(array $rows): LengthAwarePaginator
    {
        $page = max(1, $this->getPage());
        $slice = array_slice($rows, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        $products = Product::query()
            ->whereIn('id', array_map(fn (array $row): int => $row['product']->id, $slice))
            ->select(['id', 'store_id', 'name', 'slug', 'daily_quota'])
            ->withCardDetails()
            ->get()
            ->keyBy('id');

        $items = [];

        foreach ($slice as $row) {
            $product = $products->get($row['product']->id);

            if ($product !== null) {
                $items[] = ['product' => $product, 'earliest' => $row['earliest'], 'distance' => $row['distance']];
            }
        }

        return (new LengthAwarePaginator($items, count($rows), self::PER_PAGE, $page, ['path' => route('pre-orders')]))
            ->appends(array_filter(['paling_lambat' => $this->deadline, 'kategori' => $this->category, 'radius' => $this->radius]));
    }

    /**
     * Ready stock products whose stock was checked today and is not sold
     * out, real stores first, the last checked first.
     *
     * @return Collection<int, Product>
     */
    private function readyToday(CarbonImmutable $today): Collection
    {
        $query = Product::query()
            ->visibleInCatalog()
            ->where('sales_mode', SalesMode::ReadyStock)
            ->select(['products.id', 'products.store_id', 'products.name', 'products.slug', 'products.condition'])
            ->whereHas('variants', fn (Builder $variant) => $variant
                ->where('is_active', true)
                ->where('stock', '>', 0)
                ->where('stock_confirmed_at', '>=', $today->utc()))
            ->withCardDetails()
            ->withLastCheckedAt();

        if ($this->isLocated()) {
            $query->joinSub(Store::query()->select('stores.id')->withDistanceFrom((string) $this->lat, (string) $this->lng), 'store_distances', 'store_distances.id', '=', 'products.store_id')
                ->addSelect('store_distances.distance_km');
        }

        return $query->realStoresFirst()
            ->orderByDesc('last_checked_at')
            ->orderByDesc('products.id')
            ->limit(self::READY_TODAY)
            ->get();
    }

    /**
     * "15.00" when some listed product can still be ordered today for
     * delivery tomorrow: the latest such order time. Null otherwise.
     *
     * @param  list<array{product: Product, earliest: CarbonImmutable, root: string|null, isDemo: bool, distance: float|null}>  $rows
     */
    private function orderByForTomorrow(array $rows, CarbonImmutable $today): ?string
    {
        $tomorrow = $today->addDay();
        $latest = null;

        foreach ($rows as $row) {
            if ($row['earliest']->isSameDay($tomorrow)) {
                $deadline = $row['product']->orderDeadlineFor($tomorrow);
                $latest = $latest === null || $deadline->gt($latest) ? $deadline : $latest;
            }
        }

        return $latest?->format('H.i');
    }

    /**
     * @param  array{product: Product, earliest: CarbonImmutable, root: string|null, isDemo: bool, distance: float|null}  $row
     */
    private function isDeliverableBy(array $row, string $deadline, CarbonImmutable $today): bool
    {
        $days = self::DEADLINES[$deadline] ?? null;

        return $days === null || $row['earliest']->lte($today->addDays($days));
    }

    /**
     * @param  array{product: Product, earliest: CarbonImmutable, root: string|null, isDemo: bool, distance: float|null}  $row
     */
    private function isNearby(array $row): bool
    {
        $radius = $this->radiusKm();

        return $radius === null || $row['distance'] === null || $row['distance'] <= $radius;
    }

    /**
     * The chosen radius in km, or null when it is not one of the options or
     * the buyer is not located.
     */
    private function radiusKm(): ?int
    {
        return $this->isLocated() && ctype_digit($this->radius) && in_array((int) $this->radius, Catalog::RADII, true) ? (int) $this->radius : null;
    }

    private function isLocated(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    private function offeredDeadline(string $value): string
    {
        return array_key_exists($value, self::DEADLINES) ? $value : '';
    }

    private function offeredCategory(string $slug): string
    {
        return in_array($slug, array_column($this->roots, 'slug'), true) ? $slug : '';
    }

    /**
     * The root categories in admin order.
     *
     * @return list<array{slug: string, name: string}>
     */
    private function rootCategories(): array
    {
        return array_values(array_map(
            fn (Category $category): array => ['slug' => $category->slug, 'name' => $category->name],
            $this->categories()->whereNull('parent_id')->all(),
        ));
    }

    /**
     * The root category slug of every category id. The category tree is
     * small enough to walk in memory.
     *
     * @return array<int, string>
     */
    private function rootSlugs(): array
    {
        $categories = $this->categories();
        $roots = [];

        foreach ($categories as $category) {
            $root = $category;
            $seen = [];

            while ($root !== null && $root->parent_id !== null && ! isset($seen[$root->id])) {
                $seen[$root->id] = true;
                $root = $categories->get($root->parent_id);
            }

            if ($root !== null) {
                $roots[$category->id] = $root->slug;
            }
        }

        return $roots;
    }

    /**
     * Every category, keyed by id, in admin order. One query per request.
     *
     * @return Collection<int, Category>
     */
    private function categories(): Collection
    {
        return $this->categories ??= Category::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug'])
            ->keyBy('id');
    }
}
