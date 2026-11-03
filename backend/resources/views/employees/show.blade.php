<x-app-layout>
    <x-slot name="title">{{ $employee->name }}</x-slot><x-slot name="pageTitle">Employee profile</x-slot><x-slot name="breadcrumb">HR & people / Employees / {{ $employee->employee_code }}</x-slot>
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
        $employment = $profile->employmentStatus();
        $family = $profile->emergency_family ?? [];
        $dependents = collect(data_get($family, 'dependents', []))->filter(fn ($d) => is_array($d) && filled($d['name'] ?? null));
        $safety = $profile->safety ?? [];
        $exit = $profile->exit_separation ?? [];
        $date = fn ($value) => filled($value) ? \Illuminate\Support\Carbon::parse($value)->format('d M Y') : null;
        $hiringSources = $options::options('hiring_sources');
        $currentAddress = $address($profile->current_address);
        $permanentAddress = $address($profile->permanent_address);
    @endphp
    @if(session('temporary_password'))<div class="alert alert-warning nx-flash border-0 shadow-sm d-flex align-items-start gap-2"><i data-lucide="key-round" style="width:18px;flex:0 0 auto;margin-top:2px"></i><div><strong>Temporary password:</strong> <code class="notranslate">{{ session('temporary_password') }}</code> — share it securely once. The employee can change it from their profile.</div></div>@endif

    <div class="nx-card nx-employee-hero-card mb-3">
        <div class="nx-card-body d-flex flex-wrap align-items-center gap-4">
            @if($profile->profile_picture_path)<img class="nx-employee-photo" src="{{ asset('storage/'.$profile->profile_picture_path) }}" alt="{{ $employee->name }}">@else<span class="nx-employee-initial notranslate">{{ str($employee->name)->substr(0,1)->upper() }}</span>@endif
            <div class="flex-grow-1 min-w-0">
                <div class="d-flex flex-wrap gap-2 mb-2"><span class="badge badge-soft-primary notranslate">{{ $employee->employee_code }}</span><x-pill :tone="$statusTone" :label="str($employee->status)->headline()" /><x-pill :tone="$employment['tone']" :label="$employment['label']" :title="$employment['detail']" :icon="$employment['key']==='exited' ? 'door-open' : ($employment['key']==='notice' ? 'calendar-clock' : (str_starts_with($employment['key'],'probation') ? 'timer' : 'badge-check'))" /></div>
                <h2 class="h4 fw-bold mb-1">{{ collect([$profile->salutation, $employee->name])->filter()->join(' ') }}</h2>
                <div class="text-secondary">{{ $employee->designation ?: 'Designation not set' }} · {{ $employee->department ?: 'Department not set' }}@if($profile->local_script_name) · <span class="notranslate">{{ $profile->local_script_name }}</span>@endif</div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    @forelse($languages as $language)<span class="nx-lang-pill {{ $language === $preferred ? 'is-preferred' : '' }}"><i data-lucide="{{ $language === $preferred ? 'sparkles' : 'languages' }}"></i>{{ $language }}@if($language === $preferred) · app language @endif</span>@empty<span class="nx-lang-pill"><i data-lucide="languages"></i>{{ $preferred }} (default)</span>@endforelse
                </div>
            </div>
            <div class="nx-page-actions align-self-start">
                <a class="btn btn-light border" href="{{ route('employees.index') }}"><i data-lucide="arrow-left"></i>All employees</a>
                @can('employees.update')<a class="btn btn-primary" href="{{ route('employees.edit',$employee) }}"><i data-lucide="square-pen"></i>Edit profile</a>@endcan
            </div>
        </div>
        <div class="nx-employee-stats">
            <div><strong>{{ $profile->date_of_joining?->format('d M Y') ?? '—' }}</strong><span>Joined{{ $tenure ? ' · '.$tenure : '' }}</span></div>
            <div><strong>{{ $employee->getRoleNames()->join(', ') ?: '—' }}</strong><span>App role</span></div>
            <div><strong>{{ $employee->premises->count() }}</strong><span>Assigned premises</span></div>
            <div><strong class="notranslate">₹{{ number_format((float) $profile->gross_monthly_salary) }}</strong><span>Gross monthly · {{ ucfirst($profile->pay_frequency ?? 'monthly') }}</span></div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            @if($profile->isOnProbation() || $employment['key']==='notice' || $employment['key']==='exited' || $profile->probation_confirmed_at)
            <section class="nx-card nx-card-body mb-3 border-start border-4 border-{{ $employment['tone'] }}">
                <div class="d-flex flex-wrap align-items-start gap-3">
                    <span class="nx-field-group-icon" style="--group-a:{{ $employment['tone']==='danger' ? '#e11d48' : '#0284c7' }};--group-b:{{ $employment['tone']==='danger' ? '#f97316' : '#22d3ee' }}"><i data-lucide="{{ $profile->isOnProbation() ? 'timer' : 'calendar-clock' }}"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <h3 class="h6 fw-bold mb-1">Employment status: {{ $employment['label'] }}</h3>
                        <p class="small text-secondary mb-2">{{ $employment['detail'] ?? 'Confirmed permanent employee.' }}</p>
                        <div class="row g-3 small">
                            @if($profile->isOnProbation() || $profile->probation_confirmed_at)
                            <div class="col-6 col-md-3"><div class="text-secondary">Probation period</div><div class="fw-semibold">{{ $profile->probation_months ? $profile->probation_months.' months' : '—' }}</div></div>
                            <div class="col-6 col-md-3"><div class="text-secondary">Probation ends</div><div class="fw-semibold">{{ $profile->probation_end_date?->format('d M Y') ?? '—' }}</div></div>
                            @endif
                            @if($profile->probation_confirmed_at)<div class="col-12 col-md-6"><div class="text-secondary">Confirmed permanent</div><div class="fw-semibold">{{ $profile->probation_confirmed_at->format('d M Y') }}@if($profile->probationConfirmedBy) by {{ $profile->probationConfirmedBy->name }}@endif</div></div>@endif
                            @if($profile->noticePeriodDays() !== null)<div class="col-6 col-md-3"><div class="text-secondary">Notice period</div><div class="fw-semibold">{{ $profile->noticePeriodDays() }} days</div></div>@endif
                            @if($profile->resignationDate())<div class="col-6 col-md-3"><div class="text-secondary">Resignation date</div><div class="fw-semibold">{{ $profile->resignationDate()->format('d M Y') }}</div></div>@endif
                            @if($profile->lastWorkingDate())<div class="col-6 col-md-3"><div class="text-secondary">Last working day</div><div class="fw-semibold">{{ $profile->lastWorkingDate()->format('d M Y') }}</div></div>@endif
                        </div>
                    </div>
                    @if($profile->isOnProbation())
                    @can('employees.update')
                    <form method="POST" action="{{ route('employees.confirm-probation',$employee) }}" class="d-flex flex-column gap-2" style="min-width:240px">@csrf
                        <input class="form-control form-control-sm" name="remarks" maxlength="2000" placeholder="HR review remarks (optional)">
                        <button class="btn btn-success btn-sm"><i data-lucide="badge-check" style="width:16px"></i> Confirm as permanent</button>
                        <a class="small text-secondary text-center" href="{{ route('employees.edit',$employee) }}">Extend probation instead</a>
                    </form>
                    @endcan
                    @endif
                </div>
            </section>
            @endif
            <section class="nx-card mb-3">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="shield-check"></i>Verification status</h3></div>
                <div class="nx-card-body">
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
                    <dl class="nx-dl mt-3">
                        <div><dt>Source of hiring</dt><dd @class(['text-muted-empty' => blank($verification['source_of_hiring'] ?? null)])>{{ $hiringSources[$verification['source_of_hiring'] ?? ''] ?? ($verification['source_of_hiring'] ?? '—') }}@if(($verification['source_of_hiring'] ?? null)==='internal_staff' && $referredBy) · referred by {{ $referredBy->name }}@endif</dd></div>
                        <div><dt>Medical fitness</dt><dd @class(['text-muted-empty' => blank($safety['medical_fitness'] ?? null)])>{{ $safety['medical_fitness'] ?? '—' }}@if($date($safety['medical_fitness_date'] ?? null)) · checked {{ $date($safety['medical_fitness_date']) }}@endif</dd></div>
                        <div><dt>Fitness valid till</dt><dd @class(['text-muted-empty' => blank($safety['medical_fitness_valid_till'] ?? null), 'text-danger' => filled($safety['medical_fitness_valid_till'] ?? null) && \Illuminate\Support\Carbon::parse($safety['medical_fitness_valid_till'])->isPast()])>{{ $date($safety['medical_fitness_valid_till'] ?? null) ?? '—' }}</dd></div>
                    </dl>
                    @if($identity->isNotEmpty() || !empty($verification['uan']) || !empty($verification['esic']))
                    <dl class="nx-dl mt-3">
                        @foreach(['aadhaar'=>'Aadhaar','pan'=>'PAN','passport'=>'Passport','driving_licence'=>'Driving licence','voter_id'=>'Voter ID'] as $key=>$label)@if($identity->get($key))<div><dt>{{ $label }}</dt><dd class="notranslate">{{ in_array($key,['aadhaar']) ? $mask($identity->get($key)) : $identity->get($key) }}</dd></div>@endif @endforeach
                        @if(!empty($verification['uan']))<div><dt>UAN</dt><dd class="notranslate">{{ $verification['uan'] }}</dd></div>@endif
                        @if(!empty($verification['esic']))<div><dt>ESIC</dt><dd class="notranslate">{{ $verification['esic'] }}</dd></div>@endif
                    </dl>
                    @endif
                    @if(count($profile->documents ?? []))<div class="d-flex flex-wrap gap-2 mt-3">@foreach($profile->documents as $doc)<a class="nx-doc-chip" target="_blank" rel="noopener" href="{{ asset('storage/'.($doc['path'] ?? '')) }}"><i data-lucide="file-text"></i><span>{{ $doc['name'] ?? 'Document' }}</span></a>@endforeach</div>@endif
                </div>
            </section>

            <section class="nx-card mb-3">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="briefcase-business"></i>Employment & contact</h3></div>
                <div class="nx-card-body">
                    <dl class="nx-dl">
                        @foreach(['Login email'=>$employee->email,'Phone'=>$employee->phone,'Permanent email'=>$profile->personal_email,'Role'=>$employee->getRoleNames()->join(', '),'Joining date'=>$profile->date_of_joining?->format('d M Y'),'Employment status'=>$employment['label'].($employment['detail'] ? ' · '.$employment['detail'] : ''),'Category'=>$profile->employee_category,'Job grade'=>$profile->job_grade,'Reporting manager'=>$profile->reportingManager?->name,'Date of birth'=>$profile->date_of_birth?->format('d M Y'),'Gender'=>$profile->gender ? str($profile->gender)->headline()->value() : null,'Blood group'=>$profile->blood_group] as $label=>$value)
                        <div><dt>{{ $label }}</dt><dd @class(['text-muted-empty' => blank($value)])>{{ filled($value) ? $value : '—' }}</dd></div>
                        @endforeach
                    </dl>
                    <div class="nx-subhead">Address & family</div>
                    <dl class="nx-dl">
                        <div><dt>Current address</dt><dd @class(['text-muted-empty' => blank($currentAddress)])>{{ $currentAddress ?: '—' }}</dd></div>
                        <div><dt>Permanent address</dt><dd @class(['text-muted-empty' => blank($permanentAddress)])>{{ $permanentAddress ?: '—' }}</dd></div>
                        @foreach($profile->emergencyContacts() as $index => $contact)<div><dt>Emergency contact {{ $index + 1 }}</dt><dd>{{ $contact['name'] ?? '' }}@if(!empty($contact['relation'])) ({{ $contact['relation'] }})@endif · <span class="notranslate">{{ $contact['mobile'] ?? '' }}</span></dd></div>@endforeach
                        <div><dt>Dependents</dt><dd @class(['text-muted-empty' => blank(data_get($family,'dependents_count')) && $dependents->isEmpty()])>{{ data_get($family,'dependents_count') ?? '—' }}@if($dependents->isNotEmpty()) · {{ $dependents->map(fn ($d) => trim($d['name'].' ('.collect([isset($d['gender']) ? str($d['gender'])->headline() : null, isset($d['age']) ? $d['age'].' yrs' : null])->filter()->join(', ').')', ' ()'))->join(', ') }}@endif</dd></div>
                    </dl>
                </div>
            </section>

            <section class="nx-card mb-3">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="graduation-cap"></i>Education, certifications & policies</h3></div>
                <div class="nx-card-body">
                    <dl class="nx-dl">
                        @foreach(['Education'=>[$profile->educationRecords(),'institution'], 'Certifications'=>[$profile->certificationRecords(),'issuer'], 'Policies'=>[$profile->insurancePolicies(),'number']] as $label=>[$records,$detail])
                            <div><dt>{{ $label }}</dt><dd @class(['text-muted-empty' => blank($records)])>@forelse($records as $record)<div @class(['py-1', 'border-bottom' => ! $loop->last])>{{ $record['name'] ?? '' }}@if(!empty($record[$detail]))<span class="d-block small fw-normal text-secondary">{{ $record[$detail] }}</span>@endif</div>@empty None added.@endforelse</dd></div>
                        @endforeach
                    </dl>
                </div>
            </section>
            <section class="nx-card mb-3">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="laptop"></i>Assigned assets & tools</h3></div>
                <div class="nx-card-body">
                    @forelse($profile->assignedAssets() as $asset)
                        <div class="py-2 border-bottom"><strong>{{ $asset['name'] ?? '' }}</strong>@if(!empty($asset['identifier']))<span class="small text-secondary ms-2">{{ $asset['identifier'] }}</span>@endif
                            @if(!empty($asset['items']))<div class="d-flex flex-wrap gap-2 mt-2">@foreach($asset['items'] as $item)<span class="badge badge-soft-primary">{{ $item['name'] ?? '' }}</span>@endforeach</div>@endif
                        </div>
                    @empty<p class="small text-secondary mb-0">No assets assigned.</p>@endforelse
                </div>
            </section>
            <section class="nx-card overflow-hidden">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="wallet-cards"></i>Recent payroll</h3></div>
                <div class="table-responsive"><table class="table nx-table mb-0"><thead><tr><th class="ps-4">Month</th><th>Gross</th><th>Deductions</th><th>Net</th><th class="pe-4">Status</th></tr></thead><tbody>@forelse($recentPayroll as $entry)<tr><td class="ps-4 notranslate">{{ $entry->payrollRun->payroll_month }}</td><td class="notranslate">₹{{ number_format($entry->gross_amount,2) }}</td><td class="notranslate">₹{{ number_format($entry->deduction_amount,2) }}</td><td class="fw-bold notranslate">₹{{ number_format($entry->net_amount,2) }}</td><td class="pe-4"><x-pill :tone="$entry->status==='paid'?'success':'warning'" :label="str($entry->status)->headline()" /></td></tr>@empty<tr><td colspan="5" class="ps-4 text-secondary">No payroll generated yet.</td></tr>@endforelse</tbody></table></div>
            </section>
        </div>
        <div class="col-xl-4">
            <section class="nx-card mb-3">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="map-pin-check"></i>Assigned premises</h3><span class="nx-count notranslate">{{ $employee->premises->count() }}</span></div>
                <div class="nx-card-body">
                    @forelse($employee->premises as $place)<div class="border rounded-3 p-3 {{ $loop->last ? '' : 'mb-2' }}"><div class="fw-semibold">{{ $place->name }}</div><small class="text-secondary">{{ $place->address }} · {{ $place->radius_meters }}m</small></div>@empty<p class="text-secondary small mb-0">No location assigned.</p>@endforelse
                </div>
            </section>
            <section class="nx-card mb-3">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="calendar-heart"></i>Leave balance {{ now()->year }}</h3></div>
                <div class="nx-card-body">
                    @forelse($employee->leaveBalances as $balance)@php $available = $balance->allocated-$balance->used-$balance->pending; $pct = $balance->allocated > 0 ? max(0, min(100, $available / $balance->allocated * 100)) : 0; @endphp
                    <div class="py-2 small border-bottom"><div class="d-flex justify-content-between"><span>{{ $balance->leaveType->name }}</span><strong class="notranslate">{{ number_format($available,1) }} / {{ number_format($balance->allocated,1) }}</strong></div><div class="progress mt-1" style="height:5px"><div class="progress-bar bg-{{ $balance->leaveType->color ?? 'primary' }}" style="width:{{ $pct }}%"></div></div></div>
                    @empty<p class="text-secondary small mb-0">No leave balances yet.</p>@endforelse
                </div>
            </section>
            <section class="nx-card">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="activity"></i>Recent requests</h3></div>
                <div class="nx-card-body">
                    @forelse($recentLeaves as $leave)<div class="d-flex justify-content-between align-items-center py-2 border-bottom small"><div><div class="fw-semibold">{{ $leave->leaveType->name }}</div><div class="text-secondary">{{ $leave->from_date->format('d M') }} – {{ $leave->to_date->format('d M Y') }}</div></div><x-pill :tone="$leave->status==='approved'?'success':($leave->status==='rejected'?'danger':'warning')" :label="ucfirst($leave->status)" /></div>@empty<p class="text-secondary small mb-0">No leave requests.</p>@endforelse
                    @foreach($recentVouchers as $voucher)<div class="d-flex justify-content-between align-items-center py-2 border-bottom small"><div><div class="fw-semibold">{{ $voucher->category }} <span class="text-secondary notranslate">{{ $voucher->voucher_no }}</span></div><div class="text-secondary notranslate">₹{{ number_format($voucher->amount,2) }} · {{ $voucher->expense_date->format('d M Y') }}</div></div><x-pill :tone="$voucher->status==='approved'?'success':($voucher->status==='rejected'?'danger':'warning')" :label="ucfirst($voucher->status)" /></div>@endforeach
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
