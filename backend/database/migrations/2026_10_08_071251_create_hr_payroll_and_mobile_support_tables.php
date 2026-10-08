<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('salutation', 20)->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('local_script_name')->nullable();
            $table->string('profile_picture_path')->nullable();
            $table->string('gender', 30)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('marital_status', 30)->nullable();
            $table->date('anniversary_date')->nullable();
            $table->string('blood_group', 10)->nullable();
            $table->string('nationality', 80)->default('Indian');
            $table->string('religion', 80)->nullable();
            $table->json('languages')->nullable();
            $table->json('identity_documents')->nullable();
            $table->string('personal_email')->nullable();
            $table->json('current_address')->nullable();
            $table->json('permanent_address')->nullable();
            $table->json('company_accommodation_address')->nullable();
            $table->json('emergency_family')->nullable();
            $table->date('date_of_joining')->nullable()->index();
            $table->string('employment_type', 50)->nullable();
            $table->string('employee_category', 80)->nullable();
            $table->string('job_grade', 30)->nullable();
            $table->foreignId('reporting_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('functional_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hr_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('employment_details')->nullable();
            $table->json('verification')->nullable();
            $table->string('salary_currency', 10)->default('INR');
            $table->string('pay_frequency', 20)->default('monthly');
            $table->string('pay_group', 50)->nullable();
            $table->decimal('basic_salary', 14, 2)->default(0);
            $table->decimal('hra', 14, 2)->default(0);
            $table->decimal('transport_allowance', 14, 2)->default(0);
            $table->decimal('fuel_allowance', 14, 2)->default(0);
            $table->decimal('other_allowance', 14, 2)->default(0);
            $table->decimal('gross_monthly_salary', 14, 2)->default(0);
            $table->json('compensation')->nullable();
            $table->json('bank_details')->nullable();
            $table->json('benefits_allowances')->nullable();
            $table->json('education_skills')->nullable();
            $table->json('safety')->nullable();
            $table->json('performance_career')->nullable();
            $table->json('assets_access')->nullable();
            $table->json('engagement_wellbeing')->nullable();
            $table->json('exit_separation')->nullable();
            $table->json('documents')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('holiday_date')->unique();
            $table->string('type', 30)->default('company');
            $table->boolean('optional')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->decimal('annual_quota', 6, 2)->default(0);
            $table->string('color', 20)->default('primary');
            $table->boolean('paid')->default(true);
            $table->boolean('requires_document')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('allocated', 6, 2)->default(0);
            $table->decimal('used', 6, 2)->default(0);
            $table->decimal('pending', 6, 2)->default(0);
            $table->timestamps();
            $table->unique(['user_id', 'leave_type_id', 'year'], 'leave_balance_user_type_year_unique');
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('total_days', 6, 2);
            $table->text('reason');
            $table->string('document_path')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_remark')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'from_date', 'to_date'], 'leave_request_user_period_index');
        });

        Schema::create('expense_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_no', 30)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('expense_date')->index();
            $table->string('category', 80);
            $table->decimal('amount', 14, 2);
            $table->text('description');
            $table->string('receipt_path')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_remark')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_month', 7)->unique();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('generated_at');
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('working_days', 6, 2)->default(0);
            $table->decimal('present_days', 6, 2)->default(0);
            $table->decimal('half_days', 6, 2)->default(0);
            $table->decimal('paid_leave_days', 6, 2)->default(0);
            $table->decimal('unpaid_days', 6, 2)->default(0);
            $table->decimal('payable_days', 6, 2)->default(0);
            $table->json('earnings');
            $table->json('deductions');
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('deduction_amount', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['payroll_run_id', 'user_id'], 'payroll_entry_run_user_unique');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->foreignId('checkout_premises_id')->nullable()->after('premises_id')->constrained('premises')->nullOnDelete();
            $table->unsignedInteger('checkout_distance_meters')->nullable()->after('distance_meters');
            $table->boolean('checkout_inside_premises')->nullable()->after('inside_premises');
            $table->string('checkout_selfie_path')->nullable()->after('selfie_path');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checkout_premises_id');
            $table->dropColumn(['checkout_distance_meters', 'checkout_inside_premises', 'checkout_selfie_path']);
        });
        Schema::dropIfExists('payroll_entries');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('expense_vouchers');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('employee_profiles');
    }
};
