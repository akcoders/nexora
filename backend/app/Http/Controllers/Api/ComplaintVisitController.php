<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Complaints\ApiComplaintVisitReportRequest;
use App\Models\Complaint;
use App\Models\ComplaintPart;
use App\Models\ComplaintVisit;
use App\Models\User;
use App\Services\ComplaintOptions;
use App\Services\ComplaintService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Complaint visits for the technician app: the visits the user is on (as technician or assistant),
 * one visit with everything its report form needs, and the visit report itself.
 */
class ComplaintVisitController extends Controller
{
    public function __construct(private readonly ComplaintService $complaints) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate(['scope' => ['nullable', Rule::in(['open', 'completed'])]]);
        $user = $request->user();

        $visits = ComplaintVisit::query()
            ->where(fn (Builder $query) => $query->where('technician_id', $user->id)->orWhere('assistant_one_id', $user->id)->orWhere('assistant_two_id', $user->id))
            ->with('complaint.customer:id,name')
            ->when(($filters['scope'] ?? 'open') === 'open',
                fn (Builder $query) => $query->whereNotIn('status', ['completed', 'cancelled'])
                    ->whereHas('complaint', fn (Builder $complaint) => $complaint->whereNotIn('status', ['closed', 'cancelled']))
                    ->orderByRaw('appointment_at IS NULL')->orderBy('appointment_at')->orderBy('id'),
                fn (Builder $query) => $query->where('status', 'completed')->whereHas('complaint')
                    ->orderByRaw('COALESCE(ended_at, updated_at) DESC')->orderByDesc('id')->limit(50))
            ->get();

        return response()->json(['data' => $visits->map(fn (ComplaintVisit $visit) => $this->visitSummary($visit, $user))->values()]);
    }

    public function show(Request $request, ComplaintVisit $visit): JsonResponse
    {
        $complaint = $visit->complaint;
        abort_if($complaint === null, 404);
        abort_unless($this->roleOn($visit, $request->user()) !== null || $request->user()->can('view', $complaint), 403);
        $complaint->load('customer:id,name');

        return response()->json([
            'visit' => $this->visitDetail($visit, $request->user()),
            'complaint' => $this->complaintDetail($complaint),
            'previous_visits' => $complaint->visits()->where('visit_no', '<', $visit->visit_no)->with('technician:id,name')->get()
                ->map(fn (ComplaintVisit $previous) => [
                    'id' => $previous->id,
                    'label' => $previous->label(),
                    'status' => $previous->status,
                    'status_label' => $previous->statusMeta()['label'],
                    'outcome_label' => $previous->outcomeMeta()['label'] ?? null,
                    'technician_name' => $previous->technician?->name,
                    'ended_at' => $previous->ended_at,
                    'problem_diagnosis' => $previous->problem_diagnosis,
                    'work_performed' => $previous->work_performed,
                ])->values(),
            'form' => $this->form($complaint),
            'can_report' => $visit->isOpen() && $request->user()->can('report', [$complaint, $visit]),
        ]);
    }

    public function report(ApiComplaintVisitReportRequest $request, Complaint $complaint, ComplaintVisit $visit): JsonResponse
    {
        $saved = $this->complaints->recordVisitReport($visit, $request->safe()->except(['attachments', 'signature_data']), $request->file('attachments', []), $request->signaturePng(), $request->user());
        $complaint->refresh();

        return response()->json([
            'message' => $request->resultMessage($saved),
            'visit' => $this->visitDetail($saved, $request->user()),
            'complaint_status' => $complaint->status,
            'complaint_status_label' => $complaint->statusMeta()['label'],
        ]);
    }

    /** "technician" or "assistant" for the visit team, null for an office user viewing it. */
    private function roleOn(ComplaintVisit $visit, User $user): ?string
    {
        return match ($user->id) {
            $visit->technician_id => 'technician',
            $visit->assistant_one_id, $visit->assistant_two_id => 'assistant',
            default => null,
        };
    }

    /** @return array<string, mixed> */
    private function visitSummary(ComplaintVisit $visit, User $user): array
    {
        return [
            ...$visit->only(['id', 'complaint_id']),
            'label' => $visit->label(),
            'visit_no' => $visit->visit_no,
            'status' => $visit->status,
            'status_label' => $visit->statusMeta()['label'],
            'outcome' => $visit->outcome,
            'outcome_label' => $visit->outcomeMeta()['label'] ?? null,
            ...$visit->only(['appointment_at', 'assigned_at', 'accepted_at', 'en_route_at', 'arrived_at', 'started_at', 'ended_at', 'reason_for_visit', 'special_instructions']),
            'role' => $this->roleOn($visit, $user),
            'complaint' => $this->complaintSummary($visit->complaint),
        ];
    }

    /** @return array<string, mixed> */
    private function visitDetail(ComplaintVisit $visit, User $user): array
    {
        $visit->loadMissing(['complaint.customer:id,name', 'technician:id,name', 'assistantOne:id,name', 'assistantTwo:id,name', 'parts']);

        return [
            ...$this->visitSummary($visit, $user),
            'checks' => (object) ($visit->checks ?? []),
            'diagnosis' => (object) ($visit->diagnosis ?? []),
            ...$visit->only([
                'observation', 'initial_diagnosis', 'fault_category', 'fault_code', 'root_cause', 'abnormality_found', 'problem_diagnosis', 'work_performed',
                'final_result', 'customer_signed_name', 'customer_remarks', 'customer_rating', 'technician_remarks', 'travel_minutes',
            ]),
            'labour_hours' => $visit->labour_hours === null ? null : (float) $visit->labour_hours,
            'team' => collect([$visit->technician?->name, $visit->assistantOne?->name, $visit->assistantTwo?->name])->filter()->values(),
            'has_signature' => filled($visit->customer_signature_path),
            'attachments' => collect($visit->attachments ?? [])->map(fn (array $file) => [
                'name' => $file['name'] ?? 'File',
                'url' => Storage::disk('public')->url($file['path'] ?? ''),
            ])->values(),
            'parts' => $visit->parts->map(fn (ComplaintPart $part) => [
                'kind' => $part->kind,
                'part_name' => $part->part_name,
                'part_code' => $part->part_code,
                'quantity' => (float) $part->quantity,
                'uom' => $part->uom,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function complaintSummary(Complaint $complaint): array
    {
        return [
            ...$complaint->only(['id', 'complaint_no', 'status']),
            'status_label' => $complaint->statusMeta()['label'],
            'priority' => $complaint->priority,
            'priority_label' => $complaint->priorityLabel(),
            'call_type' => $complaint->call_type,
            'call_type_label' => $complaint->callTypeLabel(),
            'nature_of_complaint' => $complaint->nature_of_complaint,
            'customer_name' => $complaint->customer?->name,
            ...$complaint->only(['site_location', 'address', 'contact_person', 'contact_no']),
            'equipment_label' => $complaint->equipmentLabel(),
            ...$complaint->only(['response_due_at', 'resolution_due_at']),
        ];
    }

    /** @return array<string, mixed> */
    private function complaintDetail(Complaint $complaint): array
    {
        return [
            ...$this->complaintSummary($complaint),
            ...$complaint->only(['description', 'alternate_no', 'unit_location', 'equipment_type']),
            'equipment_type_label' => ComplaintOptions::label('equipment_types', $complaint->equipment_type),
            ...$complaint->only(['equipment_tag', 'make', 'model', 'capacity', 'serial_no', 'service_category']),
            'service_category_label' => ComplaintOptions::label('service_categories', $complaint->service_category),
            'registered_at' => $complaint->registered_at,
            'customer' => ['id' => $complaint->customer_id, 'name' => $complaint->customer?->name],
        ];
    }

    /**
     * Option lists for the report form as ordered {value, label} pairs. Fault category, root cause and
     * pending reason / sub reason values are the master labels, which is what the web form posts.
     *
     * @return array<string, mixed>
     */
    private function form(Complaint $complaint): array
    {
        $pairs = fn (array $options): array => collect($options)->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])->values()->all();
        $labels = fn (array $master): array => collect($master)->map(fn (string $label) => ['value' => $label, 'label' => $label])->values()->all();

        return [
            'checks' => collect(ComplaintOptions::VISIT_CHECKS)->map(fn (array $check, string $key) => ['key' => $key, ...$check])->values(),
            'diagnosis_fields' => collect(ComplaintOptions::diagnosisFieldsFor($complaint->equipment_type))
                ->map(fn (array $field, string $key) => ['key' => $key, 'label' => $field['label'], 'type' => $field['type'], 'unit' => $field['unit'] ?? null])->values(),
            'conditions' => $pairs(ComplaintOptions::options('conditions')),
            'yes_no' => $pairs(ComplaintOptions::options('yes_no')),
            'final_results' => $pairs(ComplaintOptions::options('final_results')),
            'outcomes' => $pairs(array_map(fn (array $outcome) => $outcome['label'], ComplaintOptions::VISIT_OUTCOMES)),
            'pending_groups' => $pairs(ComplaintOptions::options('pending_groups')),
            'pending_owners' => $pairs(ComplaintOptions::options('pending_owners')),
            'delay_reasons' => $pairs(ComplaintOptions::options('delay_reasons')),
            'uoms' => $pairs(ComplaintOptions::options('uoms')),
            'fault_categories' => $labels(ComplaintOptions::master('fault_category')),
            'root_causes' => $labels(ComplaintOptions::master('root_cause')),
            'pending_reasons' => (object) array_map(
                fn (array $reasons) => array_map(fn (array $reason) => ['code' => $reason['code'], 'value' => $reason['label'], 'label' => $reason['label'], 'subs' => $reason['subs']], $reasons),
                ComplaintOptions::pendingReasonTree(),
            ),
        ];
    }
}
