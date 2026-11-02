<?php

namespace App\Http\Requests\Complaints;

use Illuminate\Foundation\Http\FormRequest;

class AssignComplaintVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('assign', $this->route('complaint'));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ComplaintRules::assignment();
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
