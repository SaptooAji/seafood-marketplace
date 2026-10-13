<?php

declare(strict_types=1);

namespace App\Filament\Seller\Resources\Products\Schemas;

use App\Actions\SuggestProductDescription;
use App\Enums\AttributeType;
use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Exceptions\InvalidProductData;
use App\Exceptions\ProductDescriptionLimitReached;
use App\Exceptions\ProductDescriptionUnavailable;
use App\Filament\Seller\Resources\Products\ProductResource;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * The product form. Bounds match the API's ProductRequest; the catalog rules
 * (leaf category, attributes, pre-order fields, blocked products) are checked
 * by SaveProduct and shown on the field at fault. There is no stock field:
 * stock only changes through the stock book.
 */
class ProductForm
{
    /**
     * The fields the AI suggestion needs, named as the seller is told about them.
     *
     * @var array<string, string>
     */
    private const array SUGGESTION_FIELDS = [
        'category_id' => 'kategori',
        'name' => 'nama',
        'condition' => 'kondisi',
        'sales_mode' => 'mode jual',
    ];

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
                        // Next to the category and name: the three fields the AI suggestion needs.
                        // No default: a wrong condition would slip into the description, the
                        // catalog filter, and the shipping rules unnoticed.
                        ToggleButtons::make('condition')
                            ->label('Kondisi')
                            ->options(collect(ProductCondition::cases())->mapWithKeys(
                                fn (ProductCondition $condition): array => [$condition->value => $condition->label()],
                            ))
                            ->colors([
                                ProductCondition::Live->value => 'success',
                                ProductCondition::Fresh->value => 'info',
                                ProductCondition::Frozen->value => 'primary',
                                ProductCondition::Marinated->value => 'warning',
                            ])
                            ->inline()
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->required()
                            ->maxLength(5000)
                            ->rows(5)
                            ->columnSpanFull()
                            ->hintAction(self::suggestDescriptionAction()),
                        Textarea::make('seo_description')
                            ->label('Ringkasan SEO')
                            ->helperText('Tampil di hasil pencarian Google. Kosongkan untuk memakai awal deskripsi.')
                            ->maxLength(Product::SEO_DESCRIPTION_MAX_LENGTH)
                            ->live(debounce: 500)
                            ->hint(fn (?string $state): string => mb_strlen((string) $state).'/'.Product::SEO_DESCRIPTION_MAX_LENGTH)
                            ->rows(2)
                            ->columnSpanFull(),
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
                        ToggleButtons::make('sales_mode')
                            ->label('Mode jual')
                            ->options(collect(SalesMode::cases())->mapWithKeys(
                                fn (SalesMode $mode): array => [$mode->value => $mode->label()],
                            ))
                            ->default(SalesMode::ReadyStock->value)
                            ->inline()
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
     * Fills the description and SEO summary with an AI suggestion from what
     * the form holds now. Nothing is saved: the seller reviews the text and
     * saves the form as usual. A description already written is only
     * replaced after the seller confirms.
     */
    private static function suggestDescriptionAction(): Action
    {
        return Action::make('suggestDescription')
            ->label('Buat deskripsi dengan AI')
            ->icon(Heroicon::OutlinedSparkles)
            ->visible(fn (): bool => filled(config('services.gemini.key'))
                && Gate::allows('suggestDescription', [Product::class, Filament::getTenant()]))
            ->requiresConfirmation(fn (Get $get): bool => filled($get('description')))
            ->modalHeading('Ganti deskripsi?')
            ->modalDescription('Deskripsi dan ringkasan SEO yang sudah ditulis akan diganti dengan saran AI. Perubahan baru tersimpan setelah formulir disimpan.')
            ->modalSubmitActionLabel('Ganti')
            ->action(function (Get $get, Set $set, Component $livewire): void {
                $livewire->resetErrorBag(array_map(fn (string $field): string => 'data.'.$field, array_keys(self::SUGGESTION_FIELDS)));

                $missing = array_filter(
                    self::SUGGESTION_FIELDS,
                    fn (string $field): bool => blank(is_string($get($field)) ? trim($get($field)) : $get($field)),
                    ARRAY_FILTER_USE_KEY,
                );

                if ($missing !== []) {
                    foreach (array_keys($missing) as $field) {
                        $livewire->addError('data.'.$field, 'Isi dulu sebelum meminta saran deskripsi.');
                    }

                    Notification::make()->warning()->title('Lengkapi '.self::listInWords($missing).' sebelum meminta saran deskripsi.')->send();

                    return;
                }

                /** @var Store $store */
                $store = Filament::getTenant();
                /** @var User $user */
                $user = Filament::auth()->user();

                $data = ProductResource::toProductData([
                    'category_id' => $get('category_id'),
                    'name' => $get('name'),
                    'description' => '',
                    'condition' => $get('condition'),
                    'sales_mode' => $get('sales_mode'),
                    'daily_quota' => $get('daily_quota'),
                    'lead_time_days' => $get('lead_time_days'),
                    'cutoff_time' => $get('cutoff_time'),
                    'shelf_life_days' => $get('shelf_life_days'),
                    'storage_instructions' => $get('storage_instructions'),
                    'is_active' => $get('is_active'),
                    'attributes' => $get('attributes') ?? [],
                ]);

                try {
                    $suggestion = app(SuggestProductDescription::class)->handle($store, $user, $data);
                } catch (InvalidProductData|ProductDescriptionLimitReached|ProductDescriptionUnavailable $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                // Plain text into plain text fields: Filament escapes it when rendering.
                $set('description', $suggestion->description);
                $set('seo_description', $suggestion->seoDescription);

                Notification::make()->success()->title('Saran deskripsi sudah diisi. Tinjau dan ubah bila perlu sebelum menyimpan.')->send();
            });
    }

    /**
     * "kategori", "nama dan kondisi", or "kategori, nama, dan kondisi".
     *
     * @param  array<string, string>  $words
     */
    private static function listInWords(array $words): string
    {
        return count($words) === 2 ? implode(' dan ', $words) : Arr::join($words, ', ', ', dan ');
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
