<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Enums\PlatformRole;
use App\Filament\Admin\Resources\Categories\Pages\CreateCategory;
use App\Filament\Admin\Resources\Categories\Pages\EditCategory;
use App\Filament\Admin\Resources\Categories\Pages\ListCategories;
use App\Filament\Admin\Resources\Categories\RelationManagers\AttributesRelationManager;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');

    $admin = User::factory()->create();
    $admin->assignRole(PlatformRole::Admin);
    $this->actingAs($admin);
});

describe('access', function () {
    it('opens the list for platform roles', function (PlatformRole $role) {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get('/admin/categories')->assertOk();
    })->with([
        'admin' => [PlatformRole::Admin],
        'super admin' => [PlatformRole::SuperAdmin],
    ]);

    it('is forbidden to users without a platform role', function () {
        $this->actingAs(User::factory()->create())->get('/admin/categories')->assertForbidden();
    });
});

describe('list', function () {
    it('lists the categories', function () {
        $categories = Category::factory()->count(3)->create();

        Livewire::test(ListCategories::class)->assertCanSeeTableRecords($categories);
    });

    it('shows the parent name and the number of children', function () {
        $kepiting = Category::factory()->create(['name' => 'Kepiting']);
        Category::factory()->childOf($kepiting)->count(2)->create();
        $bakau = Category::factory()->childOf($kepiting)->create(['name' => 'Bakau']);

        Livewire::test(ListCategories::class)
            ->assertTableColumnStateSet('children_count', 3, $kepiting)
            ->assertSee('Bakau')
            ->assertTableColumnStateSet('parent.name', 'Kepiting', $bakau);
    });
});

describe('create and edit', function () {
    it('creates a root category with a slug from its name', function () {
        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Kepiting'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Category::query()->sole())
            ->name->toBe('Kepiting')
            ->slug->toBe('kepiting')
            ->parent_id->toBeNull();
    });

    it('creates a subcategory', function () {
        $kepiting = Category::factory()->create(['name' => 'Kepiting']);

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Bakau', 'parent_id' => $kepiting->id])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Category::query()->where('name', 'Bakau')->sole()->parent_id)->toBe($kepiting->id);
    });

    it('requires a name', function () {
        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => '', 'slug' => 'tanpa-nama'])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);

        expect(Category::query()->count())->toBe(0);
    });

    it('saves the quality standard', function () {
        $category = Category::factory()->create();

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['quality_standard' => '**Segar**: mata jernih, cangkang keras.'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($category->fresh()->quality_standard)->toBe('**Segar**: mata jernih, cangkang keras.');
    });

    it('rejects a manually changed slug that is taken', function () {
        Category::factory()->create(['slug' => 'kepiting']);
        $bakau = Category::factory()->create(['slug' => 'bakau']);

        Livewire::test(EditCategory::class, ['record' => $bakau->getRouteKey()])
            ->fillForm(['slug' => 'kepiting'])
            ->call('save')
            ->assertHasFormErrors(['slug' => 'unique']);

        expect($bakau->fresh()->slug)->toBe('bakau');
    });

    it('keeps its own slug when saving without changes', function () {
        $category = Category::factory()->create(['slug' => 'kepiting']);

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();
    });

    it('does not offer the category itself or its descendants as parent', function () {
        $kepiting = Category::factory()->create(['name' => 'Kepiting']);
        $bakau = Category::factory()->childOf($kepiting)->create(['name' => 'Bakau']);
        $super = Category::factory()->childOf($bakau)->create(['name' => 'Bakau Super']);
        $udang = Category::factory()->create(['name' => 'Udang']);

        Livewire::test(EditCategory::class, ['record' => $kepiting->getRouteKey()])
            ->assertFormFieldExists('parent_id', function (Select $field) use ($kepiting, $bakau, $super, $udang): bool {
                $options = array_keys($field->getOptions());

                return in_array($udang->id, $options, true)
                    && ! in_array($kepiting->id, $options, true)
                    && ! in_array($bakau->id, $options, true)
                    && ! in_array($super->id, $options, true);
            });
    });
});

describe('delete', function () {
    it('keeps a category that has children and shows a notification', function (string $where) {
        $kepiting = Category::factory()->create();
        Category::factory()->childOf($kepiting)->create();

        $page = $where === 'table'
            ? Livewire::test(ListCategories::class)->callAction(TestAction::make('delete')->table($kepiting))
            : Livewire::test(EditCategory::class, ['record' => $kepiting->getRouteKey()])->callAction(DeleteAction::class);

        $page->assertNotified();

        expect($kepiting->fresh())->not->toBeNull()
            ->and(Category::query()->count())->toBe(2);
    })->with(['table', 'edit page']);

    it('deletes a category without children', function () {
        $leaf = Category::factory()->create();

        Livewire::test(ListCategories::class)->callAction(TestAction::make('delete')->table($leaf));

        expect($leaf->fresh())->toBeNull();
    });
});

describe('attributes', function () {
    it('adds a select attribute with options', function () {
        $category = Category::factory()->create();

        Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $category, 'pageClass' => EditCategory::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'name' => 'Jenis kelamin',
                'type' => AttributeType::Select->value,
                'options' => ['Jantan', 'Betina'],
                'is_required' => true,
                'is_filterable' => true,
            ])
            ->assertHasNoFormErrors();

        expect(CategoryAttribute::query()->sole())
            ->category_id->toBe($category->id)
            ->name->toBe('Jenis kelamin')
            ->type->toBe(AttributeType::Select)
            ->options->toBe(['Jantan', 'Betina'])
            ->is_required->toBeTrue()
            ->is_filterable->toBeTrue();
    });

    it('adds a number attribute with a unit and no options', function () {
        $category = Category::factory()->create();

        Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $category, 'pageClass' => EditCategory::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'name' => 'Berat per ekor',
                'type' => AttributeType::Number->value,
                'unit' => 'g',
            ])
            ->assertHasNoFormErrors();

        expect(CategoryAttribute::query()->sole())
            ->unit->toBe('g')
            ->options->toBeNull();
    });

    it('requires options for a select attribute', function () {
        $category = Category::factory()->create();

        Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $category, 'pageClass' => EditCategory::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'name' => 'Jenis kelamin',
                'type' => AttributeType::Select->value,
                'options' => [],
            ])
            ->assertHasFormErrors(['options' => 'required']);

        expect(CategoryAttribute::query()->count())->toBe(0);
    });

    it('rejects a duplicate attribute name in the same category', function () {
        $category = Category::factory()->create();
        CategoryAttribute::factory()->for($category)->create(['name' => 'Berat per ekor']);

        Livewire::test(AttributesRelationManager::class, ['ownerRecord' => $category, 'pageClass' => EditCategory::class])
            ->callAction(TestAction::make('create')->table(), data: [
                'name' => 'Berat per ekor',
                'type' => AttributeType::Number->value,
            ])
            ->assertHasFormErrors(['name' => 'unique']);

        expect(CategoryAttribute::query()->count())->toBe(1);
    });
});
