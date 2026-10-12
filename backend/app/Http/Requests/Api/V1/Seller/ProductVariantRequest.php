<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Seller;

use App\Data\VariantData;
use App\Enums\Unit;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * A variant from the seller app. Only types and ranges are checked here; the
 * catalog rules (unique name, positive quantities) are SaveVariant's job. The
 * opening stock is only read on POST, and `stock` is never read: stock only
 * changes through the stock book.
 */
class ProductVariantRequest extends FormRequest
{
    /**
     * Who may change the store's products is checked by the controller and the policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100'],
            'unit' => ['required', Rule::enum(Unit::class)],
            'price' => ['required', 'integer', 'min:1', 'max:100000000'],
            'min_qty' => ['required', 'numeric'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if (! $this->isMethod('POST')) {
            return $rules;
        }

        return [...$rules, 'initial_stock' => ['nullable', 'numeric']];
    }

    /**
     * Build the variant input for SaveVariant.
     */
    public function toData(): VariantData
    {
        return new VariantData(
            name: $this->string('name')->toString(),
            unit: $this->enum('unit', Unit::class) ?? throw new LogicException('The unit is validated as required.'),
            price: $this->integer('price'),
            minQty: $this->string('min_qty')->toString(),
            isActive: $this->boolean('is_active', true),
            initialStock: $this->isMethod('POST') && $this->filled('initial_stock') ? $this->string('initial_stock')->toString() : null,
        );
    }
}
