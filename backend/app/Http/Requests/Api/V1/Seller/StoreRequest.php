<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Seller;

use App\Data\StoreData;
use App\Rules\IndonesianPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Store details sent when a seller creates or edits their store. Status and
 * verification fields have no rules, so they never reach the Action.
 */
class StoreRequest extends FormRequest
{
    /**
     * Who may act on the store is checked by the controller and StorePolicy.
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
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['required', 'string', new IndonesianPhone],
            'address' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:100'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_account_no' => ['required', 'string', 'regex:/^\d{5,20}$/'],
            'bank_account_name' => ['required', 'string', 'max:255'],
            'stock_reminder_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Build the Action input from the validated fields. Coordinates become
     * decimal strings so they reach the database without float rounding.
     */
    public function toData(): StoreData
    {
        return new StoreData(
            name: $this->string('name')->toString(),
            description: $this->filled('description') ? $this->string('description')->toString() : null,
            phone: $this->string('phone')->toString(),
            address: $this->string('address')->toString(),
            city: $this->string('city')->toString(),
            lat: $this->string('lat')->toString(),
            lng: $this->string('lng')->toString(),
            bankName: $this->string('bank_name')->toString(),
            bankAccountNo: $this->string('bank_account_no')->toString(),
            bankAccountName: $this->string('bank_account_name')->toString(),
            stockReminderTime: $this->filled('stock_reminder_time') ? $this->string('stock_reminder_time')->toString() : null,
        );
    }
}
