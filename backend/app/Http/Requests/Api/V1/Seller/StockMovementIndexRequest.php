<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Seller;

use App\Enums\StockMovementType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters for the store's stock book. `from` and `to` are calendar days,
 * both included.
 */
class StockMovementIndexRequest extends FormRequest
{
    /**
     * Who may read the stock book is checked by the controller and the policy.
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
            'variant_id' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(StockMovementType::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
