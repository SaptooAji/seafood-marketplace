<?php

declare(strict_types=1);

namespace App\Filament\Seller\Resources\StockMovements\Pages;

use App\Actions\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidStockMovement;
use App\Filament\Seller\Resources\StockMovements\StockMovementResource;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ListStockMovements extends ListRecords
{
    protected static string $resource = StockMovementResource::class;

    /**
     * The types a seller records by hand. Sales from the app and returns of
     * cancelled orders are recorded by the system.
     */
    private const array MANUAL_TYPES = [
        StockMovementType::StockIn,
        StockMovementType::SaleOffline,
        StockMovementType::Shrinkage,
        StockMovementType::Adjustment,
    ];

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('record')
                ->label('Catat')
                ->modalHeading('Catat stok')
                ->authorize(fn (): bool => Gate::allows('create', [StockMovement::class, Filament::getTenant()]))
                ->schema([
                    Select::make('variant_id')
                        ->label('Varian')
                        ->options(fn (): array => StockMovementResource::variantOptions(withTrashed: false))
                        ->searchable()
                        ->required(),
                    Select::make('type')
                        ->label('Jenis')
                        ->options(collect(self::MANUAL_TYPES)->mapWithKeys(
                            fn (StockMovementType $type): array => [$type->value => $type->label()],
                        ))
                        ->required()
                        ->live(),
                    TextInput::make('qty')
                        ->label('Jumlah')
                        ->helperText('Penyesuaian boleh negatif; jenis lain selalu positif.')
                        ->numeric()
                        ->required(),
                    TextInput::make('supplier_name')
                        ->label('Penyetor')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('type') === StockMovementType::StockIn->value),
                    TextInput::make('unit_cost')
                        ->label('Harga beli per satuan')
                        ->prefix('Rp')
                        ->integer()
                        ->minValue(0)
                        ->maxValue(100_000_000)
                        ->visible(fn (Get $get): bool => $get('type') === StockMovementType::StockIn->value),
                    Textarea::make('note')
                        ->label('Catatan')
                        ->maxLength(1000),
                ])
                ->action(function (array $data, Action $action): void {
                    $this->record($data, "mountedActions.{$action->getNestingIndex()}.data");

                    Notification::make()->success()->title('Catatan stok tersimpan')->send();
                }),
        ];
    }

    /**
     * Record through RecordStockMovement, for a variant of the tenant store
     * only, and show its errors on the field at fault.
     *
     * @param  array<string, mixed>  $data
     */
    private function record(array $data, string $statePath): void
    {
        $variant = ProductVariant::query()
            ->whereIn('id', StockMovementResource::variantIds(withTrashed: false))
            ->find($data['variant_id'])
            ?? throw ValidationException::withMessages([$statePath.'.variant_id' => 'Varian tidak ditemukan di toko ini.']);

        $type = StockMovementType::from((string) $data['type']);
        $isStockIn = $type === StockMovementType::StockIn;

        /** @var User $user */
        $user = auth()->user();

        try {
            app(RecordStockMovement::class)->handle(
                $variant,
                $type,
                (string) $data['qty'],
                by: $user,
                supplierName: $isStockIn && filled($data['supplier_name'] ?? null) ? trim((string) $data['supplier_name']) : null,
                unitCost: $isStockIn && filled($data['unit_cost'] ?? null) ? (int) $data['unit_cost'] : null,
                note: filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
            );
        } catch (InsufficientStock $e) {
            throw ValidationException::withMessages([$statePath.'.qty' => $e->getMessage()]);
        } catch (InvalidStockMovement $e) {
            throw ValidationException::withMessages([$statePath.'.'.$e->field => $e->getMessage()]);
        }
    }
}
