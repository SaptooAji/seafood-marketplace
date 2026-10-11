<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Anyone may register.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The phone must be an Indonesian mobile number: `08` or `+628` followed by
     * 8 to 13 digits, which is 10 to 15 digits in the local `08` form.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'regex:/^(?:08|\+628)\d{8,13}$/'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
            'device_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Lower-case the email so the unique rule and the stored value ignore letter case.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower($this->input('email'))]);
        }
    }
}
