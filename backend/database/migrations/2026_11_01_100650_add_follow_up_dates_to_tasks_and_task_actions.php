<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dateTime('next_follow_up_at')->nullable();
            $table->dateTime('revised_due_at')->nullable();
        });
        Schema::table('task_actions', function (Blueprint $table) {
            $table->dateTime('next_follow_up_at')->nullable();
            $table->dateTime('revised_due_at')->nullable()->index();
            $table->text('delay_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('task_actions', function (Blueprint $table) {
            $table->dropIndex(['revised_due_at']);
            $table->dropColumn(['next_follow_up_at', 'revised_due_at', 'delay_reason']);
        });
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['next_follow_up_at', 'revised_due_at']);
        });
    }
};
