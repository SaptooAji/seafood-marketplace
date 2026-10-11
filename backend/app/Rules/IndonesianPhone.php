<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * An Indonesian mobile number: `08` or `+628` followed by 8 to 13 digits,
 * which is 10 to 15 digits in the local `08` form.
 */
class IndonesianPhone implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^(?:08|\+628)\d{8,13}$/', $value) !== 1) {
            $fail('validation.regex')->translate();
        }
    }
}
