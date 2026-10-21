<?php

declare(strict_types=1);

namespace App\Filament\Seller\Resources\Products\RelationManagers;

use App\Actions\DeleteVariant;
use App\Actions\SaveVariant;
use App\Data\VariantData;
use App\Enums\Unit;
use App\Exceptions\InvalidProductData;
use App\Filament\Seller\Resources\Products\ProductResource;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The product's variants. The stock is shown but never edited here: a new
 * variant's opening stock goes to the stock book through SaveVariant, and
 * later changes go through the stock book pages.
 */
class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Varian';

    protected static ?string $modelLabel = 'varian';

    /**
     * Bounds match the API; the unique name and positive quantities are
     * SaveVariant's rules. The opening stock is only asked when creating.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(100),
                Select::make('unit')
                    ->label('Satuan')
                    ->options(collect(Unit::cases())->mapWithKeys(fn (Unit $unit): array => [$unit->value => $unit->label()]))
                    ->required(),
                TextInput::make('price')
                    ->label('Harga')
                    ->prefix('Rp')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(100_000_000)
                    ->required(),
                TextInput::make('min_qty')
                    ->label('Jumlah minimal')
                    ->numeric()
                    ->required(),
                TextInput::make('initial_stock')
                    ->label('Stok awal')
                    ->numeric()
                    ->minValue(0)
                    ->visibleOn('create'),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama'),
                TextColumn::make('unit')
                    ->label('Satuan')
                    ->formatStateUsing(fn (Unit $state): string => $state->label()),
                TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR', locale: 'id'),
                TextColumn::make('stock')
                    ->label('Stok'),
                TextColumn::make('stock_confirmed_at')
                    ->label('Stok dikonfirmasi')
                    ->since()
                    ->placeholder('Belum pernah'),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data, CreateAction $action): ProductVariant {
                        /** @var User $user */
                        $user = auth()->user();

                        return $this->save($action, $data, by: $user);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (ProductVariant $record, array $data, EditAction $action): ProductVariant => $this->save($action, $data, $record)),
                DeleteAction::make()
                    ->using(function (ProductVariant $record, DeleteAction $action): void {
                        try {
                            app(DeleteVariant::class)->handle($record);
                        } catch (InvalidProductData $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                            $action->cancel();
                        }
                    }),
            ]);
    }

    /**
     * Save through SaveVariant and show its errors on the field at fault.
     *
     * @param  array<string, mixed>  $data
     */
    private function save(Action $action, array $data, ?ProductVariant $variant = null, ?User $by = null): ProductVariant
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        $variantData = new VariantData(
            name: (string) $data['name'],
            unit: Unit::from((string) $data['unit']),
            price: (int) $data['price'],
            minQty: (string) $data['min_qty'],
            isActive: (bool) ($data['is_active'] ?? true),
            initialStock: $variant === null && filled($data['initial_stock'] ?? null) ? (string) $data['initial_stock'] : null,
        );

        try {
            return app(SaveVariant::class)->handle($product, $variantData, $variant, $by);
        } catch (InvalidProductData $e) {
            throw ProductResource::validationError($e, "mountedActions.{$action->getNestingIndex()}.data");
        }
    }
}
