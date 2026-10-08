<?php

namespace App\Http\Controllers;

use App\Models\PayrollEntry;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('payroll.read'), 403);
        $selectedRun = $request->filled('run')
            ? PayrollRun::query()->with(['entries.user.employeeProfile', 'generator', 'approver'])->findOrFail($request->integer('run'))
            : PayrollRun::query()->with(['entries.user.employeeProfile', 'generator', 'approver'])->latest('payroll_month')->first();
        $selectedRun?->entries->each(fn (PayrollEntry $entry) => $entry->setAttribute(
            'salary_slip_url',
            URL::temporarySignedRoute('salary-slips.download', now()->addHour(), ['payrollEntry' => $entry]),
        ));

        return view('payroll.index', [
            'runs' => PayrollRun::query()->withCount('entries')->withSum('entries', 'net_amount')->latest('payroll_month')->get(),
            'selectedRun' => $selectedRun,
        ]);
    }

    public function generate(Request $request, PayrollService $payroll): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.create'), 403);
        $validated = $request->validate(['month' => ['required', 'date_format:Y-m'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $run = $payroll->generate($validated['month'], $request->user(), $validated['notes'] ?? null);

        return redirect()->route('payroll.index', ['run' => $run])->with('success', 'Payroll calculated successfully.');
    }

    public function approve(Request $request, PayrollRun $payrollRun): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.approve'), 403);
        abort_unless($payrollRun->status === 'draft', 422, 'Only draft payroll can be approved.');
        abort_if($payrollRun->entries()->doesntExist(), 422, 'Generate payroll entries before approval.');
        DB::transaction(function () use ($request, $payrollRun): void {
            $payrollRun->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $payrollRun->entries()->update(['status' => 'approved']);
        });

        return back()->with('success', 'Payroll approved and locked.');
    }

    public function markPaid(Request $request, PayrollEntry $payrollEntry): RedirectResponse
    {
        abort_unless($request->user()->can('payroll.update'), 403);
        $validated = $request->validate(['status' => ['required', Rule::in(['paid', 'on_hold'])]]);
        abort_unless($payrollEntry->payrollRun->status === 'approved', 422, 'Approve the payroll run first.');
        $payrollEntry->update(['status' => $validated['status'], 'paid_at' => $validated['status'] === 'paid' ? now() : null]);

        return back()->with('success', 'Salary payment status updated.');
    }
}
