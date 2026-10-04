<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\DemoDataRecord;
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
        return DB::transaction(function (): int {
            $deleted = 0;

            foreach (self::DELETE_ORDER as $modelClass) {
                $ids = DemoDataRecord::where('model_type', $modelClass)->pluck('model_id');
                if ($ids->isEmpty()) {
                    continue;
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
}
