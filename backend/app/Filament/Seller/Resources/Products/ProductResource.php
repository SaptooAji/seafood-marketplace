<?php

declare(strict_types=1);

namespace App\Filament\Seller\Resources\Products;

use App\Data\ProductData;
use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Exceptions\InvalidProductData;
use App\Filament\Seller\Resources\Products\Pages\CreateProduct;
use App\Filament\Seller\Resources\Products\Pages\EditProduct;
use App\Filament\Seller\Resources\Products\Pages\ListProducts;
use App\Filament\Seller\Resources\Products\RelationManagers\ImagesRelationManager;
use App\Filament\Seller\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Filament\Seller\Resources\Products\Schemas\ProductForm;
use App\Filament\Seller\Resources\Products\Tables\ProductsTable;
use App\Models\Product;
use App\Models\Store;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource as FilamentResource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use UnitEnum;

/**
 * Products of the store the seller is working in (the panel tenant). Records
 * of other stores are out of the query, so their URLs are a 404. Saving goes
 * through SaveProduct, like the API.
 *
 * @extends FilamentResource<Product>
 */
class ProductResource extends FilamentResource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $modelLabel = 'produk';

    protected static ?string $pluralModelLabel = 'produk';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $tenantOwnershipRelationshipName = 'store';

    public static function form(Schema $schema): Schema
    {
        return ProductForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductsTable::configure($table);
    }

    /**
     * ProductPolicy decides per store, so abilities without a record
     * (`viewAny`, `create`) get the tenant store as their argument.
     */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        if ($record !== null) {
            return parent::getAuthorizationResponse($action, $record);
        }

        return Gate::inspect($action, [Product::class, Filament::getTenant()]);
    }

    /**
     * Only the tenant store's products, stated here as well as by Filament's
     * tenancy scope, so the edit page of another store's product is a 404.
     *
     * @return Builder<Product>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Store $store */
        $store = Filament::getTenant();

        return parent::getEloquentQuery()->whereBelongsTo($store);
    }

    public static function getRelations(): array
    {
        return [
            VariantsRelationManager::class,
            ImagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    /**
     * The form state as SaveProduct's input.
     *
     * @param  array<string, mixed>  $data
     */
    public static function toProductData(array $data): ProductData
    {
        /** @var array<int, string|null> $attributes */
        $attributes = $data['attributes'] ?? [];

        return new ProductData(
            categoryId: (int) $data['category_id'],
            name: trim((string) $data['name']),
            description: (string) $data['description'],
            condition: ProductCondition::from((string) $data['condition']),
            salesMode: SalesMode::from((string) $data['sales_mode']),
            dailyQuota: filled($data['daily_quota'] ?? null) ? (string) $data['daily_quota'] : null,
            leadTimeDays: filled($data['lead_time_days'] ?? null) ? (int) $data['lead_time_days'] : null,
            cutoffTime: filled($data['cutoff_time'] ?? null) ? substr((string) $data['cutoff_time'], 0, 5) : null,
            shelfLifeDays: filled($data['shelf_life_days'] ?? null) ? (int) $data['shelf_life_days'] : null,
            storageInstructions: filled($data['storage_instructions'] ?? null) ? (string) $data['storage_instructions'] : null,
            isActive: (bool) ($data['is_active'] ?? false),
            attributes: array_map(fn (mixed $value): ?string => filled($value) ? (string) $value : null, $attributes),
        );
    }

    /**
     * Show a catalog rule broken in SaveProduct or SaveVariant on its field.
     *
     * @param  string  $statePath  where the form keeps its state, e.g. `data`
     */
    public static function validationError(InvalidProductData $e, string $statePath): ValidationException
    {
        return ValidationException::withMessages([$statePath.'.'.$e->field => $e->getMessage()]);
    }
}
