<?php

declare(strict_types=1);

namespace App\Filament\Seller\Resources\StockMovements;

use App\Filament\Seller\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Seller\Resources\StockMovements\Tables\StockMovementsTable;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource as FilamentResource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

/**
 * "Buku stok": every stock movement of the tenant store, read only. New
 * entries are recorded with the "Catat" action on the list page; there is no
 * way to edit or delete one, since the stock book is the audit trail.
 *
 * Movements reach the store through variant and product, which Filament's
 * tenancy cannot follow, so the query is scoped here instead.
 *
 * @extends FilamentResource<StockMovement>
 */
class StockMovementResource extends FilamentResource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Buku stok';

    protected static ?string $modelLabel = 'catatan stok';

    protected static ?string $pluralModelLabel = 'buku stok';

    protected static ?int $navigationSort = 3;

    protected static bool $isScopedToTenant = false;

    public static function table(Table $table): Table
    {
        return StockMovementsTable::configure($table);
    }

    /**
     * StockMovementPolicy decides per store, so abilities without a record
     * get the tenant store. Abilities on a record (edit, delete) are not in
     * the policy, so they are always denied.
     */
    public static function getAuthorizationResponse(string|UnitEnum $action, ?Model $record = null): Response
    {
        if ($record !== null) {
            return parent::getAuthorizationResponse($action, $record);
        }

        return Gate::inspect($action, [StockMovement::class, Filament::getTenant()]);
    }

    /**
     * Movements of the tenant store, including those of deleted variants and
     * products, which stay in the history.
     *
     * @return Builder<StockMovement>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereIn('variant_id', self::variantIds(withTrashed: true))
            ->with(['variant.product' => fn ($query) => $query->withTrashed(), 'createdBy']);
    }

    /**
     * Ids of the tenant store's variants, as a subquery.
     *
     * @return Builder<ProductVariant>
     */
    public static function variantIds(bool $withTrashed): Builder
    {
        /** @var Store $store */
        $store = Filament::getTenant();

        $products = Product::query()->select('id')->whereBelongsTo($store)->when($withTrashed, fn (Builder $query) => $query->withTrashed());

        return ProductVariant::query()->select('id')->whereIn('product_id', $products)->when($withTrashed, fn (Builder $query) => $query->withTrashed());
    }

    /**
     * Variant choices labelled `Product — Variant`.
     *
     * @return array<int, string>
     */
    public static function variantOptions(bool $withTrashed): array
    {
        return ProductVariant::query()
            ->whereIn('id', self::variantIds($withTrashed))
            ->when($withTrashed, fn (Builder $query) => $query->withTrashed())
            ->with(['product' => fn ($query) => $query->withTrashed()])
            ->get()
            ->sortBy(fn (ProductVariant $variant): string => $variant->product->name.' '.$variant->name)
            ->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => $variant->product->name.' — '.$variant->name])
            ->all();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
        ];
    }
}
