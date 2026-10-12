<?php

declare(strict_types=1);

use App\Actions\AddProductImage;
use App\Actions\DeleteProduct;
use App\Actions\RemoveProductImage;
use App\Actions\ReorderProductImages;
use App\Exceptions\InvalidProductData;
use App\Exceptions\InvalidProductPhoto;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->product = Product::factory()->create();
});

function addPhoto(?Product $product = null, string $name = 'tuna.jpg'): ProductImage
{
    return app(AddProductImage::class)->handle($product ?? test()->product, UploadedFile::fake()->image($name, 640, 480));
}

/**
 * Ids of the product's photos in display order.
 *
 * @return list<int>
 */
function photoOrder(Product $product): array
{
    return $product->images()->pluck('id')->all();
}

describe('adding', function () {
    it('stores the photo and its thumbnail as WebP under the product', function () {
        $image = addPhoto();

        $directory = 'products/'.$this->product->id;
        expect($image->fresh())
            ->product_id->toBe($this->product->id)
            ->sort_order->toBe(1)
            ->path->toStartWith($directory.'/')
            ->path->toEndWith('.webp')
            ->path->not->toEndWith('_thumb.webp');

        Storage::disk('public')->assertExists($image->path);
        Storage::disk('public')->assertExists(str_replace('.webp', '_thumb.webp', $image->path));
        expect(Storage::disk('public')->files($directory))->toHaveCount(2);
    });

    it('puts a new photo last', function () {
        addPhoto();
        $second = addPhoto();

        expect($second->sort_order)->toBe(2);
    });

    it('rejects a photo beyond the maximum and writes no file', function () {
        ProductImage::factory()->for($this->product)->count(8)
            ->state(new Sequence(fn (Sequence $sequence) => ['sort_order' => $sequence->index + 1]))
            ->create();

        expect(fn () => addPhoto())
            ->toThrow(fn (InvalidProductData $e) => expect($e->field)->toBe('photo'));

        expect($this->product->images()->count())->toBe(8)
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('counts the maximum per product', function () {
        ProductImage::factory()->count(8)->create();

        expect(addPhoto()->sort_order)->toBe(1);
    });

    it('writes nothing for a file that is not an image', function () {
        $file = UploadedFile::fake()->createWithContent('katalog.pdf', '%PDF-1.4 not an image');

        expect(fn () => app(AddProductImage::class)->handle($this->product, $file))
            ->toThrow(InvalidProductPhoto::class);

        expect(ProductImage::query()->count())->toBe(0)
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    });
});

describe('urls', function () {
    it('links to the photo and thumbnail on the public disk', function () {
        $image = addPhoto();

        expect($image->url())->toBe(Storage::disk('public')->url($image->path))
            ->and($image->thumbnailUrl())->toBe(Storage::disk('public')->url(str_replace('.webp', '_thumb.webp', $image->path)));
    });
});

describe('removing', function () {
    it('deletes the row and both files and closes the gap in the order', function () {
        $first = addPhoto();
        $second = addPhoto();
        $third = addPhoto();

        app(RemoveProductImage::class)->handle($first);

        expect(ProductImage::query()->find($first->id))->toBeNull()
            ->and($second->fresh()->sort_order)->toBe(1)
            ->and($third->fresh()->sort_order)->toBe(2);
        Storage::disk('public')->assertMissing($first->path);
        Storage::disk('public')->assertMissing(str_replace('.webp', '_thumb.webp', $first->path));
        expect(Storage::disk('public')->files('products/'.$this->product->id))->toHaveCount(4);
    });

    it('keeps the files when the product is soft deleted', function () {
        $image = addPhoto();

        app(DeleteProduct::class)->handle($this->product);

        expect(ProductImage::query()->find($image->id))->not->toBeNull();
        Storage::disk('public')->assertExists($image->path);
        Storage::disk('public')->assertExists(str_replace('.webp', '_thumb.webp', $image->path));
    });
});

describe('reordering', function () {
    it('puts the photos in the given order', function () {
        [$first, $second, $third] = [addPhoto(), addPhoto(), addPhoto()];

        app(ReorderProductImages::class)->handle($this->product, [$third->id, $first->id, $second->id]);

        expect(photoOrder($this->product))->toBe([$third->id, $first->id, $second->id])
            ->and($this->product->images()->pluck('sort_order')->all())->toBe([1, 2, 3]);
    });

    it('rejects a list that is not exactly the product’s photos', function (Closure $ids) {
        [$first, $second, $third] = [addPhoto(), addPhoto(), addPhoto()];
        $other = addPhoto(Product::factory()->create());

        expect(fn () => app(ReorderProductImages::class)->handle($this->product, $ids($first, $second, $third, $other)))
            ->toThrow(fn (InvalidProductData $e) => expect($e->field)->toBe('images'));

        expect(photoOrder($this->product))->toBe([$first->id, $second->id, $third->id])
            ->and($other->fresh()->sort_order)->toBe(1);
    })->with([
        'a photo of another product' => [fn ($a, $b, $c, $other) => [$c->id, $a->id, $other->id]],
        'a photo left out' => [fn ($a, $b, $c) => [$c->id, $a->id]],
        'a photo twice' => [fn ($a, $b, $c) => [$c->id, $a->id, $a->id]],
        'an extra photo of another product' => [fn ($a, $b, $c, $other) => [$c->id, $a->id, $b->id, $other->id]],
    ]);
});
