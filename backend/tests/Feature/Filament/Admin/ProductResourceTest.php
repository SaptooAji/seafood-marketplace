<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Enums\StoreRole;
use App\Filament\Admin\Resources\Products\Pages\ListProducts;
use App\Filament\Admin\Resources\Products\Pages\ViewProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');

    $this->admin = User::factory()->create();
    $this->admin->assignRole(PlatformRole::Admin);
    $this->actingAs($this->admin);
});

describe('access', function () {
    it('opens the list and the view page for platform roles', function (PlatformRole $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $product = Product::factory()->create();

        $this->actingAs($user)->get('/admin/products')->assertOk();
        $this->actingAs($user)->get("/admin/products/{$product->id}")->assertOk();
    })->with([
        'admin' => [PlatformRole::Admin],
        'super admin' => [PlatformRole::SuperAdmin],
    ]);

    it('is forbidden to users without a platform role, store owners included', function () {
        $owner = User::factory()->create();
        Store::factory()->active()->create()->users()->attach($owner, ['role' => StoreRole::Owner->value]);

        $this->actingAs(User::factory()->create())->get('/admin/products')->assertForbidden();
        $this->actingAs($owner)->get('/admin/products')->assertForbidden();
    });

    it('has no create or edit pages', function () {
        expect(Route::has('filament.admin.resources.products.create'))->toBeFalse()
            ->and(Route::has('filament.admin.resources.products.edit'))->toBeFalse();
    });
});

describe('list', function () {
    it('lists the products of every store', function () {
        $products = Product::factory()->count(3)->create();

        Livewire::test(ListProducts::class)->assertCanSeeTableRecords($products);
    });

    it('filters by status', function (string $status, string $expected) {
        $products = [
            'active' => Product::factory()->create(),
            'inactive' => Product::factory()->inactive()->create(),
            'blocked' => Product::factory()->blocked()->create(),
        ];

        Livewire::test(ListProducts::class)
            ->filterTable('status', $status)
            ->assertCanSeeTableRecords([$products[$expected]])
            ->assertCanNotSeeTableRecords(collect($products)->except($expected)->values());
    })->with([
        'aktif' => ['active', 'active'],
        'nonaktif' => ['inactive', 'inactive'],
        'diblokir' => ['blocked', 'blocked'],
    ]);

    it('filters by store and by category', function () {
        $budi = Store::factory()->active()->create();
        $bakau = Category::factory()->create();
        $ofBudi = Product::factory()->for($budi)->create();
        $inBakau = Product::factory()->for($bakau)->create();

        Livewire::test(ListProducts::class)
            ->filterTable('store', $budi->id)
            ->assertCanSeeTableRecords([$ofBudi])
            ->assertCanNotSeeTableRecords([$inBakau]);

        Livewire::test(ListProducts::class)
            ->filterTable('category', $bakau->id)
            ->assertCanSeeTableRecords([$inBakau])
            ->assertCanNotSeeTableRecords([$ofBudi]);
    });

    it('leaves out deleted products', function () {
        $deleted = Product::factory()->create(['deleted_at' => now()]);

        Livewire::test(ListProducts::class)->assertCanNotSeeTableRecords([$deleted]);
    });
});

describe('view', function () {
    it('shows the product with its store, variants, and photos', function () {
        $product = Product::factory()->for(Store::factory()->active()->create(['name' => 'UD Kepiting Pak Budi']))->create(['name' => 'Kepiting Bakau']);
        ProductVariant::factory()->for($product)->create(['name' => 'Jantan 300-400 g']);
        $image = ProductImage::factory()->for($product)->create();

        Livewire::test(ViewProduct::class, ['record' => $product->getRouteKey()])
            ->assertSee(['Kepiting Bakau', 'UD Kepiting Pak Budi', 'Jantan 300-400 g'])
            ->assertSee($image->thumbnailUrl(), escape: false);
    });

    it('does not show the store’s bank account', function () {
        $store = Store::factory()->active()->create();
        $product = Product::factory()->for($store)->create();

        Livewire::test(ViewProduct::class, ['record' => $product->getRouteKey()])
            ->assertDontSee($store->bank_account_no);
    });
});

describe('moderation', function () {
    it('blocks a product with a reason from the list', function () {
        $product = Product::factory()->create();

        Livewire::test(ListProducts::class)
            ->callAction(TestAction::make('block')->table($product), data: ['reason' => 'Foto tidak sesuai produk'])
            ->assertHasNoActionErrors();

        expect($product->fresh())
            ->blocked_at->not->toBeNull()
            ->block_reason->toBe('Foto tidak sesuai produk');
    });

    it('blocks a product from the view page', function () {
        $product = Product::factory()->create();

        Livewire::test(ViewProduct::class, ['record' => $product->getRouteKey()])
            ->callAction('block', data: ['reason' => 'Foto tidak sesuai produk'])
            ->assertHasNoActionErrors();

        expect($product->fresh()->block_reason)->toBe('Foto tidak sesuai produk');
    });

    it('requires a reason of at most 500 characters', function (?string $reason) {
        $product = Product::factory()->create();

        Livewire::test(ViewProduct::class, ['record' => $product->getRouteKey()])
            ->callAction('block', data: ['reason' => $reason])
            ->assertHasActionErrors(['reason']);

        expect($product->fresh()->blocked_at)->toBeNull();
    })->with([
        'none' => [null],
        'only spaces' => ['   '],
        'over 500 characters' => [str_repeat('a', 501)],
    ]);

    it('unblocks a blocked product', function () {
        $product = Product::factory()->blocked()->create();

        Livewire::test(ViewProduct::class, ['record' => $product->getRouteKey()])
            ->callAction('unblock')
            ->assertHasNoActionErrors();

        expect($product->fresh())
            ->blocked_at->toBeNull()
            ->block_reason->toBeNull();
    });

    it('offers block for products that are not blocked and unblock for those that are', function () {
        $open = Product::factory()->create();
        $blocked = Product::factory()->blocked()->create();

        Livewire::test(ListProducts::class)
            ->assertActionVisible(TestAction::make('block')->table($open))
            ->assertActionHidden(TestAction::make('unblock')->table($open))
            ->assertActionHidden(TestAction::make('block')->table($blocked))
            ->assertActionVisible(TestAction::make('unblock')->table($blocked));
    });
});

describe('policy', function () {
    beforeEach(function () {
        $this->store = Store::factory()->active()->create();
        $this->product = Product::factory()->for($this->store)->create();
        $this->owner = User::factory()->create();
        $this->staff = User::factory()->create();
        $this->store->users()->attach($this->owner, ['role' => StoreRole::Owner->value]);
        $this->store->users()->attach($this->staff, ['role' => StoreRole::Staff->value]);
    });

    it('lets platform roles see and moderate every product', function (PlatformRole $role) {
        $user = User::factory()->create();
        $user->assignRole($role);

        expect($user->can('viewAny', Product::class))->toBeTrue()
            ->and($user->can('view', $this->product))->toBeTrue()
            ->and($user->can('moderate', $this->product))->toBeTrue();
    })->with([
        'admin' => [PlatformRole::Admin],
        'super admin' => [PlatformRole::SuperAdmin],
    ]);

    it('does not give admins the seller’s update or delete', function () {
        expect($this->admin->can('update', $this->product))->toBeFalse()
            ->and($this->admin->can('delete', $this->product))->toBeFalse();
    });

    it('does not let store members moderate their own products', function (string $who) {
        expect($this->{$who}->can('moderate', $this->product))->toBeFalse()
            ->and($this->{$who}->can('viewAny', Product::class))->toBeFalse();
    })->with(['owner', 'staff']);
});
