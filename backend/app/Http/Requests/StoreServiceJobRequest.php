<?php

namespace App\Http\Requests;

use App\Models\CustomerBranch;
use App\Models\CustomerEquipment;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServiceJobRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('service_jobs.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'exists:customers,id'],
            'customer_branch_id' => ['nullable', 'exists:customer_branches,id'],
            'customer_equipment_id' => ['nullable', 'exists:customer_equipments,id'],
            'service_type_id' => ['nullable', 'exists:service_types,id'],
            'origin' => ['required', Rule::in(['manual', 'complaint', 'crm', 'amc', 'sales', 'project', 'preventive_maintenance'])],
            'customer_phone' => ['required', 'string', 'max:20'],
            'alternate_phone' => ['nullable', 'string', 'max:20'],
            'service_address' => ['required', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'complaint' => ['required', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['normal', 'high', 'very_high', 'emergency'])],
            'preferred_visit_date' => ['nullable', 'date'],
            'preferred_visit_time' => ['nullable', 'date_format:H:i'],
            'scheduled_at' => ['required', 'date'],
            'assigned_to' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('customer_id')) {
                    return;
                }

                if ($this->filled('customer_branch_id') && ! CustomerBranch::query()
                    ->whereKey($this->integer('customer_branch_id'))
                    ->where('customer_id', $this->integer('customer_id'))
                    ->exists()) {
                    $validator->errors()->add('customer_branch_id', 'The selected branch does not belong to this customer.');
                }

                if ($this->filled('customer_equipment_id') && ! CustomerEquipment::query()
                    ->whereKey($this->integer('customer_equipment_id'))
                    ->where('customer_id', $this->integer('customer_id'))
                    ->exists()) {
                    $validator->errors()->add('customer_equipment_id', 'The selected equipment does not belong to this customer.');
                }

                if ($this->filled('assigned_to') && ! User::role('Technician')->whereKey($this->integer('assigned_to'))->where('status', 'active')->exists()) {
                    $validator->errors()->add('assigned_to', 'Select an active technician.');
                }
            },
        ];
    }
}
