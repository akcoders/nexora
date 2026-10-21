<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A 10-digit Indian mobile number starting with 6-9. Spaces, dots, dashes and brackets
 * are ignored, and a +91 / 91 / 0 prefix is accepted - the same as the browser "mobile" rule.
 */
class IndianMobile implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::passes($value)) {
            $fail('The :attribute must be a valid 10-digit mobile number starting with 6-9 (a +91 prefix is optional).');
        }
    }

    public static function passes(string $value): bool
    {
        if (preg_match('/^\+?[0-9\s().-]+$/', $value) !== 1) {
            return false;
        }
        $digits = preg_replace('/\D/', '', $value);
        $digits = preg_replace('/^(?:91(?=\d{10}$)|0(?=\d{10}$))/', '', $digits);

        return preg_match('/^[6-9]\d{9}$/', $digits) === 1 && preg_match('/^(\d)\1+$/', $digits) !== 1;
    }
}
