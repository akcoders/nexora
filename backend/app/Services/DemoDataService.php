<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DemoDataRecord;
use App\Models\PayrollEntry;
use App\Models\ServiceJob;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoDataService
{
    /** @var list<class-string<Model>> */
    private const DELETE_ORDER = [
        ServiceJob::class,
        Task::class,
        Attendance::class,
        Customer::class,
        User::class,
        CustomerGroup::class,
    ];

    public function clear(): int
    {
        $this->ensureNoLiveReferences();

        return DB::transaction(function (): int {
            $deleted = 0;

            foreach (self::DELETE_ORDER as $modelClass) {
                $ids = DemoDataRecord::where('model_type', $modelClass)->pluck('model_id');
                if ($ids->isEmpty()) {
                    continue;
                }

                if ($modelClass === User::class) {
                    PayrollEntry::query()->whereIn('user_id', $ids)->delete();
                }

                $query = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
                    ? $modelClass::withTrashed()->whereKey($ids)
                    : $modelClass::query()->whereKey($ids);

                $deleted += in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
                    ? $query->forceDelete()
                    : $query->delete();
            }

            DemoDataRecord::query()->delete();
            Storage::disk('public')->deleteDirectory('demo');

            return $deleted;
        });
    }

    public function stats(): array
    {
        return [
            'scenarios' => DemoDataRecord::distinct('scenario_key')->count('scenario_key'),
            'customers' => DemoDataRecord::where('model_type', Customer::class)->count(),
            'service_jobs' => DemoDataRecord::where('model_type', ServiceJob::class)->count(),
            'tasks' => DemoDataRecord::where('model_type', Task::class)->count(),
            'technicians' => DemoDataRecord::where('model_type', User::class)->count(),
        ];
    }

    /**
     * Demo technicians and customers can be picked in normal dropdowns. Refuse to wipe them while
     * real (non-demo) service jobs or tasks still point at them, instead of failing mid-transaction.
     */
    private function ensureNoLiveReferences(): void
    {
        $demoUserIds = DemoDataRecord::where('model_type', User::class)->pluck('model_id');
        $demoCustomerIds = DemoDataRecord::where('model_type', Customer::class)->pluck('model_id');
        $demoJobIds = DemoDataRecord::where('model_type', ServiceJob::class)->pluck('model_id');
        $demoTaskIds = DemoDataRecord::where('model_type', Task::class)->pluck('model_id');

        $liveJobs = ServiceJob::query()->whereNotIn('id', $demoJobIds)
            ->where(fn ($query) => $query->whereIn('assigned_to', $demoUserIds)->orWhereIn('customer_id', $demoCustomerIds))
            ->count();
        $liveTasks = Task::query()->whereNotIn('id', $demoTaskIds)
            ->where(fn ($query) => $query->whereIn('assigned_to', $demoUserIds)->orWhereIn('customer_id', $demoCustomerIds))
            ->count();

        abort_if($liveJobs + $liveTasks > 0, 422, sprintf(
            'Demo data is still referenced by %d live service job(s) and %d live task(s). Reassign them to a real technician/customer before clearing demo data.',
            $liveJobs,
            $liveTasks,
        ));
    }
}
