<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\View\Component;

/**
 * The products buyers can see whose stock was checked most recently, for
 * the 404 page, real stores before demo stores like on the home page.
 * Products never checked are left out. When the database
 * cannot be reached the list is empty, so the page around it still renders.
 */
class RecentlyCheckedProducts extends Component
{
    /**
     * @var Collection<int, Product>
     */
    public Collection $products;

    public function __construct(public int $limit = 4)
    {
        try {
            $this->products = Product::query()
                ->select(['id', 'store_id', 'category_id', 'name', 'slug', 'created_at'])
                ->visibleInCatalog()
                ->withCardDetails()
                ->withLastCheckedAt()
                ->whereHas('variants', fn ($variant) => $variant->where('is_active', true)->whereNotNull('stock_confirmed_at'))
                ->realStoresFirst()
                ->orderByDesc('last_checked_at')
                ->latest('id')
                ->limit($this->limit)
                ->get();
        } catch (QueryException) {
            $this->products = new Collection;
        }
    }

    public function shouldRender(): bool
    {
        return $this->products->isNotEmpty();
    }

    public function render(): View
    {
        return view('components.recently-checked-products');
    }
}
