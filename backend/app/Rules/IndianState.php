<?php

namespace App\Rules;

use App\Services\IndiaLocations;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IndianState implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(IndiaLocations::class)->hasState($value)) {
            $fail('Select a valid Indian state from the list.');
        }
    }
}
