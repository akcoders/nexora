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
        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_no', 30)->nullable()->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_equipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('origin', 30)->default('manual');
            $table->string('customer_phone', 20);
            $table->string('alternate_phone', 20)->nullable();
            $table->text('service_address');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('complaint');
            $table->string('priority', 20)->default('normal');
            $table->date('preferred_visit_date')->nullable();
            $table->time('preferred_visit_time')->nullable();
            $table->dateTime('scheduled_at')->nullable();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->string('status', 40)->default('assigned');
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('journey_started_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('inspection_completed_at')->nullable();
            $table->dateTime('service_started_at')->nullable();
            $table->dateTime('service_completed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->decimal('final_amount', 14, 2)->default(0);
            $table->string('payment_status', 30)->default('pending');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['assigned_to', 'status', 'scheduled_at']);
            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('service_job_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->text('remark')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->dateTime('changed_at');
            $table->timestamps();
            $table->index(['service_job_id', 'changed_at']);
        });

        Schema::create('service_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20)->default('contact');
            $table->string('outcome', 40);
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('occurred_at');
            $table->string('reason')->nullable();
            $table->text('remark')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->string('arrival_method', 20)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['service_job_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_visits');
        Schema::dropIfExists('service_job_status_histories');
        Schema::dropIfExists('service_jobs');
    }
};
