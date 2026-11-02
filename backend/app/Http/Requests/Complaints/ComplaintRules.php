<?php

namespace App\Http\Requests\Complaints;

use App\Services\ComplaintOptions;
use Illuminate\Validation\Rule;

/**
 * Validation rules shared by the complaint form requests.
 */
final class ComplaintRules
{
    public const PHONE = 'regex:/^\+?[0-9\s().-]{8,20}$/';

    /** @return array<string, mixed> */
    public static function details(): array
    {
        return [
            'customer_branch_id' => ['nullable', 'integer', 'exists:customer_branches,id'],
            'site_location' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'contact_person' => ['required', 'string', 'max:120'],
            'contact_no' => ['required', 'string', self::PHONE],
            'alternate_no' => ['nullable', 'string', self::PHONE, 'different:contact_no'],
            'complaint_given_by' => ['nullable', 'string', 'max:120'],
            'call_taken_by' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'call_mode' => ['required', Rule::in(ComplaintOptions::keys('call_modes'))],
            'complaint_source' => ['required', Rule::in(ComplaintOptions::keys('sources'))],
            'call_type' => ['required', Rule::in(array_keys(ComplaintOptions::CALL_TYPES))],
            'nature_of_complaint' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(array_keys(ComplaintOptions::PRIORITIES))],
            'sla_response_hours' => ['nullable', 'integer', 'between:1,720'],
            'sla_resolution_hours' => ['nullable', 'integer', 'between:1,2160', 'gte:sla_response_hours'],
            'unit_location' => ['nullable', 'string', 'max:255'],
            'customer_equipment_id' => ['nullable', 'integer', 'exists:customer_equipments,id'],
            'equipment_tag' => ['nullable', 'string', 'max:60'],
            'equipment_type' => ['nullable', Rule::in(ComplaintOptions::keys('equipment_types'))],
            'equipment_criticality' => ['nullable', Rule::in(ComplaintOptions::keys('criticality'))],
            'make' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:120'],
            'capacity' => ['nullable', 'string', 'max:60'],
            'serial_no' => ['nullable', 'string', 'max:120'],
            'refrigerant' => ['nullable', Rule::in(ComplaintOptions::keys('refrigerants'))],
            'compressor_type' => ['nullable', Rule::in(ComplaintOptions::keys('compressor_types'))],
            'technology' => ['nullable', Rule::in(ComplaintOptions::keys('technologies'))],
            'power_supply' => ['nullable', Rule::in(ComplaintOptions::keys('power_supplies'))],
            'installation_date' => ['nullable', 'date', 'before_or_equal:today'],
            'last_service_date' => ['nullable', 'date', 'before_or_equal:today'],
            'last_pm_date' => ['nullable', 'date', 'before_or_equal:today'],
            'service_category' => ['nullable', Rule::in(ComplaintOptions::keys('service_categories'))],
            'contract_no' => ['nullable', 'string', 'max:60', 'required_if:service_category,amc,company_amc'],
            'contract_expiry' => ['nullable', 'date', 'required_with:contract_no'],
        ];
    }

    /**
     * Assignment of a visit; $prefix lets the registration form nest it under "assign.".
     *
     * @return array<string, mixed>
     */
    public static function assignment(string $prefix = '', bool $required = true): array
    {
        $technician = Rule::exists('users', 'id')->where('status', 'active');

        return [
            $prefix.'technician_id' => [$required ? 'required' : 'nullable', 'integer', $technician],
            $prefix.'assistant_one_id' => ['nullable', 'integer', $technician, 'different:'.$prefix.'technician_id'],
            $prefix.'assistant_two_id' => ['nullable', 'integer', $technician, 'different:'.$prefix.'technician_id', 'different:'.$prefix.'assistant_one_id'],
            $prefix.'assignment_method' => ['nullable', Rule::in(ComplaintOptions::keys('assignment_methods'))],
            $prefix.'appointment_at' => ['nullable', 'date', 'after_or_equal:'.now()->subDay()->toDateString()],
            $prefix.'special_instructions' => ['nullable', 'string', 'max:1000'],
            $prefix.'reason_for_visit' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * The pending reason, owner, next action and dates a pending complaint must carry.
     *
     * @return array<string, mixed>
     */
    public static function pending(string $prefix = '', ?string $requiredWhen = null): array
    {
        $required = $requiredWhen ? 'required_if:'.$requiredWhen : 'required';

        return [
            $prefix.'pending_group' => [$required, 'nullable', Rule::in(ComplaintOptions::keys('pending_groups'))],
            $prefix.'pending_reason' => [$required, 'nullable', 'string', 'max:120'],
            $prefix.'pending_sub_reason' => ['nullable', 'string', 'max:120'],
            $prefix.'pending_owner' => [$required, 'nullable', Rule::in(ComplaintOptions::keys('pending_owners'))],
            $prefix.'responsible_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            $prefix.'next_action' => [$required, 'nullable', 'string', 'max:255'],
            $prefix.'follow_up_date' => [$required, 'nullable', 'date', 'after_or_equal:today'],
            $prefix.'expected_material_date' => ['nullable', 'date', 'after_or_equal:today'],
            $prefix.'target_date' => [$required, 'nullable', 'date', 'after_or_equal:'.$prefix.'follow_up_date'],
            $prefix.'delay_reason' => [$required, 'nullable', Rule::in(ComplaintOptions::keys('delay_reasons'))],
            $prefix.'escalation_point' => ['nullable', 'string', 'max:120'],
            $prefix.'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, mixed> */
    public static function parts(string $field): array
    {
        return [
            $field => ['nullable', 'array', 'max:30'],
            $field.'.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            $field.'.*.part_code' => ['nullable', 'string', 'max:60'],
            $field.'.*.part_name' => ['nullable', 'string', 'max:160'],
            $field.'.*.category' => ['nullable', 'string', 'max:80'],
            $field.'.*.make' => ['nullable', 'string', 'max:80'],
            $field.'.*.quantity' => ['nullable', 'numeric', 'min:0.01', 'max:100000', 'required_with:'.$field.'.*.part_name'],
            $field.'.*.uom' => ['nullable', Rule::in(ComplaintOptions::keys('uoms'))],
            $field.'.*.source' => ['nullable', Rule::in(ComplaintOptions::keys('part_sources'))],
            $field.'.*.part_type' => ['nullable', Rule::in(ComplaintOptions::keys('part_types'))],
            $field.'.*.part_warranty' => ['nullable', 'string', 'max:60'],
            $field.'.*.stock_location' => ['nullable', 'string', 'max:80'],
            $field.'.*.available_in_stock' => ['nullable', 'boolean'],
            $field.'.*.estimated_cost' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            $field.'.*.billable_rate' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            $field.'.*.oem_warranty' => ['nullable', 'boolean'],
            $field.'.*.old_part_returned' => ['nullable', 'boolean'],
            $field.'.*.old_part_no' => ['nullable', 'string', 'max:80'],
            $field.'.*.additional_parts' => ['nullable', 'string', 'max:255'],
            $field.'.*.recommended_action' => ['nullable', 'string', 'max:255'],
            $field.'.*.customer_approval_required' => ['nullable', 'boolean'],
            $field.'.*.quotation_given' => ['nullable', 'boolean'],
            $field.'.*.customer_approval_status' => ['nullable', Rule::in(ComplaintOptions::keys('approval_statuses'))],
            $field.'.*.manager_approval_required' => ['nullable', 'boolean'],
            $field.'.*.higher_approval_required' => ['nullable', 'boolean'],
            $field.'.*.approval_status' => ['nullable', Rule::in(ComplaintOptions::keys('approval_statuses'))],
            $field.'.*.purchase_status' => ['nullable', Rule::in(ComplaintOptions::keys('purchase_statuses'))],
            $field.'.*.estimated_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            $field.'.*.remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public static function attributes(): array
    {
        return [
            'contact_no' => 'contact number', 'alternate_no' => 'alternate number', 'call_mode' => 'mode of call',
            'complaint_source' => 'complaint source', 'call_type' => 'type of call', 'nature_of_complaint' => 'nature of complaint',
            'sla_response_hours' => 'SLA response time', 'sla_resolution_hours' => 'SLA resolution time',
            'pending_group' => 'pending group', 'pending_reason' => 'pending reason', 'pending_owner' => 'pending owner',
            'next_action' => 'next action', 'follow_up_date' => 'next follow-up date', 'target_date' => 'targeted closing date',
            'delay_reason' => 'reason for delay', 'technician_id' => 'technician', 'assistant_one_id' => 'assistant 1', 'assistant_two_id' => 'assistant 2',
            'pending.pending_group' => 'pending group', 'pending.pending_reason' => 'pending reason', 'pending.pending_owner' => 'pending owner',
            'pending.next_action' => 'next action', 'pending.follow_up_date' => 'next follow-up date', 'pending.target_date' => 'targeted closing date',
            'pending.delay_reason' => 'reason for delay', 'new_customer.name' => 'customer name', 'new_customer.phone' => 'customer phone',
            'assign.technician_id' => 'technician', 'assign.appointment_at' => 'appointment',
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'sla_resolution_hours.gte' => 'Resolution time cannot be shorter than the response time.',
            'contract_no.required_if' => 'Enter the AMC / contract number (for example AMC-25/26-014).',
            '*.different' => 'Choose a different person for each role.',
            'target_date.after_or_equal' => 'The targeted closing date cannot be before the next follow-up.',
            'pending.target_date.after_or_equal' => 'The targeted closing date cannot be before the next follow-up.',
        ];
    }
}
