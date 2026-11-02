<?php

namespace App\Http\Controllers;

use App\Http\Requests\Complaints\CloseComplaintRequest;
use App\Http\Requests\Complaints\ComplaintCommercialRequest;
use App\Http\Requests\Complaints\ComplaintFollowUpRequest;
use App\Http\Requests\Complaints\ComplaintPendingRequest;
use App\Http\Requests\Complaints\StoreComplaintRequest;
use App\Models\Complaint;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use App\Services\ComplaintOptions;
use App\Services\ComplaintService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ComplaintController extends Controller
{
    public function __construct(private readonly ComplaintService $complaints) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Complaint::class);
        $validated = $request->validate([
            'type' => ['nullable', 'in:all,'.implode(',', array_keys(ComplaintOptions::CALL_TYPES))],
            'status' => ['nullable', 'in:active,all,'.implode(',', array_keys(ComplaintOptions::STATUSES))],
            'priority' => ['nullable', 'in:'.implode(',', array_keys(ComplaintOptions::PRIORITIES))],
            'technician' => ['nullable', 'integer'],
            'customer' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'flag' => ['nullable', 'in:sla_breached,follow_up_today,unassigned'],
        ]);
        $type = $validated['type'] ?? 'all';
        $status = $validated['status'] ?? 'active';
        $visible = Complaint::query()->visibleTo($request->user());
        $typed = (clone $visible)->when($type !== 'all', fn (Builder $query) => $query->where('call_type', $type));

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : null;
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : null;
        if (! $from && ! in_array($status, ComplaintOptions::ACTIVE_STATUSES, true) && $status !== 'active') {
            $from = now()->subDays(90)->startOfDay();
        }

        $complaints = (clone $typed)
            ->with(['customer:id,name,code', 'branch:id,name', 'technician:id,name', 'callTaker:id,name'])
            ->withCount('visits')
            ->when($status === 'active', fn (Builder $query) => $query->active())
            ->when(! in_array($status, ['active', 'all'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($validated['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($validated['technician'] ?? null, fn (Builder $query, int $technician) => $query->where('assigned_to', $technician))
            ->when($validated['customer'] ?? null, fn (Builder $query, int $customer) => $query->where('customer_id', $customer))
            ->when($from, fn (Builder $query) => $query->where('registered_at', '>=', $from))
            ->when($to, fn (Builder $query) => $query->where('registered_at', '<=', $to))
            ->when(($validated['flag'] ?? null) === 'sla_breached', fn (Builder $query) => $query->slaBreached())
            ->when(($validated['flag'] ?? null) === 'follow_up_today', fn (Builder $query) => $query->followUpDue())
            ->when(($validated['flag'] ?? null) === 'unassigned', fn (Builder $query) => $query->where('status', 'open'))
            ->latest('registered_at')
            ->limit(2000)
            ->get();

        $statusCounts = (clone $typed)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('complaints.index', [
            'complaints' => $complaints,
            'type' => $type,
            'status' => $status,
            'filters' => $validated,
            'from' => $from,
            'to' => $to,
            'typeCounts' => (clone $visible)->active()->selectRaw('call_type, COUNT(*) as total')->groupBy('call_type')->pluck('total', 'call_type'),
            'statusCounts' => $statusCounts,
            'metrics' => [
                'active' => (clone $typed)->active()->count(),
                'unassigned' => (clone $typed)->where('status', 'open')->count(),
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'breached' => (clone $typed)->slaBreached()->count(),
                'followUps' => (clone $typed)->followUpDue()->count(),
                'closedThisMonth' => (clone $typed)->where('status', 'closed')->where('closed_at', '>=', now()->startOfMonth())->count(),
            ],
            'technicians' => $this->technicians(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Complaint::class);
        [, , $nextNumber] = $this->complaints->nextNumber(now());

        return view('complaints.create', $this->formData() + [
            'nextNumber' => $nextNumber,
            'selectedCustomer' => $request->integer('customer') ?: null,
        ]);
    }

    public function store(StoreComplaintRequest $request): RedirectResponse
    {
        $complaint = $this->complaints->create($request->validated(), $request->user());

        return redirect()->route('complaints.show', $complaint)
            ->with('success', 'Complaint '.$complaint->complaint_no.' registered'.($complaint->assigned_to ? ' and assigned.' : '. Assign a technician to schedule the first visit.'));
    }

    public function show(Request $request, Complaint $complaint): View
    {
        Gate::authorize('view', $complaint);
        $complaint->load([
            'customer.branches', 'customer.equipments', 'branch', 'equipment', 'callTaker', 'technician', 'responsibleUser', 'creator', 'closedBy', 'fsrCheckedBy', 'happyCallBy',
            'visits.technician', 'visits.assistantOne', 'visits.assistantTwo', 'visits.assigner', 'visits.parts',
            'parts.visit', 'activities.user', 'activities.visit',
        ]);

        return view('complaints.show', $this->formData() + [
            'complaint' => $complaint,
            'openVisit' => $complaint->visits->first(fn ($visit) => $visit->isOpen()),
            'relatedComplaints' => Complaint::query()->visibleTo($request->user())->where('customer_id', $complaint->customer_id)
                ->whereKeyNot($complaint->id)->latest('registered_at')->limit(5)->get(['id', 'complaint_no', 'nature_of_complaint', 'status', 'registered_at']),
        ]);
    }

    public function update(StoreComplaintRequest $request, Complaint $complaint): RedirectResponse
    {
        $this->complaints->updateDetails($complaint, $request->validated(), $request->user());

        return redirect()->route('complaints.show', $complaint)->with('success', 'Complaint details updated.');
    }

    public function customerContext(Request $request, Customer $customer): JsonResponse
    {
        abort_unless($request->user()->canAny(['complaints.create', 'complaints.update']), 403);

        return response()->json($this->complaints->customerContext($customer));
    }

    public function pending(ComplaintPendingRequest $request, Complaint $complaint): RedirectResponse
    {
        $this->complaints->markPending($complaint, $request->validated(), $request->user());

        return redirect()->route('complaints.show', [$complaint, 'tab' => 'pending'])->with('success', 'Complaint marked pending with owner and next follow-up.');
    }

    public function followUp(ComplaintFollowUpRequest $request, Complaint $complaint): RedirectResponse
    {
        $this->complaints->addFollowUp($complaint, $request->validated(), $request->user());

        return back()->with('success', 'Follow-up saved for '.$complaint->complaint_no.'.');
    }

    public function commercial(ComplaintCommercialRequest $request, Complaint $complaint): RedirectResponse
    {
        $this->complaints->updateCommercial($complaint, $request->validated(), $request->user());

        return redirect()->route('complaints.show', [$complaint, 'tab' => 'commercial'])->with('success', 'Commercial details saved.');
    }

    public function close(CloseComplaintRequest $request, Complaint $complaint): RedirectResponse
    {
        $this->complaints->close($complaint, $request->validated(), $request->user());

        return redirect()->route('complaints.show', $complaint)->with('success', 'Complaint '.$complaint->complaint_no.' closed.');
    }

    public function cancel(Request $request, Complaint $complaint): RedirectResponse
    {
        Gate::authorize('close', $complaint);
        $validated = $request->validate(['cancellation_reason' => ['required', 'string', 'max:255']]);
        $this->complaints->cancel($complaint, $validated['cancellation_reason'], $request->user());

        return redirect()->route('complaints.show', $complaint)->with('success', 'Complaint cancelled.');
    }

    public function reopen(Request $request, Complaint $complaint): RedirectResponse
    {
        Gate::authorize('reopen', $complaint);
        $validated = $request->validate(['reopen_reason' => ['required', 'string', 'max:255']]);
        $this->complaints->reopen($complaint, $validated['reopen_reason'], $request->user());

        return redirect()->route('complaints.show', $complaint)->with('success', 'Complaint reopened. Assign the next visit to continue.');
    }

    /**
     * Pending management: every pending or observed complaint with its reason, owner, age band
     * and next follow-up, so nothing stays pending without a plan.
     */
    public function pendingBoard(Request $request): View
    {
        Gate::authorize('viewAny', Complaint::class);
        $validated = $request->validate([
            'group' => ['nullable', 'in:'.implode(',', ComplaintOptions::keys('pending_groups'))],
            'owner' => ['nullable', 'in:'.implode(',', ComplaintOptions::keys('pending_owners'))],
            'bucket' => ['nullable', 'in:'.implode(',', array_column(ComplaintOptions::AGE_BUCKETS, 'key'))],
            'responsible' => ['nullable', 'integer'],
            'follow_up' => ['nullable', 'in:today,overdue,upcoming'],
        ]);

        $all = Complaint::query()->visibleTo($request->user())->whereIn('status', ['pending', 'under_observation'])
            ->with(['customer:id,name,code', 'responsibleUser:id,name', 'technician:id,name'])
            ->orderBy('pending_since')
            ->get();
        $buckets = collect(ComplaintOptions::AGE_BUCKETS)->mapWithKeys(fn (array $bucket) => [$bucket['key'] => $bucket + ['count' => 0]])->all();
        foreach ($all as $complaint) {
            $buckets[$complaint->pendingBucket()['key']]['count']++;
        }

        $today = now()->toDateString();
        $rows = $all
            ->when($validated['group'] ?? null, fn ($rows, $group) => $rows->where('pending_group', $group))
            ->when($validated['owner'] ?? null, fn ($rows, $owner) => $rows->where('pending_owner', $owner))
            ->when($validated['responsible'] ?? null, fn ($rows, $user) => $rows->where('responsible_user_id', $user))
            ->when($validated['bucket'] ?? null, fn ($rows, $bucket) => $rows->filter(fn (Complaint $complaint) => $complaint->pendingBucket()['key'] === $bucket))
            ->when(($validated['follow_up'] ?? null) === 'today', fn ($rows) => $rows->filter(fn (Complaint $complaint) => $complaint->follow_up_date?->toDateString() === $today))
            ->when(($validated['follow_up'] ?? null) === 'overdue', fn ($rows) => $rows->filter(fn (Complaint $complaint) => $complaint->follow_up_date && $complaint->follow_up_date->toDateString() < $today))
            ->when(($validated['follow_up'] ?? null) === 'upcoming', fn ($rows) => $rows->filter(fn (Complaint $complaint) => $complaint->follow_up_date && $complaint->follow_up_date->toDateString() > $today))
            ->values();

        return view('complaints.pending', [
            'rows' => $rows,
            'all' => $all,
            'buckets' => $buckets,
            'filters' => $validated,
            'groupCounts' => $all->countBy('pending_group'),
            'ownerCounts' => $all->countBy('pending_owner'),
            'withoutPlan' => $all->filter(fn (Complaint $complaint) => blank($complaint->pending_reason) || ! $complaint->follow_up_date)->count(),
            'followUpsToday' => $all->filter(fn (Complaint $complaint) => $complaint->follow_up_date && $complaint->follow_up_date->toDateString() <= $today)->count(),
            'users' => User::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Printable field service report (FSR) with every visit. */
    public function print(Complaint $complaint): View
    {
        Gate::authorize('view', $complaint);
        $complaint->load(['customer', 'branch', 'callTaker', 'technician', 'closedBy', 'visits.technician', 'visits.assistantOne', 'visits.assistantTwo', 'visits.assigner', 'parts.visit']);

        return view('complaints.print', [
            'complaint' => $complaint,
            'companyName' => Setting::valueFor('company_name', config('app.name')),
            'companyPhone' => Setting::valueFor('company_phone'),
            'companyEmail' => Setting::valueFor('company_email'),
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'code', 'city']),
            'technicians' => $this->technicians(),
            'staff' => User::query()->active()->orderBy('name')->get(['id', 'name', 'designation']),
            'natures' => ComplaintOptions::master('complaint_nature'),
            'rootCauses' => ComplaintOptions::master('root_cause'),
            'faultCategories' => ComplaintOptions::master('fault_category'),
            'pendingTree' => ComplaintOptions::pendingReasonTree(),
            'options' => fn (string $list): array => ComplaintOptions::options($list),
        ];
    }

    /** Active field staff; technicians first. */
    private function technicians()
    {
        return User::query()->active()
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'designation'])
            ->sortBy(fn (User $user) => $user->roles->contains('name', 'Technician') ? 0 : 1)
            ->values();
    }
}
