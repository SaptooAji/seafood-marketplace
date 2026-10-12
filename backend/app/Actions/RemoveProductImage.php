<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Removes a photo from its product for good.
 */
class RemoveProductImage
{
    /**
     * Delete the row, move the photos after it up one place, then delete the
     * photo and thumbnail files once the database change is committed.
     */
    public function handle(ProductImage $image): void
    {
        DB::transaction(function () use ($image): void {
            $image->delete();

            ProductImage::query()
                ->where('product_id', $image->product_id)
                ->where('sort_order', '>', $image->sort_order)
                ->decrement('sort_order');
        });

        Storage::disk('public')->delete([$image->path, $image->thumbnailPath()]);
    }
}
