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
        Schema::create('service_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('status', 30)->default('pending_approval');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('final_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('approval_signature_path')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->text('decision_remark')->nullable();
            $table->timestamps();
            $table->unique(['service_job_id', 'version']);
        });

        Schema::create('service_estimate_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_estimate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_catalog_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 30)->default('service');
            $table->decimal('unit_rate', 12, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('line_subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('service_job_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_catalog_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_rate', 12, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->text('remark')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('performed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('service_job_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_inventory')->default(false);
            $table->string('name');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 30);
            $table->decimal('unit_rate', 12, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->text('remark')->nullable();
            $table->string('photo_path')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('service_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('signer_name');
            $table->string('path');
            $table->dateTime('signed_at');
            $table->timestamps();
            $table->unique(['service_job_id', 'type']);
        });

        Schema::create('service_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method', 30);
            $table->string('transaction_reference')->nullable();
            $table->string('status', 20)->default('received');
            $table->string('proof_path')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('service_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_job_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('token')->unique();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('comments')->nullable();
            $table->boolean('issue_still_exists')->default(false);
            $table->boolean('request_callback')->default(false);
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_feedback');
        Schema::dropIfExists('service_payments');
        Schema::dropIfExists('service_signatures');
        Schema::dropIfExists('service_job_materials');
        Schema::dropIfExists('service_job_services');
        Schema::dropIfExists('service_estimate_items');
        Schema::dropIfExists('service_estimates');
    }
};
