<x-app-layout>
    <x-slot name="title">{{ $employee->name }}</x-slot><x-slot name="pageTitle">Employee profile</x-slot><x-slot name="breadcrumb">HR / Employees / {{ $employee->employee_code }}</x-slot>
    @php
        $profile = $employee->employeeProfile;
        $options = \App\Services\EmployeeMasterOptions::class;
        $languages = collect($profile->languages ?? [])->filter()->values();
        $preferred = $options::preferredLanguageFor($employee);
        $verification = $profile->verification ?? [];
        $identity = collect($profile->identity_documents ?? [])->filter();
        $mask = fn(?string $value) => $value ? str_repeat('•', max(strlen($value) - 4, 0)).substr($value, -4) : '—';
        $address = fn($block) => collect([data_get($block,'line1'), data_get($block,'city'), data_get($block,'district'), data_get($block,'state'), data_get($block,'pin')])->filter()->join(', ');
        $tenure = $profile->date_of_joining ? $profile->date_of_joining->diffForHumans(now(), ['parts' => 2, 'short' => true, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) : null;
        $statusTone = ['active'=>'success','inactive'=>'secondary','on_leave'=>'warning','suspended'=>'danger'][$employee->status] ?? 'secondary';
    @endphp
    @if(session('temporary_password'))<div class="alert alert-warning nx-flash border-0 shadow-sm d-flex align-items-start gap-2"><i data-lucide="key-round" style="width:18px;flex:0 0 auto;margin-top:2px"></i><div><strong>Temporary password:</strong> <code class="notranslate">{{ session('temporary_password') }}</code> — share it securely once. The employee can change it from their profile.</div></div>@endif

    <div class="nx-card nx-employee-hero-card mb-4">
        <div class="p-4 d-flex flex-wrap align-items-center gap-4">
            @if($profile->profile_picture_path)<img class="nx-employee-photo" src="{{ asset('storage/'.$profile->profile_picture_path) }}" alt="{{ $employee->name }}">@else<span class="nx-employee-initial notranslate">{{ str($employee->name)->substr(0,1)->upper() }}</span>@endif
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap gap-2 mb-2"><span class="badge badge-soft-primary notranslate">{{ $employee->employee_code }}</span><span class="badge badge-soft-{{ $statusTone }}">{{ str($employee->status)->headline() }}</span>@if($profile->employment_type)<span class="badge bg-light text-dark border">{{ str($profile->employment_type)->headline() }}</span>@endif</div>
                <h2 class="h4 fw-bold mb-1">{{ collect([$profile->salutation, $employee->name])->filter()->join(' ') }}</h2>
                <div class="text-secondary">{{ $employee->designation ?: 'Designation not set' }} · {{ $employee->department ?: 'Department not set' }}@if($profile->local_script_name) · <span class="notranslate">{{ $profile->local_script_name }}</span>@endif</div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    @forelse($languages as $language)<span class="nx-lang-pill {{ $language === $preferred ? 'is-preferred' : '' }}"><i data-lucide="{{ $language === $preferred ? 'sparkles' : 'languages' }}"></i>{{ $language }}@if($language === $preferred) · app language @endif</span>@empty<span class="nx-lang-pill"><i data-lucide="languages"></i>{{ $preferred }} (default)</span>@endforelse
                </div>
            </div>
            <div class="d-flex flex-column gap-2">
                @can('employees.update')<a class="btn btn-primary" href="{{ route('employees.edit',$employee) }}"><i data-lucide="square-pen" style="width:17px"></i>Edit profile</a>@endcan
                <a class="btn btn-light border" href="{{ route('employees.index') }}"><i data-lucide="arrow-left" style="width:17px"></i>All employees</a>
            </div>
        </div>
        <div class="nx-employee-stats">
            <div><strong>{{ $profile->date_of_joining?->format('d M Y') ?? '—' }}</strong><span>Joined{{ $tenure ? ' · '.$tenure : '' }}</span></div>
            <div><strong>{{ $employee->getRoleNames()->join(', ') ?: '—' }}</strong><span>App role</span></div>
            <div><strong>{{ $employee->premises->count() }}</strong><span>Assigned premises</span></div>
            <div><strong class="notranslate">₹{{ number_format((float) $profile->gross_monthly_salary) }}</strong><span>Gross monthly · {{ ucfirst($profile->pay_frequency ?? 'monthly') }}</span></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="nx-card p-4 mb-4">
                <h3 class="h6 fw-bold mb-3 d-flex align-items-center gap-2"><span class="nx-field-group-icon" style="--group-a:#0284c7;--group-b:#22d3ee"><i data-lucide="shield-check"></i></span>Verification status</h3>
                <div class="row g-3">
                    @foreach(['police_verification'=>['Police verification','siren','police_verification_reference','Reference'],'background_check'=>['Background verification','search-check','background_check_agency','Agency']] as $key=>[$label,$icon,$refKey,$refLabel])
                    @php $status = $verification[$key] ?? 'not_started'; $tone = $options::verificationTone($status); @endphp
                    <div class="col-md-6"><div class="nx-verify-tile h-100">
                        <span class="nx-field-group-icon" style="--group-a:{{ $key==='police_verification' ? '#d97706' : '#059669' }};--group-b:{{ $key==='police_verification' ? '#f59e0b' : '#84cc16' }}"><i data-lucide="{{ $icon }}"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <strong>{{ $label }}</strong>
                            <small>@if(!empty($verification[$key.'_date'])) Completed {{ \Illuminate\Support\Carbon::parse($verification[$key.'_date'])->format('d M Y') }}@else No completion date @endif @if(!empty($verification[$refKey])) · {{ $refLabel }}: <span class="notranslate">{{ $verification[$refKey] }}</span>@endif</small>
                            @if(!empty($verification[$key.'_remarks']))<small class="text-dark mt-1">{{ $verification[$key.'_remarks'] }}</small>@endif
                        </div>
                        <span class="nx-verify-status tone-{{ $tone }}">{{ $options::verificationLabel($status) }}</span>
                    </div></div>
                    @endforeach
                </div>
                @if($identity->isNotEmpty() || !empty($verification['uan']) || !empty($verification['esic']))
                <div class="row g-3 small mt-1">
                    @foreach(['aadhaar'=>'Aadhaar','pan'=>'PAN','passport'=>'Passport','driving_licence'=>'Driving licence','voter_id'=>'Voter ID'] as $key=>$label)@if($identity->get($key))<div class="col-6 col-md-4"><div class="text-secondary">{{ $label }}</div><div class="fw-semibold notranslate">{{ in_array($key,['aadhaar']) ? $mask($identity->get($key)) : $identity->get($key) }}</div></div>@endif @endforeach
                    @if(!empty($verification['uan']))<div class="col-6 col-md-4"><div class="text-secondary">UAN</div><div class="fw-semibold notranslate">{{ $verification['uan'] }}</div></div>@endif
                    @if(!empty($verification['esic']))<div class="col-6 col-md-4"><div class="text-secondary">ESIC</div><div class="fw-semibold notranslate">{{ $verification['esic'] }}</div></div>@endif
                </div>
                @endif
                @if(count($profile->documents ?? []))<div class="d-flex flex-wrap gap-2 mt-3">@foreach($profile->documents as $doc)<a class="nx-doc-chip" target="_blank" rel="noopener" href="{{ asset('storage/'.($doc['path'] ?? '')) }}"><i data-lucide="file-text"></i><span>{{ $doc['name'] ?? 'Document' }}</span></a>@endforeach</div>@endif
            </section>

            <section class="nx-card p-4 mb-4">
                <h3 class="h6 fw-bold mb-3 d-flex align-items-center gap-2"><span class="nx-field-group-icon" style="--group-a:#7c3aed;--group-b:#a855f7"><i data-lucide="briefcase-business"></i></span>Employment & contact</h3>
                <div class="row g-3 small">
                    @foreach(['Work email'=>$employee->email,'Phone'=>$employee->phone,'Personal email'=>$profile->personal_email,'Role'=>$employee->getRoleNames()->join(', '),'Joining date'=>$profile->date_of_joining?->format('d M Y'),'Employment type'=>str($profile->employment_type)->headline(),'Category'=>$profile->employee_category,'Job grade'=>$profile->job_grade,'Reporting manager'=>$profile->reportingManager?->name,'Date of birth'=>$profile->date_of_birth?->format('d M Y'),'Gender'=>str($profile->gender)->headline(),'Blood group'=>$profile->blood_group] as $label=>$value)
                    <div class="col-6 col-md-4"><div class="text-secondary">{{ $label }}</div><div class="fw-semibold text-break">{{ $value ?: '—' }}</div></div>
                    @endforeach
                    <div class="col-md-6"><div class="text-secondary">Current address</div><div class="fw-semibold">{{ $address($profile->current_address) ?: '—' }}</div></div>
                    <div class="col-md-6"><div class="text-secondary">Permanent address</div><div class="fw-semibold">{{ $address($profile->permanent_address) ?: '—' }}</div></div>
                    @if(data_get($profile->emergency_family,'emergency_name'))<div class="col-md-6"><div class="text-secondary">Emergency contact</div><div class="fw-semibold">{{ data_get($profile->emergency_family,'emergency_name') }}@if(data_get($profile->emergency_family,'relation')) ({{ data_get($profile->emergency_family,'relation') }})@endif · <span class="notranslate">{{ data_get($profile->emergency_family,'mobile') }}</span></div></div>@endif
                </div>
            </section>

            <section class="nx-card p-4">
                <h3 class="h6 fw-bold mb-3 d-flex align-items-center gap-2"><span class="nx-field-group-icon" style="--group-a:#059669;--group-b:#84cc16"><i data-lucide="wallet-cards"></i></span>Recent payroll</h3>
                <div class="table-responsive"><table class="table nx-table mb-0"><thead><tr><th>Month</th><th>Gross</th><th>Deductions</th><th>Net</th><th>Status</th></tr></thead><tbody>@forelse($recentPayroll as $entry)<tr><td class="notranslate">{{ $entry->payrollRun->payroll_month }}</td><td class="notranslate">₹{{ number_format($entry->gross_amount,2) }}</td><td class="notranslate">₹{{ number_format($entry->deduction_amount,2) }}</td><td class="fw-bold notranslate">₹{{ number_format($entry->net_amount,2) }}</td><td><span class="badge {{ $entry->status==='paid'?'badge-soft-success':'badge-soft-warning' }}">{{ str($entry->status)->headline() }}</span></td></tr>@empty<tr><td colspan="5" class="text-secondary">No payroll generated yet.</td></tr>@endforelse</tbody></table></div>
            </section>
        </div>
        <div class="col-xl-4">
            <section class="nx-card p-4 mb-4">
                <h3 class="h6 fw-bold mb-3 d-flex align-items-center gap-2"><span class="nx-field-group-icon" style="--group-a:#0d9488;--group-b:#34d399"><i data-lucide="map-pin-check"></i></span>Assigned premises</h3>
                @forelse($employee->premises as $place)<div class="border rounded-3 p-3 mb-2"><div class="fw-semibold">{{ $place->name }}</div><small class="text-secondary">{{ $place->address }} · {{ $place->radius_meters }}m</small></div>@empty<p class="text-secondary small mb-0">No location assigned.</p>@endforelse
            </section>
            <section class="nx-card p-4 mb-4">
                <h3 class="h6 fw-bold mb-3 d-flex align-items-center gap-2"><span class="nx-field-group-icon" style="--group-a:#d97706;--group-b:#f59e0b"><i data-lucide="calendar-heart"></i></span>Leave balance {{ now()->year }}</h3>
                @forelse($employee->leaveBalances as $balance)@php $available = $balance->allocated-$balance->used-$balance->pending; $pct = $balance->allocated > 0 ? max(0, min(100, $available / $balance->allocated * 100)) : 0; @endphp
                <div class="py-2 small border-bottom"><div class="d-flex justify-content-between"><span>{{ $balance->leaveType->name }}</span><strong class="notranslate">{{ number_format($available,1) }} / {{ number_format($balance->allocated,1) }}</strong></div><div class="progress mt-1" style="height:5px"><div class="progress-bar bg-{{ $balance->leaveType->color ?? 'primary' }}" style="width:{{ $pct }}%"></div></div></div>
                @empty<p class="text-secondary small mb-0">No leave balances yet.</p>@endforelse
            </section>
            <section class="nx-card p-4">
                <h3 class="h6 fw-bold mb-3 d-flex align-items-center gap-2"><span class="nx-field-group-icon" style="--group-a:#e11d48;--group-b:#f97316"><i data-lucide="activity"></i></span>Recent requests</h3>
                @forelse($recentLeaves as $leave)<div class="d-flex justify-content-between align-items-center py-2 border-bottom small"><div><div class="fw-semibold">{{ $leave->leaveType->name }}</div><div class="text-secondary">{{ $leave->from_date->format('d M') }} – {{ $leave->to_date->format('d M Y') }}</div></div><span class="badge {{ $leave->status==='approved'?'badge-soft-success':($leave->status==='rejected'?'badge-soft-danger':'badge-soft-warning') }}">{{ ucfirst($leave->status) }}</span></div>@empty<p class="text-secondary small mb-0">No leave requests.</p>@endforelse
                @foreach($recentVouchers as $voucher)<div class="d-flex justify-content-between align-items-center py-2 border-bottom small"><div><div class="fw-semibold">{{ $voucher->category }} <span class="text-secondary notranslate">{{ $voucher->voucher_no }}</span></div><div class="text-secondary notranslate">₹{{ number_format($voucher->amount,2) }} · {{ $voucher->expense_date->format('d M Y') }}</div></div><span class="badge {{ $voucher->status==='approved'?'badge-soft-success':($voucher->status==='rejected'?'badge-soft-danger':'badge-soft-warning') }}">{{ ucfirst($voucher->status) }}</span></div>@endforeach
            </section>
        </div>
    </div>
</x-app-layout>
