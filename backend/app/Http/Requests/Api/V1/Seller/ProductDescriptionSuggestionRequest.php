<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Seller;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The product form as it is when the seller asks for a description: the same
 * fields and limits as ProductRequest, so nothing longer is sent to Gemini,
 * but without variants. The description is optional, since it is what is
 * being asked for.
 */
class ProductDescriptionSuggestionRequest extends ProductRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = array_filter(
            parent::rules(),
            fn (string $field): bool => ! str_starts_with($field, 'variants'),
            ARRAY_FILTER_USE_KEY,
        );

        $rules['description'] = ['nullable', 'string', 'max:5000'];

        return $rules;
    }
}
