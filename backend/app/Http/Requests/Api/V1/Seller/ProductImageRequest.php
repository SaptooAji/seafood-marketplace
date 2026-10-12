<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Seller;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A product photo uploaded from the seller app. Whether it really is an image
 * is checked again when AddProductImage decodes it.
 */
class ProductImageRequest extends FormRequest
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
        return [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config()->integer('catalog.max_photo_kb')],
        ];
    }
}
