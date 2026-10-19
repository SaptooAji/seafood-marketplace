<?php

declare(strict_types=1);

use App\Actions\AddProductImage;
use App\Actions\DeleteProduct;
use App\Actions\RemoveProductImage;
use App\Actions\ReorderProductImages;
use App\Exceptions\InvalidProductData;
use App\Exceptions\InvalidProductPhoto;
use App\Exceptions\ProductPhotoLimitReached;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->product = Product::factory()->create();
    $this->uploader = User::factory()->create();
});

function addPhoto(?Product $product = null, string $name = 'tuna.jpg', ?User $by = null): ProductImage
{
    return app(AddProductImage::class)->handle($product ?? test()->product, UploadedFile::fake()->image($name, 800, 600), $by ?? test()->uploader);
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

    it('writes nothing for a photo below the standard', function (Closure $file, string $message) {
        expect(fn () => app(AddProductImage::class)->handle($this->product, $file(), $this->uploader))
            ->toThrow(fn (InvalidProductPhoto $e) => expect($e->getMessage())->toContain($message));

        expect(ProductImage::query()->count())->toBe(0)
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    })->with('photos below the standard');

    it('writes nothing for a file that is not an image', function () {
        $file = UploadedFile::fake()->createWithContent('katalog.pdf', '%PDF-1.4 not an image');

        expect(fn () => app(AddProductImage::class)->handle($this->product, $file, $this->uploader))
            ->toThrow(InvalidProductPhoto::class);

        expect(ProductImage::query()->count())->toBe(0)
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    });
});

/*
 * RATE-3: resizing a photo is expensive, so uploads are limited per user
 * (10 a minute) and per store (200 a day, shared by its members), for the
 * API and the panel alike. Only a stored photo counts.
 */
describe('limits', function () {
    beforeEach(function () {
        $this->travelTo(now()->setTime(0, 10));
        config(['catalog.max_photos' => 1000]);
    });

    it('accepts ten photos from one user in a minute and refuses the eleventh', function () {
        foreach (range(1, 10) as $_) {
            addPhoto();
        }

        expect(fn () => addPhoto())->toThrow(function (ProductPhotoLimitReached $e) {
            expect($e->getStatusCode())->toBe(429)
                ->and($e->getHeaders()['Retry-After'])->toBeBetween(1, 60);
        });

        expect($this->product->images()->count())->toBe(10)
            ->and(Storage::disk('public')->allFiles())->toHaveCount(20);
    });

    it('counts each user on their own', function () {
        foreach (range(1, 10) as $_) {
            addPhoto();
        }

        expect(addPhoto(by: User::factory()->create())->sort_order)->toBe(11);
    });

    it('allows the user again after a minute', function () {
        foreach (range(1, 10) as $_) {
            addPhoto();
        }

        $this->travel(61)->seconds();

        expect(addPhoto()->sort_order)->toBe(11);
    });

    it('shares 200 photos a day between the members of a store', function () {
        $owner = $this->uploader;
        $staff = User::factory()->create();
        // Tiny photos keep 200 uploads fast; the standard is not what this test is about.
        config(['catalog.photo_min_side' => 8]);

        foreach (range(1, 200) as $i) {
            app(AddProductImage::class)->handle($this->product, UploadedFile::fake()->image('kecil.jpg', 8, 8), $i % 2 === 0 ? $owner : $staff);

            if ($i % 20 === 0) {
                $this->travel(1)->minutes();
            }
        }

        expect(fn () => addPhoto(by: User::factory()->create()))->toThrow(ProductPhotoLimitReached::class);
        expect($this->product->images()->count())->toBe(200);

        // Another store has its own quota, and the next day starts a new one.
        expect(addPhoto(Product::factory()->create(), by: User::factory()->create()))->toBeInstanceOf(ProductImage::class);

        $this->travelTo(now()->addDay()->startOfDay());
        expect(addPhoto(by: User::factory()->create()))->toBeInstanceOf(ProductImage::class);
    });

    it('does not count an upload that is refused', function (Closure $refused, string $exception) {
        foreach (range(1, 10) as $_) {
            expect($refused)->toThrow($exception);
        }

        foreach (range(1, 10) as $_) {
            addPhoto();
        }

        expect($this->product->images()->count())->toBe(10);
    })->with([
        'not an image' => [
            fn () => app(AddProductImage::class)->handle(test()->product, UploadedFile::fake()->createWithContent('tuna.jpg', 'not an image'), test()->uploader),
            InvalidProductPhoto::class,
        ],
        'beyond the maximum' => [
            function () {
                $full = Product::factory()->create();
                ProductImage::factory()->for($full)->create(['sort_order' => 1]);
                config(['catalog.max_photos' => 1]);

                try {
                    addPhoto($full);
                } finally {
                    config(['catalog.max_photos' => 1000]);
                }
            },
            InvalidProductData::class,
        ],
        'below the shortest side' => [
            fn () => app(AddProductImage::class)->handle(test()->product, UploadedFile::fake()->image('kecil.jpg', 300, 300), test()->uploader),
            InvalidProductPhoto::class,
        ],
        'wider than 4:3' => [
            fn () => app(AddProductImage::class)->handle(test()->product, UploadedFile::fake()->image('lebar.jpg', 1600, 900), test()->uploader),
            InvalidProductPhoto::class,
        ],
        'over the pixel limit' => [
            fn () => app(AddProductImage::class)->handle(test()->product, blankPng(8000, 6000), test()->uploader),
            InvalidProductPhoto::class,
        ],
    ]);
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
