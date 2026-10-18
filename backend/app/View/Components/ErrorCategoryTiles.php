<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\View\Component;

/**
 * The category photo tiles of the error pages, from config/error-pages.php.
 * With `counted`, each tile also shows how many products buyers can see in
 * that category and its subcategories (the 404 page); without it, the tiles
 * run no queries (419, 429, 500). When the database cannot be reached the
 * counts are left out.
 */
class ErrorCategoryTiles extends Component
{
    /**
     * @var list<array{name: string, slug: string, photo: string, count: int|null}>
     */
    public array $tiles;

    public function __construct(public bool $counted = false)
    {
        $counts = $counted ? $this->counts() : null;

        $this->tiles = array_map(fn (array $tile): array => [
            'name' => (string) $tile['name'],
            'slug' => (string) $tile['slug'],
            'photo' => (string) $tile['photo'],
            'count' => $counts === null ? null : ($counts[$tile['slug']] ?? 0),
        ], array_values(config()->array('error-pages.categories')));
    }

    public function render(): View
    {
        return view('components.error-category-tiles');
    }

    /**
     * Visible products per root category slug, or null when the database is
     * down. Two queries; the category tree is small enough to walk in memory.
     *
     * @return array<string, int>|null
     */
    private function counts(): ?array
    {
        try {
            /** @var array<int, int> $byCategory */
            $byCategory = Product::query()
                ->visibleInCatalog()
                ->toBase()
                ->select('category_id')
                ->selectRaw('count(*) as product_count')
                ->groupBy('category_id')
                ->pluck('product_count', 'category_id')
                ->all();

            $categories = Category::query()->get(['id', 'parent_id', 'slug'])->keyBy('id');
        } catch (QueryException) {
            return null;
        }

        $counts = [];

        foreach ($byCategory as $categoryId => $count) {
            $category = $categories->get((int) $categoryId);
            $seen = [];

            while ($category !== null && $category->parent_id !== null && ! isset($seen[$category->id])) {
                $seen[$category->id] = true;
                $category = $categories->get($category->parent_id);
            }

            if ($category !== null) {
                $counts[$category->slug] = ($counts[$category->slug] ?? 0) + (int) $count;
            }
        }

        return $counts;
    }
}
