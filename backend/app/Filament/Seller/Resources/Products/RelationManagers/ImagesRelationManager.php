<?php

declare(strict_types=1);

namespace App\Filament\Seller\Resources\Products\RelationManagers;

use App\Actions\AddProductImage;
use App\Actions\RemoveProductImage;
use App\Actions\ReorderProductImages;
use App\Exceptions\InvalidProductData;
use App\Exceptions\InvalidProductPhoto;
use App\Models\Product;
use App\Models\ProductImage;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Table;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * The product's photos, in display order. Uploads, deletes, and reordering go
 * through the same Actions as the API, so photos are always resized to WebP
 * with a thumbnail and the order has no gaps.
 */
class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    protected static ?string $title = 'Foto';

    protected static ?string $modelLabel = 'foto';

    /**
     * The file is not stored by Filament; AddProductImage decodes it and
     * writes the resized copies.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('photo')
                    ->label('Foto')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(config()->integer('catalog.max_photo_kb'))
                    ->storeFiles(false)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->paginated(false)
            ->columns([
                ImageColumn::make('thumbnail')
                    ->label('Foto')
                    ->state(fn (ProductImage $record): string => $record->thumbnailUrl()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Unggah foto')
                    ->using(function (array $data, CreateAction $action): ProductImage {
                        /** @var Product $product */
                        $product = $this->getOwnerRecord();
                        /** @var UploadedFile $photo */
                        $photo = $data['photo'];

                        try {
                            return app(AddProductImage::class)->handle($product, $photo);
                        } catch (InvalidProductData|InvalidProductPhoto $e) {
                            throw ValidationException::withMessages([
                                "mountedActions.{$action->getNestingIndex()}.data.photo" => $e->getMessage(),
                            ]);
                        }
                    }),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->using(fn (ProductImage $record) => app(RemoveProductImage::class)->handle($record)),
            ]);
    }

    /**
     * Save the dragged order through ReorderProductImages instead of
     * Filament's own update.
     *
     * @param  array<int|string>  $order
     */
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        app(ReorderProductImages::class)->handle($product, array_map(intval(...), array_values($order)));
    }
}
