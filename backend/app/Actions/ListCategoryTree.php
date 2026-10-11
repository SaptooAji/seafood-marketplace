<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Category;
use App\Models\CategoryAttribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Builds the whole category tree with every category's effective attributes,
 * for the seller app's product form.
 */
class ListCategoryTree
{
    /**
     * Load all categories and all attributes in two queries and assemble the
     * tree in memory; the category list is small, so this stays cheap and has
     * no N+1 at any size. Each category gets its `children` and
     * `effectiveAttributes` relations set, children ordered by sort_order, then name.
     *
     * @return Collection<int, Category> the root categories
     */
    public function handle(): Collection
    {
        $categories = Category::query()->orderBy('sort_order')->orderBy('name')->get();
        $attributesByCategory = CategoryAttribute::query()->orderBy('id')->get()->groupBy('category_id');
        $childrenByParent = $categories->groupBy('parent_id');
        $byId = $categories->keyBy('id');

        foreach ($categories as $category) {
            $category->setRelation('children', $childrenByParent->get($category->id, new Collection));
            $category->setRelation('effectiveAttributes', $this->effectiveAttributes($category, $byId, $attributesByCategory));
        }

        return $categories->whereNull('parent_id')->values();
    }

    /**
     * Collect the attributes of the chain from the top ancestor down, then
     * apply the same inheritance rule as Category::effectiveAttributes().
     *
     * @param  Collection<int, Category>  $byId
     * @param  SupportCollection<int, Collection<int, CategoryAttribute>>  $attributesByCategory
     * @return Collection<int, CategoryAttribute>
     */
    private function effectiveAttributes(Category $category, Collection $byId, SupportCollection $attributesByCategory): Collection
    {
        $chain = [];

        // The depth guard mirrors Category::MAX_DEPTH against a parent loop in bad data.
        for ($node = $category; $node !== null && count($chain) < 50; $node = $byId->get($node->parent_id)) {
            array_unshift($chain, $node);
        }

        $topFirst = new Collection;

        foreach ($chain as $node) {
            $topFirst = $topFirst->merge($attributesByCategory->get($node->id, new Collection));
        }

        return Category::keepClosest($topFirst);
    }
}
