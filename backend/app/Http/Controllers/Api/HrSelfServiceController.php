<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseVoucher;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HrSelfServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user()->load(['employeeProfile', 'roles', 'premises:id,name,address']);
        $balances = $user->leaveBalances()->with('leaveType')->where('year', now()->year)->get()->map(function (LeaveBalance $balance): array {
            return [
                'id' => $balance->id,
                'leave_type_id' => $balance->leave_type_id,
                'name' => $balance->leaveType->name,
                'code' => $balance->leaveType->code,
                'color' => $balance->leaveType->color,
                'allocated' => (float) $balance->allocated,
                'used' => (float) $balance->used,
                'pending' => (float) $balance->pending,
                'available' => max(0, (float) $balance->allocated - (float) $balance->used - (float) $balance->pending),
                'requires_document' => $balance->leaveType->requires_document,
            ];
        });

        return response()->json([
            'user' => $user,
            'leave_balances' => $balances,
            'leave_types' => LeaveType::query()->where('active', true)->orderBy('name')->get(),
            'leave_requests' => $user->leaveRequests()->with('leaveType')->latest()->limit(30)->get(),
            'holidays' => Holiday::query()->where('holiday_date', '>=', today())->orderBy('holiday_date')->limit(30)->get(),
            'vouchers' => $user->expenseVouchers()->latest()->limit(30)->get(),
            'payslips' => $user->payrollEntries()->with('payrollRun:id,payroll_month,status')->whereIn('status', ['approved', 'paid', 'on_hold'])->latest()->limit(12)->get(),
        ]);
    }

    public function storeLeave(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'from_date' => ['required', 'date', 'after_or_equal:today'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['required', 'string', 'max:3000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ]);
        $from = CarbonImmutable::parse($validated['from_date']);
        $to = CarbonImmutable::parse($validated['to_date']);
        if ($from->year !== $to->year) {
            throw ValidationException::withMessages(['to_date' => 'Leave request must stay within one calendar year.']);
        }
        $leaveType = LeaveType::query()->where('active', true)->findOrFail($validated['leave_type_id']);
        if ($leaveType->requires_document && ! $request->hasFile('document')) {
            throw ValidationException::withMessages(['document' => 'A supporting document is required for this leave type.']);
        }
        $overlaps = $request->user()->leaveRequests()->whereIn('status', ['pending', 'approved'])->where('from_date', '<=', $to)->where('to_date', '>=', $from)->exists();
        if ($overlaps) {
            throw ValidationException::withMessages(['from_date' => 'These dates overlap an existing leave request.']);
        }
        $holidayDates = Holiday::query()->whereBetween('holiday_date', [$from, $to])->pluck('holiday_date')->map->format('Y-m-d')->all();
        $totalDays = collect(CarbonPeriod::create($from, $to))->filter(fn ($date): bool => ! $date->isSunday() && ! in_array($date->format('Y-m-d'), $holidayDates, true))->count();
        if ($totalDays === 0) {
            throw ValidationException::withMessages(['from_date' => 'Selected period has no working days.']);
        }

        $leaveRequest = DB::transaction(function () use ($request, $validated, $from, $leaveType, $totalDays): LeaveRequest {
            $balance = LeaveBalance::query()->where('user_id', $request->user()->id)->where('leave_type_id', $leaveType->id)->where('year', $from->year)->lockForUpdate()->firstOrFail();
            $available = (float) $balance->allocated - (float) $balance->used - (float) $balance->pending;
            if ($available < $totalDays) {
                throw ValidationException::withMessages(['to_date' => 'Insufficient leave balance.']);
            }
            $leaveRequest = $request->user()->leaveRequests()->create([
                'leave_type_id' => $leaveType->id,
                'from_date' => $validated['from_date'],
                'to_date' => $validated['to_date'],
                'total_days' => $totalDays,
                'reason' => $validated['reason'],
                'document_path' => $request->file('document')?->store('leave-documents', 'public'),
                'status' => 'pending',
            ]);
            $balance->increment('pending', $totalDays);

            return $leaveRequest;
        });

        return response()->json(['message' => 'Leave request submitted for approval.', 'leave_request' => $leaveRequest->load('leaveType')], 201);
    }

    public function storeVoucher(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:10000000'],
            'description' => ['required', 'string', 'max:3000'],
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
        ]);
        $voucher = ExpenseVoucher::create([
            'voucher_no' => 'EXP-'.now()->format('Ym').'-'.str_pad((string) ((ExpenseVoucher::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT),
            'user_id' => $request->user()->id,
            'expense_date' => $validated['expense_date'],
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'receipt_path' => $request->file('receipt')->store('expense-receipts', 'public'),
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Expense voucher submitted for approval.', 'voucher' => $voucher], 201);
    }
}
