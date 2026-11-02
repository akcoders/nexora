<?php

namespace App\Http\Requests\Complaints;

use App\Models\ComplaintVisit;
use App\Services\ComplaintOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Technician visit report. Submitting an outcome completes the visit; choosing "pending"
 * requires the full pending reason so no complaint is left pending without one.
 */
class ComplaintVisitReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('report', [$this->route('complaint'), $this->route('visit')]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $yesNo = Rule::in(ComplaintOptions::keys('yes_no'));
        $condition = Rule::in(ComplaintOptions::keys('conditions'));
        $diagnosis = [];
        foreach (ComplaintOptions::DIAGNOSIS_FIELDS as $key => $field) {
            $diagnosis['diagnosis.'.$key] = match ($field['type']) {
                'number' => ['nullable', 'numeric', 'between:-100,100000'],
                'condition' => ['nullable', $condition],
                'yes_no' => ['nullable', $yesNo],
                default => ['nullable', 'string', 'max:60'],
            };
        }
        $outcomeSet = 'required_with:outcome';

        return [
            'accepted_at' => ['nullable', 'date'],
            'en_route_at' => ['nullable', 'date', 'after_or_equal:accepted_at'],
            'arrived_at' => ['nullable', 'date', 'after_or_equal:en_route_at'],
            'started_at' => ['nullable', 'date', 'after_or_equal:arrived_at'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at', 'before_or_equal:'.now()->addHour()->toDateTimeString()],
            'travel_minutes' => ['nullable', 'integer', 'between:0,1440'],
            'labour_hours' => ['nullable', 'numeric', 'between:0,200'],
            'checks' => ['nullable', 'array:'.implode(',', array_keys(ComplaintOptions::VISIT_CHECKS))],
            'checks.*' => ['nullable', $yesNo],
            'abnormality_found' => ['nullable', 'string', 'max:2000'],
            'diagnosis' => ['nullable', 'array:'.implode(',', array_keys(ComplaintOptions::DIAGNOSIS_FIELDS))],
            ...$diagnosis,
            'observation' => ['nullable', 'string', 'max:3000'],
            'initial_diagnosis' => ['nullable', 'string', 'max:3000'],
            'fault_category' => ['nullable', 'string', 'max:120'],
            'fault_code' => ['nullable', 'string', 'max:60'],
            'root_cause' => ['nullable', 'string', 'max:120'],
            'problem_diagnosis' => ['nullable', $outcomeSet, 'string', 'max:3000'],
            'work_performed' => ['nullable', $outcomeSet, 'string', 'max:3000'],
            'final_result' => ['nullable', $outcomeSet, Rule::in(ComplaintOptions::keys('final_results'))],
            'outcome' => ['nullable', Rule::in(array_keys(ComplaintOptions::VISIT_OUTCOMES))],
            ...ComplaintRules::pending('pending.', 'outcome,pending'),
            'observation_follow_up_date' => ['nullable', 'required_if:outcome,observation', 'date', 'after_or_equal:today'],
            'observation_next_action' => ['nullable', 'string', 'max:255'],
            ...ComplaintRules::parts('used_parts'),
            ...ComplaintRules::parts('recommended_parts'),
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'signature_data' => ['nullable', 'string', 'max:700000', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/'],
            'customer_signed_name' => ['nullable', 'required_with:signature_data', 'string', 'max:120'],
            'customer_remarks' => ['nullable', 'string', 'max:2000'],
            'customer_rating' => ['nullable', 'integer', 'between:1,5'],
            'technician_remarks' => ['nullable', 'string', 'max:2000'],
            'supervisor_remarks' => ['nullable', 'string', 'max:2000'],
            'store_remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('outcome') === 'resolved' && $this->input('final_result') === 'not_working') {
                $validator->errors()->add('final_result', 'A visit cannot be marked resolved while the unit is not working. Choose "Pending" or "Under observation".');
            }
        }];
    }

    /** PNG bytes from the signature pad, if one was drawn. */
    public function signaturePng(): ?string
    {
        $data = $this->validated('signature_data');
        if (blank($data)) {
            return null;
        }
        $bytes = base64_decode(substr($data, strlen('data:image/png;base64,')), true);

        return $bytes !== false && str_starts_with($bytes, "\x89PNG") ? $bytes : null;
    }

    /** Confirmation for the saved report, worded by the outcome chosen. */
    public function resultMessage(ComplaintVisit $visit): string
    {
        return match ($this->input('outcome')) {
            'resolved' => $visit->label().' completed. The complaint is ready for closure checks.',
            'pending' => $visit->label().' completed. Complaint is pending: '.$this->input('pending.pending_reason').'.',
            'observation' => $visit->label().' completed. Complaint is under observation.',
            default => $visit->label().' progress saved.',
        };
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ComplaintRules::attributes() + [
            'problem_diagnosis' => 'problem diagnosis', 'work_performed' => 'work performed', 'final_result' => 'final result',
            'observation_follow_up_date' => 'observation follow-up date', 'en_route_at' => 'en route time', 'arrived_at' => 'arrival time',
            'started_at' => 'site start time', 'ended_at' => 'site end time', 'accepted_at' => 'accepted time',
            'used_parts.*.quantity' => 'part quantity', 'recommended_parts.*.quantity' => 'part quantity',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ComplaintRules::messages() + [
            'problem_diagnosis.required_with' => 'Describe exactly what was wrong before submitting the visit result.',
            'work_performed.required_with' => 'Describe the work performed before submitting the visit result.',
            'final_result.required_with' => 'Choose whether the unit is working after the visit.',
            'pending.*.required_if' => 'This is required when the visit leaves the complaint pending.',
        ];
    }
}
