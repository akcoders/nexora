<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('tasks')->where('task_type', 'job')->update(['task_type' => 'ticket']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Customer tickets cannot be safely distinguished from tickets created after this migration.
    }
};
