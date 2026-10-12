<?php

declare(strict_types=1);

namespace App\Filament\Seller\Resources\Products\Pages;

use App\Actions\SaveProduct;
use App\Exceptions\InvalidProductData;
use App\Filament\Seller\Resources\Products\ProductResource;
use App\Models\Store;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates the product only; variants and photos are added on the edit page
 * it opens next.
 */
class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * Save through SaveProduct for the tenant store.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var Store $store */
        $store = Filament::getTenant();

        try {
            return app(SaveProduct::class)->handle($store, ProductResource::toProductData($data));
        } catch (InvalidProductData $e) {
            throw ProductResource::validationError($e, 'data');
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('edit', ['record' => $this->getRecord()]);
    }
}
