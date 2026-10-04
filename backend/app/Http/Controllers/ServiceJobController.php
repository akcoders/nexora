<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreServiceJobRequest;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\ServiceJob;
use App\Models\ServiceType;
use App\Models\User;
use App\Notifications\ServiceJobAssignedNotification;
use App\Services\ServiceJobWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ServiceJobController extends Controller
{
    public function index(Request $request, ServiceJobWorkflow $workflow): View
    {
        Gate::authorize('viewAny', ServiceJob::class);

        $jobs = ServiceJob::query()
            ->with(['customer:id,name,code', 'serviceType:id,name', 'technician:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->string('priority')))
            ->when($request->filled('technician'), fn ($query) => $query->where('assigned_to', $request->integer('technician')))
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request) {
                $search = '%'.$request->string('search').'%';
                $query->where('job_no', 'like', $search)
                    ->orWhere('complaint', 'like', $search)
                    ->orWhereHas('customer', fn ($query) => $query->where('name', 'like', $search));
            }))
            ->orderByRaw("CASE WHEN status IN ('completed','cancelled','customer_declined') THEN 2 ELSE 1 END")
            ->orderBy('scheduled_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('service-jobs.index', [
            'jobs' => $jobs,
            'technicians' => User::role('Technician')->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'statusLabels' => ServiceJobWorkflow::LABELS,
            'workflow' => $workflow,
            'metrics' => [
                'today' => ServiceJob::query()->whereDate('scheduled_at', today())->count(),
                'pending' => ServiceJob::query()->whereNotIn('status', ['completed', 'cancelled', 'customer_declined'])->count(),
                'in_progress' => ServiceJob::query()->whereIn('status', ['on_the_way', 'arrived', 'inspection_in_progress', 'service_in_progress'])->count(),
                'completed' => ServiceJob::query()->where('status', 'completed')->count(),
                'payment_pending' => ServiceJob::query()->whereIn('payment_status', ['pending', 'partial'])->where('status', '!=', 'cancelled')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', ServiceJob::class);

        return view('service-jobs.create', [
            'customers' => Customer::query()
                ->where('status', 'active')
                ->with(['branches:id,customer_id,name,address_line_1,address_line_2,area,city,state,country,pin_code,latitude,longitude,contact_no_1', 'equipments:id,customer_id,customer_branch_id,equipment_type,brand,model,serial_no,capacity,location'])
                ->orderBy('name')
                ->get(),
            'technicians' => User::role('Technician')->where('status', 'active')->orderBy('name')->get(),
            'serviceTypes' => ServiceType::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreServiceJobRequest $request, ServiceJobWorkflow $workflow): RedirectResponse
    {
        $serviceJob = DB::transaction(function () use ($request, $workflow): ServiceJob {
            $serviceJob = ServiceJob::create(array_merge($request->validated(), [
                'created_by' => $request->user()->id,
                'status' => 'assigned',
            ]));
            $serviceJob->update(['job_no' => 'SRV-'.str_pad((string) $serviceJob->id, 6, '0', STR_PAD_LEFT)]);
            $workflow->recordInitialStatus($serviceJob, $request->user());
            ActivityLog::create([
                'user_id' => $request->user()->id,
                'event' => 'service_job.created',
                'subject_type' => ServiceJob::class,
                'subject_id' => $serviceJob->id,
                'properties' => ['job_no' => $serviceJob->job_no, 'assigned_to' => $serviceJob->assigned_to],
                'ip_address' => $request->ip(),
            ]);

            return $serviceJob;
        });

        $serviceJob->technician->notify(new ServiceJobAssignedNotification($serviceJob->load('customer')));

        return redirect()->route('service-jobs.show', $serviceJob)->with('success', 'Service job created and assigned.');
    }

    public function show(ServiceJob $serviceJob, ServiceJobWorkflow $workflow): View
    {
        Gate::authorize('view', $serviceJob);

        $serviceJob->load([
            'customer', 'branch', 'equipment', 'serviceType', 'technician', 'creator',
            'statusHistory.changedBy', 'visits.technician', 'inspections.items.condition',
            'estimates.items', 'performedServices', 'materials', 'photos', 'signatures', 'payments', 'feedback',
        ]);

        $technicians = User::role('Technician')->where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return view('service-jobs.show', compact('serviceJob', 'workflow', 'technicians'));
    }

    public function update(Request $request, ServiceJob $serviceJob, ServiceJobWorkflow $workflow): RedirectResponse
    {
        abort_unless($request->user()->can('service_jobs.assign') || $request->user()->can('service_jobs.update'), 403);
        $validated = $request->validate([
            'assigned_to' => ['required', Rule::exists('users', 'id')->where(fn ($query) => $query->where('status', 'active'))],
            'scheduled_at' => ['required', 'date'],
            'priority' => ['required', Rule::in(['normal', 'high', 'very_high', 'emergency'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if (! User::role('Technician')->whereKey($validated['assigned_to'])->exists()) {
            throw ValidationException::withMessages(['assigned_to' => 'Select an active technician.']);
        }
        $wasReassigned = $serviceJob->assigned_to !== (int) $validated['assigned_to'];
        $wasRescheduled = ! $serviceJob->scheduled_at?->equalTo(Carbon::parse($validated['scheduled_at']));

        DB::transaction(function () use ($request, $serviceJob, $workflow, $validated, $wasReassigned, $wasRescheduled): void {
            $serviceJob->update($validated);
            if ($wasReassigned) {
                $serviceJob->statusHistory()->create([
                    'from_status' => $serviceJob->status,
                    'to_status' => $serviceJob->status,
                    'changed_by' => $request->user()->id,
                    'remark' => 'Reassigned to '.$serviceJob->technician->name.'.',
                    'changed_at' => now(),
                ]);
            }
            if ($wasRescheduled) {
                $serviceJob->visits()->create([
                    'technician_id' => $serviceJob->assigned_to,
                    'type' => 'admin',
                    'outcome' => 'rescheduled',
                    'scheduled_at' => $serviceJob->scheduled_at,
                    'occurred_at' => now(),
                    'reason' => 'Admin schedule update',
                ]);
                if (in_array($serviceJob->status, ['assigned', 'contacted', 'no_answer', 'confirmed'], true)) {
                    $workflow->transition($serviceJob, 'rescheduled', $request->user(), ['reason' => 'Admin schedule update']);
                }
            }
        });

        if ($wasReassigned) {
            $serviceJob->fresh()->technician->notify(new ServiceJobAssignedNotification($serviceJob->fresh()->load('customer')));
        }

        return back()->with('success', 'Service job assignment and schedule updated.');
    }
}
