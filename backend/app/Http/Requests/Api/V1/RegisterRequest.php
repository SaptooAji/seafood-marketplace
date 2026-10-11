<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\IndonesianPhone;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', new IndonesianPhone],
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
