<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Seller;

use App\Data\ShippingMethodData;
use App\Enums\ShippingFeeType;
use App\Enums\ShippingMethodType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shipping method settings from the seller app. Only types and ranges are
 * checked here; which fields fit each shipping type is SaveShippingMethod's job.
 */
class ShippingMethodRequest extends FormRequest
{
    /**
     * Who may change the store's methods is checked by the controller and the policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The type is only read on POST; an existing method keeps its type.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => [$this->isMethod('POST') ? 'required' : 'nullable', Rule::enum(ShippingMethodType::class)],
            'fee_type' => ['required', Rule::enum(ShippingFeeType::class)],
            // Fits the decimal(6,2) column.
            'max_radius_km' => ['nullable', 'numeric', 'max:9999.99'],
            // Upper bounds keep values inside the unsigned integer columns.
            'flat_fee' => ['nullable', 'integer', 'max:100000000'],
            'fee_per_km' => ['nullable', 'integer', 'max:100000000'],
            'min_fee' => ['nullable', 'integer', 'max:100000000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Build the Action input. `$type` is the existing method's type on PUT.
     */
    public function toData(?ShippingMethodType $type = null): ShippingMethodData
    {
        return new ShippingMethodData(
            type: $type ?? $this->enum('type', ShippingMethodType::class) ?? throw new \LogicException('The type is validated as required on POST.'),
            feeType: $this->enum('fee_type', ShippingFeeType::class) ?? throw new \LogicException('The fee type is validated as required.'),
            maxRadiusKm: $this->filled('max_radius_km') ? $this->string('max_radius_km')->toString() : null,
            flatFee: $this->filled('flat_fee') ? $this->integer('flat_fee') : null,
            feePerKm: $this->filled('fee_per_km') ? $this->integer('fee_per_km') : null,
            minFee: $this->filled('min_fee') ? $this->integer('min_fee') : null,
            isActive: $this->boolean('is_active', true),
        );
    }
}
