<?php

declare(strict_types=1);

namespace App\Filament\Seller\Pages;

use App\Actions\ConfirmDailyStock;
use App\Exceptions\InvalidStockMovement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * "Stok hari ini": the morning count of every active variant of the store's
 * active products, like the Android screen. "Sama seperti kemarin" confirms
 * everything as it is; "Simpan" corrects the variants whose count changed
 * and confirms the rest. Both go through ConfirmDailyStock.
 *
 * @property-read Schema $form
 */
class DailyStock extends Page
{
    protected string $view = 'filament.seller.pages.daily-stock';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Stok hari ini';

    protected static ?string $title = 'Stok hari ini';

    protected static ?int $navigationSort = 2;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * The stock of each variant when the page was loaded, to send only the
     * counts the seller changed.
     *
     * @var array<int, string>
     */
    public array $loadedStock = [];

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', [StockMovement::class, Filament::getTenant()]);
    }

    public function mount(): void
    {
        $this->fillCounts();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components($this->products()->map(fn (Product $product): Section => Section::make($product->name)
                ->columns(3)
                ->schema($product->variants->map(fn (ProductVariant $variant): TextInput => $this->countField($variant))->all()))
                ->all());
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('sameAsYesterday')
                ->label('Sama seperti kemarin')
                ->color('gray')
                ->authorize(fn (): bool => Gate::allows('create', [StockMovement::class, $this->store()]))
                ->action(fn () => $this->confirm([])),
            Action::make('save')
                ->label('Simpan')
                ->authorize(fn (): bool => Gate::allows('create', [StockMovement::class, $this->store()]))
                ->action(function (): void {
                    /** @var array{counts?: array<int, string|int|float>} $state */
                    $state = $this->form->getState();

                    $this->confirm($this->changedCounts($state['counts'] ?? []));
                }),
        ];
    }

    private function countField(ProductVariant $variant): TextInput
    {
        $stale = $variant->isStockStale();

        return TextInput::make('counts.'.$variant->id)
            ->label($variant->name)
            ->suffix($variant->unit->label())
            ->required()
            ->numeric()
            ->minValue(0)
            ->maxValue(9_999_999.999)
            ->rule('decimal:0,3')
            ->hint(match (true) {
                $stale => 'Perlu dikonfirmasi',
                default => 'Dikonfirmasi '.$variant->stock_confirmed_at?->diffForHumans(),
            })
            ->hintColor($stale ? 'warning' : 'gray');
    }

    /**
     * Apply the counts and confirm every variant on the page.
     *
     * @param  list<array{id: int, stock: string}>  $counts
     */
    private function confirm(array $counts): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            app(ConfirmDailyStock::class)->handle($this->store(), $counts, true, $user);
        } catch (InvalidStockMovement $e) {
            throw ValidationException::withMessages([$this->fieldFor($e->field, $counts) => $e->getMessage()]);
        }

        $this->fillCounts();

        Notification::make()->success()->title('Stok hari ini sudah dikonfirmasi')->send();
    }

    /**
     * The counts that differ from the stock shown when the page was loaded.
     *
     * @param  array<int, string|int|float>  $counts
     * @return list<array{id: int, stock: string}>
     */
    private function changedCounts(array $counts): array
    {
        $changed = [];

        foreach ($counts as $id => $count) {
            $count = (string) $count;

            if (! isset($this->loadedStock[$id]) || self::thousandths($count) !== self::thousandths($this->loadedStock[$id])) {
                $changed[] = ['id' => (int) $id, 'stock' => $count];
            }
        }

        return $changed;
    }

    /**
     * Map ConfirmDailyStock's `variants.{index}.{field}` back to the field of
     * that variant.
     *
     * @param  list<array{id: int, stock: string}>  $counts
     */
    private function fieldFor(string $field, array $counts): string
    {
        if (preg_match('/^variants\.(\d+)\./', $field, $match) === 1 && isset($counts[(int) $match[1]])) {
            return 'data.counts.'.$counts[(int) $match[1]]['id'];
        }

        return 'data.counts';
    }

    private function fillCounts(): void
    {
        $this->loadedStock = $this->products()
            ->flatMap(fn (Product $product) => $product->variants)
            ->mapWithKeys(fn (ProductVariant $variant): array => [$variant->id => $variant->stock])
            ->all();

        $this->form->fill(['counts' => $this->loadedStock]);
    }

    /**
     * The store's active products that have active variants, by name, with
     * only those variants loaded.
     *
     * @return Collection<int, Product>
     */
    private function products(): Collection
    {
        $activeVariants = fn (Builder|HasMany $variant) => $variant->where('is_active', true)->orderBy('name');

        return $this->store()->products()
            ->where('is_active', true)
            ->whereHas('variants', $activeVariants)
            ->with(['variants' => $activeVariants])
            ->orderBy('name')
            ->get();
    }

    private function store(): Store
    {
        /** @var Store */
        return Filament::getTenant();
    }

    /**
     * Compare decimal strings exactly: `8.5` and `8.500` are the same count.
     */
    private static function thousandths(string $decimal): string
    {
        [$whole, $fraction] = array_pad(explode('.', ltrim($decimal, '+'), 2), 2, '');

        return ltrim($whole, '0').'.'.str_pad(substr($fraction, 0, 3), 3, '0');
    }
}
