<?php

use App\Services\ComplaintOptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table): void {
            $table->id();
            $table->string('complaint_no', 60)->unique();
            $table->string('financial_year', 5);
            $table->unsignedInteger('sequence');
            $table->dateTime('registered_at')->index();

            // Customer, site and caller
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('site_location')->nullable();
            $table->text('address')->nullable();
            $table->string('contact_person', 120)->nullable();
            $table->string('contact_no', 20)->nullable();
            $table->string('alternate_no', 20)->nullable();
            $table->string('complaint_given_by', 120)->nullable();
            $table->foreignId('call_taken_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('call_mode', 30);
            $table->string('complaint_source', 30);
            $table->string('call_type', 30)->index();

            // Complaint and SLA
            $table->string('nature_of_complaint', 120);
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('medium');
            $table->unsignedSmallInteger('sla_response_hours');
            $table->unsignedSmallInteger('sla_resolution_hours');
            $table->dateTime('response_due_at')->nullable();
            $table->dateTime('resolution_due_at')->nullable();
            $table->dateTime('first_response_at')->nullable();
            $table->string('unit_location')->nullable();

            // Equipment snapshot (auto-filled from the customer's equipment register)
            $table->foreignId('customer_equipment_id')->nullable()->constrained('customer_equipments')->nullOnDelete();
            $table->string('equipment_tag', 60)->nullable();
            $table->string('equipment_type', 30)->nullable();
            $table->string('equipment_criticality', 20)->nullable();
            $table->string('make', 80)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('capacity', 60)->nullable();
            $table->string('serial_no', 120)->nullable();
            $table->string('refrigerant', 20)->nullable();
            $table->string('compressor_type', 30)->nullable();
            $table->string('technology', 30)->nullable();
            $table->string('power_supply', 30)->nullable();
            $table->date('installation_date')->nullable();
            $table->date('last_service_date')->nullable();
            $table->date('last_pm_date')->nullable();
            $table->string('service_category', 30)->nullable();
            $table->string('contract_no', 60)->nullable();
            $table->date('contract_expiry')->nullable();

            // Workflow
            $table->string('status', 30)->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // Current pending snapshot (history lives in complaint_activities)
            $table->string('pending_group', 30)->nullable();
            $table->string('pending_reason', 120)->nullable();
            $table->string('pending_sub_reason', 120)->nullable();
            $table->string('pending_owner', 30)->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('next_action')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->date('expected_material_date')->nullable();
            $table->date('target_date')->nullable();
            $table->string('delay_reason', 40)->nullable();
            $table->string('escalation_point', 120)->nullable();
            $table->dateTime('pending_since')->nullable();

            // Commercial
            $table->decimal('visit_charges', 12, 2)->default(0);
            $table->decimal('labour_charges', 12, 2)->default(0);
            $table->decimal('parts_charges', 12, 2)->default(0);
            $table->decimal('travel_charges', 12, 2)->default(0);
            $table->decimal('other_charges', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('gst_percent', 5, 2)->default(18);
            $table->decimal('gst_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('customer_po_no', 60)->nullable();
            $table->string('quotation_no', 60)->nullable();
            $table->string('invoice_no', 60)->nullable();
            $table->string('payment_status', 20)->default('not_applicable');

            // Closure
            $table->boolean('fsr_checked')->default(false);
            $table->foreignId('fsr_checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('corrective_action')->nullable();
            $table->text('manager_remarks')->nullable();
            $table->boolean('happy_call_required')->default(true);
            $table->dateTime('happy_call_at')->nullable();
            $table->foreignId('happy_call_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('customer_feedback')->nullable();
            $table->unsignedTinyInteger('customer_rating')->nullable();
            $table->unsignedTinyInteger('team_rating')->nullable();
            $table->boolean('callback_required')->default(false);
            $table->date('callback_date')->nullable();
            $table->string('root_cause', 120)->nullable();
            $table->string('complaint_category', 120)->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closure_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['financial_year', 'sequence']);
            $table->index(['status', 'call_type']);
            $table->index(['status', 'follow_up_date']);
            $table->index(['customer_id', 'registered_at']);
        });

        Schema::create('complaint_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('visit_no');
            $table->string('status', 20)->default('scheduled');
            $table->string('reason_for_visit')->nullable();
            $table->foreignId('technician_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assistant_one_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assistant_two_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('assigned_at');
            $table->string('assignment_method', 20)->nullable();
            $table->dateTime('appointment_at')->nullable();
            $table->text('special_instructions')->nullable();

            $table->dateTime('accepted_at')->nullable();
            $table->dateTime('en_route_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->unsignedSmallInteger('travel_minutes')->nullable();
            $table->decimal('labour_hours', 6, 2)->nullable();

            $table->json('checks')->nullable();
            $table->text('abnormality_found')->nullable();
            $table->json('diagnosis')->nullable();
            $table->text('observation')->nullable();
            $table->text('initial_diagnosis')->nullable();
            $table->string('fault_category', 120)->nullable();
            $table->string('fault_code', 60)->nullable();
            $table->string('root_cause', 120)->nullable();
            $table->text('problem_diagnosis')->nullable();
            $table->text('work_performed')->nullable();
            $table->string('final_result', 20)->nullable();
            $table->string('outcome', 20)->nullable();

            $table->string('customer_signed_name', 120)->nullable();
            $table->string('customer_signature_path')->nullable();
            $table->text('customer_remarks')->nullable();
            $table->unsignedTinyInteger('customer_rating')->nullable();
            $table->text('technician_remarks')->nullable();
            $table->text('supervisor_remarks')->nullable();
            $table->text('store_remarks')->nullable();
            $table->json('attachments')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['complaint_id', 'visit_no']);
            $table->index(['technician_id', 'status', 'appointment_at']);
        });

        Schema::create('complaint_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('complaint_visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 20);
            $table->string('part_code', 60)->nullable();
            $table->string('part_name', 160);
            $table->string('category', 80)->nullable();
            $table->string('make', 80)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('uom', 20)->default('nos');
            $table->string('source', 20)->nullable();
            $table->string('part_type', 20)->nullable();
            $table->string('part_warranty', 60)->nullable();
            $table->string('stock_location', 80)->nullable();
            $table->boolean('available_in_stock')->default(false);
            $table->decimal('estimated_cost', 12, 2)->default(0);
            $table->decimal('billable_rate', 12, 2)->default(0);
            $table->boolean('oem_warranty')->default(false);
            $table->boolean('old_part_returned')->default(false);
            $table->string('old_part_no', 80)->nullable();
            $table->string('additional_parts')->nullable();
            $table->string('recommended_action')->nullable();
            $table->boolean('customer_approval_required')->default(false);
            $table->boolean('quotation_given')->default(false);
            $table->string('customer_approval_status', 20)->default('not_required');
            $table->boolean('manager_approval_required')->default(false);
            $table->boolean('higher_approval_required')->default(false);
            $table->string('approval_status', 20)->default('not_required');
            $table->string('purchase_status', 20)->default('not_required');
            $table->unsignedSmallInteger('estimated_days')->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['complaint_id', 'kind']);
        });

        Schema::create('complaint_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('complaint_visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->string('title');
            $table->string('status_from', 30)->nullable();
            $table->string('status_to', 30)->nullable();
            $table->text('remarks')->nullable();
            $table->json('details')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->timestamps();
            $table->index(['complaint_id', 'created_at']);
        });

        $this->seedPermissions();
        $this->seedMasters();
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_activities');
        Schema::dropIfExists('complaint_parts');
        Schema::dropIfExists('complaint_visits');
        Schema::dropIfExists('complaints');
        Permission::where('name', 'like', 'complaints.%')->delete();
        DB::table('service_master_options')->whereIn('type', array_keys(ComplaintOptions::masterGroups()))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedPermissions(): void
    {
        $actions = ['create', 'read', 'update', 'delete', 'approve', 'assign', 'export', 'view_own'];
        foreach ($actions as $action) {
            Permission::findOrCreate("complaints.{$action}", 'web');
        }
        $all = array_map(fn (string $action) => "complaints.{$action}", $actions);

        Role::where('guard_name', 'web')->whereIn('name', ['Super Admin', 'Admin'])->get()->each->givePermissionTo($all);
        Role::where('guard_name', 'web')->where('name', 'Manager')->first()?->givePermissionTo(['complaints.create', 'complaints.read', 'complaints.update', 'complaints.assign', 'complaints.approve', 'complaints.export']);
        Role::where('guard_name', 'web')->where('name', 'Technician')->first()?->givePermissionTo(['complaints.view_own', 'complaints.update']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedMasters(): void
    {
        $now = now();
        $rows = [];
        $add = function (string $type, string $label, int $order, array $metadata = []) use (&$rows, $now): string {
            $code = Str::limit(Str::slug($label, '_'), 50, '');
            $rows[] = ['type' => $type, 'code' => $code, 'label' => $label, 'sort_order' => $order, 'active' => true, 'metadata' => $metadata ? json_encode($metadata) : null, 'created_at' => $now, 'updated_at' => $now];

            return $code;
        };

        foreach (ComplaintOptions::DEFAULT_NATURES as $order => $label) {
            $add('complaint_nature', $label, $order);
        }
        foreach (ComplaintOptions::DEFAULT_ROOT_CAUSES as $order => $label) {
            $add('root_cause', $label, $order);
        }
        foreach (ComplaintOptions::DEFAULT_FAULT_CATEGORIES as $order => $label) {
            $add('fault_category', $label, $order);
        }
        $order = 0;
        foreach (ComplaintOptions::DEFAULT_PENDING_REASONS as $group => $reasons) {
            foreach ($reasons as $label) {
                $code = $add('pending_reason', $label, $order++, ['group' => $group]);
                foreach (ComplaintOptions::DEFAULT_PENDING_SUB_REASONS[$label] ?? [] as $subOrder => $subLabel) {
                    $add('pending_sub_reason', $subLabel, $subOrder, ['reason' => $code]);
                }
            }
        }

        DB::table('service_master_options')->insertOrIgnore($rows);
    }
};
