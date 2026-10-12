<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Seller;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The daily stock check from the seller app: the counts that differ, and
 * whether to confirm every other variant as it is ("Sama seperti kemarin").
 */
class ConfirmDailyStockRequest extends FormRequest
{
    /**
     * Who may confirm the store's stock is checked by the controller and the policy.
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
        return [
            'variants' => [Rule::requiredIf(! $this->boolean('confirm_all')), 'array', 'list'],
            'variants.*' => ['array'],
            'variants.*.id' => ['required', 'integer', 'distinct'],
            // Fits the decimal(10,3) column.
            'variants.*.stock' => ['required', 'numeric', 'min:0', 'max:9999999.999', 'decimal:0,3'],
            'confirm_all' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The counts for ConfirmDailyStock, with stock as decimal strings.
     *
     * @return list<array{id: int, stock: string}>
     */
    public function counts(): array
    {
        /** @var list<array{id: int|string, stock: int|float|string}> $variants */
        $variants = $this->array('variants');

        return array_map(fn (array $variant): array => [
            'id' => (int) $variant['id'],
            'stock' => (string) $variant['stock'],
        ], $variants);
    }
}
