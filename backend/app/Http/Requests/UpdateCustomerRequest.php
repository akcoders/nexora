<?php

namespace App\Http\Requests;

use App\Rules\IndianDistrict;
use App\Rules\IndianState;
use App\Services\IndiaLocations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('customers.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'customer_group_id' => ['required', Rule::exists('customer_groups', 'id')->where('status', 'active')],
            'mother_tongue' => ['nullable', 'string', 'max:50'],
            'types' => ['required', 'array', 'size:1'],
            'types.*' => [Rule::in(['individual', 'corporate', 'architect', 'consultant', 'agent', 'dealer', 'government'])],
            'segments' => ['nullable', 'array', 'max:1'],
            'segments.*' => ['nullable', Rule::in(['healthcare', 'education', 'industrials', 'builder', 'hospitality', 'office'])],
            'priority' => ['required', Rule::in(['normal', 'high', 'very_high'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'blacklisted'])],
            'classification' => ['nullable', Rule::in(['strategic', 'regular', 'transactional', 'one_time'])],
            'business_potential' => ['nullable', 'string', 'max:255'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'landmark' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120', new IndianState],
            'district' => ['required', 'string', 'max:120', new IndianDistrict],
            'pin_code' => ['required', 'regex:/^[0-9]{6}$/'],
            'country' => ['required', Rule::in([IndiaLocations::COUNTRY])],
            'email_1' => ['required_without:email_2', 'nullable', 'email'],
            'email_2' => ['nullable', 'email'],
            'contact_no_1' => ['required_without:contact_no_2', 'nullable', 'string', 'max:20'],
            'contact_no_2' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'site_access_instructions' => ['nullable', 'string', 'max:2000'],
            'billing_address' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'gstin' => ['nullable', 'regex:/^[0-9A-Za-z]{15}$/'],
            'pan' => ['nullable', 'regex:/^[0-9A-Za-z]{10}$/'],
            'gst_registration_type' => ['nullable', Rule::in(['regular', 'composition', 'unregistered'])],
            'tan' => ['nullable', 'regex:/^[A-Za-z]{4}[0-9]{5}[A-Za-z]$/'],
            'tds_percent' => ['nullable', 'numeric', 'between:0,100'],
            'udyam_no' => ['nullable', 'string', 'max:40'],
            'msme_type' => ['nullable', Rule::in(['micro', 'small', 'medium'])],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'sales_person_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'credit' => ['nullable', 'array'],
            'credit.credit_limit' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'credit.credit_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'credit.payment_mode' => ['nullable', 'string', 'max:40'],
            'credit.advance_required' => ['nullable', 'boolean'],
            'credit.tds_applicable' => ['nullable', 'boolean'],
            'credit.retention_applicable' => ['nullable', 'boolean'],
            'credit.retention_percent' => ['nullable', 'numeric', 'between:0,100'],
            'credit.billing_cycle' => ['nullable', 'string', 'max:40'],
            'credit.invoice_submission_method' => ['nullable', 'string', 'max:40'],
            'credit.po_mandatory' => ['nullable', 'boolean'],
            'credit.eway_bill_applicable' => ['nullable', 'boolean'],
            'credit.default_payment_terms' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'types.size' => 'Select exactly one customer type.',
            'segments.max' => 'Select no more than one segment.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['country' => IndiaLocations::COUNTRY]);
    }
}
