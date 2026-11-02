<?php

namespace App\Http\Requests\Complaints;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A complaint can only be pending with a reason, an owner, the next action and dates.
 */
class ComplaintPendingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('complaint'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ComplaintRules::pending();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ComplaintRules::attributes();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ComplaintRules::messages();
    }
}
