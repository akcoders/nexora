<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable();
            $table->string('employee_code', 30)->nullable()->unique();
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            $table->string('status', 20)->default('active')->index();
        });

        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->foreignId('parent_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('customer_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->json('types');
            $table->json('segments')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 20)->default('active');
            $table->string('branch_type', 20)->default('single');
            $table->string('classification', 30)->nullable();
            $table->json('sources')->nullable();
            $table->text('business_potential')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('landmark')->nullable();
            $table->string('area')->nullable();
            $table->string('city')->nullable()->index();
            $table->string('district')->nullable();
            $table->string('state')->nullable();
            $table->string('pin_code', 6)->nullable();
            $table->string('country')->default('India');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('contact_no_1', 20)->nullable();
            $table->string('contact_no_2', 20)->nullable();
            $table->string('email_1')->nullable();
            $table->string('email_2')->nullable();
            $table->text('site_access_instructions')->nullable();
            $table->text('billing_address')->nullable();
            $table->text('remarks')->nullable();
            $table->string('gstin', 15)->nullable();
            $table->string('pan', 10)->nullable();
            $table->string('gst_registration_type', 20)->nullable();
            $table->string('tan')->nullable();
            $table->decimal('tds_percent', 5, 2)->nullable();
            $table->string('udyam_no')->nullable();
            $table->string('msme_type', 20)->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sales_person_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['name', 'city']);
        });

        Schema::create('customer_drafts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->json('data')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });

        Schema::create('customer_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pin_code', 6)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('gstin', 15)->nullable();
            $table->text('billing_address')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('department')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('whatsapp', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('mother_tongue', 50)->nullable();
            $table->date('dob')->nullable();
            $table->date('anniversary')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_service')->default(false);
            $table->boolean('is_billing')->default(false);
            $table->boolean('is_escalation')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('customer_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('name');
            $table->string('path');
            $table->date('expires_at')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_credit_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->unsignedSmallInteger('credit_days')->default(0);
            $table->string('payment_mode', 40)->nullable();
            $table->boolean('advance_required')->default(false);
            $table->boolean('tds_applicable')->default(false);
            $table->boolean('retention_applicable')->default(false);
            $table->decimal('retention_percent', 5, 2)->default(0);
            $table->string('billing_cycle', 40)->nullable();
            $table->string('invoice_submission_method', 40)->nullable();
            $table->boolean('po_mandatory')->default(false);
            $table->boolean('eway_bill_applicable')->default(false);
            $table->text('default_payment_terms')->nullable();
            $table->decimal('default_discount_percent', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('service_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('frequency')->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->nullable();
            $table->string('required_skill')->nullable();
            $table->text('safety_notes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_checklist_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('response_type', 30)->default('checkbox');
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_checklist_id')->nullable()->constrained()->nullOnDelete();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_no')->nullable();
            $table->json('specifications')->nullable();
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('premises', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meters')->default(200);
            $table->time('shift_start')->nullable();
            $table->time('shift_end')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('premises_user', function (Blueprint $table) {
            $table->foreignId('premises_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['premises_id', 'user_id']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('premises_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date')->index();
            $table->dateTime('checked_in_at');
            $table->dateTime('checked_out_at')->nullable();
            $table->decimal('check_in_latitude', 10, 7);
            $table->decimal('check_in_longitude', 10, 7);
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->unsignedInteger('distance_meters')->nullable();
            $table->boolean('inside_premises')->default(false);
            $table->string('selfie_path');
            $table->text('remark')->nullable();
            $table->string('status', 20)->default('pending');
            $table->boolean('auto_checked_out')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'attendance_date']);
        });

        Schema::create('attendance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision', 20);
            $table->text('remark')->nullable();
            $table->timestamps();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_no', 30)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category', 60)->nullable();
            $table->string('priority', 20)->default('normal');
            $table->dateTime('due_at')->nullable()->index();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('observer');
            $table->timestamps();
            $table->unique(['task_id', 'user_id', 'role']);
        });

        Schema::create('task_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->text('remark');
            $table->json('attachments')->nullable();
            $table->timestamps();
        });

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('token')->unique();
            $table->string('platform', 20)->default('android');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 80);
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('device_tokens');
        Schema::dropIfExists('task_actions');
        Schema::dropIfExists('task_members');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('attendance_reviews');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('premises_user');
        Schema::dropIfExists('premises');
        Schema::dropIfExists('products');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('service_checklists');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('customer_credit_terms');
        Schema::dropIfExists('customer_documents');
        Schema::dropIfExists('customer_contacts');
        Schema::dropIfExists('customer_branches');
        Schema::dropIfExists('customer_drafts');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('customer_groups');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'employee_code', 'department', 'designation', 'status']);
        });
    }
};
