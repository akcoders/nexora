<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->boolean('attachment_required_on_completion')->default(false);
        });

        Schema::table('task_actions', function (Blueprint $table) {
            $table->foreignId('related_task_id')->nullable()->constrained('tasks')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_task_id');
            $table->dropColumn('attachment_required_on_completion');
        });

        Schema::table('task_actions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('related_task_id');
        });
    }
};
