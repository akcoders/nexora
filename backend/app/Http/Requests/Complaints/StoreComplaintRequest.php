<?php

namespace App\Http\Requests\Complaints;

use App\Models\Complaint;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        $complaint = $this->route('complaint');

        return $complaint instanceof Complaint
            ? $this->user()->can('update', $complaint)
            : $this->user()->can('create', Complaint::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('customer_mode') === 'new') {
            $this->merge(['customer_id' => null]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->route('complaint') instanceof Complaint) {
            return ComplaintRules::details() + ['change_remarks' => ['nullable', 'string', 'max:1000']];
        }

        return [
            'customer_mode' => ['required', Rule::in(['existing', 'new'])],
            'customer_id' => ['exclude_if:customer_mode,new', 'required', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'new_customer' => ['exclude_unless:customer_mode,new', 'required', 'array:name,phone,email,address,city,type'],
            'new_customer.name' => ['exclude_unless:customer_mode,new', 'required', 'string', 'min:2', 'max:255'],
            'new_customer.phone' => ['exclude_unless:customer_mode,new', 'required', 'string', ComplaintRules::PHONE],
            'new_customer.email' => ['exclude_unless:customer_mode,new', 'nullable', 'email', 'max:254'],
            'new_customer.address' => ['exclude_unless:customer_mode,new', 'nullable', 'string', 'max:255'],
            'new_customer.city' => ['exclude_unless:customer_mode,new', 'nullable', 'string', 'max:120'],
            'new_customer.type' => ['exclude_unless:customer_mode,new', 'nullable', Rule::in(['individual', 'corporate'])],
            ...ComplaintRules::details(),
            'assign' => ['nullable', 'array'],
            ...($this->user()->can('complaints.assign') ? ComplaintRules::assignment('assign.', false) : ['assign.technician_id' => ['prohibited']]),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ComplaintRules::attributes();
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ComplaintRules::messages() + ['assign.technician_id.prohibited' => 'You do not have permission to assign technicians.'];
    }
}
