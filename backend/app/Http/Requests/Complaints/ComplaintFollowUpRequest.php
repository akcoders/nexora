<?php

namespace App\Http\Requests\Complaints;

use App\Services\ComplaintOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ComplaintFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('complaint'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'remarks' => ['required', 'string', 'max:2000'],
            'next_action' => ['nullable', 'string', 'max:255'],
            'follow_up_date' => ['required', 'date', 'after_or_equal:today'],
            'expected_material_date' => ['nullable', 'date'],
            'target_date' => ['nullable', 'date', 'after_or_equal:follow_up_date'],
            'delay_reason' => ['nullable', Rule::in(ComplaintOptions::keys('delay_reasons'))],
            'pending_owner' => ['nullable', Rule::in(ComplaintOptions::keys('pending_owners'))],
            'responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ComplaintRules::attributes() + ['remarks' => 'follow-up remarks'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ComplaintRules::messages();
    }
}
