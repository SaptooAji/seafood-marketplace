<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\InvalidProductData;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Sets the display order of a product's photos.
 */
class ReorderProductImages
{
    /**
     * @param  list<int>  $imageIds  every photo of the product, each once, first photo first
     *
     * @throws InvalidProductData on `images` when the list is not exactly the product's photos
     */
    public function handle(Product $product, array $imageIds): void
    {
        $current = $product->images()->pluck('id')->all();
        $given = $imageIds;
        sort($current);
        sort($given);

        if ($given !== $current) {
            throw new InvalidProductData('images', 'Urutan harus memuat setiap foto produk ini tepat satu kali.');
        }

        DB::transaction(function () use ($product, $imageIds): void {
            foreach ($imageIds as $index => $id) {
                $product->images()->whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });
    }
}
