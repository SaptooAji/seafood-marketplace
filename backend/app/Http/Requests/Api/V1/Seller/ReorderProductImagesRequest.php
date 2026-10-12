<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Seller;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The new order of a product's photos, first photo first. That the list is
 * exactly the product's photos is ReorderProductImages' job.
 */
class ReorderProductImagesRequest extends FormRequest
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
            'image_ids' => ['required', 'array', 'list'],
            'image_ids.*' => ['integer'],
        ];
    }

    /**
     * The photo ids in the order sent.
     *
     * @return list<int>
     */
    public function imageIds(): array
    {
        /** @var list<int|string> $ids */
        $ids = $this->array('image_ids');

        return array_map(intval(...), $ids);
    }
}
