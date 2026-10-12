<?php

namespace App\Rules;

use App\Services\IndiaLocations;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Checks that a district belongs to the state submitted beside it. The state
 * field is the sibling whose last key is "state" (address.district ->
 * address.state, branches.0.district -> branches.0.state) unless given.
 */
class IndianDistrict implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private ?string $stateAttribute = null) {}

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $stateAttribute = $this->stateAttribute ?? preg_replace('/district$/', 'state', $attribute);
        $state = data_get($this->data, $stateAttribute);
        $locations = app(IndiaLocations::class);

        if (! is_string($state) || ! $locations->hasState($state)) {
            $fail('Select the state before choosing a district.');

            return;
        }

        if (! is_string($value) || ! $locations->hasDistrict($state, $value)) {
            $fail("Select a district of {$state} from the list.");
        }
    }
}
