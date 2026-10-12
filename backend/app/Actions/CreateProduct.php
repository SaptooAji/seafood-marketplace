<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\ProductData;
use App\Data\VariantData;
use App\Exceptions\InvalidProductData;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates a product together with its first variants, so a product is never
 * saved without something to sell.
 */
class CreateProduct
{
    public function __construct(
        private SaveProduct $saveProduct,
        private SaveVariant $saveVariant,
    ) {}

    /**
     * Save the product, then each variant with its opening stock, in one
     * transaction: if any variant is rejected, nothing is saved.
     *
     * @param  list<VariantData>  $variants
     *
     * @throws InvalidProductData on a product field, or `variants.<index>.<field>` for a variant
     */
    public function handle(Store $store, ProductData $data, array $variants, ?User $by = null): Product
    {
        if ($variants === []) {
            throw new InvalidProductData('variants', 'Tambahkan minimal satu varian.');
        }

        return DB::transaction(function () use ($store, $data, $variants, $by): Product {
            $product = $this->saveProduct->handle($store, $data);

            foreach ($variants as $index => $variant) {
                try {
                    $this->saveVariant->handle($product, $variant, by: $by);
                } catch (InvalidProductData $e) {
                    throw new InvalidProductData("variants.{$index}.{$e->field}", $e->getMessage());
                }
            }

            return $product;
        });
    }
}
