<x-app-layout>
    <x-slot name="title">Payroll</x-slot>
    <x-slot name="pageTitle">Payroll</x-slot>
    <x-slot name="breadcrumb">HR & people / Payroll</x-slot>
    <div class="nx-page-header"><div><div class="nx-page-kicker">HR & people</div><h2>Payroll</h2><p>Generate, approve and pay monthly payroll from attendance and leave.</p></div></div>

    <div class="row g-3">
        <div class="col-xl-3">
            @can('payroll.create')
                <section class="nx-card mb-3">
                    <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="calculator"></i>Generate payroll</h3></div>
                    <form class="nx-card-body" method="POST" action="{{ route('payroll.generate') }}">
                        @csrf
                        <label class="form-label" for="payrollMonth">Payroll month</label>
                        <input type="month" id="payrollMonth" class="form-control mb-3" name="month" value="{{ now()->subMonth()->format('Y-m') }}" required>
                        <label class="form-label" for="payrollNotes">Run notes <span class="text-secondary fw-normal">(optional)</span></label>
                        <textarea id="payrollNotes" rows="2" class="form-control mb-3" name="notes" maxlength="2000"></textarea>
                        <button class="btn btn-primary w-100">Calculate payroll</button>
                    </form>
                </section>
            @endcan
            <section class="nx-card overflow-hidden">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="history"></i>Payroll runs</h3><span class="nx-count notranslate">{{ $runs->count() }}</span></div>
                <div class="list-group list-group-flush">
                    @forelse($runs as $run)
                        <a href="{{ route('payroll.index', ['run' => $run]) }}" class="list-group-item list-group-item-action {{ $selectedRun?->id === $run->id ? 'active' : '' }}" style="padding:10px var(--nx-gutter)" @if($selectedRun?->id === $run->id) aria-current="true" @endif>
                            <div class="d-flex justify-content-between gap-2"><strong class="notranslate">{{ $run->payroll_month }}</strong><span class="small">{{ str($run->status)->headline() }}</span></div>
                            <small class="{{ $selectedRun?->id === $run->id ? '' : 'text-secondary' }}">{{ $run->entries_count }} employees · <span class="notranslate">₹{{ number_format($run->entries_sum_net_amount, 0) }}</span></small>
                        </a>
                    @empty
                        <div class="nx-empty py-4">No payroll runs yet.</div>
                    @endforelse
                </div>
            </section>
        </div>
        <div class="col-xl-9">
            @if($selectedRun)
                <section class="nx-card nx-datatable-card">
                    <div class="nx-card-header">
                        <div>
                            <h3 class="nx-card-title"><i data-lucide="wallet-cards"></i><span>Payroll · <span class="notranslate">{{ $selectedRun->payroll_month }}</span></span><span class="badge {{ $selectedRun->status === 'approved' ? 'badge-soft-success' : 'badge-soft-warning' }}">{{ str($selectedRun->status)->headline() }}</span></h3>
                            <p class="nx-card-subtitle">Generated {{ $selectedRun->generated_at->format('d M Y, h:i A') }} by {{ $selectedRun->generator->name }}</p>
                        </div>
                        @if($selectedRun->status === 'draft' && auth()->user()->can('payroll.approve'))
                            <form method="POST" action="{{ route('payroll.approve', $selectedRun) }}">@csrf<button class="btn btn-success btn-sm"><i data-lucide="badge-check"></i>Approve payroll</button></form>
                        @endif
                    </div>
                    <table class="table nx-table mb-0" data-datatable data-dt-title="Payroll {{ $selectedRun->payroll_month }}" data-dt-page-length="50">
                        <thead><tr><th class="ps-4">Employee</th><th>Attendance</th><th>Payable</th><th>Gross</th><th>Deductions</th><th>Net salary</th><th>Status</th><th class="pe-4 no-export no-sort" data-orderable="false">Slip</th></tr></thead>
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
                                            <span class="badge badge-soft-{{ ['paid' => 'success', 'approved' => 'primary', 'on_hold' => 'warning'][$entry->status] ?? 'secondary' }}">{{ str($entry->status)->headline() }}</span>
                                        @endif
                                    </td>
                                    <td class="pe-4"><a class="btn btn-sm btn-outline-primary" href="{{ $entry->salary_slip_url }}"><i data-lucide="file-down" style="width:15px"></i> PDF</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @else
                <div class="nx-card">
                    <div class="nx-empty d-flex flex-column align-items-center justify-content-center" style="min-height:280px">
                        <i data-lucide="wallet-cards"></i>
                        <strong>No payroll run selected</strong>
                        <span>@can('payroll.create')Pick a payroll month and click <b>Calculate payroll</b>. @endcan Generated runs appear in the Payroll runs list.</span>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
