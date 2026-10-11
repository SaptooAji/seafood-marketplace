<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Categories\RelationManagers;

use App\Enums\AttributeType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * The attributes defined on the category itself. Inherited attributes are
 * edited on the ancestor that defines them.
 */
class AttributesRelationManager extends RelationManager
{
    protected static string $relationship = 'attributes';

    protected static ?string $title = 'Atribut';

    protected static ?string $modelLabel = 'atribut';

    /**
     * Options are shown and required only for `select` attributes.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(100)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('category_id', $this->getOwnerRecord()->getKey()),
                    ),
                Select::make('type')
                    ->label('Tipe')
                    ->options(collect(AttributeType::cases())->mapWithKeys(
                        fn (AttributeType $type): array => [$type->value => $type->label()],
                    ))
                    ->required()
                    ->live(),
                TagsInput::make('options')
                    ->label('Pilihan')
                    ->placeholder('Ketik lalu tekan Enter')
                    ->visible(fn (Get $get): bool => self::isSelect($get('type')))
                    ->required(fn (Get $get): bool => self::isSelect($get('type'))),
                TextInput::make('unit')
                    ->label('Satuan')
                    ->placeholder('mis. g, kg, cm')
                    ->maxLength(20),
                Toggle::make('is_required')->label('Wajib diisi penjual'),
                Toggle::make('is_filterable')->label('Bisa difilter pembeli'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Nama'),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->formatStateUsing(fn (AttributeType $state): string => $state->label()),
                TextColumn::make('options')->label('Pilihan')->badge()->placeholder('-'),
                TextColumn::make('unit')->label('Satuan')->placeholder('-'),
                IconColumn::make('is_required')->label('Wajib')->boolean(),
                IconColumn::make('is_filterable')->label('Filter')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->mutateDataUsing(self::clearUnusedOptions(...)),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing(self::clearUnusedOptions(...)),
                DeleteAction::make(),
            ]);
    }

    private static function isSelect(mixed $type): bool
    {
        return ($type instanceof AttributeType ? $type : AttributeType::tryFrom((string) $type)) === AttributeType::Select;
    }

    /**
     * A hidden field is not saved, so changing an attribute away from `select`
     * would keep its old options. Clear them explicitly.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function clearUnusedOptions(array $data): array
    {
        if (! self::isSelect($data['type'] ?? null)) {
            $data['options'] = null;
        }

        return $data;
    }
}
