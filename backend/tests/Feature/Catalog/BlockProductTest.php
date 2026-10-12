<?php

declare(strict_types=1);

use App\Actions\BlockProduct;
use App\Actions\UnblockProduct;
use App\Enums\PlatformRole;
use App\Exceptions\InvalidProductData;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(PlatformRole::Admin);
    $this->actingAs($this->admin);

    $this->travelTo(now()->setDateTime(2026, 10, 12, 9, 0));
    $this->product = Product::factory()->create();
    ProductVariant::factory()->for($this->product)->create();
});

/**
 * Whether the product is in the buyer catalog.
 */
function inCatalog(Product $product): bool
{
    return Product::query()->visibleInCatalog()->whereKey($product->id)->exists();
}

describe('blocking', function () {
    it('blocks with the reason and takes the product out of the catalog', function () {
        expect(inCatalog($this->product))->toBeTrue();

        app(BlockProduct::class)->handle($this->product, 'Foto tidak sesuai produk');

        expect($this->product->fresh())
            ->blocked_at->toEqual(now())
            ->block_reason->toBe('Foto tidak sesuai produk')
            ->is_active->toBeTrue()
            ->and(inCatalog($this->product))->toBeFalse();
    });

    it('trims the reason', function () {
        app(BlockProduct::class)->handle($this->product, "  Foto tidak sesuai produk \n");

        expect($this->product->fresh()->block_reason)->toBe('Foto tidak sesuai produk');
    });

    it('rejects a reason that is empty or too long and changes nothing', function (string $reason) {
        expect(fn () => app(BlockProduct::class)->handle($this->product, $reason))
            ->toThrow(fn (InvalidProductData $e) => expect($e->field)->toBe('reason'));

        expect($this->product->fresh())
            ->blocked_at->toBeNull()
            ->block_reason->toBeNull();
    })->with([
        'empty' => [''],
        'only spaces' => ['   '],
        'over 500 characters' => [str_repeat('a', 501)],
    ]);

    it('accepts a reason of exactly 500 characters', function () {
        app(BlockProduct::class)->handle($this->product, str_repeat('a', 500));

        expect($this->product->fresh()->block_reason)->toHaveLength(500);
    });

    it('updates the reason of a blocked product and keeps when it was blocked', function () {
        app(BlockProduct::class)->handle($this->product, 'Foto tidak sesuai produk');
        $this->travel(2)->hours();

        app(BlockProduct::class)->handle($this->product, 'Harga tidak wajar');

        expect($this->product->fresh())
            ->block_reason->toBe('Harga tidak wajar')
            ->blocked_at->toEqual(now()->subHours(2));
    });
});

describe('unblocking', function () {
    it('puts an active product back in the catalog', function () {
        app(BlockProduct::class)->handle($this->product, 'Foto tidak sesuai produk');

        app(UnblockProduct::class)->handle($this->product);

        expect($this->product->fresh())
            ->blocked_at->toBeNull()
            ->block_reason->toBeNull()
            ->and(inCatalog($this->product))->toBeTrue();
    });

    it('keeps a product the seller turned off inactive', function () {
        $this->product->update(['is_active' => false]);
        app(BlockProduct::class)->handle($this->product, 'Foto tidak sesuai produk');

        app(UnblockProduct::class)->handle($this->product);

        expect($this->product->fresh())
            ->blocked_at->toBeNull()
            ->is_active->toBeFalse()
            ->and(inCatalog($this->product))->toBeFalse();
    });

    it('does nothing to a product that is not blocked', function () {
        app(UnblockProduct::class)->handle($this->product);

        expect($this->product->fresh()->blocked_at)->toBeNull();
    });
});

describe('activity log', function () {
    it('records who blocked the product and why', function () {
        app(BlockProduct::class)->handle($this->product, 'Foto tidak sesuai produk');

        $activity = Activity::query()->whereMorphedTo('subject', $this->product)->where('event', 'updated')->sole();

        expect($activity->causer->is($this->admin))->toBeTrue()
            ->and($activity->attribute_changes['attributes'])->toMatchArray(['block_reason' => 'Foto tidak sesuai produk'])
            ->and($activity->attribute_changes['attributes'])->toHaveKey('blocked_at')
            ->and($activity->attribute_changes['old']['blocked_at'])->toBeNull();
    });

    it('records who unblocked the product', function () {
        app(BlockProduct::class)->handle($this->product, 'Foto tidak sesuai produk');

        app(UnblockProduct::class)->handle($this->product);

        $activity = Activity::query()->whereMorphedTo('subject', $this->product)->where('event', 'updated')->latest('id')->first();

        expect($activity?->causer?->is($this->admin))->toBeTrue()
            ->and($activity?->attribute_changes['attributes'])->toMatchArray(['blocked_at' => null, 'block_reason' => null]);
    });

    it('records the seller’s name and active switch, but not other fields', function () {
        $this->product->update(['name' => 'Kepiting Bakau Jumbo', 'is_active' => false, 'description' => 'Deskripsi baru']);

        $activity = Activity::query()->whereMorphedTo('subject', $this->product)->where('event', 'updated')->sole();

        expect(array_keys($activity->attribute_changes['attributes']))->toEqualCanonicalizing(['name', 'is_active']);
    });
});
