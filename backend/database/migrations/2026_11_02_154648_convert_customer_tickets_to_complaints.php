<?php

use App\Services\ComplaintOptions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The old "customer ticket" tasks are replaced by the complaint register. Each live ticket is
 * copied into a complaint (keeping its customer, owner, priority and status) and the ticket row
 * is soft-deleted. Rolling back restores the tickets and removes the copied complaints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table): void {
            $table->foreignId('legacy_task_id')->nullable()->unique()->after('id')->constrained('tasks')->nullOnDelete();
        });

        $prefix = Str::upper((string) (DB::table('settings')->where('key', 'complaint_prefix')->value('value') ?? 'CCSPL'));
        $branch = Str::upper((string) (DB::table('settings')->where('key', 'complaint_branch_code')->value('value') ?? 'MUM'));
        $priorities = ['very_high' => 'critical', 'high' => 'high', 'normal' => 'medium'];

        DB::table('tasks')->where('task_type', 'ticket')->whereNull('deleted_at')->whereNotNull('customer_id')
            ->lazyById(200)
            ->each(function (object $ticket) use ($prefix, $branch, $priorities): void {
                $registeredAt = Carbon::parse($ticket->created_at);
                $financialYear = ComplaintOptions::financialYear($registeredAt);
                $sequence = (int) DB::table('complaints')->where('financial_year', $financialYear)->max('sequence') + 1;
                $priority = $priorities[$ticket->priority] ?? 'medium';
                $sla = ComplaintOptions::slaFor($priority);
                $customer = DB::table('customers')->where('id', $ticket->customer_id)->first(['contact_no_1', 'address_line_1', 'city']);
                $closed = $ticket->status === 'closed';
                $now = now();

                $complaintId = DB::table('complaints')->insertGetId([
                    'legacy_task_id' => $ticket->id,
                    'complaint_no' => implode('/', array_filter([$prefix, $branch, $financialYear, str_pad((string) $sequence, 3, '0', STR_PAD_LEFT)])),
                    'financial_year' => $financialYear,
                    'sequence' => $sequence,
                    'registered_at' => $registeredAt,
                    'customer_id' => $ticket->customer_id,
                    'address' => collect([$customer?->address_line_1, $customer?->city])->filter()->join(', ') ?: null,
                    'contact_no' => $customer?->contact_no_1,
                    'call_mode' => 'other',
                    'complaint_source' => 'customer',
                    'call_type' => 'breakdown',
                    'nature_of_complaint' => Str::limit($ticket->title, 120, ''),
                    'description' => $ticket->description,
                    'priority' => $priority,
                    'sla_response_hours' => $sla['response'],
                    'sla_resolution_hours' => $sla['resolution'],
                    'response_due_at' => $registeredAt->copy()->addHours($sla['response']),
                    'resolution_due_at' => $registeredAt->copy()->addHours($sla['resolution']),
                    'status' => $closed ? 'closed' : ($ticket->assigned_to ? 'assigned' : 'open'),
                    'assigned_to' => $ticket->assigned_to,
                    'closed_at' => $closed ? ($ticket->closed_at ?? $ticket->updated_at) : null,
                    'closed_by' => $closed ? $ticket->assigned_to : null,
                    'payment_status' => 'not_applicable',
                    'created_by' => $ticket->created_by,
                    'updated_by' => $ticket->created_by,
                    'created_at' => $ticket->created_at,
                    'updated_at' => $now,
                ]);

                $latestRemark = DB::table('task_actions')->where('task_id', $ticket->id)->orderByDesc('id')->value('remark');
                DB::table('complaint_activities')->insert([
                    'complaint_id' => $complaintId,
                    'user_id' => $ticket->created_by,
                    'type' => 'created',
                    'title' => 'Imported from customer ticket '.$ticket->task_no,
                    'status_to' => $closed ? 'closed' : 'open',
                    'remarks' => $latestRemark,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('tasks')->where('id', $ticket->id)->update(['deleted_at' => $now]);
            });
    }

    public function down(): void
    {
        $taskIds = DB::table('complaints')->whereNotNull('legacy_task_id')->pluck('legacy_task_id');
        DB::table('tasks')->whereIn('id', $taskIds)->update(['deleted_at' => null]);
        DB::table('complaints')->whereNotNull('legacy_task_id')->delete();

        Schema::table('complaints', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('legacy_task_id');
        });
    }
};
