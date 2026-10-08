<x-app-layout>
    <x-slot name="title">Payroll</x-slot>
    <x-slot name="pageTitle">Payroll</x-slot>
    <x-slot name="breadcrumb">HR / Payroll</x-slot>

    <div class="row g-4">
        <div class="col-xl-3">
            <section class="nx-card p-3 mb-3">
                @can('payroll.create')
                    <form method="POST" action="{{ route('payroll.generate') }}">
                        @csrf
                        <label class="form-label fw-semibold">Generate monthly payroll</label>
                        <input type="month" class="form-control mb-2" name="month" value="{{ now()->subMonth()->format('Y-m') }}" required>
                        <textarea class="form-control mb-2" name="notes" placeholder="Run notes"></textarea>
                        <button class="btn btn-primary w-100">Calculate payroll</button>
                    </form>
                @endcan
            </section>
            <section class="nx-card p-2">
                <div class="px-2 pt-2 fw-bold small">PAYROLL RUNS</div>
                @forelse($runs as $run)
                    <a href="{{ route('payroll.index', ['run' => $run]) }}" class="d-block p-3 rounded-3 {{ $selectedRun?->id === $run->id ? 'bg-primary text-white' : 'text-dark' }}">
                        <div class="d-flex justify-content-between"><strong>{{ $run->payroll_month }}</strong><span>{{ str($run->status)->headline() }}</span></div>
                        <small>{{ $run->entries_count }} employees · ₹{{ number_format($run->entries_sum_net_amount, 0) }}</small>
                    </a>
                @empty
                    <div class="p-3 text-secondary small">No payroll runs.</div>
                @endforelse
            </section>
        </div>
        <div class="col-xl-9">
            @if($selectedRun)
                <section class="nx-card overflow-hidden">
                    <div class="p-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <span class="badge {{ $selectedRun->status === 'approved' ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ str($selectedRun->status)->headline() }}</span>
                            <h2 class="h5 fw-bold mt-2 mb-1">Payroll · {{ $selectedRun->payroll_month }}</h2>
                            <div class="small text-secondary">Generated {{ $selectedRun->generated_at->format('d M Y, h:i A') }} by {{ $selectedRun->generator->name }}</div>
                        </div>
                        @if($selectedRun->status === 'draft' && auth()->user()->can('payroll.approve'))
                            <form method="POST" action="{{ route('payroll.approve', $selectedRun) }}">@csrf<button class="btn btn-success">Approve payroll</button></form>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table nx-table mb-0">
                            <thead><tr><th class="ps-4">Employee</th><th>Attendance</th><th>Payable</th><th>Gross</th><th>Deductions</th><th>Net salary</th><th>Status</th><th class="pe-4">Slip</th></tr></thead>
                            <tbody>
                                @foreach($selectedRun->entries as $entry)
                                    <tr>
                                        <td class="ps-4"><div class="fw-semibold">{{ $entry->user->name }}</div><small class="text-secondary">{{ $entry->user->employee_code }}</small></td>
                                        <td><small>P {{ $entry->present_days }} · H {{ $entry->half_days }} · L {{ $entry->paid_leave_days }}</small></td>
                                        <td>{{ $entry->payable_days }} / {{ $entry->working_days }}</td>
                                        <td>₹{{ number_format($entry->gross_amount, 2) }}</td>
                                        <td>₹{{ number_format($entry->deduction_amount, 2) }}</td>
                                        <td class="fw-bold text-success">₹{{ number_format($entry->net_amount, 2) }}</td>
                                        <td>
                                            @if($selectedRun->status === 'approved' && auth()->user()->can('payroll.update'))
                                                <form method="POST" action="{{ route('payroll.entries.update', $entry) }}">@csrf @method('PATCH')
                                                    <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                                                        <option value="approved" @selected($entry->status === 'approved')>Approved</option>
                                                        <option value="paid" @selected($entry->status === 'paid')>Paid</option>
                                                        <option value="on_hold" @selected($entry->status === 'on_hold')>On hold</option>
                                                    </select>
                                                </form>
                                            @else
                                                <span class="badge bg-light text-dark">{{ str($entry->status)->headline() }}</span>
                                            @endif
                                        </td>
                                        <td class="pe-4"><a class="btn btn-sm btn-outline-primary" href="{{ $entry->salary_slip_url }}"><i data-lucide="file-down" style="width:15px"></i> PDF</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @else
                <div class="nx-card py-5 text-center text-secondary">Generate a payroll run to begin.</div>
            @endif
        </div>
    </div>
</x-app-layout>
