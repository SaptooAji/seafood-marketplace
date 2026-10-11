<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * Input type of a category attribute, stored in `category_attributes.type`.
 * Select attributes take their choices from `category_attributes.options`.
 */
enum AttributeType: string
{
    case Text = 'text';
    case Number = 'number';
    case Select = 'select';
    case Date = 'date';

    /**
     * Get the label for the attribute type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Text => 'Teks',
            self::Number => 'Angka',
            self::Select => 'Pilihan',
            self::Date => 'Tanggal',
        };
    }
}
