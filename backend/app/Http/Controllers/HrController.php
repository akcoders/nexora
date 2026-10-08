<?php

namespace App\Http\Controllers;

use App\Models\ExpenseVoucher;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\OneSignalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HrController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('hr.read'), 403);

        return view('hr.index', [
            'holidays' => Holiday::query()->whereYear('holiday_date', $request->integer('year', now()->year))->orderBy('holiday_date')->get(),
            'leaveTypes' => LeaveType::query()->orderBy('name')->get(),
            'leaveRequests' => LeaveRequest::query()->with(['user', 'leaveType', 'reviewer'])->latest()->paginate(15, ['*'], 'leave_page')->withQueryString(),
            'vouchers' => ExpenseVoucher::query()->with(['user', 'reviewer'])->latest()->paginate(15, ['*'], 'voucher_page')->withQueryString(),
            'metrics' => [
                'pending_leaves' => LeaveRequest::where('status', 'pending')->count(),
                'pending_vouchers' => ExpenseVoucher::where('status', 'pending')->count(),
                'voucher_value' => ExpenseVoucher::where('status', 'pending')->sum('amount'),
                'holidays' => Holiday::whereYear('holiday_date', now()->year)->count(),
            ],
        ]);
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('hr.update'), 403);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'holiday_date' => ['required', 'date', 'unique:holidays,holiday_date'],
            'type' => ['required', Rule::in(['company', 'national', 'regional', 'restricted'])],
            'optional' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);
        $validated['optional'] = $request->boolean('optional');
        Holiday::create($validated);

        return back()->with('success', 'Holiday added.');
    }

    public function storeLeaveType(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('hr.update'), 403);
        $validated = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', 'unique:leave_types,code'],
            'name' => ['required', 'string', 'max:100'],
            'annual_quota' => ['required', 'numeric', 'min:0', 'max:365'],
            'color' => ['required', Rule::in(['primary', 'success', 'warning', 'danger', 'secondary'])],
            'paid' => ['nullable', 'boolean'],
            'requires_document' => ['nullable', 'boolean'],
        ]);
        $validated['paid'] = $request->boolean('paid');
        $validated['requires_document'] = $request->boolean('requires_document');
        $validated['active'] = true;
        LeaveType::create($validated);

        return back()->with('success', 'Leave type created.');
    }

    public function reviewLeave(Request $request, LeaveRequest $leaveRequest, OneSignalService $oneSignal): RedirectResponse
    {
        abort_unless($request->user()->can('leaves.approve'), 403);
        abort_unless($leaveRequest->status === 'pending', 422, 'This leave request is already reviewed.');
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'review_remark' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($request, $leaveRequest, $validated): void {
            $balance = LeaveBalance::query()
                ->where('user_id', $leaveRequest->user_id)
                ->where('leave_type_id', $leaveRequest->leave_type_id)
                ->where('year', $leaveRequest->from_date->year)
                ->lockForUpdate()
                ->firstOrFail();
            $balance->update([
                'pending' => max(0, (float) $balance->pending - (float) $leaveRequest->total_days),
                'used' => (float) $balance->used + ($validated['decision'] === 'approved' ? (float) $leaveRequest->total_days : 0),
            ]);
            $leaveRequest->update([
                'status' => $validated['decision'],
                'reviewed_by' => $request->user()->id,
                'review_remark' => $validated['review_remark'] ?? null,
                'reviewed_at' => now(),
            ]);
        });
        $oneSignal->sendToUser($leaveRequest->user, 'Leave request '.str($validated['decision'])->headline(), $leaveRequest->from_date->format('d M').' – '.$leaveRequest->to_date->format('d M'), ['type' => 'leave_request', 'id' => $leaveRequest->id]);

        return back()->with('success', 'Leave request reviewed.');
    }

    public function reviewVoucher(Request $request, ExpenseVoucher $expenseVoucher, OneSignalService $oneSignal): RedirectResponse
    {
        abort_unless($request->user()->can('vouchers.approve'), 403);
        abort_unless($expenseVoucher->status === 'pending', 422, 'This voucher is already reviewed.');
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'review_remark' => ['nullable', 'required_if:decision,rejected', 'string', 'max:2000'],
        ]);
        $expenseVoucher->update([
            'status' => $validated['decision'],
            'reviewed_by' => $request->user()->id,
            'review_remark' => $validated['review_remark'] ?? null,
            'reviewed_at' => now(),
        ]);
        $oneSignal->sendToUser($expenseVoucher->user, 'Expense voucher '.str($validated['decision'])->headline(), $expenseVoucher->voucher_no.' · ₹'.number_format((float) $expenseVoucher->amount, 2), ['type' => 'expense_voucher', 'id' => $expenseVoucher->id]);

        return back()->with('success', 'Expense voucher reviewed.');
    }
}
