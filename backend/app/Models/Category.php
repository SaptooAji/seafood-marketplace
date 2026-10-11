<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A product category in a tree (Kepiting > Bakau > Bakau Super). A category
 * inherits the attributes of its ancestors; see effectiveAttributes().
 *
 * Note: inside this class `$this->attributes` is Eloquent's raw attribute
 * array, not the relation. Use `$this->attributes()` for the relation.
 */
#[Fillable(['parent_id', 'name', 'slug', 'sort_order', 'quality_standard'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, LogsActivity;

    /**
     * Log every fillable field, only the ones that changed.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * Guards effectiveAttributes() against a parent loop in bad data.
     * Real trees are a few levels deep.
     */
    private const int MAX_DEPTH = 50;

    /**
     * Get the parent category, or null for a root category.
     *
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the direct subcategories, ordered by sort_order, then name.
     *
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Get the products filed directly under this category.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the attributes defined on this category only, without inherited ones.
     *
     * @return HasMany<CategoryAttribute, $this>
     */
    public function attributes(): HasMany
    {
        return $this->hasMany(CategoryAttribute::class);
    }

    /**
     * IDs of every category below this one, at any depth, in one query.
     * Used to keep a category from being moved under its own subtree.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE tree AS (
                SELECT id, 0 AS depth FROM categories WHERE parent_id = ?
                UNION ALL
                SELECT categories.id, tree.depth + 1
                FROM categories
                JOIN tree ON categories.parent_id = tree.id
                WHERE tree.depth < ?
            )
            SELECT id FROM tree
            SQL, [$this->getKey(), self::MAX_DEPTH]);

        return array_map(fn (object $row): int => (int) $row->id, $rows);
    }

    /**
     * The attributes that apply to this category: the top ancestor's first,
     * then down to this category's own. When a name is defined at several
     * levels, the one closest to this category wins.
     *
     * The chain of ancestors is walked with a recursive CTE, so this is one
     * query at any depth.
     *
     * @return Collection<int, CategoryAttribute>
     */
    public function effectiveAttributes(): Collection
    {
        $chain = <<<'SQL'
            WITH RECURSIVE chain AS (
                SELECT id, parent_id, 0 AS depth FROM categories WHERE id = ?
                UNION ALL
                SELECT categories.id, categories.parent_id, chain.depth + 1
                FROM categories
                JOIN chain ON categories.id = chain.parent_id
                WHERE chain.depth < ?
            )
            SELECT id, depth FROM chain
            SQL;

        return self::keepClosest(CategoryAttribute::query()
            ->select('category_attributes.*')
            ->join(DB::raw("({$chain}) AS chain"), 'chain.id', '=', 'category_attributes.category_id')
            ->addBinding([$this->getKey(), self::MAX_DEPTH], 'join')
            ->orderByDesc('chain.depth')
            ->orderBy('category_attributes.id')
            ->get());
    }

    /**
     * The inheritance rule, shared by effectiveAttributes() and the category
     * tree API: given a chain's attributes ordered top ancestor first, keep one
     * attribute per name, the one defined closest to the category.
     *
     * @param  Collection<int, CategoryAttribute>  $topFirst
     * @return Collection<int, CategoryAttribute>
     */
    public static function keepClosest(Collection $topFirst): Collection
    {
        return $topFirst
            ->reverse()
            ->unique('name')
            ->reverse()
            ->values();
    }
}
