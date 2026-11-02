<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Customer;
use App\Models\ServiceJob;
use App\Models\Task;
use App\Models\User;
use App\Support\RecordVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Top-bar search across complaints, customers, employees, service jobs and workflows.
 * Each group only searches records the signed-in user is allowed to open.
 */
class GlobalSearchController extends Controller
{
    private const LIMIT = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $term = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']])['q']);
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
        $user = $request->user();
        $groups = [];

        if ($user->canAny(['complaints.read', 'complaints.view_own'])) {
            $groups[] = ['label' => 'Complaints', 'items' => Complaint::query()->visibleTo($user)->with('customer:id,name')
                ->where(fn (Builder $query) => $query->where('complaint_no', 'like', $like)->orWhere('contact_no', 'like', $like)->orWhere('serial_no', 'like', $like)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', $like)))
                ->latest('registered_at')->limit(self::LIMIT)->get()
                ->map(fn (Complaint $complaint) => ['title' => $complaint->complaint_no.' · '.$complaint->customer?->name, 'subtitle' => $complaint->nature_of_complaint.' · '.$complaint->statusMeta()['label'], 'url' => route('complaints.show', $complaint), 'icon' => 'clipboard-list'])];
        }

        if ($user->canAny(['customers.read', 'customers.view_own'])) {
            $groups[] = ['label' => 'Customers', 'items' => RecordVisibility::scope(Customer::query(), $user, 'customers', false)
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('contact_no_1', 'like', $like)->orWhere('city', 'like', $like))
                ->orderBy('name')->limit(self::LIMIT)->get(['id', 'name', 'code', 'city'])
                ->map(fn (Customer $customer) => ['title' => $customer->name, 'subtitle' => collect([$customer->code, $customer->city])->filter()->join(' · '), 'url' => route('customers.show', $customer), 'icon' => 'building-2'])];
        }

        if ($user->can('employees.read')) {
            $groups[] = ['label' => 'Employees', 'items' => User::query()->whereHas('employeeProfile')
                ->where(fn (Builder $query) => $query->where('name', 'like', $like)->orWhere('employee_code', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like))
                ->orderBy('name')->limit(self::LIMIT)->get(['id', 'name', 'employee_code', 'designation'])
                ->map(fn (User $employee) => ['title' => $employee->name, 'subtitle' => collect([$employee->employee_code, $employee->designation])->filter()->join(' · '), 'url' => route('employees.show', $employee), 'icon' => 'contact-round'])];
        }

        if ($user->canAny(['service_jobs.read', 'service_jobs.view_own'])) {
            $groups[] = ['label' => 'Service jobs', 'items' => RecordVisibility::scope(ServiceJob::query(), $user, 'service_jobs')->with('customer:id,name')
                ->where(fn (Builder $query) => $query->where('job_no', 'like', $like)->orWhere('customer_phone', 'like', $like))
                ->latest()->limit(self::LIMIT)->get()
                ->map(fn (ServiceJob $job) => ['title' => ($job->job_no ?: 'Job #'.$job->id).' · '.$job->customer?->name, 'subtitle' => str($job->status)->headline()->toString(), 'url' => route('service-jobs.show', $job), 'icon' => 'wrench'])];
        }

        if ($user->canAny(['tasks.read', 'tasks.view_own'])) {
            $groups[] = ['label' => 'Internal workflows', 'items' => RecordVisibility::scope(Task::query(), $user, 'tasks')->workItems()
                ->where(fn (Builder $query) => $query->where('task_no', 'like', $like)->orWhere('title', 'like', $like))
                ->latest()->limit(self::LIMIT)->get(['id', 'task_no', 'title', 'status'])
                ->map(fn (Task $task) => ['title' => $task->task_no.' · '.$task->title, 'subtitle' => $task->isClosed() ? 'Completed' : 'Pending', 'url' => route('tasks.show', $task), 'icon' => 'git-branch'])];
        }

        return response()->json(['groups' => collect($groups)->filter(fn (array $group) => $group['items']->isNotEmpty())->values()]);
    }
}
