<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->string('day_status', 20)->default('full_day')->after('status')->index();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('task_type', 20)->default('job')->after('task_no')->index();
        });

        DB::table('tasks')->whereNull('customer_id')->update(['task_type' => 'workflow']);

        $now = now();
        foreach ([
            'attendance_check_in_time' => '09:00',
            'attendance_check_in_grace_minutes' => '15',
            'attendance_checkout_time' => '18:00',
            'attendance_checkout_grace_minutes' => '15',
        ] as $key => $value) {
            DB::table('settings')->insertOrIgnore([
                'key' => $key,
                'value' => $value,
                'group' => 'operations',
                'type' => str_contains($key, 'minutes') ? 'number' : 'time',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'attendance_check_in_time',
            'attendance_check_in_grace_minutes',
            'attendance_checkout_time',
            'attendance_checkout_grace_minutes',
        ])->delete();
        Schema::table('attendances', fn (Blueprint $table) => $table->dropColumn('day_status'));
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('task_type'));
    }
};
