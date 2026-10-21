<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Rules\IndonesianPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The signed-in user's own name and phone. Any other field sent is ignored.
 */
class UpdateProfileRequest extends FormRequest
{
    /**
     * Every signed-in user may edit their own profile; there is no id in the URL.
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
            'phone' => ['required', 'string', new IndonesianPhone],
        ];
    }
}
