<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\InvalidProductData;
use App\Exceptions\InvalidProductPhoto;
use App\Exceptions\ProductPhotoLimitReached;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Support\ProductPhotoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Adds a photo to a product, after the ones it already has.
 */
class AddProductImage
{
    /**
     * Resizing is expensive, so uploads are limited per user and per store
     * (RATE-3). The API and the panel both upload through here, so they share
     * the limits.
     */
    private const int USER_PER_MINUTE_LIMIT = 10;

    private const int STORE_DAILY_LIMIT = 200;

    private const int DAY_IN_SECONDS = 86_400;

    public function __construct(private ProductPhotoProcessor $processor) {}

    /**
     * Resize the upload into a WebP photo and thumbnail on the public disk
     * (`products/{product_id}/{uuid}.webp` and `{uuid}_thumb.webp`) and put it
     * last in the product's order. Only a stored photo counts toward the
     * uploader's and the store's limits.
     *
     * @throws ProductPhotoLimitReached when the uploader or the store has used up a limit
     * @throws InvalidProductData on `photo` when the product already has the maximum
     * @throws InvalidProductPhoto when the file is not an image
     */
    public function handle(Product $product, UploadedFile $file, User $uploader): ProductImage
    {
        $userKey = 'product-photo:user:'.$uploader->id;
        $storeKey = 'product-photo:store:'.$product->store_id.':'.today()->toDateString();

        if (RateLimiter::tooManyAttempts($userKey, self::USER_PER_MINUTE_LIMIT)) {
            throw ProductPhotoLimitReached::tooFrequent(RateLimiter::availableIn($userKey));
        }

        if (RateLimiter::tooManyAttempts($storeKey, self::STORE_DAILY_LIMIT)) {
            throw ProductPhotoLimitReached::storeQuotaUsed(max(1, (int) ceil(now()->diffInSeconds(today()->addDay()))));
        }

        // Checked before the slow resize as well as under the lock below.
        $this->ensureRoom($product);

        $processed = $this->processor->process($file);

        $image = new ProductImage(['path' => 'products/'.$product->id.'/'.Str::uuid().'.webp']);
        $disk = Storage::disk('public');
        $disk->put($image->path, $processed->photo);
        $disk->put($image->thumbnailPath(), $processed->thumbnail);

        try {
            $image = DB::transaction(function () use ($product, $image): ProductImage {
                // Serializes concurrent uploads to one product so the count and order hold.
                Product::query()->whereKey($product->getKey())->lockForUpdate()->first();
                $this->ensureRoom($product);

                $image->sort_order = (int) $product->images()->max('sort_order') + 1;
                $product->images()->save($image);

                return $image;
            });
        } catch (Throwable $e) {
            $disk->delete([$image->path, $image->thumbnailPath()]);

            throw $e;
        }

        RateLimiter::hit($userKey, 60);
        RateLimiter::hit($storeKey, self::DAY_IN_SECONDS);

        return $image;
    }

    /**
     * @throws InvalidProductData when the product already has the maximum
     */
    private function ensureRoom(Product $product): void
    {
        $max = config()->integer('catalog.max_photos');

        if ($product->images()->count() >= $max) {
            throw new InvalidProductData('photo', "Produk paling banyak memiliki {$max} foto.");
        }
    }
}
