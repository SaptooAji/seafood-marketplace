<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AttributeType;
use Database\Factories\CategoryAttributeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A field sellers fill in for products of a category, such as "Berat per ekor"
 * (number, unit g) or "Jenis kelamin" (select: Jantan, Betina).
 *
 * @property AttributeType $type
 * @property list<string>|null $options
 */
#[Fillable(['category_id', 'name', 'type', 'options', 'unit', 'is_required', 'is_filterable'])]
class CategoryAttribute extends Model
{
    /** @use HasFactory<CategoryAttributeFactory> */
    use HasFactory, LogsActivity;

    /**
     * Log every fillable field, only the ones that changed.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AttributeType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'is_filterable' => 'boolean',
        ];
    }

    /**
     * Get the category that defines this attribute.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
