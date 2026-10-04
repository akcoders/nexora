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
    }

    public function down(): void
    {
        Schema::table('attendances', fn (Blueprint $table) => $table->dropColumn('day_status'));
        Schema::table('tasks', fn (Blueprint $table) => $table->dropColumn('task_type'));
    }
};
