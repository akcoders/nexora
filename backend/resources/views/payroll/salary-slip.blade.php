<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Salary Slip · {{ $entry->payrollRun->payroll_month }}</title>
    <style>
        @page { margin: 24px; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172033; font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        .header { padding: 18px 20px; color: #fff; background: #123f86; }
        .brand { width: 62%; display: inline-block; vertical-align: middle; }
        .brand img { width: 145px; max-height: 54px; object-fit: contain; padding: 4px; background: #fff; border-radius: 5px; }
        .brand-name { margin-top: 6px; font-size: 15px; font-weight: bold; }
        .period { width: 37%; display: inline-block; text-align: right; vertical-align: middle; }
        .period strong { display: block; font-size: 18px; }
        .meta { padding: 16px 20px; background: #eef5ff; border: 1px solid #dce8f8; }
        .meta table, .summary table, .lines { width: 100%; border-collapse: collapse; }
        .meta td { width: 25%; padding: 4px 8px 4px 0; vertical-align: top; }
        .label { display: block; color: #667085; font-size: 9px; text-transform: uppercase; }
        .value { display: block; margin-top: 2px; font-weight: bold; }
        .section { margin-top: 16px; }
        .section-title { padding: 8px 10px; color: #123f86; background: #e8f0fb; border-left: 4px solid #1b66c9; font-weight: bold; }
        .columns { width: 100%; }
        .column { width: 49%; display: inline-block; vertical-align: top; }
        .column:last-child { margin-left: 1.5%; }
        .lines th, .lines td { padding: 8px 10px; border-bottom: 1px solid #e5e9f0; }
        .lines th { color: #667085; background: #f8fafc; text-align: left; font-size: 9px; text-transform: uppercase; }
        .amount { text-align: right !important; }
        .total td { font-weight: bold; background: #f8fafc; }
        .summary { margin-top: 18px; padding: 14px 18px; color: #fff; background: #123f86; }
        .summary td { width: 33.33%; text-align: center; }
        .summary small { display: block; color: #cfe0fb; }
        .summary strong { display: block; margin-top: 4px; font-size: 17px; }
        .net { color: #c8f7d4; }
        .footer { margin-top: 20px; padding-top: 10px; color: #667085; border-top: 1px solid #d9dee8; text-align: center; font-size: 9px; }
    </style>
</head>
<body>
    <header class="header">
        <div class="brand">
            @if($logo)<img src="{{ $logo }}" alt="Company logo">@endif
            <div class="brand-name">{{ $company?->legal_name ?? config('app.name') }}</div>
        </div>
        <div class="period"><span>SALARY SLIP</span><strong>{{ \Carbon\CarbonImmutable::createFromFormat('Y-m', $entry->payrollRun->payroll_month)->format('F Y') }}</strong></div>
    </header>

    <section class="meta">
        <table>
            <tr>
                <td><span class="label">Employee</span><span class="value">{{ $entry->user->name }}</span></td>
                <td><span class="label">Employee code</span><span class="value">{{ $entry->user->employee_code ?: '—' }}</span></td>
                <td><span class="label">Department</span><span class="value">{{ $entry->user->department ?: '—' }}</span></td>
                <td><span class="label">Designation</span><span class="value">{{ $entry->user->designation ?: '—' }}</span></td>
            </tr>
            <tr>
                <td><span class="label">Working days</span><span class="value">{{ number_format((float) $entry->working_days, 2) }}</span></td>
                <td><span class="label">Present / half days</span><span class="value">{{ number_format((float) $entry->present_days, 2) }} / {{ number_format((float) $entry->half_days, 2) }}</span></td>
                <td><span class="label">Paid leave</span><span class="value">{{ number_format((float) $entry->paid_leave_days, 2) }}</span></td>
                <td><span class="label">Payable days</span><span class="value">{{ number_format((float) $entry->payable_days, 2) }}</span></td>
            </tr>
        </table>
    </section>

    <section class="section columns">
        <div class="column">
            <div class="section-title">Earnings</div>
            <table class="lines">
                <thead><tr><th>Component</th><th class="amount">Amount (₹)</th></tr></thead>
                <tbody>
                    @foreach($entry->earnings as $name => $amount)
                        <tr><td>{{ str($name)->headline() }}</td><td class="amount">{{ number_format((float) $amount, 2) }}</td></tr>
                    @endforeach
                    <tr class="total"><td>Gross earnings</td><td class="amount">{{ number_format((float) $entry->gross_amount, 2) }}</td></tr>
                </tbody>
            </table>
        </div>
        <div class="column">
            <div class="section-title">Deductions</div>
            <table class="lines">
                <thead><tr><th>Component</th><th class="amount">Amount (₹)</th></tr></thead>
                <tbody>
                    @foreach($entry->deductions as $name => $amount)
                        <tr><td>{{ str($name)->headline() }}</td><td class="amount">{{ number_format((float) $amount, 2) }}</td></tr>
                    @endforeach
                    <tr class="total"><td>Total deductions</td><td class="amount">{{ number_format((float) $entry->deduction_amount, 2) }}</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="summary">
        <table><tr>
            <td><small>Gross salary</small><strong>₹{{ number_format((float) $entry->gross_amount, 2) }}</strong></td>
            <td><small>Deductions</small><strong>₹{{ number_format((float) $entry->deduction_amount, 2) }}</strong></td>
            <td><small>Net salary</small><strong class="net">₹{{ number_format((float) $entry->net_amount, 2) }}</strong></td>
        </tr></table>
    </section>

    <footer class="footer">This is a system-generated salary slip and does not require a signature. Generated {{ now()->format('d M Y, h:i A') }}.</footer>
</body>
</html>
