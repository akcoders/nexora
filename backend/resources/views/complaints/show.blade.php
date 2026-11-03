@use('App\Services\ComplaintOptions')
<x-app-layout>
    <x-slot name="title">{{ $complaint->complaint_no }}</x-slot>
    <x-slot name="pageTitle">Complaint {{ $complaint->complaint_no }}</x-slot>
    <x-slot name="breadcrumb">Complaints / Register / {{ $complaint->complaint_no }}</x-slot>
    @php
        $meta = $complaint->statusMeta();
        $sla = $complaint->slaState();
        $isPending = in_array($complaint->status, ['pending', 'under_observation'], true);
        $bucket = $isPending ? $complaint->pendingBucket() : null;
        $canUpdate = auth()->user()->can('update', $complaint);
        $canAssign = auth()->user()->can('assign', $complaint);
        $canClose = auth()->user()->can('close', $complaint);
        $canReopen = auth()->user()->can('reopen', $complaint);
        $canReport = $openVisit && auth()->user()->can('report', [$complaint, $openVisit]);
        $tab = request('tab', 'overview');
        $label = fn (string $list, ?string $value) => ComplaintOptions::label($list, $value) ?? '—';
        $date = fn ($value, string $format = 'd M Y') => $value ? $value->format($format) : '—';
        $dd = fn ($value) => filled($value) ? e($value) : '<span class="text-secondary fw-normal">—</span>';
        $usedParts = $complaint->parts->where('kind', 'used');
        $recommendedParts = $complaint->parts->where('kind', 'recommended');
        $flow = [
            ['Registered', 'file-plus-2', true],
            ['Assigned', 'user-check', $complaint->visits->isNotEmpty()],
            ['On site', 'map-pin', $complaint->visits->contains(fn ($visit) => $visit->arrived_at || $visit->status === 'completed')],
            [$isPending ? $meta['label'] : 'Work completed', $isPending ? 'hourglass' : 'circle-check', in_array($complaint->status, ['completed', 'closed'], true)],
            ['Closure checks', 'clipboard-check', $complaint->status === 'closed'],
            ['Closed', 'lock', $complaint->status === 'closed'],
        ];
        $currentStep = collect($flow)->search(fn ($step) => ! $step[2]);
        $tabs = [
            'overview' => ['Overview', 'layout-list', null],
            'visits' => ['Visits', 'route', $complaint->visits->count()],
            'parts' => ['Parts', 'cog', $complaint->parts->count()],
            'pending' => ['Pending & follow-up', 'hourglass', null],
            'commercial' => ['Commercial', 'indian-rupee', null],
            'closure' => ['Closure', 'clipboard-check', null],
            'activity' => ['Activity', 'history', $complaint->activities->count()],
        ];
        $staffOptions = $staff;
    @endphp

    @if($errors->any())
        <div class="alert alert-danger d-flex gap-2"><i data-lucide="circle-alert" style="width:18px;flex:0 0 auto"></i><div><strong>Could not save.</strong> {{ $errors->first() }}</div></div>
    @endif

    {{-- Header --}}
    <section class="nx-card mb-3">
        <div class="nx-complaint-head">
            <div class="min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="nx-complaint-no notranslate">{{ $complaint->complaint_no }}</span>
                    <x-pill :tone="$meta['tone']" :label="$meta['label']" />
                    <x-pill :tone="$complaint->priorityTone()" :label="$complaint->priorityLabel().' priority'" />
                    <span class="nx-tag"><i data-lucide="{{ ComplaintOptions::CALL_TYPES[$complaint->call_type]['icon'] ?? 'tag' }}"></i>{{ $complaint->callTypeLabel() }}</span>
                    @if($bucket)<x-pill :tone="$bucket['tone']" :label="'Pending '.$complaint->pendingAgeInDays().' days · '.$bucket['label']" />@endif
                </div>
                <h2>{{ $complaint->nature_of_complaint }}</h2>
                <div class="nx-complaint-meta">
                    <span><i data-lucide="building-2"></i><a class="notranslate" href="{{ route('customers.show', $complaint->customer_id) }}">{{ $complaint->customer?->name }}</a></span>
                    @if($complaint->branch || $complaint->site_location)<span><i data-lucide="map-pin"></i>{{ collect([$complaint->branch?->name, $complaint->site_location])->filter()->unique()->join(' · ') }}</span>@endif
                    <span><i data-lucide="calendar"></i>{{ $complaint->registered_at->format('d M Y, h:i A') }}</span>
                    @if($complaint->technician)<span><i data-lucide="hard-hat"></i>{{ $complaint->technician->name }}</span>@endif
                    <span><i data-lucide="air-vent"></i>{{ $complaint->equipmentSummary() }}</span>
                </div>
            </div>
            <div class="nx-page-actions">
                @if($canReport)<a class="btn btn-primary" href="{{ route('complaints.visits.edit', [$complaint, $openVisit]) }}"><i data-lucide="clipboard-pen"></i>{{ $openVisit->label() }} visit report</a>@endif
                @if($canAssign && ! $openVisit)<button class="btn {{ $canReport ? 'btn-light border' : 'btn-primary' }}" data-bs-toggle="modal" data-bs-target="#assignModal"><i data-lucide="user-plus"></i>Assign {{ $complaint->visitLabel($complaint->visits->max('visit_no') + 1) }}</button>@endif
                @if($canUpdate && ! $complaint->isClosed())<button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#pendingModal"><i data-lucide="hourglass"></i>{{ $complaint->status === 'pending' ? 'Update pending' : 'Mark pending' }}</button>@endif
                @if($canUpdate && $isPending)<button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#followUpModal"><i data-lucide="phone-forwarded"></i>Follow-up</button>@endif
                @if($canClose && in_array($complaint->status, ['completed', 'under_observation'], true))<a class="btn btn-success" href="{{ route('complaints.show', [$complaint, 'tab' => 'closure']) }}"><i data-lucide="lock"></i>Close complaint</a>@endif
                <a class="btn btn-light border" href="{{ route('complaints.print', $complaint) }}" target="_blank" rel="noopener"><i data-lucide="printer"></i>FSR</a>
                <div class="dropdown">
                    <button class="btn btn-light border btn-icon" data-bs-toggle="dropdown" aria-label="More actions"><i data-lucide="ellipsis-vertical"></i></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        @if($canUpdate)<button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#detailsModal"><i data-lucide="square-pen"></i>Edit complaint details</button>@endif
                        @can('complaints.create')<a class="dropdown-item" href="{{ route('complaints.create', ['customer' => $complaint->customer_id]) }}"><i data-lucide="copy-plus"></i>New complaint for this customer</a>@endcan
                        @if($canReopen)<button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#reopenModal"><i data-lucide="rotate-ccw"></i>Reopen complaint</button>@endif
                        @if($canClose)<div class="dropdown-divider"></div><button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"><i data-lucide="ban"></i>Cancel complaint</button>@endif
                    </div>
                </div>
            </div>
        </div>
        <div class="nx-flow" style="--steps: {{ count($flow) }}">
            @foreach($flow as $index => [$stepLabel, $icon, $done])
                <div class="nx-flow-step {{ $done ? 'done' : ($index === $currentStep ? ($isPending ? 'warn' : 'current') : '') }}"><span>@if($done)<i data-lucide="check"></i>@else{{ $index + 1 }}@endif</span>{{ $stepLabel }}</div>
            @endforeach
        </div>
    </section>

    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3"><x-kpi label="Resolution SLA" text :value="$sla['label']" icon="timer" :tone="$sla['tone']" :foot="'Due '.$date($complaint->resolution_due_at, 'd M, h:i A')" /></div>
        <div class="col-6 col-lg-3"><x-kpi label="First response" text :value="$complaint->first_response_at ? $complaint->first_response_at->format('d M, h:i A') : 'Awaited'" icon="clock" :tone="$complaint->first_response_at && $complaint->response_due_at && $complaint->first_response_at->lte($complaint->response_due_at) ? 'success' : ($complaint->first_response_at ? 'warning' : 'info')" :foot="'Due '.$date($complaint->response_due_at, 'd M, h:i A')" /></div>
        <div class="col-6 col-lg-3"><x-kpi label="Visits" :value="$complaint->visits->count()" icon="route" tone="primary" :foot="$openVisit ? $openVisit->label().' '.$openVisit->statusMeta()['label'] : 'No open visit'" /></div>
        <div class="col-6 col-lg-3"><x-kpi label="Complaint age" :value="$complaint->ageInDays().' days'" icon="calendar-days" tone="secondary" :foot="$complaint->follow_up_date ? 'Next follow-up '.$complaint->follow_up_date->format('d M Y') : null" /></div>
    </div>

    <section class="nx-card">
        <div class="nx-card-tabs">
            <ul class="nav nav-tabs nx-tabs-scroll border-0" role="tablist">
                @foreach($tabs as $key => [$tabLabel, $icon, $count])
                    <li class="nav-item" role="presentation"><button class="nav-link {{ $tab === $key ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-{{ $key }}" type="button" role="tab" data-tab-key="{{ $key }}"><i data-lucide="{{ $icon }}"></i>{{ $tabLabel }}@if($count !== null)<span class="nx-count notranslate">{{ $count }}</span>@endif</button></li>
                @endforeach
            </ul>
        </div>
        <div class="tab-content">
            {{-- Overview --}}
            <div class="tab-pane fade {{ $tab === 'overview' ? 'show active' : '' }}" id="tab-overview" role="tabpanel">
                <div class="nx-card-body">
                    @if($complaint->visits->isNotEmpty())
                        <div class="nx-subhead mt-0">Visit history</div>
                        <div class="nx-card mb-3"><div class="nx-history-strip">
                            @foreach($complaint->visits as $visit)
                                @php $outcome = $visit->outcomeMeta(); @endphp
                                <div><div class="d-flex align-items-center gap-2 mb-1"><strong class="notranslate">{{ $visit->label() }}</strong><x-pill :tone="$outcome['tone'] ?? $visit->statusMeta()['tone']" :label="$outcome ? str($outcome['label'])->before(' -') : $visit->statusMeta()['label']" /></div><div class="small">{{ $visit->technician?->name }} · {{ $date($visit->ended_at ?? $visit->appointment_at ?? $visit->assigned_at) }}</div><div class="small text-secondary text-truncate" title="{{ $visit->work_performed ?? $visit->reason_for_visit }}">{{ $visit->work_performed ?? $visit->reason_for_visit ?? '—' }}</div></div>
                            @endforeach
                        </div></div>
                    @endif
                    <div class="row g-3">
                        <div class="col-xl-6">
                            <div class="nx-subhead mt-0">Call details</div>
                            <dl class="nx-dl">
                                <div><dt>Complaint no</dt><dd class="notranslate">{{ $complaint->complaint_no }}</dd></div>
                                <div><dt>Date & time</dt><dd>{{ $complaint->registered_at->format('d M Y, h:i A') }}</dd></div>
                                <div><dt>Mode of call</dt><dd>{{ $label('call_modes', $complaint->call_mode) }}</dd></div>
                                <div><dt>Complaint source</dt><dd>{{ $label('sources', $complaint->complaint_source) }}</dd></div>
                                <div><dt>Complaint given by</dt><dd>{!! $dd($complaint->complaint_given_by) !!}</dd></div>
                                <div><dt>Call taken by</dt><dd>{!! $dd($complaint->callTaker?->name) !!}</dd></div>
                                <div><dt>Type of call</dt><dd>{{ $complaint->callTypeLabel() }}</dd></div>
                                <div><dt>Priority</dt><dd>{{ $complaint->priorityLabel() }}</dd></div>
                                <div><dt>SLA response / resolution</dt><dd>{{ $complaint->sla_response_hours }}h / {{ $complaint->sla_resolution_hours }}h</dd></div>
                                @if($complaint->description)<div style="grid-column:1/-1"><dt>Description</dt><dd class="fw-normal" style="white-space:pre-line">{{ $complaint->description }}</dd></div>@endif
                            </dl>
                        </div>
                        <div class="col-xl-6">
                            <div class="nx-subhead mt-0">Customer & site</div>
                            <dl class="nx-dl">
                                <div><dt>Customer ID</dt><dd class="notranslate">{{ $complaint->customer?->code }}</dd></div>
                                <div><dt>Customer name</dt><dd class="notranslate">{{ $complaint->customer?->name }}</dd></div>
                                <div><dt>Branch / area</dt><dd>{!! $dd($complaint->branch?->name) !!}</dd></div>
                                <div><dt>Site / location</dt><dd>{!! $dd($complaint->site_location) !!}</dd></div>
                                <div><dt>Unit location</dt><dd>{!! $dd($complaint->unit_location) !!}</dd></div>
                                <div><dt>Contact person</dt><dd>{!! $dd($complaint->contact_person) !!}</dd></div>
                                <div><dt>Contact no</dt><dd class="notranslate">@if($complaint->contact_no)<a href="tel:{{ $complaint->contact_no }}">{{ $complaint->contact_no }}</a>@else — @endif</dd></div>
                                <div><dt>Alternate no</dt><dd class="notranslate">{!! $dd($complaint->alternate_no) !!}</dd></div>
                                <div style="grid-column:1/-1"><dt>Address</dt><dd>{!! $dd($complaint->address) !!}</dd></div>
                            </dl>
                        </div>
                        <div class="col-xl-6">
                            <div class="nx-subhead">Equipment</div>
                            <dl class="nx-dl">
                                <div><dt>Equipment ID</dt><dd class="notranslate">{!! $dd($complaint->equipment_tag) !!}</dd></div>
                                <div><dt>Equipment type</dt><dd>{{ $label('equipment_types', $complaint->equipment_type) }}</dd></div>
                                <div><dt>Criticality</dt><dd>{{ $label('criticality', $complaint->equipment_criticality) }}</dd></div>
                                <div><dt>Make</dt><dd>{!! $dd($complaint->make) !!}</dd></div>
                                <div><dt>Model</dt><dd>{!! $dd($complaint->model) !!}</dd></div>
                                <div><dt>Capacity</dt><dd>{!! $dd($complaint->capacity) !!}</dd></div>
                                <div><dt>Unit serial no</dt><dd class="notranslate">{!! $dd($complaint->serial_no) !!}</dd></div>
                                <div><dt>Refrigerant</dt><dd>{{ $label('refrigerants', $complaint->refrigerant) }}</dd></div>
                                <div><dt>Compressor type</dt><dd>{{ $label('compressor_types', $complaint->compressor_type) }}</dd></div>
                                <div><dt>Technology</dt><dd>{{ $label('technologies', $complaint->technology) }}</dd></div>
                                <div><dt>Power supply</dt><dd>{{ $label('power_supplies', $complaint->power_supply) }}</dd></div>
                                <div><dt>Installation date</dt><dd>{{ $date($complaint->installation_date) }}</dd></div>
                                <div><dt>Last service attended</dt><dd>{{ $date($complaint->last_service_date) }}</dd></div>
                                <div><dt>Last PM date</dt><dd>{{ $date($complaint->last_pm_date) }}</dd></div>
                            </dl>
                        </div>
                        <div class="col-xl-6">
                            <div class="nx-subhead">Warranty & AMC</div>
                            <dl class="nx-dl">
                                <div><dt>Category</dt><dd>{{ $label('service_categories', $complaint->service_category) }}</dd></div>
                                <div><dt>AMC / contract no</dt><dd class="notranslate">{!! $dd($complaint->contract_no) !!}</dd></div>
                                <div><dt>Warranty / AMC expiry</dt><dd class="{{ $complaint->contract_expiry?->isPast() ? 'text-danger' : '' }}">{{ $date($complaint->contract_expiry) }}@if($complaint->contract_expiry?->isPast()) (expired)@endif</dd></div>
                            </dl>
                            @if($relatedComplaints->isNotEmpty())
                                <div class="nx-subhead">Other complaints from this customer</div>
                                <ul class="nx-mini-list">
                                    @foreach($relatedComplaints as $related)
                                        <li><a class="nx-code notranslate flex-shrink-0" href="{{ route('complaints.show', $related) }}">{{ $related->complaint_no }}</a><span class="text-truncate flex-grow-1 min-w-0 px-2">{{ $related->nature_of_complaint }}</span><x-pill :tone="$related->statusMeta()['tone']" :label="$related->statusMeta()['label']" /></li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Visits --}}
            <div class="tab-pane fade {{ $tab === 'visits' ? 'show active' : '' }}" id="tab-visits" role="tabpanel">
                <div class="nx-card-body">
                    <div class="nx-section-head">
                        <div><h3>Visit history</h3><div class="small text-secondary">Each visit (V01, V02 …) keeps its own team, times, diagnosis and result. The complaint stays open until it is closed.</div></div>
                        @if($canAssign && ! $openVisit)<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignModal"><i data-lucide="user-plus"></i>Assign {{ $complaint->visitLabel($complaint->visits->max('visit_no') + 1) }}</button>@endif
                    </div>
                    @forelse($complaint->visits->sortByDesc('visit_no') as $visit)
                        @php $vMeta = $visit->statusMeta(); $outcome = $visit->outcomeMeta(); $diagnosisFields = ComplaintOptions::diagnosisFieldsFor($complaint->equipment_type); @endphp
                        <article class="nx-visit">
                            <header class="nx-visit-head">
                                <span class="nx-visit-no notranslate" style="color:var(--nx-primary);background:var(--nx-primary-soft)">{{ $visit->label() }}</span>
                                <div class="min-w-0 flex-grow-1">
                                    <div class="d-flex flex-wrap align-items-center gap-2"><strong>{{ $visit->reason_for_visit ?: 'Visit' }}</strong><x-pill :tone="$vMeta['tone']" :label="$vMeta['label']" />@if($outcome)<x-pill :tone="$outcome['tone']" :label="$outcome['label']" />@endif</div>
                                    <div class="nx-visit-summary mt-1">
                                        <span><i data-lucide="users"></i>{{ $visit->teamNames() }}</span>
                                        <span><i data-lucide="user-cog"></i>Assigned by {{ $visit->assigner?->name ?? '—' }} · {{ $visit->assigned_at->format('d M, h:i A') }} · {{ $label('assignment_methods', $visit->assignment_method) }}</span>
                                        @if($visit->appointment_at)<span><i data-lucide="calendar-clock"></i>Appointment {{ $visit->appointment_at->format('d M Y, h:i A') }}</span>@endif
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    @if($visit->isOpen() && auth()->user()->can('report', [$complaint, $visit]))<a class="btn btn-sm btn-primary" href="{{ route('complaints.visits.edit', [$complaint, $visit]) }}"><i data-lucide="clipboard-pen"></i>Visit report</a>@endif
                                    @if($visit->isOpen() && $canAssign)<button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#cancelVisitModal" data-visit-cancel="{{ route('complaints.visits.cancel', [$complaint, $visit]) }}" data-visit-label="{{ $visit->label() }}"><i data-lucide="x"></i>Cancel visit</button>@endif
                                </div>
                            </header>
                            <div class="nx-visit-body">
                                @if($visit->special_instructions)<div class="alert alert-info py-2 small"><i data-lucide="info" style="width:14px"></i> {{ $visit->special_instructions }}</div>@endif
                                <dl class="nx-dl mb-3">
                                    <div><dt>Technician accepted</dt><dd>{{ $date($visit->accepted_at, 'd M, h:i A') }}</dd></div>
                                    <div><dt>En route</dt><dd>{{ $date($visit->en_route_at, 'd M, h:i A') }}</dd></div>
                                    <div><dt>Arrival</dt><dd>{{ $date($visit->arrived_at, 'd M, h:i A') }}</dd></div>
                                    <div><dt>Site start</dt><dd>{{ $date($visit->started_at, 'd M, h:i A') }}</dd></div>
                                    <div><dt>Site end</dt><dd>{{ $date($visit->ended_at, 'd M, h:i A') }}</dd></div>
                                    <div><dt>Travel time</dt><dd>{{ $visit->travel_minutes !== null ? $visit->travel_minutes.' min' : '—' }}</dd></div>
                                    <div><dt>Labour hours</dt><dd>{{ $visit->labour_hours !== null ? number_format((float) $visit->labour_hours, 2).' h' : '—' }}</dd></div>
                                    <div><dt>Final result</dt><dd>{{ $label('final_results', $visit->final_result) }}</dd></div>
                                </dl>
                                @if($visit->checks)
                                    <div class="nx-tl-tags mb-3">
                                        @foreach(['unit_checked_before' => 'Unit checked before service', 'photo_taken' => 'Photograph taken', 'informed_customer' => 'Customer informed', 'informed_back_office' => 'Back office informed', 'unit_checked_after' => 'Unit checked after service', 'equipment_working_normally' => 'Working normally', 'customer_confirmation' => 'Customer confirmation'] as $checkKey => $checkLabel)
                                            @if($visit->check($checkKey))<span class="badge badge-soft-{{ $visit->check($checkKey) === 'yes' ? 'success' : 'danger' }}"><i data-lucide="{{ $visit->check($checkKey) === 'yes' ? 'check' : 'x' }}"></i>{{ $checkLabel }}</span>@endif
                                        @endforeach
                                    </div>
                                @endif
                                @if($visit->abnormality_found)<p class="small mb-2"><strong>Abnormality found:</strong> {{ $visit->abnormality_found }}</p>@endif
                                @if($visit->diagnosis)
                                    <div class="nx-subhead mt-2">Technician diagnosis</div>
                                    <div class="nx-readings mb-3">
                                        @foreach($visit->diagnosis as $readingKey => $reading)
                                            @php $field = ComplaintOptions::DIAGNOSIS_FIELDS[$readingKey] ?? ['label' => str($readingKey)->headline(), 'type' => 'text']; @endphp
                                            <div><small>{{ $field['label'] }}</small><strong class="notranslate">{{ match ($field['type']) { 'condition' => ComplaintOptions::label('conditions', $reading), 'yes_no' => ComplaintOptions::label('yes_no', $reading), default => $reading } }}@if(isset($field['unit']) && is_numeric($reading)) {{ $field['unit'] }}@endif</strong></div>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="row g-3">
                                    @foreach(['observation' => "Technician's observation", 'initial_diagnosis' => 'Initial diagnosis', 'problem_diagnosis' => 'Problem diagnosis (what was wrong)', 'work_performed' => 'Work performed'] as $textKey => $textLabel)
                                        @if($visit->{$textKey})<div class="col-md-6"><div class="form-text fw-semibold mt-0 mb-1">{{ $textLabel }}</div><div class="nx-tl-body mt-0">{{ $visit->{$textKey} }}</div></div>@endif
                                    @endforeach
                                </div>
                                @if($visit->fault_category || $visit->fault_code || $visit->root_cause)
                                    <div class="nx-tl-tags mt-2">@if($visit->fault_category)<span class="nx-tag">Fault: {{ $visit->fault_category }}</span>@endif @if($visit->fault_code)<span class="nx-tag notranslate">Code: {{ $visit->fault_code }}</span>@endif @if($visit->root_cause)<span class="nx-tag">Root cause: {{ $visit->root_cause }}</span>@endif</div>
                                @endif
                                @if($visit->parts->isNotEmpty())
                                    <div class="nx-subhead">Parts on this visit</div>
                                    <ul class="nx-mini-list">@foreach($visit->parts as $part)<li><span><span class="badge badge-soft-{{ $part->kind === 'used' ? 'success' : 'warning' }} me-1">{{ $part->kind === 'used' ? 'Used' : 'Recommended' }}</span>{{ $part->part_name }} <span class="text-secondary notranslate">{{ $part->part_code }}</span></span><span class="notranslate">{{ rtrim(rtrim(number_format((float) $part->quantity, 2), '0'), '.') }} {{ $label('uoms', $part->uom) }}</span></li>@endforeach</ul>
                                @endif
                                @if($visit->technician_remarks || $visit->supervisor_remarks || $visit->store_remarks)
                                    <div class="row g-2 mt-1 small">
                                        @foreach(['technician_remarks' => 'Technician remarks', 'supervisor_remarks' => 'Supervisor remarks', 'store_remarks' => 'Store remarks'] as $remarkKey => $remarkLabel)
                                            @if($visit->{$remarkKey})<div class="col-md-4"><strong>{{ $remarkLabel }}:</strong> {{ $visit->{$remarkKey} }}</div>@endif
                                        @endforeach
                                    </div>
                                @endif
                                @if($visit->customer_signed_name || $visit->customer_rating || $visit->customer_remarks)
                                    <div class="nx-subhead">Customer sign-off</div>
                                    <div class="d-flex flex-wrap align-items-center gap-3">
                                        @if($visit->customer_signature_path)<img src="{{ asset('storage/'.$visit->customer_signature_path) }}" alt="Customer signature" style="height:60px;border:1px solid var(--nx-border);border-radius:6px;background:#fff">@endif
                                        <div class="small"><div><strong class="notranslate">{{ $visit->customer_signed_name }}</strong></div>@if($visit->customer_rating)<div class="nx-stars" aria-label="{{ $visit->customer_rating }} of 5">{{ str_repeat('★', $visit->customer_rating) }}{{ str_repeat('☆', 5 - $visit->customer_rating) }}</div>@endif @if($visit->customer_remarks)<div class="text-secondary">“{{ $visit->customer_remarks }}”</div>@endif</div>
                                    </div>
                                @endif
                                @if(! empty($visit->attachments))
                                    <div class="nx-subhead">Pictures & documents</div>
                                    <div class="d-flex flex-wrap gap-2">@foreach($visit->attachments as $file)<a class="nx-doc-chip" target="_blank" rel="noopener" href="{{ asset('storage/'.($file['path'] ?? '')) }}"><i data-lucide="{{ str_ends_with(strtolower($file['name'] ?? ''), '.pdf') ? 'file-text' : 'image' }}"></i><span>{{ $file['name'] ?? 'File' }}</span></a>@endforeach</div>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="nx-empty"><i data-lucide="route"></i><strong>No visit assigned yet</strong><div>Assign a technician to schedule the first visit (V01).</div>@if($canAssign)<button class="btn btn-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#assignModal"><i data-lucide="user-plus"></i>Assign V01</button>@endif</div>
                    @endforelse
                </div>
            </div>

            {{-- Parts --}}
            <div class="tab-pane fade {{ $tab === 'parts' ? 'show active' : '' }}" id="tab-parts" role="tabpanel">
                <div class="nx-card-body">
                    <div class="nx-section-head"><h3>Parts replaced</h3><span class="small text-secondary">Recorded on visit reports</span></div>
                    <table class="table nx-table nx-table-sm" data-datatable data-dt-title="Parts used · {{ $complaint->complaint_no }}" data-dt-paging="false" data-dt-empty="No parts replaced yet.">
                        <thead><tr><th data-priority="1">Visit</th><th data-priority="2">Part code</th><th data-priority="1">Part name</th><th>Category</th><th>Make</th><th data-priority="1">Qty</th><th>UOM</th><th>Source</th><th>Part type</th><th>Warranty</th><th>Stock location</th><th data-priority="2">Est. cost</th><th data-priority="1">Billable</th><th class="none">OEM warranty</th><th class="none">Old part returned</th><th class="none">Old part no</th><th class="none">Remarks</th></tr></thead>
                        <tbody>
                            @foreach($usedParts as $part)
                                <tr><td class="notranslate">{{ $part->visit?->label() }}</td><td class="notranslate">{{ $part->part_code }}</td><td class="fw-semibold">{{ $part->part_name }}</td><td>{{ $part->category }}</td><td>{{ $part->make }}</td><td class="notranslate">{{ rtrim(rtrim(number_format((float) $part->quantity, 2), '0'), '.') }}</td><td>{{ $label('uoms', $part->uom) }}</td><td>{{ $label('part_sources', $part->source) }}</td><td>{{ $label('part_types', $part->part_type) }}</td><td>{{ $part->part_warranty }}</td><td>{{ $part->stock_location }}</td><td class="notranslate" data-order="{{ $part->estimated_cost }}">₹{{ number_format((float) $part->estimated_cost, 2) }}</td><td class="notranslate" data-order="{{ $part->lineTotal() }}">₹{{ number_format($part->lineTotal(), 2) }}</td><td>{{ $part->oem_warranty ? 'Yes' : 'No' }}</td><td>{{ $part->old_part_returned ? 'Yes' : 'No' }}</td><td class="notranslate">{{ $part->old_part_no }}</td><td>{{ $part->remarks }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="nx-section-head"><h3>Parts to be replaced · recommendation & follow-up</h3><span class="small text-secondary">Approvals and purchase status</span></div>
                    <table class="table nx-table nx-table-sm" data-datatable data-dt-title="Recommended parts · {{ $complaint->complaint_no }}" data-dt-paging="false" data-dt-empty="No parts recommended.">
                        <thead><tr><th data-priority="1">Visit</th><th data-priority="2">Recommended action</th><th data-priority="1">Part name</th><th>Part code</th><th>Part type</th><th data-priority="1">Qty</th><th>In stock</th><th>Stock location</th><th data-priority="2">Est. cost</th><th>Billable rate</th><th>Customer approval</th><th class="none">Quotation given</th><th class="none">Manager approval req.</th><th class="none">Higher approval req.</th><th data-priority="1">Approval status</th><th data-priority="1">Purchase status</th><th class="none">Est. days</th><th class="none">Old part no</th><th class="none">Supporting parts</th></tr></thead>
                        <tbody>
                            @foreach($recommendedParts as $part)
                                <tr><td class="notranslate">{{ $part->visit?->label() }}</td><td>{{ $part->recommended_action }}</td><td class="fw-semibold">{{ $part->part_name }}</td><td class="notranslate">{{ $part->part_code }}</td><td>{{ $label('part_types', $part->part_type) }}</td><td class="notranslate">{{ rtrim(rtrim(number_format((float) $part->quantity, 2), '0'), '.') }}</td><td>{{ $part->available_in_stock ? 'Yes' : 'No' }}</td><td>{{ $part->stock_location }}</td><td class="notranslate" data-order="{{ $part->estimated_cost }}">₹{{ number_format((float) $part->estimated_cost, 2) }}</td><td class="notranslate" data-order="{{ $part->billable_rate }}">₹{{ number_format((float) $part->billable_rate, 2) }}</td><td><x-pill :tone="['approved' => 'success', 'rejected' => 'danger', 'pending' => 'warning'][$part->customer_approval_status] ?? 'secondary'" :label="$label('approval_statuses', $part->customer_approval_status)" /></td><td>{{ $part->quotation_given ? 'Yes' : 'No' }}</td><td>{{ $part->manager_approval_required ? 'Yes' : 'No' }}</td><td>{{ $part->higher_approval_required ? 'Yes' : 'No' }}</td><td><x-pill :tone="['approved' => 'success', 'rejected' => 'danger', 'pending' => 'warning'][$part->approval_status] ?? 'secondary'" :label="$label('approval_statuses', $part->approval_status)" /></td><td>{{ $label('purchase_statuses', $part->purchase_status) }}</td><td class="notranslate">{{ $part->estimated_days }}</td><td class="notranslate">{{ $part->old_part_no }}</td><td>{{ $part->additional_parts }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pending & follow-up --}}
            <div class="tab-pane fade {{ $tab === 'pending' ? 'show active' : '' }}" id="tab-pending" role="tabpanel">
                <div class="nx-card-body">
                    @if($isPending)
                        <div class="row g-3 mb-3">
                            <div class="col-lg-8">
                                <div class="nx-subhead mt-0">Why is this complaint {{ strtolower($meta['label']) }}?</div>
                                <dl class="nx-dl">
                                    <div><dt>Pending group</dt><dd>{{ $label('pending_groups', $complaint->pending_group) }}</dd></div>
                                    <div><dt>Reason for pending</dt><dd>{!! $dd($complaint->pending_reason) !!}</dd></div>
                                    <div><dt>Sub category</dt><dd>{!! $dd($complaint->pending_sub_reason) !!}</dd></div>
                                    <div><dt>Who is responsible</dt><dd>{{ $label('pending_owners', $complaint->pending_owner) }}@if($complaint->responsibleUser) · {{ $complaint->responsibleUser->name }}@endif</dd></div>
                                    <div><dt>Next action</dt><dd>{!! $dd($complaint->next_action) !!}</dd></div>
                                    <div><dt>Next follow-up</dt><dd class="{{ $complaint->follow_up_date?->lte(today()) ? 'text-danger' : '' }}">{{ $date($complaint->follow_up_date) }}</dd></div>
                                    <div><dt>Expected material date</dt><dd>{{ $date($complaint->expected_material_date) }}</dd></div>
                                    <div><dt>Targeted closing date</dt><dd>{{ $date($complaint->target_date) }}</dd></div>
                                    <div><dt>Reason for delay</dt><dd>{{ $label('delay_reasons', $complaint->delay_reason) }}</dd></div>
                                    <div><dt>Next escalation point</dt><dd>{!! $dd($complaint->escalation_point) !!}</dd></div>
                                </dl>
                            </div>
                            <div class="col-lg-4">
                                <div class="nx-subhead mt-0">Pending age</div>
                                <div><x-kpi :label="'Pending since '.$date($complaint->pending_since)" :value="$complaint->pendingAgeInDays().' days'" icon="hourglass" :tone="$bucket['tone'] === 'dark' ? 'dark' : $bucket['tone']" :foot="$bucket['label']" /></div>
                                <div class="d-grid gap-2 mt-3">
                                    @if($canUpdate)<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#followUpModal"><i data-lucide="phone-forwarded"></i>Record follow-up</button><button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#pendingModal"><i data-lucide="square-pen"></i>Change pending details</button>@endif
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-info d-flex gap-2 align-items-center"><i data-lucide="info" style="width:18px"></i><div>This complaint is not pending. If it cannot be finished, mark it pending with a reason, owner, next action and follow-up date.</div>@if($canUpdate && ! $complaint->isClosed())<button class="btn btn-sm btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#pendingModal">Mark pending</button>@endif</div>
                    @endif
                    <div class="nx-subhead">Pending & follow-up history</div>
                    @php $history = $complaint->activities->whereIn('type', ['pending', 'follow_up']); @endphp
                    @if($history->isEmpty())<div class="nx-empty py-3">No pending updates or follow-ups yet.</div>@else
                    <ol class="nx-tl">
                        @foreach($history as $activity)
                            <li><span class="nx-tl-icon tone-{{ $activity->type === 'pending' ? 'danger' : 'info' }}"><i data-lucide="{{ $activity->icon() }}"></i></span>
                                <div class="nx-tl-head"><span class="nx-tl-title">{{ $activity->title }}</span><span class="nx-tl-meta">{{ $activity->user?->name }} · {{ $activity->created_at->format('d M Y, h:i A') }}</span></div>
                                <div class="nx-tl-tags">@if($activity->follow_up_date)<span class="badge badge-soft-warning">Next follow-up {{ $activity->follow_up_date->format('d M Y') }}</span>@endif @foreach(($activity->details ?? []) as $detailKey => $detail)@if(is_scalar($detail) && ! in_array($detailKey, ['pending_reason'], true))<span class="nx-tag">{{ str($detailKey)->replace('_', ' ')->headline() }}: {{ ComplaintOptions::label(match ($detailKey) { 'pending_group' => 'pending_groups', 'pending_owner' => 'pending_owners', 'delay_reason' => 'delay_reasons', default => 'yes_no' }, (string) $detail) ?? $detail }}</span>@endif @endforeach</div>
                                @if($activity->remarks)<div class="nx-tl-body">{{ $activity->remarks }}</div>@endif
                            </li>
                        @endforeach
                    </ol>
                    @endif
                </div>
            </div>

            {{-- Commercial --}}
            <div class="tab-pane fade {{ $tab === 'commercial' ? 'show active' : '' }}" id="tab-commercial" role="tabpanel">
                <form class="nx-card-body" method="POST" action="{{ route('complaints.commercial', $complaint) }}" data-commercial-form>
                    @csrf @method('PUT')
                    <fieldset @disabled(! $canUpdate)>
                        <div class="nx-subhead mt-0">Contract</div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Service category</label><select class="form-select" name="service_category"><option value="">Select</option>@foreach(ComplaintOptions::options('service_categories') as $value => $text)<option value="{{ $value }}" @selected(old('service_category', $complaint->service_category) === $value)>{{ $text }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label">Contract / job no</label><input class="form-control notranslate" name="contract_no" value="{{ old('contract_no', $complaint->contract_no) }}" maxlength="60"></div>
                            <div class="col-md-4"><label class="form-label">AMC validity</label><input type="date" class="form-control" name="contract_expiry" value="{{ old('contract_expiry', $complaint->contract_expiry?->toDateString()) }}"></div>
                        </div>
                        <div class="nx-subhead">Charges</div>
                        <div class="row g-3">
                            @foreach(['visit_charges' => 'Visit charges', 'labour_charges' => 'Labour charges', 'parts_charges' => 'Parts charges', 'travel_charges' => 'Travel charges', 'other_charges' => 'Other charges', 'discount' => 'Discount'] as $field => $text)
                                <div class="col-6 col-md-4 col-xl-2"><label class="form-label">{{ $text }}</label><div class="input-group"><span class="input-group-text">₹</span><input type="number" step="0.01" min="0" class="form-control" name="{{ $field }}" value="{{ old($field, $complaint->{$field}) }}" data-charge="{{ $field }}"></div></div>
                            @endforeach
                            <div class="col-6 col-md-4 col-xl-2"><label class="form-label">GST %</label><input type="number" step="0.01" min="0" max="28" class="form-control" name="gst_percent" value="{{ old('gst_percent', $complaint->gst_percent) }}" data-charge="gst_percent" required></div>
                            <div class="col-6 col-md-4 col-xl-2"><label class="form-label">GST amount</label><input class="form-control" value="₹{{ number_format((float) $complaint->gst_amount, 2) }}" readonly data-gst-amount></div>
                            <div class="col-12 col-md-4 col-xl-4 ms-xl-auto"><label class="form-label">Total invoice amount</label><input class="form-control fw-bold" value="₹{{ number_format((float) $complaint->total_amount, 2) }}" readonly data-total-amount></div>
                        </div>
                        <div class="form-text mt-1">Parts charges update automatically from billable rates of parts used on visit reports. You can override them here.</div>
                        <div class="nx-subhead">Documents & payment</div>
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label">Customer PO no</label><input class="form-control notranslate" name="customer_po_no" value="{{ old('customer_po_no', $complaint->customer_po_no) }}" maxlength="60"></div>
                            <div class="col-md-3"><label class="form-label">Quotation no</label><input class="form-control notranslate" name="quotation_no" value="{{ old('quotation_no', $complaint->quotation_no) }}" maxlength="60"></div>
                            <div class="col-md-3"><label class="form-label">Invoice no</label><input class="form-control notranslate" name="invoice_no" value="{{ old('invoice_no', $complaint->invoice_no) }}" maxlength="60"></div>
                            <div class="col-md-3"><label class="form-label">Payment status</label><select class="form-select" name="payment_status">@foreach(ComplaintOptions::options('payment_statuses') as $value => $text)<option value="{{ $value }}" @selected(old('payment_status', $complaint->payment_status) === $value)>{{ $text }}</option>@endforeach</select></div>
                        </div>
                    </fieldset>
                    @if($canUpdate)<div class="d-grid d-sm-flex justify-content-sm-end mt-3"><button class="btn btn-primary"><i data-lucide="save"></i>Save commercial details</button></div>@endif
                </form>
            </div>

            {{-- Closure --}}
            <div class="tab-pane fade {{ $tab === 'closure' ? 'show active' : '' }}" id="tab-closure" role="tabpanel">
                <div class="nx-card-body">
                    @if($complaint->status === 'closed')
                        <div class="alert alert-success d-flex gap-2"><i data-lucide="lock" style="width:18px"></i><div>Closed on {{ $date($complaint->closed_at, 'd M Y, h:i A') }} by {{ $complaint->closedBy?->name ?? '—' }}.</div></div>
                        <dl class="nx-dl">
                            <div><dt>FSR checked</dt><dd>{{ $complaint->fsr_checked ? 'Yes' : 'No' }}@if($complaint->fsrCheckedBy) · {{ $complaint->fsrCheckedBy->name }}@endif</dd></div>
                            <div><dt>Root cause</dt><dd>{!! $dd($complaint->root_cause) !!}</dd></div>
                            <div><dt>Complaint category</dt><dd>{!! $dd($complaint->complaint_category) !!}</dd></div>
                            <div><dt>Happy call</dt><dd>{{ $complaint->happy_call_at ? $complaint->happy_call_at->format('d M Y, h:i A').' · '.($complaint->happyCallBy?->name ?? '') : ($complaint->happy_call_required ? 'Pending' : 'Not required') }}</dd></div>
                            <div><dt>Customer rating</dt><dd class="nx-stars">{{ $complaint->customer_rating ? str_repeat('★', $complaint->customer_rating).str_repeat('☆', 5 - $complaint->customer_rating) : '—' }}</dd></div>
                            <div><dt>Team rating</dt><dd class="nx-stars">{{ $complaint->team_rating ? str_repeat('★', $complaint->team_rating).str_repeat('☆', 5 - $complaint->team_rating) : '—' }}</dd></div>
                            <div><dt>Call back</dt><dd>{{ $complaint->callback_required ? 'Required · '.$date($complaint->callback_date) : 'Not required' }}</dd></div>
                            <div style="grid-column:1/-1"><dt>Customer feedback</dt><dd>{!! $dd($complaint->customer_feedback) !!}</dd></div>
                            <div style="grid-column:1/-1"><dt>Corrective action</dt><dd>{!! $dd($complaint->corrective_action) !!}</dd></div>
                            <div style="grid-column:1/-1"><dt>Manager remarks</dt><dd>{!! $dd($complaint->manager_remarks) !!}</dd></div>
                        </dl>
                    @elseif($complaint->status === 'cancelled')
                        <div class="alert alert-secondary">Cancelled on {{ $date($complaint->closed_at, 'd M Y, h:i A') }}: {{ $complaint->cancellation_reason }}</div>
                    @elseif(! $canClose)
                        <div class="nx-empty"><i data-lucide="lock"></i><strong>Closure is done by the service manager</strong><div>You can see the closure details here once the complaint is closed.</div></div>
                    @elseif($openVisit)
                        <div class="alert alert-warning d-flex gap-2"><i data-lucide="triangle-alert" style="width:18px"></i><div>{{ $openVisit->label() }} is still open. Submit or cancel the visit report before closing the complaint.</div></div>
                    @else
                        <form method="POST" action="{{ route('complaints.close', $complaint) }}" data-closure-form>
                            @csrf
                            @if(! in_array($complaint->status, ['completed', 'under_observation'], true))<div class="alert alert-warning small">The complaint is <strong>{{ strtolower($meta['label']) }}</strong>. Close it only if the issue is genuinely resolved.</div>@endif
                            <div class="nx-subhead mt-0">Closure checks</div>
                            <div class="row g-3">
                                <div class="col-md-3"><div class="form-check form-switch mt-md-4 pt-md-2"><input class="form-check-input" type="checkbox" role="switch" id="fsrChecked" name="fsr_checked" value="1" @checked(old('fsr_checked')) required><label class="form-check-label nx-required" for="fsrChecked">FSR checked</label></div><div class="form-text">Field service report reviewed and correct.</div></div>
                                <div class="col-md-3"><label class="form-label nx-required">Root cause of the complaint</label><input class="form-control" name="root_cause" value="{{ old('root_cause', $complaint->root_cause ?? $complaint->visits->last()?->root_cause) }}" list="rootCauseList" required maxlength="120"><datalist id="rootCauseList">@foreach($rootCauses as $cause)<option value="{{ $cause }}">@endforeach</datalist></div>
                                <div class="col-md-6"><label class="form-label nx-required">Complaint category</label><input class="form-control" name="complaint_category" value="{{ old('complaint_category', $complaint->complaint_category ?? $complaint->visits->last()?->fault_category) }}" list="faultList" required maxlength="120"><datalist id="faultList">@foreach($faultCategories as $category)<option value="{{ $category }}">@endforeach</datalist></div>
                                <div class="col-md-6"><label class="form-label">Corrective action required, if any</label><textarea class="form-control" name="corrective_action" rows="2" maxlength="2000">{{ old('corrective_action') }}</textarea></div>
                                <div class="col-md-6"><label class="form-label">Manager remarks</label><textarea class="form-control" name="manager_remarks" rows="2" maxlength="2000">{{ old('manager_remarks') }}</textarea></div>
                            </div>
                            <div class="nx-subhead">Happy call & feedback</div>
                            <div class="row g-3">
                                <div class="col-md-3"><div class="form-check form-switch mt-md-4 pt-md-2"><input type="hidden" name="happy_call_required" value="0"><input class="form-check-input" type="checkbox" role="switch" id="happyRequired" name="happy_call_required" value="1" @checked(old('happy_call_required', $complaint->happy_call_required)) data-toggle-target="#happyFields"><label class="form-check-label" for="happyRequired">Happy call required</label></div></div>
                                <div class="col-md-9" id="happyFields"><div class="row g-3">
                                    <div class="col-md-4"><label class="form-label">Happy call date & time</label><input type="datetime-local" class="form-control" name="happy_call_at" value="{{ old('happy_call_at') }}" max="{{ now()->format('Y-m-d\TH:i') }}"></div>
                                    <div class="col-md-8"><label class="form-label">Customer feedback</label><input class="form-control" name="customer_feedback" value="{{ old('customer_feedback') }}" maxlength="2000" placeholder="What did the customer say?"></div>
                                </div></div>
                                <div class="col-md-3"><label class="form-label d-block">Customer rating</label><div class="nx-rating">@for($star = 5; $star >= 1; $star--)<input type="radio" id="cr{{ $star }}" name="customer_rating" value="{{ $star }}" @checked((int) old('customer_rating') === $star)><label for="cr{{ $star }}" title="{{ $star }} star">★</label>@endfor</div></div>
                                <div class="col-md-3"><label class="form-label d-block">Team rating</label><div class="nx-rating">@for($star = 5; $star >= 1; $star--)<input type="radio" id="tr{{ $star }}" name="team_rating" value="{{ $star }}" @checked((int) old('team_rating') === $star)><label for="tr{{ $star }}" title="{{ $star }} star">★</label>@endfor</div></div>
                                <div class="col-md-3"><div class="form-check form-switch mt-md-4 pt-md-2"><input type="hidden" name="callback_required" value="0"><input class="form-check-input" type="checkbox" role="switch" id="callbackRequired" name="callback_required" value="1" @checked(old('callback_required')) data-toggle-target="#callbackFields"><label class="form-check-label" for="callbackRequired">Call back required</label></div></div>
                                <div class="col-md-3" id="callbackFields"><label class="form-label">Call back date</label><input type="date" class="form-control" name="callback_date" value="{{ old('callback_date') }}" min="{{ today()->toDateString() }}"></div>
                            </div>
                            <div class="d-grid d-sm-flex justify-content-sm-end gap-2 mt-3"><button class="btn btn-success"><i data-lucide="lock"></i>Close complaint</button></div>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Activity --}}
            <div class="tab-pane fade {{ $tab === 'activity' ? 'show active' : '' }}" id="tab-activity" role="tabpanel">
                <div class="nx-card-body">
                    <ol class="nx-tl">
                        @foreach($complaint->activities as $activity)
                            @php $toneFor = ['created' => 'primary', 'visit_assigned' => 'info', 'visit_report' => 'purple', 'pending' => 'danger', 'follow_up' => 'warning', 'commercial' => 'secondary', 'closed' => 'success', 'cancelled' => 'dark', 'reopened' => 'orange']; @endphp
                            <li><span class="nx-tl-icon tone-{{ $toneFor[$activity->type] ?? 'secondary' }}"><i data-lucide="{{ $activity->icon() }}"></i></span>
                                <div class="nx-tl-head"><span class="nx-tl-title">{{ $activity->title }}</span><span class="nx-tl-meta">{{ $activity->user?->name ?? 'System' }} · {{ $activity->created_at->format('d M Y, h:i A') }}</span></div>
                                @if($activity->status_to)<div class="nx-tl-tags">@if($activity->status_from)<x-pill :tone="ComplaintOptions::status($activity->status_from)['tone']" :label="ComplaintOptions::status($activity->status_from)['label']" /><i data-lucide="arrow-right" style="width:14px"></i>@endif<x-pill :tone="ComplaintOptions::status($activity->status_to)['tone']" :label="ComplaintOptions::status($activity->status_to)['label']" /></div>@endif
                                @if($activity->remarks)<div class="nx-tl-body">{{ $activity->remarks }}</div>@endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </div>
    </section>

    {{-- Assign visit --}}
    @if($canAssign && ! $openVisit)
    <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignTitle">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('complaints.visits.store', $complaint) }}">
                @csrf <input type="hidden" name="_modal" value="assignModal">
                <div class="modal-header"><div><h5 class="modal-title" id="assignTitle">Assign visit {{ $complaint->visitLabel($complaint->visits->max('visit_no') + 1) }}</h5><small class="text-secondary">{{ $complaint->visits->isEmpty() ? 'First visit for diagnosis.' : 'Follow-up visit on the same complaint — the complaint number stays the same.' }}</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label" for="mTech">Technician <span class="text-danger">*</span></label><select class="form-select" id="mTech" name="technician_id" required>@php $lastTech = $complaint->visits->last()?->technician_id; @endphp<option value="">Select technician</option>@foreach($technicians as $person)<option value="{{ $person->id }}" @selected((int) old('technician_id', $lastTech) === $person->id)>{{ $person->name }}{{ $person->designation ? ' · '.$person->designation : '' }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label" for="mReason">Reason for visit</label><input class="form-control" id="mReason" name="reason_for_visit" value="{{ old('reason_for_visit', $complaint->status === 'pending' ? $complaint->next_action : null) }}" maxlength="255" placeholder="{{ $complaint->visits->isEmpty() ? 'Diagnosis' : 'e.g. PCB replacing' }}"></div>
                        <div class="col-md-6"><label class="form-label" for="mOne">Assistant 1</label><select class="form-select" id="mOne" name="assistant_one_id" data-v="different:technician_id"><option value="">None</option>@foreach($technicians as $person)<option value="{{ $person->id }}" @selected((int) old('assistant_one_id') === $person->id)>{{ $person->name }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label" for="mTwo">Assistant 2</label><select class="form-select" id="mTwo" name="assistant_two_id" data-v="different:technician_id"><option value="">None</option>@foreach($technicians as $person)<option value="{{ $person->id }}" @selected((int) old('assistant_two_id') === $person->id)>{{ $person->name }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label" for="mAt">Appointment date & time</label><input type="datetime-local" class="form-control" id="mAt" name="appointment_at" value="{{ old('appointment_at') }}"></div>
                        <div class="col-md-6"><label class="form-label" for="mMethod">Assigned via</label><select class="form-select" id="mMethod" name="assignment_method">@foreach(ComplaintOptions::options('assignment_methods') as $value => $text)<option value="{{ $value }}" @selected(old('assignment_method', 'app') === $value)>{{ $text }}</option>@endforeach</select></div>
                        <div class="col-12"><label class="form-label" for="mNotes">Special instructions</label><textarea class="form-control" id="mNotes" name="special_instructions" rows="2" maxlength="1000" placeholder="e.g. Call the customer 30 minutes before reaching site">{{ old('special_instructions') }}</textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i data-lucide="send"></i>Assign & notify technician</button></div>
            </form>
        </div>
    </div>
    @endif

    {{-- Pending --}}
    @if($canUpdate && ! $complaint->isClosed())
    <div class="modal fade" id="pendingModal" tabindex="-1" aria-labelledby="pendingTitle">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('complaints.pending.store', $complaint) }}">
                @csrf <input type="hidden" name="_modal" value="pendingModal">
                <div class="modal-header"><div><h5 class="modal-title" id="pendingTitle">{{ $complaint->status === 'pending' ? 'Update pending details' : 'Mark complaint pending' }}</h5><small class="text-secondary">No complaint stays pending without a reason, an owner, the next action and a follow-up date.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body"><x-complaints.pending-fields :values="$complaint" :pending-tree="$pendingTree" :staff="$staffOptions" /></div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i data-lucide="save"></i>Save pending details</button></div>
            </form>
        </div>
    </div>
    @endif

    {{-- Follow-up --}}
    @if($canUpdate && $isPending)
    <div class="modal fade" id="followUpModal" tabindex="-1" aria-labelledby="followTitle">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('complaints.follow-ups.store', $complaint) }}">
                @csrf <input type="hidden" name="_modal" value="followUpModal">
                <div class="modal-header"><h5 class="modal-title" id="followTitle">Record follow-up</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">What happened? <span class="text-danger">*</span></label><textarea class="form-control" name="remarks" rows="3" required maxlength="2000" placeholder="e.g. Spoke to Samsung, PCB dispatched today">{{ old('remarks') }}</textarea></div>
                        <div class="col-12"><label class="form-label">Next action</label><input class="form-control" name="next_action" value="{{ old('next_action', $complaint->next_action) }}" maxlength="255"></div>
                        <div class="col-md-6"><label class="form-label">Next follow-up date <span class="text-danger">*</span></label><input type="date" class="form-control" name="follow_up_date" value="{{ old('follow_up_date') }}" min="{{ today()->toDateString() }}" required></div>
                        <div class="col-md-6"><label class="form-label">Targeted closing date</label><input type="date" class="form-control" name="target_date" value="{{ old('target_date', $complaint->target_date?->toDateString()) }}" data-v="after_or_equal:follow_up_date"></div>
                        <div class="col-md-6"><label class="form-label">Expected material date</label><input type="date" class="form-control" name="expected_material_date" value="{{ old('expected_material_date', $complaint->expected_material_date?->toDateString()) }}"></div>
                        <div class="col-md-6"><label class="form-label">Reason for delay</label><select class="form-select" name="delay_reason"><option value="">Keep current</option>@foreach(ComplaintOptions::options('delay_reasons') as $value => $text)<option value="{{ $value }}" @selected(old('delay_reason', $complaint->delay_reason) === $value)>{{ $text }}</option>@endforeach</select></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i data-lucide="save"></i>Save follow-up</button></div>
            </form>
        </div>
    </div>
    @endif

    {{-- Edit details --}}
    @if($canUpdate)
    <div class="modal fade" id="detailsModal" tabindex="-1" aria-labelledby="detailsTitle">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content" method="POST" action="{{ route('complaints.update', $complaint) }}">
                @csrf @method('PUT') <input type="hidden" name="_modal" value="detailsModal">
                <div class="modal-header"><h5 class="modal-title" id="detailsTitle">Edit complaint details</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    @php $v = fn (string $field) => old($field, $complaint->{$field} instanceof \Carbon\CarbonInterface ? $complaint->{$field}->toDateString() : $complaint->{$field}); $sel = fn (string $list, string $field, ?string $blank = 'Select') => collect(ComplaintOptions::options($list))->map(fn ($text, $value) => '<option value="'.e($value).'"'.((string) $v($field) === (string) $value ? ' selected' : '').'>'.e($text).'</option>')->prepend($blank !== null ? '<option value="">'.e($blank).'</option>' : '')->join(''); @endphp
                    <div class="nx-subhead mt-0">Site & contact</div>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label">Branch / area</label><select class="form-select" name="customer_branch_id"><option value="">Main site</option>@foreach($complaint->customer?->branches ?? [] as $branch)<option value="{{ $branch->id }}" @selected((int) $v('customer_branch_id') === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">Site / location</label><input class="form-control" name="site_location" value="{{ $v('site_location') }}" maxlength="255"></div>
                        <div class="col-md-4"><label class="form-label">Unit location</label><input class="form-control" name="unit_location" value="{{ $v('unit_location') }}" maxlength="255"></div>
                        <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2" maxlength="1000">{{ $v('address') }}</textarea></div>
                        <div class="col-md-4"><label class="form-label">Contact person <span class="text-danger">*</span></label><input class="form-control" name="contact_person" value="{{ $v('contact_person') }}" required maxlength="120"></div>
                        <div class="col-md-4"><label class="form-label">Contact no <span class="text-danger">*</span></label><input class="form-control" name="contact_no" value="{{ $v('contact_no') }}" required data-v="phone"></div>
                        <div class="col-md-4"><label class="form-label">Alternate no</label><input class="form-control" name="alternate_no" value="{{ $v('alternate_no') }}" data-v="phone"></div>
                    </div>
                    <div class="nx-subhead">Call & complaint</div>
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label">Type of call <span class="text-danger">*</span></label><select class="form-select" name="call_type" required>{!! collect(ComplaintOptions::callTypeOptions())->map(fn ($text, $value) => '<option value="'.e($value).'"'.($v('call_type') === $value ? ' selected' : '').'>'.e($text).'</option>')->join('') !!}</select></div>
                        <div class="col-md-3"><label class="form-label">Mode of call <span class="text-danger">*</span></label><select class="form-select" name="call_mode" required>{!! $sel('call_modes', 'call_mode', null) !!}</select></div>
                        <div class="col-md-3"><label class="form-label">Complaint source <span class="text-danger">*</span></label><select class="form-select" name="complaint_source" required>{!! $sel('sources', 'complaint_source', null) !!}</select></div>
                        <div class="col-md-3"><label class="form-label">Complaint given by</label><input class="form-control" name="complaint_given_by" value="{{ $v('complaint_given_by') }}" maxlength="120"></div>
                        <div class="col-md-6"><label class="form-label">Nature of complaint <span class="text-danger">*</span></label><input class="form-control" name="nature_of_complaint" value="{{ $v('nature_of_complaint') }}" list="natureEditList" required maxlength="120"><datalist id="natureEditList">@foreach($natures as $nature)<option value="{{ $nature }}">@endforeach</datalist></div>
                        <div class="col-md-2"><label class="form-label">Priority <span class="text-danger">*</span></label><select class="form-select" name="priority" required>{!! collect(ComplaintOptions::priorityOptions())->map(fn ($text, $value) => '<option value="'.e($value).'"'.($v('priority') === $value ? ' selected' : '').'>'.e($text).'</option>')->join('') !!}</select></div>
                        <div class="col-md-2"><label class="form-label">SLA response (h)</label><input type="number" class="form-control" name="sla_response_hours" value="{{ $v('sla_response_hours') }}" min="1" max="720"></div>
                        <div class="col-md-2"><label class="form-label">SLA resolution (h)</label><input type="number" class="form-control" name="sla_resolution_hours" value="{{ $v('sla_resolution_hours') }}" min="1" max="2160"></div>
                        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2" maxlength="5000">{{ $v('description') }}</textarea></div>
                    </div>
                    <div class="nx-subhead">Equipment & contract</div>
                    <div class="row g-3">
                        <div class="col-md-3"><label class="form-label">Equipment</label><select class="form-select" name="customer_equipment_id"><option value="">Not linked</option>@foreach($complaint->customer?->equipments ?? [] as $unit)<option value="{{ $unit->id }}" @selected((int) $v('customer_equipment_id') === $unit->id)>{{ collect([$unit->equipment_type, $unit->brand, $unit->model, $unit->serial_no])->filter()->join(' · ') }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">Equipment ID</label><input class="form-control" name="equipment_tag" value="{{ $v('equipment_tag') }}" maxlength="60"></div>
                        <div class="col-md-3"><label class="form-label">Equipment type</label><select class="form-select" name="equipment_type">{!! $sel('equipment_types', 'equipment_type') !!}</select></div>
                        <div class="col-md-3"><label class="form-label">Criticality</label><select class="form-select" name="equipment_criticality">{!! $sel('criticality', 'equipment_criticality') !!}</select></div>
                        <div class="col-md-3"><label class="form-label">Make</label><input class="form-control" name="make" value="{{ $v('make') }}" maxlength="80"></div>
                        <div class="col-md-3"><label class="form-label">Model</label><input class="form-control" name="model" value="{{ $v('model') }}" maxlength="120"></div>
                        <div class="col-md-3"><label class="form-label">Capacity</label><input class="form-control" name="capacity" value="{{ $v('capacity') }}" maxlength="60"></div>
                        <div class="col-md-3"><label class="form-label">Serial no</label><input class="form-control" name="serial_no" value="{{ $v('serial_no') }}" maxlength="120"></div>
                        <div class="col-md-3"><label class="form-label">Refrigerant</label><select class="form-select" name="refrigerant">{!! $sel('refrigerants', 'refrigerant') !!}</select></div>
                        <div class="col-md-3"><label class="form-label">Compressor type</label><select class="form-select" name="compressor_type">{!! $sel('compressor_types', 'compressor_type') !!}</select></div>
                        <div class="col-md-3"><label class="form-label">Technology</label><select class="form-select" name="technology">{!! $sel('technologies', 'technology') !!}</select></div>
                        <div class="col-md-3"><label class="form-label">Power supply</label><select class="form-select" name="power_supply">{!! $sel('power_supplies', 'power_supply') !!}</select></div>
                        <div class="col-md-3"><label class="form-label">Installation date</label><input type="date" class="form-control" name="installation_date" value="{{ $v('installation_date') }}"></div>
                        <div class="col-md-3"><label class="form-label">Last service</label><input type="date" class="form-control" name="last_service_date" value="{{ $v('last_service_date') }}"></div>
                        <div class="col-md-3"><label class="form-label">Last PM</label><input type="date" class="form-control" name="last_pm_date" value="{{ $v('last_pm_date') }}"></div>
                        <div class="col-md-3"><label class="form-label">Category</label><select class="form-select" name="service_category">{!! $sel('service_categories', 'service_category') !!}</select></div>
                        <div class="col-md-3"><label class="form-label">AMC / contract no</label><input class="form-control" name="contract_no" value="{{ $v('contract_no') }}" maxlength="60" data-v="required_if:service_category,amc,company_amc"></div>
                        <div class="col-md-3"><label class="form-label">Warranty / AMC expiry</label><input type="date" class="form-control" name="contract_expiry" value="{{ $v('contract_expiry') }}" data-v="required_with:contract_no"></div>
                        <div class="col-md-9"><label class="form-label">Reason for the change</label><input class="form-control" name="change_remarks" maxlength="1000" placeholder="Shown in the activity log"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i data-lucide="save"></i>Save details</button></div>
            </form>
        </div>
    </div>
    @endif

    {{-- Cancel visit / complaint, reopen --}}
    @if($canAssign && $openVisit)
    <div class="modal fade" id="cancelVisitModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('complaints.visits.cancel', [$complaint, $openVisit]) }}">@csrf<input type="hidden" name="_modal" value="cancelVisitModal"><div class="modal-header"><h5 class="modal-title">Cancel visit {{ $openVisit->label() }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><label class="form-label">Reason <span class="text-danger">*</span></label><input class="form-control" name="reason" required maxlength="255" placeholder="e.g. Customer asked to reschedule"></div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Keep visit</button><button class="btn btn-danger">Cancel visit</button></div></form></div></div>
    @endif
    @if($canClose)
    <div class="modal fade" id="cancelModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('complaints.cancel', $complaint) }}">@csrf<input type="hidden" name="_modal" value="cancelModal"><div class="modal-header"><h5 class="modal-title">Cancel complaint</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="small text-secondary">Use this for duplicate or wrongly registered calls. Open visits are cancelled too.</p><label class="form-label">Reason <span class="text-danger">*</span></label><input class="form-control" name="cancellation_reason" required maxlength="255"></div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Keep complaint</button><button class="btn btn-danger">Cancel complaint</button></div></form></div></div>
    @endif
    @if($canReopen)
    <div class="modal fade" id="reopenModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('complaints.reopen', $complaint) }}">@csrf<input type="hidden" name="_modal" value="reopenModal"><div class="modal-header"><h5 class="modal-title">Reopen complaint</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><p class="small text-secondary">The same complaint number continues. Assign the next visit after reopening.</p><label class="form-label">Reason <span class="text-danger">*</span></label><input class="form-control" name="reopen_reason" required maxlength="255" placeholder="e.g. Same problem reported again"></div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Reopen</button></div></form></div></div>
    @endif

    @push('scripts')@vite('resources/js/complaints.js')@endpush
</x-app-layout>
