<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * The `updated` activities recorded for a model, oldest first.
 *
 * @return Collection<int, Activity>
 */
function modelUpdates(Model $model): Collection
{
    return Activity::query()
        ->whereMorphedTo('subject', $model)
        ->where('event', 'updated')
        ->orderBy('id')
        ->get();
}

it('records the old and new name of a category and who changed it', function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(PlatformRole::Admin);
    $category = Category::factory()->create(['name' => 'Kepiting']);
    $this->actingAs($admin);

    $category->update(['name' => 'Kepiting Hidup']);

    $activity = modelUpdates($category)->sole();

    expect($activity->causer->is($admin))->toBeTrue()
        ->and($activity->attribute_changes->toArray())->toBe([
            'attributes' => ['name' => 'Kepiting Hidup'],
            'old' => ['name' => 'Kepiting'],
        ]);
});

it('records changes to the quality standard and parent', function () {
    $parent = Category::factory()->create();
    $category = Category::factory()->create();

    $category->update(['parent_id' => $parent->id, 'quality_standard' => 'Cangkang keras.']);

    expect(modelUpdates($category)->sole()->attribute_changes['attributes'])
        ->toBe(['parent_id' => $parent->id, 'quality_standard' => 'Cangkang keras.']);
});

it('records changes to a category attribute', function () {
    $attribute = CategoryAttribute::factory()->create(['unit' => 'g', 'is_required' => false]);

    $attribute->update(['unit' => 'kg', 'is_required' => true]);

    expect(modelUpdates($attribute)->sole()->attribute_changes->toArray())->toBe([
        'attributes' => ['unit' => 'kg', 'is_required' => true],
        'old' => ['unit' => 'g', 'is_required' => false],
    ]);
});

it('records the creation and deletion of a category', function () {
    $category = Category::factory()->create();
    $id = $category->id;

    $category->delete();

    expect(Activity::query()->where('subject_type', $category->getMorphClass())->where('subject_id', $id)->pluck('event')->all())
        ->toBe(['created', 'deleted']);
});

it('records nothing when a save changes no value', function () {
    $category = Category::factory()->create();
    $attribute = CategoryAttribute::factory()->for($category)->create();

    $category->update(['name' => $category->name]);
    $attribute->update(['name' => $attribute->name]);

    expect(modelUpdates($category))->toBeEmpty()
        ->and(modelUpdates($attribute))->toBeEmpty();
});
