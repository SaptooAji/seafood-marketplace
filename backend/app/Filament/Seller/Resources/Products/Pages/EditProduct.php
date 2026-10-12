<?php

declare(strict_types=1);

namespace App\Filament\Seller\Resources\Products\Pages;

use App\Actions\DeleteProduct;
use App\Actions\SaveProduct;
use App\Exceptions\InvalidProductData;
use App\Filament\Seller\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\Store;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(fn (Product $record) => app(DeleteProduct::class)->handle($record)),
        ];
    }

    /**
     * Add the attribute values, keyed by attribute id, and show the cut-off
     * time as `HH:MM`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Product $product */
        $product = $this->getRecord();

        $data['attributes'] = $product->attributeValues()->pluck('value', 'category_attribute_id')->all();
        $data['cutoff_time'] = $product->cutoff_time === null ? null : substr($product->cutoff_time, 0, 5);

        return $data;
    }

    /**
     * Save through SaveProduct, which keeps the slug and refuses to turn on a
     * blocked product.
     *
     * @param  Product  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Store $store */
        $store = Filament::getTenant();

        try {
            return app(SaveProduct::class)->handle($store, ProductResource::toProductData($data), $record);
        } catch (InvalidProductData $e) {
            throw ProductResource::validationError($e, 'data');
        }
    }
}
