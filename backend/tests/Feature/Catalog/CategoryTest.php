<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Category;
use App\Models\CategoryAttribute;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Add an attribute to a category.
 *
 * @param  array<string, mixed>  $attributes
 */
function addAttribute(Category $category, string $name, array $attributes = []): CategoryAttribute
{
    return CategoryAttribute::factory()->for($category)->create(['name' => $name, ...$attributes]);
}

/**
 * @return list<string>
 */
function effectiveNames(Category $category): array
{
    return $category->fresh()->effectiveAttributes()->pluck('name')->all();
}

describe('effectiveAttributes()', function () {
    it('returns only its own attributes for a root category', function () {
        $root = Category::factory()->create(['name' => 'Kepiting']);
        addAttribute($root, 'Berat per ekor');
        addAttribute($root, 'Asal');

        expect(effectiveNames($root))->toBe(['Berat per ekor', 'Asal']);
    });

    it('puts the parent’s attributes before the child’s', function () {
        $kepiting = Category::factory()->create(['name' => 'Kepiting']);
        $bakau = Category::factory()->childOf($kepiting)->create(['name' => 'Bakau']);
        addAttribute($bakau, 'Jenis kelamin');
        addAttribute($kepiting, 'Berat per ekor');

        expect(effectiveNames($bakau))->toBe(['Berat per ekor', 'Jenis kelamin']);
    });

    it('inherits from every ancestor, top first', function () {
        $kepiting = Category::factory()->create(['name' => 'Kepiting']);
        $bakau = Category::factory()->childOf($kepiting)->create(['name' => 'Bakau']);
        $super = Category::factory()->childOf($bakau)->create(['name' => 'Bakau Super']);
        addAttribute($super, 'Grade');
        addAttribute($bakau, 'Jenis kelamin');
        addAttribute($kepiting, 'Berat per ekor');

        expect(effectiveNames($super))->toBe(['Berat per ekor', 'Jenis kelamin', 'Grade']);
    });

    it('does not include attributes of siblings or children', function () {
        $kepiting = Category::factory()->create();
        $bakau = Category::factory()->childOf($kepiting)->create();
        $rajungan = Category::factory()->childOf($kepiting)->create();
        addAttribute($kepiting, 'Berat per ekor');
        addAttribute($bakau, 'Jenis kelamin');
        addAttribute($rajungan, 'Ukuran cangkang');

        expect(effectiveNames($bakau))->toBe(['Berat per ekor', 'Jenis kelamin'])
            ->and(effectiveNames($kepiting))->toBe(['Berat per ekor']);
    });

    it('takes an attribute redefined by the child from the child', function () {
        $kepiting = Category::factory()->create();
        $bakau = Category::factory()->childOf($kepiting)->create();
        addAttribute($kepiting, 'Berat per ekor', ['unit' => 'g']);
        $own = addAttribute($bakau, 'Berat per ekor', ['unit' => 'kg']);

        $weights = $bakau->fresh()->effectiveAttributes()->where('name', 'Berat per ekor');

        expect($weights)->toHaveCount(1)
            ->and($weights->first()->is($own))->toBeTrue()
            ->and($weights->first()->unit)->toBe('kg');
    });

    it('uses the same number of queries at any depth', function () {
        $chain = [Category::factory()->create()];
        addAttribute($chain[0], 'Level 0');

        foreach (range(1, 4) as $level) {
            $chain[$level] = Category::factory()->childOf($chain[$level - 1])->create();
            addAttribute($chain[$level], "Level {$level}");
        }

        $queriesFor = function (Category $category): int {
            $category = $category->fresh();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $category->effectiveAttributes();

            return count(DB::getQueryLog());
        };

        expect(effectiveNames($chain[4]))->toBe(['Level 0', 'Level 1', 'Level 2', 'Level 3', 'Level 4'])
            ->and($queriesFor($chain[4]))->toBe($queriesFor($chain[1]));
    });
});

describe('relations', function () {
    it('links a child to its parent', function () {
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();

        expect($child->parent->is($parent))->toBeTrue()
            ->and($parent->parent)->toBeNull();
    });

    it('orders children by sort_order, then name', function () {
        $parent = Category::factory()->create();
        Category::factory()->childOf($parent)->create(['name' => 'Rajungan', 'sort_order' => 2]);
        Category::factory()->childOf($parent)->create(['name' => 'Bakau', 'sort_order' => 2]);
        Category::factory()->childOf($parent)->create(['name' => 'Soka', 'sort_order' => 1]);

        expect($parent->children()->pluck('name')->all())->toBe(['Soka', 'Bakau', 'Rajungan']);
    });

    it('reads the options of a select attribute as an array', function () {
        $attribute = addAttribute(Category::factory()->create(), 'Jenis kelamin', [
            'type' => AttributeType::Select,
            'options' => ['Jantan', 'Betina'],
        ]);

        expect($attribute->fresh())
            ->type->toBe(AttributeType::Select)
            ->options->toBe(['Jantan', 'Betina']);
    });

    it('lists the attributes of a category', function () {
        $category = Category::factory()->create();
        addAttribute($category, 'Berat per ekor');

        expect($category->attributes()->pluck('name')->all())->toBe(['Berat per ekor']);
    });
});

describe('constraints', function () {
    it('rejects two categories with the same slug', function () {
        Category::factory()->create(['slug' => 'kepiting']);

        expect(fn () => Category::factory()->create(['slug' => 'kepiting']))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('rejects two attributes with the same name in one category', function () {
        $category = Category::factory()->create();
        addAttribute($category, 'Berat per ekor');

        expect(fn () => addAttribute($category, 'Berat per ekor'))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('does not delete a category that still has children', function () {
        $parent = Category::factory()->create();
        $child = Category::factory()->childOf($parent)->create();

        expect(fn () => $parent->delete())->toThrow(QueryException::class);

        expect($child->fresh())->not->toBeNull()
            ->and($parent->fresh())->not->toBeNull();
    });

    it('deletes the attributes with the category', function () {
        $category = Category::factory()->create();
        addAttribute($category, 'Berat per ekor');

        $category->delete();

        expect(CategoryAttribute::query()->count())->toBe(0);
    });
});
