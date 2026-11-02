<?php

namespace App\Http\Requests\Complaints;

use Illuminate\Foundation\Http\FormRequest;

class CloseComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('close', $this->route('complaint'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fsr_checked' => $this->boolean('fsr_checked'),
            'happy_call_required' => $this->boolean('happy_call_required'),
            'callback_required' => $this->boolean('callback_required'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fsr_checked' => ['accepted'],
            'corrective_action' => ['nullable', 'string', 'max:2000'],
            'manager_remarks' => ['nullable', 'string', 'max:2000'],
            'happy_call_required' => ['boolean'],
            'happy_call_at' => ['nullable', 'required_if:happy_call_required,true', 'date', 'before_or_equal:now'],
            'customer_feedback' => ['nullable', 'required_with:happy_call_at', 'string', 'max:2000'],
            'customer_rating' => ['nullable', 'integer', 'between:1,5'],
            'team_rating' => ['nullable', 'integer', 'between:1,5'],
            'callback_required' => ['boolean'],
            'callback_date' => ['nullable', 'required_if:callback_required,true', 'date', 'after_or_equal:today'],
            'root_cause' => ['required', 'string', 'max:120'],
            'complaint_category' => ['required', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'fsr_checked.accepted' => 'Check the field service report (FSR) before closing.',
            'happy_call_at.required_if' => 'Record when the happy call was made, or untick "Happy call required".',
            'customer_feedback.required_with' => 'Note what the customer said on the happy call.',
            'callback_date.required_if' => 'Pick the call-back date.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['root_cause' => 'root cause of the complaint', 'complaint_category' => 'complaint category'];
    }
}
