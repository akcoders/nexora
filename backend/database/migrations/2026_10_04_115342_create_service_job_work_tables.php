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
        Schema::create('service_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('service_checklist_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phase', 20)->default('pre');
            $table->string('status', 20)->default('in_progress');
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['service_job_id', 'phase']);
        });

        Schema::create('service_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_inspection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inspection_condition_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('response_value')->nullable();
            $table->text('remark')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['service_inspection_id', 'checklist_item_id']);
        });

        Schema::create('service_job_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_inspection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_inspection_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 20);
            $table->string('path');
            $table->string('caption')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['service_job_id', 'category']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_job_photos');
        Schema::dropIfExists('service_inspection_items');
        Schema::dropIfExists('service_inspections');
    }
};
