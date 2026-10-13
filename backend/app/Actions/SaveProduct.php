<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\ProductData;
use App\Enums\AttributeType;
use App\Enums\SalesMode;
use App\Exceptions\InvalidProductData;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\Store;
use App\Support\UniqueSlug;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Creates or edits a product of a store, with the attribute values its
 * category asks for. The catalog rules for products live here.
 */
class SaveProduct
{
    /**
     * Check the data, then save the product and its attribute values together.
     * A new product gets a unique slug from its name; the slug never changes
     * afterwards so product URLs stay stable. Values for attributes that no
     * longer apply (after a category change, or left empty) are removed.
     *
     * @throws InvalidProductData naming the first field that breaks a rule
     */
    public function handle(Store $store, ProductData $data, ?Product $product = null): Product
    {
        $category = $this->leafCategory($data->categoryId);
        $values = $this->attributeValues($category, $data->attributes);
        $this->ensureSalesModeFields($data);
        $seoDescription = $this->seoDescription($data->seoDescription);
        $this->check(
            'is_active',
            ! $this->turnsOnBlockedProduct($product, $data),
            'Produk diblokir admin dan tidak bisa diaktifkan. Perbaiki sesuai alasan blokir, lalu hubungi admin.',
        );

        $preOrder = $data->salesMode === SalesMode::PreOrder;

        $attributes = [
            'category_id' => $category->id,
            'name' => $data->name,
            'description' => $data->description,
            'seo_description' => $seoDescription,
            'condition' => $data->condition,
            'sales_mode' => $data->salesMode,
            'daily_quota' => $preOrder ? $data->dailyQuota : null,
            'lead_time_days' => $preOrder ? $data->leadTimeDays : null,
            'cutoff_time' => $preOrder ? $data->cutoffTime : null,
            'shelf_life_days' => $data->shelfLifeDays,
            'storage_instructions' => $data->storageInstructions,
            'is_active' => $data->isActive,
        ];

        return DB::transaction(function () use ($store, $product, $attributes, $data, $values): Product {
            if ($product === null) {
                $product = $store->products()->create([
                    ...$attributes,
                    'slug' => UniqueSlug::for(Product::withTrashed(), $data->name, 'produk'),
                ]);
            } else {
                $product->update($attributes);
            }

            $product->attributeValues()->whereNotIn('category_attribute_id', array_keys($values))->delete();

            foreach ($values as $attributeId => $value) {
                $product->attributeValues()->updateOrCreate(['category_attribute_id' => $attributeId], ['value' => $value]);
            }

            return $product;
        });
    }

    /**
     * @throws InvalidProductData when the category is unknown or has subcategories
     */
    private function leafCategory(int $categoryId): Category
    {
        $category = Category::query()->find($categoryId);

        $this->check('category_id', $category !== null, 'Kategori tidak ditemukan.');
        /** @var Category $category */
        $this->check('category_id', ! $category->children()->exists(), 'Pilih subkategori yang paling spesifik.');

        return $category;
    }

    /**
     * The non-empty values to store, keyed by attribute id, after checking them
     * against the category's effective attributes (its own and inherited).
     *
     * @param  array<int, string|null>  $input
     * @return array<int, string>
     *
     * @throws InvalidProductData on `attributes.<id>`
     */
    private function attributeValues(Category $category, array $input): array
    {
        /** @var Collection<int, CategoryAttribute> $effective */
        $effective = $category->effectiveAttributes()->keyBy('id');

        foreach (array_keys($input) as $id) {
            $this->check("attributes.{$id}", $effective->has($id), 'Atribut ini bukan milik kategori yang dipilih.');
        }

        $values = [];

        foreach ($effective as $attribute) {
            $value = trim((string) ($input[$attribute->id] ?? ''));

            if ($value === '') {
                $this->check("attributes.{$attribute->id}", ! $attribute->is_required, "{$attribute->name} wajib diisi.");

                continue;
            }

            $this->check("attributes.{$attribute->id}", $this->fitsType($attribute, $value), $this->typeMessage($attribute));
            $values[$attribute->id] = $value;
        }

        return $values;
    }

    private function fitsType(CategoryAttribute $attribute, string $value): bool
    {
        return match ($attribute->type) {
            AttributeType::Number => is_numeric($value),
            AttributeType::Select => in_array($value, $attribute->options ?? [], true),
            AttributeType::Date => $this->isDate($value),
            AttributeType::Text => mb_strlen($value) <= 255,
        };
    }

    /**
     * A real calendar date in `Y-m-d`; `2026-02-30` is rejected rather than rolled over.
     */
    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function typeMessage(CategoryAttribute $attribute): string
    {
        return match ($attribute->type) {
            AttributeType::Number => "{$attribute->name} harus berupa angka.",
            AttributeType::Select => "{$attribute->name} harus salah satu dari: ".implode(', ', $attribute->options ?? []).'.',
            AttributeType::Date => "{$attribute->name} harus tanggal dengan format YYYY-MM-DD.",
            AttributeType::Text => "{$attribute->name} maksimal 255 karakter.",
        };
    }

    /**
     * The summary for search engines, trimmed; blank means "use the description".
     *
     * @throws InvalidProductData when it is longer than 160 characters
     */
    private function seoDescription(?string $value): ?string
    {
        $value = trim((string) $value);

        $this->check('seo_description', mb_strlen($value) <= Product::SEO_DESCRIPTION_MAX_LENGTH, 'Ringkasan SEO maksimal 160 karakter.');

        return $value === '' ? null : $value;
    }

    /**
     * Pre-order products need a daily quota, a lead time of at least a day,
     * and a cutoff time. Ready stock products ignore these fields.
     *
     * @throws InvalidProductData
     */
    private function ensureSalesModeFields(ProductData $data): void
    {
        if ($data->salesMode !== SalesMode::PreOrder) {
            return;
        }

        $this->check('daily_quota', $data->dailyQuota !== null && is_numeric($data->dailyQuota) && $data->dailyQuota > 0, 'Kuota harian wajib diisi dan lebih dari 0.');
        $this->check('lead_time_days', $data->leadTimeDays !== null && $data->leadTimeDays >= 1, 'Lama persiapan minimal 1 hari.');
        $this->check('cutoff_time', $data->cutoffTime !== null && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $data->cutoffTime) === 1, 'Jam tutup pesanan wajib diisi (HH:MM).');
    }

    /**
     * Only an admin lifts a block. A seller may still edit a blocked product,
     * and an active one stays active (the block already hides it), but an
     * inactive one may not be switched on.
     */
    private function turnsOnBlockedProduct(?Product $product, ProductData $data): bool
    {
        return $product !== null
            && $product->blocked_at !== null
            && ! $product->is_active
            && $data->isActive;
    }

    /**
     * @throws InvalidProductData when the condition does not hold
     */
    private function check(string $field, bool $condition, string $message): void
    {
        if (! $condition) {
            throw new InvalidProductData($field, $message);
        }
    }
}
