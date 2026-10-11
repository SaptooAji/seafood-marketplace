<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

const CATEGORIES_URL = '/api/v1/categories';

it('requires a token', function () {
    $this->getJson(CATEGORIES_URL)->assertUnauthorized();
});

describe('signed in', function () {
    beforeEach(function () {
        Sanctum::actingAs(User::factory()->create());
    });

    it('returns the categories as a tree', function () {
        $kepiting = Category::factory()->create(['name' => 'Kepiting', 'slug' => 'kepiting', 'quality_standard' => 'Cangkang keras.']);
        $bakau = Category::factory()->childOf($kepiting)->create(['name' => 'Bakau', 'slug' => 'bakau']);
        Category::factory()->childOf($bakau)->create(['name' => 'Bakau Super', 'slug' => 'bakau-super']);
        Category::factory()->create(['name' => 'Tiram', 'slug' => 'tiram']);

        $this->getJson(CATEGORIES_URL)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Kepiting')
            ->assertJsonPath('data.0.slug', 'kepiting')
            ->assertJsonPath('data.0.quality_standard', 'Cangkang keras.')
            ->assertJsonPath('data.0.children.0.name', 'Bakau')
            ->assertJsonPath('data.0.children.0.children.0.name', 'Bakau Super')
            ->assertJsonPath('data.0.children.0.children.0.children', [])
            ->assertJsonPath('data.1.name', 'Tiram')
            ->assertJsonPath('data.1.children', []);
    });

    it('returns the fields of every node and attribute', function () {
        $kepiting = Category::factory()->create();
        CategoryAttribute::factory()->for($kepiting)->create();

        $this->getJson(CATEGORIES_URL)
            ->assertOk()
            ->assertExactJsonStructure(['data' => ['*' => [
                'id', 'name', 'slug', 'sort_order', 'quality_standard',
                'attributes' => ['*' => [
                    'id', 'name', 'type' => ['value', 'label'], 'options', 'unit',
                    'is_required', 'is_filterable', 'inherited',
                ]],
                'children',
            ]]]);
    });

    it('includes inherited attributes and marks them', function () {
        $kepiting = Category::factory()->create(['name' => 'Kepiting']);
        $bakau = Category::factory()->childOf($kepiting)->create(['name' => 'Bakau']);
        CategoryAttribute::factory()->for($kepiting)->create([
            'name' => 'Berat per ekor', 'type' => AttributeType::Number, 'unit' => 'g', 'is_required' => true,
        ]);
        CategoryAttribute::factory()->for($bakau)->create([
            'name' => 'Jenis kelamin', 'type' => AttributeType::Select, 'options' => ['Jantan', 'Betina'],
        ]);

        $response = $this->getJson(CATEGORIES_URL)->assertOk();

        expect($response->json('data.0.attributes'))->toHaveCount(1)
            ->and($response->json('data.0.attributes.0.inherited'))->toBeFalse()
            ->and($response->json('data.0.children.0.attributes'))->toMatchArray([
                [
                    'id' => CategoryAttribute::query()->where('name', 'Berat per ekor')->value('id'),
                    'name' => 'Berat per ekor',
                    'type' => ['value' => 'number', 'label' => AttributeType::Number->label()],
                    'options' => null,
                    'unit' => 'g',
                    'is_required' => true,
                    'is_filterable' => false,
                    'inherited' => true,
                ],
                [
                    'id' => CategoryAttribute::query()->where('name', 'Jenis kelamin')->value('id'),
                    'name' => 'Jenis kelamin',
                    'type' => ['value' => 'select', 'label' => AttributeType::Select->label()],
                    'options' => ['Jantan', 'Betina'],
                    'unit' => null,
                    'is_required' => false,
                    'is_filterable' => false,
                    'inherited' => false,
                ],
            ]);
    });

    it('uses the child’s own definition when it redefines an inherited attribute', function () {
        $kepiting = Category::factory()->create();
        $bakau = Category::factory()->childOf($kepiting)->create();
        CategoryAttribute::factory()->for($kepiting)->create(['name' => 'Berat per ekor', 'unit' => 'g']);
        CategoryAttribute::factory()->for($bakau)->create(['name' => 'Berat per ekor', 'unit' => 'kg']);

        $attributes = $this->getJson(CATEGORIES_URL)->json('data.0.children.0.attributes');

        expect($attributes)->toHaveCount(1)
            ->and($attributes[0]['unit'])->toBe('kg')
            ->and($attributes[0]['inherited'])->toBeFalse();
    });

    it('orders roots and children by sort_order, then name', function () {
        $second = Category::factory()->create(['name' => 'Ikan', 'sort_order' => 2]);
        Category::factory()->create(['name' => 'Cumi', 'sort_order' => 1]);
        Category::factory()->create(['name' => 'Tiram', 'sort_order' => 1]);
        Category::factory()->childOf($second)->create(['name' => 'Tongkol', 'sort_order' => 1]);
        Category::factory()->childOf($second)->create(['name' => 'Kakap', 'sort_order' => 1]);
        Category::factory()->childOf($second)->create(['name' => 'Bandeng', 'sort_order' => 3]);

        $response = $this->getJson(CATEGORIES_URL)->assertOk();

        expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Cumi', 'Tiram', 'Ikan'])
            ->and(collect($response->json('data.2.children'))->pluck('name')->all())->toBe(['Kakap', 'Tongkol', 'Bandeng']);
    });

    it('returns an empty list when there are no categories', function () {
        $this->getJson(CATEGORIES_URL)->assertOk()->assertExactJson(['data' => []]);
    });

    it('uses the same number of queries for 3 and for 30 categories', function () {
        // Chains of three (root > child > grandchild), each with an attribute.
        $addChains = function (int $chains): void {
            foreach (range(1, $chains) as $chain) {
                $parent = null;

                foreach (range(1, 3) as $level) {
                    $parent = Category::factory()->state(['parent_id' => $parent?->id])->create();
                    CategoryAttribute::factory()->for($parent)->create();
                }
            }
        };

        $countQueries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson(CATEGORIES_URL)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $addChains(1);
        $withThree = $countQueries();

        $addChains(9);
        expect(Category::query()->count())->toBe(30)
            ->and($countQueries())->toBe($withThree);
    });
});
