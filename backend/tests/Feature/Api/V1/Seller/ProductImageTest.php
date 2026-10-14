<?php

declare(strict_types=1);

use App\Enums\StoreRole;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('public');

    $this->user = User::factory()->create();
    $this->store = Store::factory()->active()->create();
    $this->store->users()->attach($this->user, ['role' => StoreRole::Owner->value]);
    Sanctum::actingAs($this->user);

    $this->product = Product::factory()->for($this->store)->create();
});

function productImagesUrl(Product|int $product, string $suffix = ''): string
{
    return '/api/v1/seller/products/'.($product instanceof Product ? $product->id : $product).'/images'.$suffix;
}

/**
 * A photo row with its two files on the fake public disk.
 */
function storedPhoto(Product $product, int $sortOrder): ProductImage
{
    $image = ProductImage::factory()->for($product)->create([
        'path' => 'products/'.$product->id.'/'.fake()->uuid().'.webp',
        'sort_order' => $sortOrder,
    ]);
    Storage::disk('public')->put($image->path, 'photo');
    Storage::disk('public')->put($image->thumbnailPath(), 'thumbnail');

    return $image;
}

it('requires a token', function (string $method, string $suffix) {
    app('auth')->forgetGuards();

    $this->json($method, productImagesUrl(1, $suffix))->assertUnauthorized();
})->with([
    'upload' => ['POST', ''],
    'delete' => ['DELETE', '/1'],
    'reorder' => ['PUT', '/order'],
]);

describe('POST /seller/products/{id}/images', function () {
    it('stores a JPG and returns its photo and thumbnail URLs', function () {
        $response = $this->post(productImagesUrl($this->product), [
            'photo' => UploadedFile::fake()->image('tuna.jpg', 1200, 900),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertExactJsonStructure(['data' => ['id', 'url', 'thumbnail_url', 'sort_order']])
            ->assertJsonPath('data.sort_order', 1);

        $image = ProductImage::query()->sole();
        expect($image->product_id)->toBe($this->product->id)
            ->and($response->json('data.id'))->toBe($image->id)
            ->and($response->json('data.url'))->toBe(Storage::disk('public')->url($image->path))
            ->and($response->json('data.thumbnail_url'))->toBe(Storage::disk('public')->url($image->thumbnailPath()));
        Storage::disk('public')->assertExists([$image->path, $image->thumbnailPath()]);
    });

    it('accepts PNG and WebP', function (string $name) {
        $this->post(productImagesUrl($this->product), [
            'photo' => UploadedFile::fake()->image($name, 640, 480),
        ], ['Accept' => 'application/json'])->assertCreated();
    })->with(['udang.png', 'cumi.webp']);

    it('puts a new photo after the existing ones', function () {
        storedPhoto($this->product, 1);
        storedPhoto($this->product, 2);

        $this->post(productImagesUrl($this->product), [
            'photo' => UploadedFile::fake()->image('tuna.jpg', 640, 480),
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.sort_order', 3);
    });

    it('lets staff upload photos', function () {
        $staff = User::factory()->create();
        $this->store->users()->attach($staff, ['role' => StoreRole::Staff->value]);
        Sanctum::actingAs($staff);

        $this->post(productImagesUrl($this->product), [
            'photo' => UploadedFile::fake()->image('tuna.jpg', 640, 480),
        ], ['Accept' => 'application/json'])->assertCreated();
    });

    it('rejects the file and stores nothing', function (Closure $file) {
        $this->post(productImagesUrl($this->product), ['photo' => $file()], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        expect(ProductImage::query()->count())->toBe(0)
            ->and(Storage::disk('public')->allFiles())->toBe([]);
    })->with([
        'no file' => [fn () => null],
        'a PDF' => [fn () => UploadedFile::fake()->create('katalog.pdf', 100, 'application/pdf')],
        'a photo over 5 MB' => [fn () => UploadedFile::fake()->create('tuna.jpg', 5121, 'image/jpeg')],
        'text named as a JPG' => [fn () => UploadedFile::fake()->createWithContent('tuna.jpg', 'not an image')],
    ]);

    it('rejects a ninth photo', function () {
        foreach (range(1, 8) as $sortOrder) {
            storedPhoto($this->product, $sortOrder);
        }

        $this->post(productImagesUrl($this->product), [
            'photo' => UploadedFile::fake()->image('tuna.jpg', 640, 480),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');

        expect($this->product->images()->count())->toBe(8)
            ->and(Storage::disk('public')->allFiles())->toHaveCount(16);
    });

    it('refuses the eleventh photo in a minute with 429 and stores nothing', function () {
        config(['catalog.max_photos' => 100]);

        foreach (range(1, 10) as $_) {
            $this->post(productImagesUrl($this->product), [
                'photo' => UploadedFile::fake()->image('tuna.jpg', 64, 48),
            ], ['Accept' => 'application/json'])->assertCreated();
        }

        $this->post(productImagesUrl($this->product), [
            'photo' => UploadedFile::fake()->image('tuna.jpg', 64, 48),
        ], ['Accept' => 'application/json'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'Terlalu sering mengunggah foto. Tunggu sebentar lalu coba lagi.');

        expect($this->product->images()->count())->toBe(10)
            ->and(Storage::disk('public')->allFiles())->toHaveCount(20);
    });

    it('returns 404 for a product of another store', function () {
        $other = Product::factory()->create();

        $this->post(productImagesUrl($other), [
            'photo' => UploadedFile::fake()->image('tuna.jpg', 640, 480),
        ], ['Accept' => 'application/json'])->assertNotFound();

        expect(ProductImage::query()->count())->toBe(0);
    });

    it('returns 404 for a deleted product', function () {
        $this->product->delete();

        $this->post(productImagesUrl($this->product), [
            'photo' => UploadedFile::fake()->image('tuna.jpg', 640, 480),
        ], ['Accept' => 'application/json'])->assertNotFound();
    });

    it('returns 404 when the user has no store', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->post(productImagesUrl($this->product), [
            'photo' => UploadedFile::fake()->image('tuna.jpg', 640, 480),
        ], ['Accept' => 'application/json'])->assertNotFound();
    });
});

describe('DELETE /seller/products/{id}/images/{imageId}', function () {
    it('deletes the photo and its files and moves the later photos up', function () {
        $first = storedPhoto($this->product, 1);
        $second = storedPhoto($this->product, 2);

        $this->deleteJson(productImagesUrl($this->product, '/'.$first->id))->assertNoContent();

        $this->assertModelMissing($first);
        expect($second->fresh()->sort_order)->toBe(1);
        Storage::disk('public')->assertMissing([$first->path, $first->thumbnailPath()]);
        Storage::disk('public')->assertExists([$second->path, $second->thumbnailPath()]);
    });

    it('returns 404 for a photo of another store', function () {
        $image = storedPhoto(Product::factory()->create(), 1);

        $this->deleteJson(productImagesUrl($image->product_id, '/'.$image->id))->assertNotFound();

        $this->assertModelExists($image);
    });

    it('returns 404 for a photo of another product of the same store', function () {
        $image = storedPhoto(Product::factory()->for($this->store)->create(), 1);

        $this->deleteJson(productImagesUrl($this->product, '/'.$image->id))->assertNotFound();

        $this->assertModelExists($image);
        Storage::disk('public')->assertExists($image->path);
    });

    it('returns 404 for a photo that does not exist', function () {
        $this->deleteJson(productImagesUrl($this->product, '/999999'))->assertNotFound();
    });
});

describe('PUT /seller/products/{id}/images/order', function () {
    it('sets the order and returns the photos in it', function () {
        $a = storedPhoto($this->product, 1);
        $b = storedPhoto($this->product, 2);
        $c = storedPhoto($this->product, 3);

        $this->putJson(productImagesUrl($this->product, '/order'), ['image_ids' => [$c->id, $a->id, $b->id]])
            ->assertOk()
            ->assertExactJsonStructure(['data' => ['*' => ['id', 'url', 'thumbnail_url', 'sort_order']]])
            ->assertJsonPath('data.*.id', [$c->id, $a->id, $b->id])
            ->assertJsonPath('data.*.sort_order', [1, 2, 3]);

        expect($this->product->images()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id]);
    });

    it('rejects a list that is not exactly the product’s photos and keeps the order', function (Closure $ids, string $field) {
        $a = storedPhoto($this->product, 1);
        $b = storedPhoto($this->product, 2);
        $other = storedPhoto(Product::factory()->for($this->store)->create(), 1);

        $this->putJson(productImagesUrl($this->product, '/order'), ['image_ids' => $ids($a, $b, $other)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect($this->product->images()->pluck('id')->all())->toBe([$a->id, $b->id]);
    })->with([
        'left out' => [fn () => null, 'image_ids'],
        'empty' => [fn () => [], 'image_ids'],
        'not a list' => [fn () => 'all', 'image_ids'],
        'a photo missing' => [fn ($a) => [$a->id], 'image_ids'],
        'a photo twice' => [fn ($a, $b) => [$b->id, $a->id, $a->id], 'image_ids'],
        'a photo of another product' => [fn ($a, $b, $other) => [$b->id, $a->id, $other->id], 'image_ids'],
        'not an id' => [fn ($a) => [$a->id, 'kedua'], 'image_ids.1'],
    ]);

    it('returns 404 for a product of another store', function () {
        $image = storedPhoto(Product::factory()->create(), 1);

        $this->putJson(productImagesUrl($image->product_id, '/order'), ['image_ids' => [$image->id]])->assertNotFound();
    });
});
