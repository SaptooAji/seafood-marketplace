<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Categories\Schemas;

use App\Models\Category;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    /**
     * The slug follows the name until the admin edits it by hand. The parent
     * list leaves out the category itself and everything below it, so the
     * tree can never loop.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required()
                    ->maxLength(100)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                        // Only follow the name while the slug still matches the old name.
                        if (($get('slug') ?? '') === Str::slug($old ?? '')) {
                            $set('slug', Str::slug($state ?? ''));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(120)
                    ->alphaDash()
                    ->unique(ignoreRecord: true),
                Select::make('parent_id')
                    ->label('Induk')
                    ->placeholder('Tanpa induk (kategori utama)')
                    ->options(fn (?Category $record): array => self::parentOptions($record))
                    ->searchable(),
                TextInput::make('sort_order')
                    ->label('Urutan')
                    ->integer()
                    ->minValue(0)
                    ->maxValue(65535)
                    ->default(0)
                    ->required(),
                MarkdownEditor::make('quality_standard')
                    ->label('Standar kualitas')
                    ->helperText('Ditampilkan ke pembeli dan penjual pada produk di kategori ini.')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Every category that may be the parent of `$record`, keyed by id.
     *
     * @return array<int, string>
     */
    private static function parentOptions(?Category $record): array
    {
        $excluded = $record === null ? [] : [$record->id, ...$record->descendantIds()];

        return Category::query()
            ->whereNotIn('id', $excluded)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
