<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Seller;

use App\Data\ProductData;
use App\Data\VariantData;
use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Enums\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * A product from the seller app. Only types and ranges are checked here; the
 * catalog rules (leaf category, attributes, pre-order fields, variant names)
 * are SaveProduct's and SaveVariant's job. Variants are only read on POST;
 * after that they have their own endpoints.
 */
class ProductRequest extends FormRequest
{
    /**
     * Who may change the store's products is checked by the controller and the policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Attribute values are stored as text, so numbers sent as JSON numbers are
     * turned into strings before they are checked.
     */
    protected function prepareForValidation(): void
    {
        $attributes = $this->input('attributes');

        if (is_array($attributes)) {
            $this->merge(['attributes' => array_map(
                fn (mixed $value): mixed => is_int($value) || is_float($value) ? (string) $value : $value,
                $attributes,
            )]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'category_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'condition' => ['required', Rule::enum(ProductCondition::class)],
            'sales_mode' => ['required', Rule::enum(SalesMode::class)],
            // Bounds keep values inside the decimal(10,3) and small integer columns.
            'daily_quota' => ['nullable', 'numeric', 'min:0', 'max:9999999', 'decimal:0,3'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:255'],
            'cutoff_time' => ['nullable', 'date_format:H:i'],
            'shelf_life_days' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'storage_instructions' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['nullable', 'string', 'max:255'],
        ];

        if (! $this->isMethod('POST')) {
            return $rules;
        }

        return [
            ...$rules,
            'variants' => ['required', 'array', 'list', 'max:20'],
            'variants.*' => ['array'],
            'variants.*.name' => ['required', 'string', 'max:100'],
            'variants.*.unit' => ['required', Rule::enum(Unit::class)],
            'variants.*.price' => ['required', 'integer', 'min:1', 'max:100000000'],
            'variants.*.min_qty' => ['required', 'numeric'],
            'variants.*.initial_stock' => ['nullable', 'numeric'],
            'variants.*.is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Build the product input for SaveProduct.
     */
    public function toData(): ProductData
    {
        /** @var array<int, string|null> $attributes */
        $attributes = $this->array('attributes');

        return new ProductData(
            categoryId: $this->integer('category_id'),
            name: $this->string('name')->trim()->toString(),
            description: $this->string('description')->toString(),
            condition: $this->enum('condition', ProductCondition::class) ?? throw new LogicException('The condition is validated as required.'),
            salesMode: $this->enum('sales_mode', SalesMode::class) ?? throw new LogicException('The sales mode is validated as required.'),
            dailyQuota: $this->filled('daily_quota') ? $this->string('daily_quota')->toString() : null,
            leadTimeDays: $this->filled('lead_time_days') ? $this->integer('lead_time_days') : null,
            cutoffTime: $this->filled('cutoff_time') ? $this->string('cutoff_time')->toString() : null,
            shelfLifeDays: $this->filled('shelf_life_days') ? $this->integer('shelf_life_days') : null,
            storageInstructions: $this->filled('storage_instructions') ? $this->string('storage_instructions')->toString() : null,
            isActive: $this->boolean('is_active', true),
            attributes: $attributes,
        );
    }

    /**
     * Build the variants for CreateProduct, in the order they were sent.
     *
     * @return list<VariantData>
     */
    public function variants(): array
    {
        /** @var list<array{name: string, unit: string, price: int, min_qty: int|float|string, initial_stock?: int|float|string|null, is_active?: bool}> $variants */
        $variants = $this->array('variants');

        return array_map(fn (array $variant): VariantData => new VariantData(
            name: $variant['name'],
            unit: Unit::from($variant['unit']),
            price: (int) $variant['price'],
            minQty: (string) $variant['min_qty'],
            isActive: (bool) ($variant['is_active'] ?? true),
            initialStock: isset($variant['initial_stock']) ? (string) $variant['initial_stock'] : null,
        ), $variants);
    }
}
