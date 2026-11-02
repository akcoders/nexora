<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\ComplaintVisit;
use App\Models\SavedReport;
use App\Models\ServiceJob;
use App\Models\Task;
use App\Models\User;
use App\Services\ComplaintOptions;
use App\Services\Reports\ReportDatasets;
use App\Support\RecordVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Operations dashboard: KPIs, charts and work lists for the selected period, plus the
 * user's pinned custom reports. Each user can hide widgets they do not need.
 */
class DashboardController extends Controller
{
    /** @var array<string, array{label: string, hint: string}> */
    public const WIDGETS = [
        'kpis' => ['label' => 'Key figures', 'hint' => 'Complaints, SLA, follow-ups, jobs and attendance'],
        'follow_ups' => ['label' => "Today's follow-ups", 'hint' => 'Pending complaints due for follow-up'],
        'complaint_trend' => ['label' => 'Complaint trend', 'hint' => 'Registered vs closed over the period'],
        'call_types' => ['label' => 'Calls by type', 'hint' => 'Breakdown, PM, installation, site visit'],
        'status_mix' => ['label' => 'Active complaints by status', 'hint' => 'Where open calls are stuck'],
        'pending_age' => ['label' => 'Pending age', 'hint' => 'Normal to critical age bands'],
        'sla_risk' => ['label' => 'SLA watch list', 'hint' => 'Breached or close to breaching'],
        'technician_load' => ['label' => 'Technician workload', 'hint' => 'Open visits per technician'],
        'top_customers' => ['label' => 'Top customers', 'hint' => 'Most complaints in the period'],
        'root_causes' => ['label' => 'Top root causes', 'hint' => 'From closed complaints'],
        'attendance' => ['label' => 'Attendance trend', 'hint' => 'Daily check-ins this week'],
        'workflows' => ['label' => 'My workflows', 'hint' => 'Recent internal work items'],
        'my_reports' => ['label' => 'My pinned reports', 'hint' => 'Custom reports pinned from the report builder'],
    ];

    /** @var array<string, string> */
    public const PERIODS = ['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'month' => 'This month', 'quarter' => 'This quarter', 'year' => 'This year'];

    public function index(Request $request): View
    {
        $user = $request->user();
        $period = array_key_exists($request->input('period'), self::PERIODS) ? $request->input('period') : '30d';
        [$from, $to] = $this->range($period);
        $hidden = (array) data_get($user->dashboard_preferences, 'hidden', []);

        $canComplaints = $user->canAny(['complaints.read', 'complaints.view_own']);
        $complaints = fn (): Builder => $canComplaints ? Complaint::query()->visibleTo($user) : Complaint::query()->whereRaw('1 = 0');
        $tasks = $user->canAny(['tasks.read', 'tasks.view_own']) ? RecordVisibility::scope(Task::query(), $user, 'tasks')->workItems() : Task::query()->whereRaw('1 = 0');
        $canAttendance = $user->canAny(['attendance.read', 'attendance.approve', 'employees.read']);
        $technicianCount = $canAttendance ? User::active()->whereHas('roles', fn (Builder $query) => $query->where('name', 'Technician'))->count() : 0;
        $presentToday = $canAttendance ? Attendance::whereDate('attendance_date', today())->count() : 0;

        $closedInPeriod = $complaints()->where('status', 'closed')->whereBetween('closed_at', [$from, $to])->get(['registered_at', 'closed_at', 'resolution_due_at']);
        $avgResolution = $closedInPeriod->isEmpty() ? null : round($closedInPeriod->avg(fn (Complaint $complaint) => $complaint->registered_at->diffInHours($complaint->closed_at)) / 24, 1);
        $slaMet = $closedInPeriod->isEmpty() ? null : round($closedInPeriod->filter(fn (Complaint $complaint) => ! $complaint->resolution_due_at || $complaint->closed_at->lte($complaint->resolution_due_at))->count() / $closedInPeriod->count() * 100);

        $pending = $complaints()->whereIn('status', ['pending', 'under_observation'])->get(['id', 'pending_since', 'registered_at']);
        $buckets = collect(ComplaintOptions::AGE_BUCKETS)->mapWithKeys(fn (array $bucket) => [$bucket['key'] => $bucket + ['count' => 0]])->all();
        foreach ($pending as $complaint) {
            $buckets[$complaint->pendingBucket()['key']]['count']++;
        }

        $statusCounts = $complaints()->active()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');
        $typeCounts = $complaints()->whereBetween('registered_at', [$from, $to])->selectRaw('call_type, COUNT(*) as total')->groupBy('call_type')->pluck('total', 'call_type');

        return view('dashboard', [
            'period' => $period,
            'periods' => self::PERIODS,
            'from' => $from,
            'to' => $to,
            'widgets' => self::WIDGETS,
            'hidden' => $hidden,
            'canComplaints' => $canComplaints,
            'canAttendance' => $canAttendance,
            'metrics' => [
                'active' => $complaints()->active()->count(),
                'registered' => $complaints()->whereBetween('registered_at', [$from, $to])->count(),
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'breached' => $complaints()->slaBreached()->count(),
                'followUps' => $complaints()->followUpDue()->count(),
                'closed' => $closedInPeriod->count(),
                'avgResolution' => $avgResolution,
                'slaMet' => $slaMet,
                'serviceJobsToday' => $user->canAny(['service_jobs.read', 'service_jobs.view_own']) ? RecordVisibility::scope(ServiceJob::query(), $user, 'service_jobs')->whereDate('scheduled_at', today())->count() : null,
                'technicians' => $technicianCount,
                'present' => $presentToday,
                'pendingTasks' => (clone $tasks)->open()->count(),
                'overdueTasks' => (clone $tasks)->overdue()->count(),
            ],
            'followUps' => $complaints()->followUpDue()->with(['customer:id,name', 'responsibleUser:id,name'])->orderBy('follow_up_date')->limit(12)->get(),
            'slaRisk' => $complaints()->active()->whereNotNull('resolution_due_at')->where('resolution_due_at', '<', now()->addHours(12))->with(['customer:id,name', 'technician:id,name'])->orderBy('resolution_due_at')->limit(8)->get(),
            'buckets' => $buckets,
            'pendingTotal' => $pending->count(),
            'recentTasks' => (clone $tasks)->with(['assignee:id,name'])->latest()->limit(6)->get(),
            'pinnedReports' => SavedReport::query()->where('user_id', $user->id)->where('pinned', true)->orderBy('sort_order')->latest('updated_at')->get()
                ->filter(fn (SavedReport $report) => isset(ReportDatasets::availableFor($user)[$report->dataset]))->values(),
            'charts' => [
                'trend' => $canComplaints ? $this->trend($complaints, $from, $to, $period) : null,
                'callTypes' => ['labels' => array_map(fn ($key) => ComplaintOptions::CALL_TYPES[$key]['label'] ?? $key, $typeCounts->keys()->all()), 'series' => $typeCounts->values()->map(fn ($value) => (int) $value)->all()],
                'status' => ['labels' => $statusCounts->keys()->map(fn ($status) => ComplaintOptions::status($status)['label'])->all(), 'series' => $statusCounts->values()->map(fn ($value) => (int) $value)->all(), 'keys' => $statusCounts->keys()->all()],
                'technicians' => $canComplaints ? $this->technicianLoad($user) : ['labels' => [], 'series' => []],
                'customers' => $this->topBy($complaints()->whereBetween('registered_at', [$from, $to])->join('customers', 'customers.id', '=', 'complaints.customer_id'), 'customers.name'),
                'rootCauses' => $this->topBy($complaints()->where('status', 'closed')->whereBetween('closed_at', [$from, $to])->whereNotNull('root_cause'), 'complaints.root_cause'),
                'attendance' => $canAttendance ? $this->attendanceTrend() : null,
            ],
        ]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hidden' => ['present', 'array'],
            'hidden.*' => ['string', Rule::in(array_keys(self::WIDGETS))],
        ]);
        $request->user()->forceFill(['dashboard_preferences' => ['hidden' => array_values(array_unique($validated['hidden']))]])->save();

        return response()->json(['message' => 'Dashboard layout saved.']);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(string $period): array
    {
        return match ($period) {
            'today' => [today(), now()->endOfDay()],
            '7d' => [today()->subDays(6), now()->endOfDay()],
            'month' => [today()->startOfMonth(), now()->endOfDay()],
            'quarter' => [today()->startOfQuarter(), now()->endOfDay()],
            'year' => [today()->startOfYear(), now()->endOfDay()],
            default => [today()->subDays(29), now()->endOfDay()],
        };
    }

    /** @return array{labels: list<string>, registered: list<int>, closed: list<int>} */
    private function trend(\Closure $complaints, Carbon $from, Carbon $to, string $period): array
    {
        $monthly = in_array($period, ['year'], true);
        $format = $monthly ? 'Y-m' : 'Y-m-d';
        $registered = $complaints()->whereBetween('registered_at', [$from, $to])->get(['registered_at'])->countBy(fn (Complaint $complaint) => $complaint->registered_at->format($format));
        $closed = $complaints()->whereNotNull('closed_at')->where('status', 'closed')->whereBetween('closed_at', [$from, $to])->get(['closed_at'])->countBy(fn (Complaint $complaint) => $complaint->closed_at->format($format));

        $labels = [];
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $labels[$cursor->format($format)] = $monthly ? $cursor->format('M Y') : $cursor->format('d M');
            $monthly ? $cursor->addMonthNoOverflow()->startOfMonth() : $cursor->addDay();
        }

        return [
            'labels' => array_values($labels),
            'registered' => array_map(fn ($key) => (int) ($registered[$key] ?? 0), array_keys($labels)),
            'closed' => array_map(fn ($key) => (int) ($closed[$key] ?? 0), array_keys($labels)),
        ];
    }

    /** @return array{labels: list<string>, series: list<int>} */
    private function technicianLoad(User $user): array
    {
        $rows = ComplaintVisit::query()
            ->whereNotIn('complaint_visits.status', ['completed', 'cancelled'])
            ->whereHas('complaint', fn (Builder $complaints) => $complaints->visibleTo($user))
            ->join('users', 'users.id', '=', 'complaint_visits.technician_id')
            ->selectRaw('users.name as label, COUNT(*) as total')
            ->groupBy('users.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return ['labels' => $rows->pluck('label')->all(), 'series' => $rows->pluck('total')->map(fn ($value) => (int) $value)->all()];
    }

    /** @return array{labels: list<string>, series: list<int>} */
    private function topBy(Builder $query, string $column): array
    {
        $rows = $query->selectRaw("{$column} as label, COUNT(*) as total")->groupBy($column)->orderByDesc('total')->limit(8)->get();

        return ['labels' => $rows->pluck('label')->all(), 'series' => $rows->pluck('total')->map(fn ($value) => (int) $value)->all()];
    }

    /** @return array{labels: list<string>, series: list<int>} */
    private function attendanceTrend(): array
    {
        $days = collect(range(6, 0))->map(fn (int $daysAgo) => today()->subDays($daysAgo));
        $counts = Attendance::query()->whereBetween('attendance_date', [$days->first()->toDateString(), $days->last()->toDateString()])
            ->selectRaw('attendance_date as day, COUNT(*) as total')->groupBy('attendance_date')->pluck('total', 'day')
            ->mapWithKeys(fn ($total, $day) => [Carbon::parse($day)->toDateString() => (int) $total]);

        return ['labels' => $days->map->format('D d')->all(), 'series' => $days->map(fn (Carbon $day) => $counts[$day->toDateString()] ?? 0)->all()];
    }
}
