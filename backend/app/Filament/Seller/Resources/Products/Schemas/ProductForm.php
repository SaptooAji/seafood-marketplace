<?php

declare(strict_types=1);

namespace App\Filament\Seller\Resources\Products\Schemas;

use App\Enums\AttributeType;
use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * The product form. Bounds match the API's ProductRequest; the catalog rules
 * (leaf category, attributes, pre-order fields, blocked products) are checked
 * by SaveProduct and shown on the field at fault. There is no stock field:
 * stock only changes through the stock book.
 */
class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Produk')
                    ->columns(2)
                    ->schema([
                        Select::make('category_id')
                            ->label('Kategori')
                            ->options(fn (): array => self::leafCategoryOptions())
                            ->searchable()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('attributes', [])),
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->required()
                            ->maxLength(5000)
                            ->rows(5)
                            ->columnSpanFull(),
                        Select::make('condition')
                            ->label('Kondisi')
                            ->options(collect(ProductCondition::cases())->mapWithKeys(
                                fn (ProductCondition $condition): array => [$condition->value => $condition->label()],
                            ))
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->helperText(fn (?Product $record): ?string => $record?->blocked_at !== null
                                ? 'Diblokir admin: '.$record->block_reason
                                : null),
                    ]),
                Section::make('Atribut')
                    ->description('Isian mengikuti kategori yang dipilih.')
                    ->columns(2)
                    ->visible(fn (Get $get): bool => filled($get('category_id')))
                    ->schema(fn (Get $get): array => self::attributeFields($get('category_id'))),
                Section::make('Penjualan')
                    ->columns(2)
                    ->schema([
                        Select::make('sales_mode')
                            ->label('Mode jual')
                            ->options(collect(SalesMode::cases())->mapWithKeys(
                                fn (SalesMode $mode): array => [$mode->value => $mode->label()],
                            ))
                            ->default(SalesMode::ReadyStock->value)
                            ->required()
                            ->live(),
                        TextInput::make('daily_quota')
                            ->label('Kuota harian')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(9_999_999)
                            ->visible(fn (Get $get): bool => self::isPreOrder($get('sales_mode'))),
                        TextInput::make('lead_time_days')
                            ->label('Lead time (hari)')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(255)
                            ->visible(fn (Get $get): bool => self::isPreOrder($get('sales_mode'))),
                        TimePicker::make('cutoff_time')
                            ->label('Jam tutup pesanan')
                            ->seconds(false)
                            ->format('H:i')
                            ->visible(fn (Get $get): bool => self::isPreOrder($get('sales_mode'))),
                        TextInput::make('shelf_life_days')
                            ->label('Masa simpan (hari)')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(65_535),
                        Textarea::make('storage_instructions')
                            ->label('Cara penyimpanan')
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Leaf categories only, labelled with their parent: `Kepiting › Bakau`.
     *
     * @return array<int, string>
     */
    private static function leafCategoryOptions(): array
    {
        return Category::query()
            ->whereDoesntHave('children')
            ->with('parent')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Category $category): array => [
                $category->id => $category->parent === null ? $category->name : $category->parent->name.' › '.$category->name,
            ])
            ->all();
    }

    /**
     * One field per effective attribute of the category, stored under
     * `attributes.{id}` as SaveProduct expects.
     *
     * @return list<Field>
     */
    private static function attributeFields(mixed $categoryId): array
    {
        $category = filled($categoryId) ? Category::query()->find($categoryId) : null;

        if ($category === null) {
            return [];
        }

        return $category->effectiveAttributes()
            ->map(fn (CategoryAttribute $attribute): Field => self::attributeField($attribute))
            ->values()
            ->all();
    }

    private static function attributeField(CategoryAttribute $attribute): Field
    {
        $name = 'attributes.'.$attribute->id;

        $field = match ($attribute->type) {
            AttributeType::Number => TextInput::make($name)->numeric()->suffix($attribute->unit),
            AttributeType::Select => Select::make($name)->options(array_combine($attribute->options ?? [], $attribute->options ?? [])),
            AttributeType::Date => DatePicker::make($name)->format('Y-m-d'),
            AttributeType::Text => TextInput::make($name)->maxLength(255),
        };

        return $field
            ->label($attribute->name)
            ->required($attribute->is_required);
    }

    private static function isPreOrder(mixed $salesMode): bool
    {
        return ($salesMode instanceof SalesMode ? $salesMode->value : $salesMode) === SalesMode::PreOrder->value;
    }
}
