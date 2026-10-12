<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProductImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A product photo stored on the public disk, shown in `sort_order`.
 */
#[Fillable(['path', 'sort_order'])]
class ProductImage extends Model
{
    /** @use HasFactory<ProductImageFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Path of the thumbnail on the public disk, next to the photo.
     */
    public function thumbnailPath(): string
    {
        return Str::replaceLast('.webp', '_thumb.webp', $this->path);
    }

    /**
     * Public URL of the photo.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * Public URL of the thumbnail.
     */
    public function thumbnailUrl(): string
    {
        return Storage::disk('public')->url($this->thumbnailPath());
    }

    /**
     * Get the product this photo belongs to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
