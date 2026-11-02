<?php

namespace App\Services\Reports;

use App\Models\Attendance;
use App\Models\Complaint;
use App\Models\ComplaintPart;
use App\Models\ComplaintVisit;
use App\Models\Customer;
use App\Models\ExpenseVoucher;
use App\Models\LeaveRequest;
use App\Models\PayrollEntry;
use App\Models\ServiceJob;
use App\Models\Task;
use App\Models\User;
use App\Services\ComplaintOptions;
use App\Support\RecordVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Whitelisted data sources for the custom report builder. Every field maps to a fixed SQL
 * expression written here, so user input only ever chooses between these keys.
 *
 * Field shape: label, type (string|number|date|enum|bool), expr, optional join, optional options.
 */
class ReportDatasets
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $now = 'CURRENT_TIMESTAMP';
        $ageDays = self::dateDiffDays('complaints.registered_at', "COALESCE(complaints.closed_at, {$now})");
        $statuses = ComplaintOptions::statusOptions();

        return [
            'complaints' => [
                'label' => 'Complaints', 'icon' => 'clipboard-list', 'description' => 'Every registered complaint with SLA, pending and closure details.',
                'permissions' => ['complaints.read', 'complaints.view_own'],
                'query' => fn (User $user): Builder => Complaint::query()->visibleTo($user),
                'table' => 'complaints', 'default_date' => 'registered_at',
                'joins' => [
                    'customer' => ['customers as r_customer', 'r_customer.id', 'complaints.customer_id'],
                    'branch' => ['customer_branches as r_branch', 'r_branch.id', 'complaints.customer_branch_id'],
                    'technician' => ['users as r_technician', 'r_technician.id', 'complaints.assigned_to'],
                    'call_taker' => ['users as r_call_taker', 'r_call_taker.id', 'complaints.call_taken_by'],
                    'responsible' => ['users as r_responsible', 'r_responsible.id', 'complaints.responsible_user_id'],
                ],
                'fields' => [
                    'complaint_no' => ['label' => 'Complaint no', 'type' => 'string', 'expr' => 'complaints.complaint_no'],
                    'registered_at' => ['label' => 'Registered on', 'type' => 'date', 'expr' => 'complaints.registered_at'],
                    'customer' => ['label' => 'Customer', 'type' => 'string', 'expr' => 'r_customer.name', 'join' => 'customer'],
                    'branch' => ['label' => 'Branch / area', 'type' => 'string', 'expr' => 'r_branch.name', 'join' => 'branch'],
                    'site_location' => ['label' => 'Site / location', 'type' => 'string', 'expr' => 'complaints.site_location'],
                    'call_type' => ['label' => 'Type of call', 'type' => 'enum', 'expr' => 'complaints.call_type', 'options' => ComplaintOptions::callTypeOptions()],
                    'call_mode' => ['label' => 'Mode of call', 'type' => 'enum', 'expr' => 'complaints.call_mode', 'options' => ComplaintOptions::options('call_modes')],
                    'complaint_source' => ['label' => 'Complaint source', 'type' => 'enum', 'expr' => 'complaints.complaint_source', 'options' => ComplaintOptions::options('sources')],
                    'nature_of_complaint' => ['label' => 'Nature of complaint', 'type' => 'string', 'expr' => 'complaints.nature_of_complaint'],
                    'priority' => ['label' => 'Priority', 'type' => 'enum', 'expr' => 'complaints.priority', 'options' => ComplaintOptions::priorityOptions()],
                    'status' => ['label' => 'Status', 'type' => 'enum', 'expr' => 'complaints.status', 'options' => $statuses],
                    'technician' => ['label' => 'Technician', 'type' => 'string', 'expr' => 'r_technician.name', 'join' => 'technician'],
                    'call_taker' => ['label' => 'Call taken by', 'type' => 'string', 'expr' => 'r_call_taker.name', 'join' => 'call_taker'],
                    'equipment_type' => ['label' => 'Equipment type', 'type' => 'enum', 'expr' => 'complaints.equipment_type', 'options' => ComplaintOptions::options('equipment_types')],
                    'make' => ['label' => 'Make', 'type' => 'string', 'expr' => 'complaints.make'],
                    'equipment_criticality' => ['label' => 'Equipment criticality', 'type' => 'enum', 'expr' => 'complaints.equipment_criticality', 'options' => ComplaintOptions::options('criticality')],
                    'service_category' => ['label' => 'Service category', 'type' => 'enum', 'expr' => 'complaints.service_category', 'options' => ComplaintOptions::options('service_categories')],
                    'pending_group' => ['label' => 'Pending group', 'type' => 'enum', 'expr' => 'complaints.pending_group', 'options' => ComplaintOptions::options('pending_groups')],
                    'pending_reason' => ['label' => 'Pending reason', 'type' => 'string', 'expr' => 'complaints.pending_reason'],
                    'pending_owner' => ['label' => 'Pending owner', 'type' => 'enum', 'expr' => 'complaints.pending_owner', 'options' => ComplaintOptions::options('pending_owners')],
                    'responsible' => ['label' => 'Responsible person', 'type' => 'string', 'expr' => 'r_responsible.name', 'join' => 'responsible'],
                    'delay_reason' => ['label' => 'Reason for delay', 'type' => 'enum', 'expr' => 'complaints.delay_reason', 'options' => ComplaintOptions::options('delay_reasons')],
                    'follow_up_date' => ['label' => 'Next follow-up', 'type' => 'date', 'expr' => 'complaints.follow_up_date'],
                    'target_date' => ['label' => 'Targeted closing', 'type' => 'date', 'expr' => 'complaints.target_date'],
                    'root_cause' => ['label' => 'Root cause', 'type' => 'string', 'expr' => 'complaints.root_cause'],
                    'complaint_category' => ['label' => 'Complaint category', 'type' => 'string', 'expr' => 'complaints.complaint_category'],
                    'closed_at' => ['label' => 'Closed on', 'type' => 'date', 'expr' => 'complaints.closed_at'],
                    'payment_status' => ['label' => 'Payment status', 'type' => 'enum', 'expr' => 'complaints.payment_status', 'options' => ComplaintOptions::options('payment_statuses')],
                    'total_amount' => ['label' => 'Invoice amount', 'type' => 'number', 'expr' => 'complaints.total_amount'],
                    'parts_charges' => ['label' => 'Parts charges', 'type' => 'number', 'expr' => 'complaints.parts_charges'],
                    'labour_charges' => ['label' => 'Labour charges', 'type' => 'number', 'expr' => 'complaints.labour_charges'],
                    'customer_rating' => ['label' => 'Customer rating', 'type' => 'number', 'expr' => 'complaints.customer_rating'],
                    'team_rating' => ['label' => 'Team rating', 'type' => 'number', 'expr' => 'complaints.team_rating'],
                    'age_days' => ['label' => 'Age (days)', 'type' => 'number', 'expr' => $ageDays],
                    'visits' => ['label' => 'Number of visits', 'type' => 'number', 'expr' => '(SELECT COUNT(*) FROM complaint_visits WHERE complaint_visits.complaint_id = complaints.id)'],
                    'sla_breached' => ['label' => 'SLA breached', 'type' => 'bool', 'expr' => "CASE WHEN complaints.resolution_due_at < COALESCE(complaints.closed_at, {$now}) THEN 1 ELSE 0 END"],
                    'callback_required' => ['label' => 'Call-back required', 'type' => 'bool', 'expr' => 'complaints.callback_required'],
                ],
            ],
            'complaint_visits' => [
                'label' => 'Complaint visits', 'icon' => 'route', 'description' => 'Each technician visit (V01, V02 …) with times, diagnosis and result.',
                'permissions' => ['complaints.read', 'complaints.view_own'],
                'query' => fn (User $user): Builder => ComplaintVisit::query()->whereHas('complaint', fn (Builder $complaints) => $complaints->visibleTo($user)),
                'table' => 'complaint_visits', 'default_date' => 'appointment_at',
                'joins' => [
                    'complaint' => ['complaints as r_complaint', 'r_complaint.id', 'complaint_visits.complaint_id'],
                    'customer' => ['customers as r_customer', 'r_customer.id', 'r_complaint.customer_id', 'complaint'],
                    'technician' => ['users as r_technician', 'r_technician.id', 'complaint_visits.technician_id'],
                ],
                'fields' => [
                    'complaint_no' => ['label' => 'Complaint no', 'type' => 'string', 'expr' => 'r_complaint.complaint_no', 'join' => 'complaint'],
                    'visit_no' => ['label' => 'Visit number', 'type' => 'number', 'expr' => 'complaint_visits.visit_no'],
                    'customer' => ['label' => 'Customer', 'type' => 'string', 'expr' => 'r_customer.name', 'join' => 'customer'],
                    'call_type' => ['label' => 'Type of call', 'type' => 'enum', 'expr' => 'r_complaint.call_type', 'join' => 'complaint', 'options' => ComplaintOptions::callTypeOptions()],
                    'technician' => ['label' => 'Technician', 'type' => 'string', 'expr' => 'r_technician.name', 'join' => 'technician'],
                    'status' => ['label' => 'Visit status', 'type' => 'enum', 'expr' => 'complaint_visits.status', 'options' => array_map(fn ($status) => $status['label'], ComplaintOptions::VISIT_STATUSES)],
                    'outcome' => ['label' => 'Visit outcome', 'type' => 'enum', 'expr' => 'complaint_visits.outcome', 'options' => array_map(fn ($outcome) => $outcome['label'], ComplaintOptions::VISIT_OUTCOMES)],
                    'final_result' => ['label' => 'Final result', 'type' => 'enum', 'expr' => 'complaint_visits.final_result', 'options' => ComplaintOptions::options('final_results')],
                    'reason_for_visit' => ['label' => 'Reason for visit', 'type' => 'string', 'expr' => 'complaint_visits.reason_for_visit'],
                    'fault_category' => ['label' => 'Fault category', 'type' => 'string', 'expr' => 'complaint_visits.fault_category'],
                    'root_cause' => ['label' => 'Root cause', 'type' => 'string', 'expr' => 'complaint_visits.root_cause'],
                    'assignment_method' => ['label' => 'Assigned via', 'type' => 'enum', 'expr' => 'complaint_visits.assignment_method', 'options' => ComplaintOptions::options('assignment_methods')],
                    'appointment_at' => ['label' => 'Appointment', 'type' => 'date', 'expr' => 'complaint_visits.appointment_at'],
                    'ended_at' => ['label' => 'Completed on', 'type' => 'date', 'expr' => 'complaint_visits.ended_at'],
                    'labour_hours' => ['label' => 'Labour hours', 'type' => 'number', 'expr' => 'complaint_visits.labour_hours'],
                    'travel_minutes' => ['label' => 'Travel minutes', 'type' => 'number', 'expr' => 'complaint_visits.travel_minutes'],
                    'customer_rating' => ['label' => 'Customer rating', 'type' => 'number', 'expr' => 'complaint_visits.customer_rating'],
                ],
            ],
            'complaint_parts' => [
                'label' => 'Parts used & recommended', 'icon' => 'cog', 'description' => 'Spare parts replaced on visits and parts recommended for follow-up.',
                'permissions' => ['complaints.read'],
                'query' => fn (User $user): Builder => ComplaintPart::query(),
                'table' => 'complaint_parts', 'default_date' => 'created_at',
                'joins' => [
                    'complaint' => ['complaints as r_complaint', 'r_complaint.id', 'complaint_parts.complaint_id'],
                    'customer' => ['customers as r_customer', 'r_customer.id', 'r_complaint.customer_id', 'complaint'],
                ],
                'fields' => [
                    'kind' => ['label' => 'Used / recommended', 'type' => 'enum', 'expr' => 'complaint_parts.kind', 'options' => ['used' => 'Used', 'recommended' => 'Recommended']],
                    'complaint_no' => ['label' => 'Complaint no', 'type' => 'string', 'expr' => 'r_complaint.complaint_no', 'join' => 'complaint'],
                    'customer' => ['label' => 'Customer', 'type' => 'string', 'expr' => 'r_customer.name', 'join' => 'customer'],
                    'part_name' => ['label' => 'Part name', 'type' => 'string', 'expr' => 'complaint_parts.part_name'],
                    'part_code' => ['label' => 'Part code', 'type' => 'string', 'expr' => 'complaint_parts.part_code'],
                    'category' => ['label' => 'Part category', 'type' => 'string', 'expr' => 'complaint_parts.category'],
                    'make' => ['label' => 'Make', 'type' => 'string', 'expr' => 'complaint_parts.make'],
                    'part_type' => ['label' => 'Part type', 'type' => 'enum', 'expr' => 'complaint_parts.part_type', 'options' => ComplaintOptions::options('part_types')],
                    'source' => ['label' => 'Source', 'type' => 'enum', 'expr' => 'complaint_parts.source', 'options' => ComplaintOptions::options('part_sources')],
                    'approval_status' => ['label' => 'Approval status', 'type' => 'enum', 'expr' => 'complaint_parts.approval_status', 'options' => ComplaintOptions::options('approval_statuses')],
                    'customer_approval_status' => ['label' => 'Customer approval', 'type' => 'enum', 'expr' => 'complaint_parts.customer_approval_status', 'options' => ComplaintOptions::options('approval_statuses')],
                    'purchase_status' => ['label' => 'Purchase status', 'type' => 'enum', 'expr' => 'complaint_parts.purchase_status', 'options' => ComplaintOptions::options('purchase_statuses')],
                    'quantity' => ['label' => 'Quantity', 'type' => 'number', 'expr' => 'complaint_parts.quantity'],
                    'estimated_cost' => ['label' => 'Estimated cost', 'type' => 'number', 'expr' => 'complaint_parts.estimated_cost'],
                    'billable_value' => ['label' => 'Billable value', 'type' => 'number', 'expr' => '(complaint_parts.quantity * complaint_parts.billable_rate)'],
                    'created_at' => ['label' => 'Recorded on', 'type' => 'date', 'expr' => 'complaint_parts.created_at'],
                ],
            ],
            'service_jobs' => [
                'label' => 'Service jobs', 'icon' => 'wrench', 'description' => 'Mobile-app service jobs with status, technician and billing.',
                'permissions' => ['service_jobs.read', 'service_jobs.view_own'],
                'query' => fn (User $user): Builder => RecordVisibility::scope(ServiceJob::query(), $user, 'service_jobs'),
                'table' => 'service_jobs', 'default_date' => 'created_at',
                'joins' => [
                    'customer' => ['customers as r_customer', 'r_customer.id', 'service_jobs.customer_id'],
                    'technician' => ['users as r_technician', 'r_technician.id', 'service_jobs.assigned_to'],
                    'service_type' => ['service_types as r_service_type', 'r_service_type.id', 'service_jobs.service_type_id'],
                ],
                'fields' => [
                    'job_no' => ['label' => 'Job no', 'type' => 'string', 'expr' => 'service_jobs.job_no'],
                    'customer' => ['label' => 'Customer', 'type' => 'string', 'expr' => 'r_customer.name', 'join' => 'customer'],
                    'technician' => ['label' => 'Technician', 'type' => 'string', 'expr' => 'r_technician.name', 'join' => 'technician'],
                    'service_type' => ['label' => 'Service type', 'type' => 'string', 'expr' => 'r_service_type.name', 'join' => 'service_type'],
                    'status' => ['label' => 'Status', 'type' => 'string', 'expr' => 'service_jobs.status'],
                    'priority' => ['label' => 'Priority', 'type' => 'string', 'expr' => 'service_jobs.priority'],
                    'origin' => ['label' => 'Origin', 'type' => 'string', 'expr' => 'service_jobs.origin'],
                    'payment_status' => ['label' => 'Payment status', 'type' => 'string', 'expr' => 'service_jobs.payment_status'],
                    'created_at' => ['label' => 'Created on', 'type' => 'date', 'expr' => 'service_jobs.created_at'],
                    'scheduled_at' => ['label' => 'Scheduled for', 'type' => 'date', 'expr' => 'service_jobs.scheduled_at'],
                    'completed_at' => ['label' => 'Completed on', 'type' => 'date', 'expr' => 'service_jobs.completed_at'],
                    'final_amount' => ['label' => 'Final amount', 'type' => 'number', 'expr' => 'service_jobs.final_amount'],
                ],
            ],
            'customers' => [
                'label' => 'Customers', 'icon' => 'building-2', 'description' => 'Customer master by city, group, priority and status.',
                'permissions' => ['customers.read', 'customers.view_own'],
                'query' => fn (User $user): Builder => RecordVisibility::scope(Customer::query(), $user, 'customers', false),
                'table' => 'customers', 'default_date' => 'created_at',
                'joins' => ['group' => ['customer_groups as r_group', 'r_group.id', 'customers.customer_group_id']],
                'fields' => [
                    'code' => ['label' => 'Customer code', 'type' => 'string', 'expr' => 'customers.code'],
                    'name' => ['label' => 'Customer name', 'type' => 'string', 'expr' => 'customers.name'],
                    'group' => ['label' => 'Customer group', 'type' => 'string', 'expr' => 'r_group.name', 'join' => 'group'],
                    'city' => ['label' => 'City', 'type' => 'string', 'expr' => 'customers.city'],
                    'state' => ['label' => 'State', 'type' => 'string', 'expr' => 'customers.state'],
                    'status' => ['label' => 'Status', 'type' => 'string', 'expr' => 'customers.status'],
                    'priority' => ['label' => 'Priority', 'type' => 'string', 'expr' => 'customers.priority'],
                    'branch_type' => ['label' => 'Branch type', 'type' => 'string', 'expr' => 'customers.branch_type'],
                    'created_at' => ['label' => 'Created on', 'type' => 'date', 'expr' => 'customers.created_at'],
                    'complaints' => ['label' => 'Number of complaints', 'type' => 'number', 'expr' => '(SELECT COUNT(*) FROM complaints WHERE complaints.customer_id = customers.id AND complaints.deleted_at IS NULL)'],
                ],
            ],
            'employees' => [
                'label' => 'Employees', 'icon' => 'contact-round', 'description' => 'Employee master by department, designation and employment status.',
                'permissions' => ['employees.read'],
                'query' => fn (User $user): Builder => User::query()->whereHas('employeeProfile'),
                'table' => 'users', 'default_date' => 'date_of_joining',
                'joins' => ['profile' => ['employee_profiles as r_profile', 'r_profile.user_id', 'users.id']],
                'fields' => [
                    'name' => ['label' => 'Employee', 'type' => 'string', 'expr' => 'users.name'],
                    'employee_code' => ['label' => 'Employee code', 'type' => 'string', 'expr' => 'users.employee_code'],
                    'department' => ['label' => 'Department', 'type' => 'string', 'expr' => 'users.department'],
                    'designation' => ['label' => 'Designation', 'type' => 'string', 'expr' => 'users.designation'],
                    'status' => ['label' => 'Account status', 'type' => 'string', 'expr' => 'users.status'],
                    'employment_type' => ['label' => 'Employment type', 'type' => 'string', 'expr' => 'r_profile.employment_type', 'join' => 'profile'],
                    'gender' => ['label' => 'Gender', 'type' => 'string', 'expr' => 'r_profile.gender', 'join' => 'profile'],
                    'date_of_joining' => ['label' => 'Joining date', 'type' => 'date', 'expr' => 'r_profile.date_of_joining', 'join' => 'profile'],
                    'probation_end_date' => ['label' => 'Probation ends', 'type' => 'date', 'expr' => 'r_profile.probation_end_date', 'join' => 'profile'],
                    'gross_monthly_salary' => ['label' => 'Gross monthly salary', 'type' => 'number', 'expr' => 'r_profile.gross_monthly_salary', 'join' => 'profile'],
                ],
            ],
            'attendance' => [
                'label' => 'Attendance', 'icon' => 'map-pin-check', 'description' => 'Daily check-ins by employee, premises and status.',
                'permissions' => ['attendance.read', 'attendance.approve'],
                'query' => fn (User $user): Builder => Attendance::query(),
                'table' => 'attendances', 'default_date' => 'attendance_date',
                'joins' => [
                    'employee' => ['users as r_employee', 'r_employee.id', 'attendances.user_id'],
                    'premises' => ['premises as r_premises', 'r_premises.id', 'attendances.premises_id'],
                ],
                'fields' => [
                    'attendance_date' => ['label' => 'Date', 'type' => 'date', 'expr' => 'attendances.attendance_date'],
                    'employee' => ['label' => 'Employee', 'type' => 'string', 'expr' => 'r_employee.name', 'join' => 'employee'],
                    'department' => ['label' => 'Department', 'type' => 'string', 'expr' => 'r_employee.department', 'join' => 'employee'],
                    'premises' => ['label' => 'Premises', 'type' => 'string', 'expr' => 'r_premises.name', 'join' => 'premises'],
                    'status' => ['label' => 'Review status', 'type' => 'string', 'expr' => 'attendances.status'],
                    'day_status' => ['label' => 'Day status', 'type' => 'string', 'expr' => 'attendances.day_status'],
                    'inside_premises' => ['label' => 'Inside premises', 'type' => 'bool', 'expr' => 'attendances.inside_premises'],
                    'auto_checked_out' => ['label' => 'Auto checked out', 'type' => 'bool', 'expr' => 'attendances.auto_checked_out'],
                ],
            ],
            'workflows' => [
                'label' => 'Internal workflows', 'icon' => 'git-branch', 'description' => 'Internal tasks by owner, priority and completion.',
                'permissions' => ['tasks.read', 'tasks.view_own'],
                'query' => fn (User $user): Builder => RecordVisibility::scope(Task::query(), $user, 'tasks')->workItems(),
                'table' => 'tasks', 'default_date' => 'created_at',
                'joins' => [
                    'assignee' => ['users as r_assignee', 'r_assignee.id', 'tasks.assigned_to'],
                    'creator' => ['users as r_creator', 'r_creator.id', 'tasks.created_by'],
                ],
                'fields' => [
                    'task_no' => ['label' => 'Workflow no', 'type' => 'string', 'expr' => 'tasks.task_no'],
                    'title' => ['label' => 'Title', 'type' => 'string', 'expr' => 'tasks.title'],
                    'status' => ['label' => 'Status', 'type' => 'enum', 'expr' => "CASE WHEN tasks.status = 'closed' THEN 'closed' ELSE 'pending' END", 'options' => ['pending' => 'Pending', 'closed' => 'Completed']],
                    'priority' => ['label' => 'Priority', 'type' => 'enum', 'expr' => 'tasks.priority', 'options' => ['normal' => 'Normal', 'high' => 'High', 'very_high' => 'Very high']],
                    'assignee' => ['label' => 'Responsible person', 'type' => 'string', 'expr' => 'r_assignee.name', 'join' => 'assignee'],
                    'creator' => ['label' => 'Created by', 'type' => 'string', 'expr' => 'r_creator.name', 'join' => 'creator'],
                    'created_at' => ['label' => 'Created on', 'type' => 'date', 'expr' => 'tasks.created_at'],
                    'due_at' => ['label' => 'Due date', 'type' => 'date', 'expr' => 'tasks.due_at'],
                    'closed_at' => ['label' => 'Completed on', 'type' => 'date', 'expr' => 'tasks.closed_at'],
                    'overdue' => ['label' => 'Overdue', 'type' => 'bool', 'expr' => "CASE WHEN tasks.status <> 'closed' AND tasks.due_at < {$now} THEN 1 ELSE 0 END"],
                ],
            ],
            'leave_requests' => [
                'label' => 'Leave requests', 'icon' => 'calendar-heart', 'description' => 'Leave applications by employee, type and status.',
                'permissions' => ['leaves.read', 'hr.read'],
                'query' => fn (User $user): Builder => LeaveRequest::query(),
                'table' => 'leave_requests', 'default_date' => 'from_date',
                'joins' => [
                    'employee' => ['users as r_employee', 'r_employee.id', 'leave_requests.user_id'],
                    'leave_type' => ['leave_types as r_leave_type', 'r_leave_type.id', 'leave_requests.leave_type_id'],
                ],
                'fields' => [
                    'employee' => ['label' => 'Employee', 'type' => 'string', 'expr' => 'r_employee.name', 'join' => 'employee'],
                    'department' => ['label' => 'Department', 'type' => 'string', 'expr' => 'r_employee.department', 'join' => 'employee'],
                    'leave_type' => ['label' => 'Leave type', 'type' => 'string', 'expr' => 'r_leave_type.name', 'join' => 'leave_type'],
                    'status' => ['label' => 'Status', 'type' => 'string', 'expr' => 'leave_requests.status'],
                    'from_date' => ['label' => 'From', 'type' => 'date', 'expr' => 'leave_requests.from_date'],
                    'to_date' => ['label' => 'To', 'type' => 'date', 'expr' => 'leave_requests.to_date'],
                    'total_days' => ['label' => 'Days', 'type' => 'number', 'expr' => 'leave_requests.total_days'],
                ],
            ],
            'expense_vouchers' => [
                'label' => 'Expense vouchers', 'icon' => 'receipt-indian-rupee', 'description' => 'Expense claims by employee, category and status.',
                'permissions' => ['vouchers.read', 'hr.read'],
                'query' => fn (User $user): Builder => ExpenseVoucher::query(),
                'table' => 'expense_vouchers', 'default_date' => 'expense_date',
                'joins' => ['employee' => ['users as r_employee', 'r_employee.id', 'expense_vouchers.user_id']],
                'fields' => [
                    'voucher_no' => ['label' => 'Voucher no', 'type' => 'string', 'expr' => 'expense_vouchers.voucher_no'],
                    'employee' => ['label' => 'Employee', 'type' => 'string', 'expr' => 'r_employee.name', 'join' => 'employee'],
                    'category' => ['label' => 'Category', 'type' => 'string', 'expr' => 'expense_vouchers.category'],
                    'status' => ['label' => 'Status', 'type' => 'string', 'expr' => 'expense_vouchers.status'],
                    'expense_date' => ['label' => 'Expense date', 'type' => 'date', 'expr' => 'expense_vouchers.expense_date'],
                    'amount' => ['label' => 'Amount', 'type' => 'number', 'expr' => 'expense_vouchers.amount'],
                ],
            ],
            'payroll' => [
                'label' => 'Payroll entries', 'icon' => 'wallet-cards', 'description' => 'Monthly payroll by employee with gross, deductions and net pay.',
                'permissions' => ['payroll.read'],
                'query' => fn (User $user): Builder => PayrollEntry::query(),
                'table' => 'payroll_entries', 'default_date' => 'created_at',
                'joins' => [
                    'employee' => ['users as r_employee', 'r_employee.id', 'payroll_entries.user_id'],
                    'run' => ['payroll_runs as r_run', 'r_run.id', 'payroll_entries.payroll_run_id'],
                ],
                'fields' => [
                    'employee' => ['label' => 'Employee', 'type' => 'string', 'expr' => 'r_employee.name', 'join' => 'employee'],
                    'department' => ['label' => 'Department', 'type' => 'string', 'expr' => 'r_employee.department', 'join' => 'employee'],
                    'month' => ['label' => 'Payroll month', 'type' => 'string', 'expr' => 'r_run.payroll_month', 'join' => 'run'],
                    'status' => ['label' => 'Payment status', 'type' => 'string', 'expr' => 'payroll_entries.status'],
                    'payable_days' => ['label' => 'Payable days', 'type' => 'number', 'expr' => 'payroll_entries.payable_days'],
                    'gross_amount' => ['label' => 'Gross', 'type' => 'number', 'expr' => 'payroll_entries.gross_amount'],
                    'deduction_amount' => ['label' => 'Deductions', 'type' => 'number', 'expr' => 'payroll_entries.deduction_amount'],
                    'net_amount' => ['label' => 'Net pay', 'type' => 'number', 'expr' => 'payroll_entries.net_amount'],
                    'created_at' => ['label' => 'Generated on', 'type' => 'date', 'expr' => 'payroll_entries.created_at'],
                ],
            ],
        ];
    }

    /** Datasets the user may report on. @return array<string, array<string, mixed>> */
    public static function availableFor(User $user): array
    {
        return array_filter(self::all(), fn (array $dataset) => $user->canAny($dataset['permissions']));
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * Field catalogue for the builder UI (no SQL expressions).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function catalogueFor(User $user): array
    {
        return collect(self::availableFor($user))->map(fn (array $dataset) => [
            'label' => $dataset['label'],
            'icon' => $dataset['icon'],
            'description' => $dataset['description'],
            'default_date' => $dataset['default_date'],
            'fields' => collect($dataset['fields'])->map(fn (array $field) => [
                'label' => $field['label'],
                'type' => $field['type'],
                'options' => $field['options'] ?? null,
            ])->all(),
        ])->all();
    }

    private static function dateDiffDays(string $from, string $to): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(julianday({$to}) - julianday({$from}) AS INTEGER)",
            'pgsql' => "DATE_PART('day', {$to}::timestamp - {$from}::timestamp)",
            default => "DATEDIFF({$to}, {$from})",
        };
    }
}
