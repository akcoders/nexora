<?php

namespace App\Services;

use App\Models\Complaint;
use App\Models\ComplaintPart;
use App\Models\ComplaintVisit;
use App\Models\Customer;
use App\Models\CustomerEquipment;
use App\Models\ServiceJob;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ComplaintVisitAssignedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Complaint lifecycle: registration, visits (V01, V02 ...), pending management,
 * commercial details and closure. Every change is written to the activity log.
 */
class ComplaintService
{
    /** Columns copied from validated "details" input onto the complaint. */
    private const DETAIL_FIELDS = [
        'customer_branch_id', 'site_location', 'address', 'contact_person', 'contact_no', 'alternate_no', 'complaint_given_by',
        'call_taken_by', 'call_mode', 'complaint_source', 'call_type', 'nature_of_complaint', 'description', 'priority',
        'sla_response_hours', 'sla_resolution_hours', 'unit_location', 'customer_equipment_id', 'equipment_tag', 'equipment_type',
        'equipment_criticality', 'make', 'model', 'capacity', 'serial_no', 'refrigerant', 'compressor_type', 'technology',
        'power_supply', 'installation_date', 'last_service_date', 'last_pm_date', 'service_category', 'contract_no', 'contract_expiry',
    ];

    private const PENDING_FIELDS = [
        'pending_group', 'pending_reason', 'pending_sub_reason', 'pending_owner', 'responsible_user_id', 'next_action',
        'follow_up_date', 'expected_material_date', 'target_date', 'delay_reason', 'escalation_point',
    ];

    /** Visit columns saved from the technician report (the API fills omitted ones from the saved visit). */
    public const VISIT_REPORT_FIELDS = [
        'accepted_at', 'en_route_at', 'arrived_at', 'started_at', 'ended_at', 'travel_minutes', 'labour_hours', 'abnormality_found',
        'observation', 'initial_diagnosis', 'fault_category', 'fault_code', 'root_cause', 'problem_diagnosis', 'work_performed',
        'final_result', 'customer_signed_name', 'customer_remarks', 'customer_rating', 'technician_remarks', 'supervisor_remarks', 'store_remarks',
    ];

    public const PART_FIELDS = [
        'product_id', 'part_code', 'part_name', 'category', 'make', 'quantity', 'uom', 'source', 'part_type', 'part_warranty',
        'stock_location', 'available_in_stock', 'estimated_cost', 'billable_rate', 'oem_warranty', 'old_part_returned', 'old_part_no',
        'additional_parts', 'recommended_action', 'customer_approval_required', 'quotation_given', 'customer_approval_status',
        'manager_approval_required', 'higher_approval_required', 'approval_status', 'purchase_status', 'estimated_days', 'remarks',
    ];

    public function __construct(private readonly OneSignalService $oneSignal) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, User $actor): Complaint
    {
        $visit = null;
        $complaint = DB::transaction(function () use ($data, $actor, &$visit): Complaint {
            $customer = filled($data['customer_id'] ?? null)
                ? Customer::query()->findOrFail($data['customer_id'])
                : $this->createWalkInCustomer($data['new_customer'], $actor);

            $registeredAt = now();
            [$financialYear, $sequence, $number] = $this->nextNumber($registeredAt);
            $details = $this->detailsWithSla(Arr::only($data, self::DETAIL_FIELDS), $registeredAt);

            $complaint = Complaint::create($details + [
                'complaint_no' => $number,
                'financial_year' => $financialYear,
                'sequence' => $sequence,
                'registered_at' => $registeredAt,
                'customer_id' => $customer->id,
                'call_taken_by' => $data['call_taken_by'] ?? $actor->id,
                'status' => 'open',
                'happy_call_required' => true,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $this->log($complaint, $actor, 'created', 'Complaint registered', null, 'open', $data['description'] ?? null, [
                'call_mode' => $complaint->call_mode, 'call_type' => $complaint->call_type, 'priority' => $complaint->priority,
            ]);

            if (filled($data['assign']['technician_id'] ?? null)) {
                $visit = $this->createVisit($complaint, $data['assign'], $actor);
            }

            return $complaint;
        });

        if ($visit) {
            $this->notifyAssignment($complaint, $visit);
        }

        return $complaint;
    }

    /** @param array<string, mixed> $data */
    public function updateDetails(Complaint $complaint, array $data, User $actor): Complaint
    {
        $details = Arr::only($data, self::DETAIL_FIELDS);
        $slaChanged = ($details['priority'] ?? $complaint->priority) !== $complaint->priority
            || (int) ($details['sla_resolution_hours'] ?? $complaint->sla_resolution_hours) !== (int) $complaint->sla_resolution_hours
            || (int) ($details['sla_response_hours'] ?? $complaint->sla_response_hours) !== (int) $complaint->sla_response_hours;
        if ($slaChanged) {
            $details = $this->detailsWithSla($details, $complaint->registered_at);
        }
        $complaint->update($details + ['updated_by' => $actor->id]);
        $this->log($complaint, $actor, 'details', 'Complaint details updated', null, null, $data['change_remarks'] ?? null);

        return $complaint;
    }

    /** @param array<string, mixed> $data */
    public function assignVisit(Complaint $complaint, array $data, User $actor): ComplaintVisit
    {
        $visit = DB::transaction(fn (): ComplaintVisit => $this->createVisit($complaint, $data, $actor));
        $this->notifyAssignment($complaint, $visit);

        return $visit;
    }

    /**
     * Saves the technician's visit report. When an outcome is chosen the visit is completed and the
     * complaint moves to Work completed, Pending (with a mandatory pending reason) or Under observation.
     *
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>  $attachments
     * @param  string|null  $signaturePng  PNG bytes drawn on the signature pad
     */
    public function recordVisitReport(ComplaintVisit $visit, array $data, array $attachments, ?string $signaturePng, User $actor): ComplaintVisit
    {
        return DB::transaction(function () use ($visit, $data, $attachments, $signaturePng, $actor): ComplaintVisit {
            $complaint = $visit->complaint;
            $report = Arr::only($data, self::VISIT_REPORT_FIELDS);
            $report['checks'] = Arr::only($data['checks'] ?? [], array_keys(ComplaintOptions::VISIT_CHECKS));
            $report['diagnosis'] = array_filter($data['diagnosis'] ?? [], fn ($value) => $value !== null && $value !== '');
            if (blank($report['labour_hours'] ?? null) && filled($report['started_at'] ?? null) && filled($report['ended_at'] ?? null)) {
                $report['labour_hours'] = round(Carbon::parse($report['started_at'])->diffInMinutes(Carbon::parse($report['ended_at'])) / 60, 2);
            }
            if (blank($report['travel_minutes'] ?? null) && filled($report['en_route_at'] ?? null) && filled($report['arrived_at'] ?? null)) {
                $report['travel_minutes'] = (int) Carbon::parse($report['en_route_at'])->diffInMinutes(Carbon::parse($report['arrived_at']));
            }

            $stored = $visit->attachments ?? [];
            foreach ($attachments as $file) {
                $stored[] = ['name' => $file->getClientOriginalName(), 'path' => $file->store('complaints/'.$complaint->id, 'public')];
            }
            $report['attachments'] = $stored;
            if ($signaturePng) {
                $path = 'complaints/'.$complaint->id.'/signatures/'.$visit->label().'-'.Str::random(8).'.png';
                Storage::disk('public')->put($path, $signaturePng);
                $report['customer_signature_path'] = $path;
            }

            $outcome = $data['outcome'] ?? null;
            $report['outcome'] = $outcome;
            $report['status'] = $outcome ? 'completed' : $this->progressStatus($report, $visit);
            $visit->update($report);

            $this->syncVisitParts($visit, ComplaintPart::KIND_USED, $data['used_parts'] ?? [], $actor);
            $this->syncVisitParts($visit, ComplaintPart::KIND_RECOMMENDED, $data['recommended_parts'] ?? [], $actor);

            $complaintChanges = ['updated_by' => $actor->id];
            $firstResponse = collect([$report['accepted_at'] ?? null, $report['arrived_at'] ?? null, $report['started_at'] ?? null])->filter()->map(fn ($value) => Carbon::parse($value))->sort()->first();
            if ($firstResponse && (! $complaint->first_response_at || $firstResponse->lt($complaint->first_response_at))) {
                $complaintChanges['first_response_at'] = $firstResponse;
            }
            if (filled($report['root_cause'] ?? null)) {
                $complaintChanges['root_cause'] = $report['root_cause'];
            }
            $complaintChanges['parts_charges'] = round($complaint->usedParts()->get()->sum(fn (ComplaintPart $part) => $part->lineTotal()), 2);
            $complaint->update($complaintChanges);
            $complaint->update($this->totals($complaint->fresh()));

            $from = $complaint->status;
            if ($outcome === 'resolved') {
                $complaint->update(['status' => 'completed', ...$this->clearedPending()]);
            } elseif ($outcome === 'pending') {
                $this->applyPending($complaint, $data['pending'] ?? [], $actor, $visit);
            } elseif ($outcome === 'observation') {
                $complaint->update(['status' => 'under_observation', 'follow_up_date' => $data['observation_follow_up_date'], 'next_action' => $data['observation_next_action'] ?? 'Check unit performance', 'pending_since' => $complaint->pending_since ?? now()]);
            } elseif (in_array($complaint->status, ['assigned', 'scheduled'], true) && $report['status'] !== 'scheduled') {
                $complaint->update(['status' => 'in_progress']);
            }

            $complaint->refresh();
            $this->log($complaint, $actor, 'visit_report', $visit->label().' report '.($outcome ? 'submitted' : 'saved'), $from, $complaint->status !== $from ? $complaint->status : null,
                $report['work_performed'] ?? $report['problem_diagnosis'] ?? null,
                ['outcome' => $outcome, 'final_result' => $report['final_result'] ?? null, 'technician' => $visit->technician?->name], $visit);

            return $visit->fresh();
        });
    }

    /** @param array<string, mixed> $data */
    public function markPending(Complaint $complaint, array $data, User $actor): Complaint
    {
        return DB::transaction(fn (): Complaint => $this->applyPending($complaint, $data, $actor));
    }

    /**
     * Follow-up on a pending or observed complaint: remarks plus the next follow-up and target dates.
     *
     * @param  array<string, mixed>  $data
     */
    public function addFollowUp(Complaint $complaint, array $data, User $actor): Complaint
    {
        return DB::transaction(function () use ($complaint, $data, $actor): Complaint {
            $changes = array_filter(Arr::only($data, ['next_action', 'follow_up_date', 'expected_material_date', 'target_date', 'delay_reason', 'responsible_user_id', 'pending_owner']), fn ($value) => $value !== null && $value !== '');
            $complaint->update($changes + ['updated_by' => $actor->id]);
            $this->log($complaint, $actor, 'follow_up', 'Follow-up recorded', null, null, $data['remarks'], $changes, null, $data['follow_up_date'] ?? null);

            return $complaint;
        });
    }

    /** @param array<string, mixed> $data */
    public function updateCommercial(Complaint $complaint, array $data, User $actor): Complaint
    {
        $money = ['visit_charges', 'labour_charges', 'parts_charges', 'travel_charges', 'other_charges', 'discount'];
        $commercial = Arr::only($data, ['service_category', 'contract_no', 'contract_expiry', ...$money, 'gst_percent', 'customer_po_no', 'quotation_no', 'invoice_no', 'payment_status']);
        foreach ($money as $field) {
            if (array_key_exists($field, $commercial)) {
                $commercial[$field] = (float) ($commercial[$field] ?? 0);
            }
        }
        $complaint->fill($commercial);
        $complaint->fill($this->totals($complaint));
        $complaint->updated_by = $actor->id;
        $complaint->save();
        $this->log($complaint, $actor, 'commercial', 'Commercial details updated', null, null, null, ['total' => (float) $complaint->total_amount, 'payment_status' => $complaint->payment_status]);

        return $complaint;
    }

    /**
     * Closure: FSR check, happy call, ratings, root cause and final approval.
     *
     * @param  array<string, mixed>  $data
     */
    public function close(Complaint $complaint, array $data, User $actor): Complaint
    {
        if ($complaint->visits()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
            throw ValidationException::withMessages(['status' => 'Finish or cancel the open visit before closing the complaint.']);
        }
        if ($complaint->isClosed()) {
            throw ValidationException::withMessages(['status' => 'This complaint is already '.$complaint->statusMeta()['label'].'.']);
        }

        return DB::transaction(function () use ($complaint, $data, $actor): Complaint {
            $from = $complaint->status;
            $closure = Arr::only($data, ['fsr_checked', 'corrective_action', 'manager_remarks', 'happy_call_required', 'happy_call_at', 'customer_feedback', 'customer_rating', 'team_rating', 'callback_required', 'callback_date', 'root_cause', 'complaint_category']);
            $complaint->update($closure + [
                'fsr_checked_by' => ! empty($closure['fsr_checked']) ? $actor->id : null,
                'happy_call_by' => filled($closure['happy_call_at'] ?? null) ? $actor->id : null,
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => $actor->id,
                'closure_approved_by' => $actor->id,
                'updated_by' => $actor->id,
                ...$this->clearedPending(),
            ]);
            $this->log($complaint, $actor, 'closed', 'Complaint closed', $from, 'closed', $data['manager_remarks'] ?? $data['corrective_action'] ?? null, [
                'root_cause' => $complaint->root_cause, 'customer_rating' => $complaint->customer_rating, 'callback_required' => $complaint->callback_required,
            ]);

            return $complaint;
        });
    }

    public function cancel(Complaint $complaint, string $reason, User $actor): Complaint
    {
        return DB::transaction(function () use ($complaint, $reason, $actor): Complaint {
            $from = $complaint->status;
            $complaint->visits()->whereNotIn('status', ['completed', 'cancelled'])->update(['status' => 'cancelled']);
            $complaint->update(['status' => 'cancelled', 'cancellation_reason' => $reason, 'closed_at' => now(), 'closed_by' => $actor->id, 'updated_by' => $actor->id, ...$this->clearedPending()]);
            $this->log($complaint, $actor, 'cancelled', 'Complaint cancelled', $from, 'cancelled', $reason);

            return $complaint;
        });
    }

    public function reopen(Complaint $complaint, string $reason, User $actor): Complaint
    {
        $from = $complaint->status;
        $complaint->update(['status' => 'open', 'closed_at' => null, 'closed_by' => null, 'closure_approved_by' => null, 'cancellation_reason' => null, 'updated_by' => $actor->id]);
        $this->log($complaint, $actor, 'reopened', 'Complaint reopened', $from, 'open', $reason);

        return $complaint;
    }

    /**
     * Auto-fill data for the registration form: branches, contacts and equipment with their
     * last service / PM dates and contract details.
     *
     * @return array<string, mixed>
     */
    public function customerContext(Customer $customer): array
    {
        $customer->loadMissing(['branches', 'contacts', 'equipments.room']);
        $lastVisits = ComplaintVisit::query()
            ->join('complaints', 'complaints.id', '=', 'complaint_visits.complaint_id')
            ->where('complaints.customer_id', $customer->id)
            ->whereNotNull('complaints.customer_equipment_id')
            ->where('complaint_visits.status', 'completed')
            ->selectRaw('complaints.customer_equipment_id as equipment_id, complaints.call_type, MAX(COALESCE(complaint_visits.ended_at, complaint_visits.updated_at)) as last_at')
            ->groupBy('complaints.customer_equipment_id', 'complaints.call_type')
            ->get();
        $lastJobs = ServiceJob::query()->where('customer_id', $customer->id)->whereNotNull('customer_equipment_id')->where('status', 'completed')
            ->selectRaw('customer_equipment_id as equipment_id, MAX(completed_at) as last_at')->groupBy('customer_equipment_id')->pluck('last_at', 'equipment_id');
        $lastContract = Complaint::query()->where('customer_id', $customer->id)->whereNotNull('contract_no')->latest('registered_at')->first(['contract_no', 'contract_expiry', 'service_category']);

        return [
            'customer' => [
                'id' => $customer->id, 'code' => $customer->code, 'name' => $customer->name,
                'address' => collect([$customer->address_line_1, $customer->address_line_2, $customer->area, $customer->city, $customer->state, $customer->pin_code])->filter()->join(', '),
                'phone' => $customer->contact_no_1,
            ],
            'branches' => $customer->branches->where('active', true)->map(fn ($branch) => [
                'id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code,
                'address' => collect([$branch->address_line_1, $branch->address_line_2, $branch->area, $branch->city, $branch->state, $branch->pin_code])->filter()->join(', '),
                'phone' => $branch->contact_no_1,
            ])->values(),
            'contacts' => $customer->contacts->map(fn ($contact) => [
                'id' => $contact->id, 'name' => $contact->name, 'phone' => $contact->phone ?: $contact->whatsapp, 'branch_id' => $contact->customer_branch_id,
                'designation' => $contact->designation, 'is_service' => (bool) $contact->is_service, 'is_primary' => (bool) $contact->is_primary,
            ])->values(),
            'equipment' => $customer->equipments->where('active', true)->map(function (CustomerEquipment $equipment) use ($lastVisits, $lastJobs): array {
                $visits = $lastVisits->where('equipment_id', $equipment->id);
                $lastService = collect([$visits->max('last_at'), $lastJobs->get($equipment->id)])->filter()->max();
                $lastPm = $visits->where('call_type', 'preventive')->max('last_at');

                return [
                    'id' => $equipment->id, 'branch_id' => $equipment->customer_branch_id,
                    'label' => collect([$equipment->equipment_type, $equipment->brand, $equipment->model, $equipment->capacity])->filter()->join(' · '),
                    'tag' => data_get($equipment->metadata, 'tag') ?? $equipment->serial_no, 'type' => $this->equipmentTypeKey($equipment->equipment_type),
                    'make' => $equipment->brand, 'model' => $equipment->model, 'capacity' => $equipment->capacity, 'serial_no' => $equipment->serial_no,
                    'location' => $equipment->location ?: $equipment->room?->name,
                    'installation_date' => $equipment->installed_at?->toDateString(), 'warranty_ends_at' => $equipment->warranty_ends_at?->toDateString(),
                    'in_warranty' => $equipment->warranty_ends_at?->isFuture() ?? false,
                    'last_service_date' => $lastService ? Carbon::parse($lastService)->toDateString() : null,
                    'last_pm_date' => $lastPm ? Carbon::parse($lastPm)->toDateString() : null,
                    'refrigerant' => data_get($equipment->metadata, 'refrigerant'), 'technology' => data_get($equipment->metadata, 'technology'),
                ];
            })->values(),
            'contract' => $lastContract ? ['contract_no' => $lastContract->contract_no, 'contract_expiry' => $lastContract->contract_expiry?->toDateString(), 'service_category' => $lastContract->service_category] : null,
            'open_complaints' => Complaint::query()->where('customer_id', $customer->id)->active()->latest('registered_at')->limit(5)
                ->get(['id', 'complaint_no', 'nature_of_complaint', 'status', 'registered_at'])
                ->map(fn (Complaint $complaint) => ['id' => $complaint->id, 'no' => $complaint->complaint_no, 'nature' => $complaint->nature_of_complaint, 'status' => $complaint->statusMeta()['label'], 'date' => $complaint->registered_at->format('d M Y'), 'url' => route('complaints.show', $complaint)]),
        ];
    }

    /**
     * Next complaint number for the financial year, e.g. CCSPL/MUM/26-27/123.
     *
     * @return array{0: string, 1: int, 2: string}
     */
    public function nextNumber(Carbon $at): array
    {
        $financialYear = ComplaintOptions::financialYear($at);
        $sequence = (int) Complaint::withTrashed()->where('financial_year', $financialYear)->lockForUpdate()->max('sequence') + 1;
        $parts = [
            Str::upper((string) Setting::valueFor('complaint_prefix', 'CCSPL')),
            Str::upper((string) Setting::valueFor('complaint_branch_code', 'MUM')),
            $financialYear,
            str_pad((string) $sequence, 3, '0', STR_PAD_LEFT),
        ];

        return [$financialYear, $sequence, implode('/', array_filter($parts, fn ($part) => $part !== ''))];
    }

    /** @param array<string, mixed> $data */
    private function createVisit(Complaint $complaint, array $data, User $actor): ComplaintVisit
    {
        if ($complaint->isClosed()) {
            throw ValidationException::withMessages(['technician_id' => 'Reopen the complaint before assigning a new visit.']);
        }
        if ($complaint->visits()->whereNotIn('status', ['completed', 'cancelled'])->exists()) {
            throw ValidationException::withMessages(['technician_id' => 'Finish or cancel the current visit before assigning the next one.']);
        }

        $visitNo = (int) $complaint->visits()->max('visit_no') + 1;
        $visit = $complaint->visits()->create([
            'visit_no' => $visitNo,
            'status' => 'scheduled',
            'reason_for_visit' => ($data['reason_for_visit'] ?? null) ?: ($visitNo === 1 ? 'Diagnosis & '.Str::lower($complaint->callTypeLabel()) : null),
            'technician_id' => $data['technician_id'],
            'assistant_one_id' => $data['assistant_one_id'] ?? null,
            'assistant_two_id' => $data['assistant_two_id'] ?? null,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
            'assignment_method' => $data['assignment_method'] ?? 'app',
            'appointment_at' => $data['appointment_at'] ?? null,
            'special_instructions' => $data['special_instructions'] ?? null,
            'created_by' => $actor->id,
        ]);

        $from = $complaint->status;
        $complaint->update([
            'assigned_to' => $visit->technician_id,
            'status' => $visit->appointment_at ? 'scheduled' : 'assigned',
            'updated_by' => $actor->id,
        ]);
        $this->log($complaint, $actor, 'visit_assigned', $visit->label().' assigned to '.$visit->technician?->name, $from, $complaint->status,
            $visit->special_instructions, ['appointment_at' => $visit->appointment_at?->toDateTimeString(), 'reason' => $visit->reason_for_visit, 'method' => $visit->assignment_method], $visit);

        return $visit;
    }

    /** @param array<string, mixed> $data */
    private function applyPending(Complaint $complaint, array $data, User $actor, ?ComplaintVisit $visit = null): Complaint
    {
        $from = $complaint->status;
        $pending = Arr::only($data, self::PENDING_FIELDS);
        $complaint->update($pending + [
            'status' => 'pending',
            'pending_since' => $from === 'pending' && $complaint->pending_since ? $complaint->pending_since : now(),
            'updated_by' => $actor->id,
        ]);
        $this->log($complaint, $actor, 'pending', 'Marked pending: '.($pending['pending_reason'] ?? 'reason not set'), $from, $from === 'pending' ? null : 'pending',
            $data['remarks'] ?? null, Arr::except($pending, ['follow_up_date']), $visit, $pending['follow_up_date'] ?? null);

        return $complaint;
    }

    /** @return array<string, null> */
    private function clearedPending(): array
    {
        return array_fill_keys([...self::PENDING_FIELDS, 'pending_since'], null);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    private function detailsWithSla(array $details, Carbon $registeredAt): array
    {
        $sla = ComplaintOptions::slaFor($details['priority'] ?? 'medium');
        $details['sla_response_hours'] = (int) (($details['sla_response_hours'] ?? null) ?: $sla['response']);
        $details['sla_resolution_hours'] = (int) (($details['sla_resolution_hours'] ?? null) ?: $sla['resolution']);
        $details['response_due_at'] = $registeredAt->copy()->addHours($details['sla_response_hours']);
        $details['resolution_due_at'] = $registeredAt->copy()->addHours($details['sla_resolution_hours']);

        return $details;
    }

    /** @param array<string, mixed> $data */
    private function createWalkInCustomer(array $data, User $actor): Customer
    {
        $nextId = (int) Customer::withTrashed()->max('id') + 1;

        return Customer::create([
            'code' => 'CUST'.str_pad((string) $nextId, 5, '0', STR_PAD_LEFT),
            'name' => $data['name'],
            'types' => [$data['type'] ?? 'individual'],
            'status' => 'active',
            'priority' => 'normal',
            'branch_type' => 'single',
            'contact_no_1' => $data['phone'],
            'email_1' => $data['email'] ?? null,
            'address_line_1' => $data['address'] ?? null,
            'city' => $data['city'] ?? null,
            'created_by' => $actor->id,
            'remarks' => 'Created from complaint registration.',
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncVisitParts(ComplaintVisit $visit, string $kind, array $rows, User $actor): void
    {
        $visit->parts()->where('kind', $kind)->delete();
        foreach ($rows as $row) {
            if (blank($row['part_name'] ?? null)) {
                continue;
            }
            $visit->parts()->create(array_merge(Arr::only($row, self::PART_FIELDS), [
                'complaint_id' => $visit->complaint_id,
                'kind' => $kind,
                'quantity' => $row['quantity'] ?? 1,
                'uom' => $row['uom'] ?? 'nos',
                'estimated_cost' => $row['estimated_cost'] ?? 0,
                'billable_rate' => $row['billable_rate'] ?? 0,
                'created_by' => $actor->id,
            ]));
        }
    }

    /** @param array<string, mixed> $report */
    private function progressStatus(array $report, ComplaintVisit $visit): string
    {
        return match (true) {
            filled($report['arrived_at'] ?? null) || filled($report['started_at'] ?? null) => 'on_site',
            filled($report['en_route_at'] ?? null) => 'en_route',
            filled($report['accepted_at'] ?? null) => 'accepted',
            default => $visit->status === 'cancelled' ? 'cancelled' : 'scheduled',
        };
    }

    /** @return array{gst_amount: float, total_amount: float} */
    private function totals(Complaint $complaint): array
    {
        $subtotal = max(0, $complaint->chargesSubtotal());
        $gst = round($subtotal * (float) $complaint->gst_percent / 100, 2);

        return ['gst_amount' => $gst, 'total_amount' => round($subtotal + $gst, 2)];
    }

    private function equipmentTypeKey(?string $type): ?string
    {
        if (blank($type)) {
            return null;
        }
        $normalized = Str::lower($type);
        foreach (['cassette' => 'cassette', 'vrf' => 'vrf', 'vrv' => 'vrf', 'ahu' => 'ahu', 'chiller' => 'chiller', 'tfa' => 'tfa', 'exhaust' => 'exhaust_fan', 'pump' => 'pump', 'cooling tower' => 'cooling_tower', 'panel' => 'electrical', 'electrical' => 'electrical', 'split' => 'split'] as $needle => $key) {
            if (str_contains($normalized, $needle)) {
                return $key;
            }
        }

        return array_key_exists($normalized, ComplaintOptions::options('equipment_types')) ? $normalized : 'other';
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function log(Complaint $complaint, User $actor, string $type, string $title, ?string $from = null, ?string $to = null, ?string $remarks = null, array $details = [], ?ComplaintVisit $visit = null, ?string $followUpDate = null): void
    {
        $complaint->activities()->create([
            'complaint_visit_id' => $visit?->id,
            'user_id' => $actor->id,
            'type' => $type,
            'title' => Str::limit($title, 250),
            'status_from' => $from,
            'status_to' => $to,
            'remarks' => $remarks,
            'details' => array_filter($details, fn ($value) => $value !== null && $value !== ''),
            'follow_up_date' => $followUpDate,
        ]);
    }

    private function notifyAssignment(Complaint $complaint, ComplaintVisit $visit): void
    {
        $visit->loadMissing('technician');
        $complaint->loadMissing('customer');
        if (! $visit->technician) {
            return;
        }
        $visit->technician->notify(new ComplaintVisitAssignedNotification($complaint, $visit));
        $this->oneSignal->sendToUser($visit->technician, 'New complaint visit '.$visit->label(), $complaint->complaint_no.' · '.$complaint->customer?->name, ['type' => 'complaint_visit', 'complaint_id' => $complaint->id, 'complaint_visit_id' => $visit->id]);
    }
}
