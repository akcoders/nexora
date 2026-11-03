@use('App\Services\ComplaintOptions')
<x-app-layout>
    <x-slot name="title">{{ $visit->label() }} report · {{ $complaint->complaint_no }}</x-slot>
    <x-slot name="pageTitle">{{ $visit->label() }} visit report</x-slot>
    <x-slot name="breadcrumb">Complaints / {{ $complaint->complaint_no }} / {{ $visit->label() }}</x-slot>
    @php
        $dt = fn (string $field) => old($field, $visit->{$field}?->format('Y-m-d\TH:i'));
        $v = fn (string $field) => old($field, $visit->{$field});
        $check = fn (string $key) => old('checks.'.$key, $visit->check($key));
        $outcome = old('outcome', $visit->outcome ?? '');
        $finalResult = old('final_result', $visit->final_result);
        $usedRows = $visit->parts->where('kind', 'used')->values()->map->toArray()->all();
        $recommendedRows = $visit->parts->where('kind', 'recommended')->values()->map->toArray()->all();
        $checks = ['unit_checked_before' => 'Unit checked before service?', 'photo_taken' => 'Photograph taken?', 'informed_customer' => 'Informed the customer?', 'informed_back_office' => 'Informed the back office?'];
        $afterChecks = ['unit_checked_after' => 'Unit checked after repair / service?', 'equipment_working_normally' => 'Equipment working normally?', 'customer_confirmation' => 'Customer confirmation received?'];
        $productMap = $products->mapWithKeys(fn ($product) => [$product->name => ['id' => $product->id, 'code' => $product->code, 'make' => $product->brand, 'category' => $product->category?->name]]);
    @endphp

    @if($errors->any())
        <div class="alert alert-danger d-flex gap-2"><i data-lucide="circle-alert" style="width:18px;flex:0 0 auto"></i><div><strong>Please check the highlighted fields.</strong> {{ $errors->first() }}</div></div>
    @endif

    <section class="nx-card mb-3">
        <div class="nx-complaint-head">
            <div class="min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2"><a class="nx-complaint-no notranslate" href="{{ route('complaints.show', $complaint) }}">{{ $complaint->complaint_no }}</a><span class="badge badge-soft-primary notranslate">{{ $visit->label() }}</span><x-pill :tone="$visit->statusMeta()['tone']" :label="$visit->statusMeta()['label']" /></div>
                <h2>{{ $complaint->nature_of_complaint }}</h2>
                <div class="nx-complaint-meta">
                    <span><i data-lucide="building-2"></i><span class="notranslate">{{ $complaint->customer?->name }}</span></span>
                    @if($complaint->branch || $complaint->site_location)<span><i data-lucide="map-pin"></i>{{ collect([$complaint->branch?->name, $complaint->site_location, $complaint->unit_location])->filter()->unique()->join(' · ') }}</span>@endif
                    <span><i data-lucide="air-vent"></i>{{ $complaint->equipmentSummary() }}</span>
                    <span><i data-lucide="users"></i>{{ $visit->teamNames() }}</span>
                    @if($visit->appointment_at)<span><i data-lucide="calendar-clock"></i>{{ $visit->appointment_at->format('d M Y, h:i A') }}</span>@endif
                </div>
            </div>
            <a class="btn btn-light border" href="{{ route('complaints.show', [$complaint, 'tab' => 'visits']) }}"><i data-lucide="arrow-left"></i>Back to complaint</a>
        </div>
        @if($visit->special_instructions || $previousVisits->isNotEmpty())
            <div class="nx-card-footer small">
                @if($visit->special_instructions)<div class="mb-1"><i data-lucide="info" style="width:14px"></i> <strong>Instructions:</strong> {{ $visit->special_instructions }}</div>@endif
                @foreach($previousVisits as $previous)<div><strong class="notranslate">{{ $previous->label() }}</strong> · {{ $previous->technician?->name }} · {{ $previous->ended_at?->format('d M Y') ?? $previous->appointment_at?->format('d M Y') }} · {{ $previous->problem_diagnosis ?? $previous->reason_for_visit }} → {{ $previous->work_performed ?? '—' }}</div>@endforeach
            </div>
        @endif
    </section>

    <form method="POST" action="{{ route('complaints.visits.update', [$complaint, $visit]) }}" enctype="multipart/form-data" data-visit-report novalidate>
        @csrf @method('PUT')
        <div class="row g-3">
            <div class="col-xl-6">
                <section class="nx-card h-100">
                    <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="clock"></i>Time log</h3><span class="small text-secondary">Tap “Now” to stamp the current time</span></div>
                    <div class="nx-card-body">
                        <div class="row g-3">
                            @foreach(['accepted_at' => 'Technician accepted', 'en_route_at' => 'En route (left for site)', 'arrived_at' => 'Arrival at site', 'started_at' => 'Site start time', 'ended_at' => 'Site end time'] as $field => $text)
                                <div class="col-md-6"><label class="form-label" for="{{ $field }}">{{ $text }}</label><div class="input-group"><input type="datetime-local" class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ $dt($field) }}" data-time-field="{{ $field }}"><button class="btn btn-light border" type="button" data-now="{{ $field }}">Now</button></div></div>
                            @endforeach
                            <div class="col-md-3 col-6"><label class="form-label" for="travel">Travel (min)</label><input type="number" class="form-control" id="travel" name="travel_minutes" value="{{ $v('travel_minutes') }}" min="0" max="1440" data-auto-travel></div>
                            <div class="col-md-3 col-6"><label class="form-label" for="labour">Labour hours</label><input type="number" step="0.01" class="form-control" id="labour" name="labour_hours" value="{{ $v('labour_hours') }}" min="0" max="200" data-auto-labour></div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="col-xl-6">
                <section class="nx-card h-100">
                    <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="list-checks"></i>Before service</h3></div>
                    <div class="nx-card-body">
                        @foreach($checks as $key => $text)
                            <div class="d-flex align-items-center justify-content-between gap-3 py-2 border-bottom"><span>{{ $text }}</span><div class="nx-yesno"><input type="radio" id="c_{{ $key }}_y" name="checks[{{ $key }}]" value="yes" @checked($check($key) === 'yes')><label for="c_{{ $key }}_y">Yes</label><input type="radio" id="c_{{ $key }}_n" name="checks[{{ $key }}]" value="no" @checked($check($key) === 'no')><label for="c_{{ $key }}_n">No</label></div></div>
                        @endforeach
                        <label class="form-label mt-3" for="abnormal">Any abnormality found (broken parts etc.)</label>
                        <textarea class="form-control" id="abnormal" name="abnormality_found" rows="2" maxlength="2000" placeholder="e.g. Main panel broken found">{{ $v('abnormality_found') }}</textarea>
                    </div>
                </section>
            </div>
        </div>

        <section class="nx-card mt-3">
            <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="stethoscope"></i>Technician diagnosis</h3><p class="nx-card-subtitle">Readings shown for {{ ComplaintOptions::label('equipment_types', $complaint->equipment_type) ?? 'this equipment' }}. Delta T is worked out from supply and return temperatures.</p></div></div>
            <div class="nx-card-body">
                <div class="row g-3">
                    @foreach($diagnosisFields as $key => $field)
                        @php $value = old('diagnosis.'.$key, data_get($visit->diagnosis, $key)); @endphp
                        <div class="col-6 col-md-4 col-xl-3">
                            <label class="form-label" for="d_{{ $key }}">{{ $field['label'] }}</label>
                            @if($field['type'] === 'number')
                                <div class="input-group"><input type="number" step="0.01" class="form-control" id="d_{{ $key }}" name="diagnosis[{{ $key }}]" value="{{ $value }}" data-reading="{{ $key }}">@if(isset($field['unit']))<span class="input-group-text notranslate">{{ $field['unit'] }}</span>@endif</div>
                            @elseif($field['type'] === 'condition')
                                <select class="form-select" id="d_{{ $key }}" name="diagnosis[{{ $key }}]"><option value="">—</option>@foreach(ComplaintOptions::options('conditions') as $option => $text)<option value="{{ $option }}" @selected($value === $option)>{{ $text }}</option>@endforeach</select>
                            @elseif($field['type'] === 'yes_no')
                                <select class="form-select" id="d_{{ $key }}" name="diagnosis[{{ $key }}]"><option value="">—</option><option value="yes" @selected($value === 'yes')>Yes</option><option value="no" @selected($value === 'no')>No</option></select>
                            @else
                                <input class="form-control notranslate" id="d_{{ $key }}" name="diagnosis[{{ $key }}]" value="{{ $value }}" maxlength="60">
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="row g-3 mt-1">
                    <div class="col-md-6"><label class="form-label" for="observation">Technician's observation</label><textarea class="form-control" id="observation" name="observation" rows="2" maxlength="3000">{{ $v('observation') }}</textarea></div>
                    <div class="col-md-6"><label class="form-label" for="initialDiag">Initial diagnosis</label><textarea class="form-control" id="initialDiag" name="initial_diagnosis" rows="2" maxlength="3000">{{ $v('initial_diagnosis') }}</textarea></div>
                    <div class="col-md-4"><label class="form-label" for="faultCat">Fault category</label><input class="form-control" id="faultCat" name="fault_category" value="{{ $v('fault_category') }}" list="faultCategoryList" maxlength="120"><datalist id="faultCategoryList">@foreach($faultCategories as $item)<option value="{{ $item }}">@endforeach</datalist></div>
                    <div class="col-md-4"><label class="form-label" for="faultCode">Fault / error code</label><input class="form-control notranslate" id="faultCode" name="fault_code" value="{{ $v('fault_code') }}" maxlength="60"></div>
                    <div class="col-md-4"><label class="form-label" for="rootCause">Root cause</label><input class="form-control" id="rootCause" name="root_cause" value="{{ $v('root_cause') }}" list="rootCauseList" maxlength="120"><datalist id="rootCauseList">@foreach($rootCauses as $item)<option value="{{ $item }}">@endforeach</datalist></div>
                </div>
            </div>
        </section>

        <section class="nx-card mt-3">
            <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="wrench"></i>Work done & result</h3></div>
            <div class="nx-card-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="problem">Problem diagnosis — what exactly was wrong</label><textarea class="form-control" id="problem" name="problem_diagnosis" rows="3" maxlength="3000" data-outcome-required>{{ $v('problem_diagnosis') }}</textarea></div>
                    <div class="col-md-6"><label class="form-label" for="work">Work performed — what the technician actually did</label><textarea class="form-control" id="work" name="work_performed" rows="3" maxlength="3000" data-outcome-required>{{ $v('work_performed') }}</textarea></div>
                    <div class="col-md-6">
                        <label class="form-label d-block">Final result</label>
                        <div class="nx-segment"><input type="radio" id="frOk" name="final_result" value="working" @checked($finalResult === 'working')><label for="frOk"><i data-lucide="circle-check"></i>Unit working OK</label><input type="radio" id="frNo" name="final_result" value="not_working" @checked($finalResult === 'not_working')><label for="frNo"><i data-lucide="circle-x"></i>Not working</label></div>
                    </div>
                    <div class="col-md-6">
                        @foreach($afterChecks as $key => $text)
                            <div @class(['d-flex align-items-center justify-content-between gap-3 py-2', 'border-bottom' => ! $loop->last])><span>{{ $text }}</span><div class="nx-yesno"><input type="radio" id="c_{{ $key }}_y" name="checks[{{ $key }}]" value="yes" @checked($check($key) === 'yes')><label for="c_{{ $key }}_y">Yes</label><input type="radio" id="c_{{ $key }}_n" name="checks[{{ $key }}]" value="no" @checked($check($key) === 'no')><label for="c_{{ $key }}_n">No</label></div></div>
                        @endforeach
                    </div>
                </div>
                <div class="nx-subhead">Parts replaced on this visit</div>
                <x-complaints.parts-table field="used_parts" kind="used" :rows="$usedRows" />
                <div class="nx-subhead">Pictures & supporting documents</div>
                <input type="file" class="form-control" name="attachments[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf" data-max-size="8192" data-max-files="10">
                <div class="form-text">Before / after photos, new parts installed, test readings. JPG, PNG, WebP or PDF up to 8 MB each.</div>
                @if(! empty($visit->attachments))<div class="d-flex flex-wrap gap-2 mt-2">@foreach($visit->attachments as $file)<a class="nx-doc-chip" target="_blank" rel="noopener" href="{{ asset('storage/'.($file['path'] ?? '')) }}"><i data-lucide="paperclip"></i><span>{{ $file['name'] ?? 'File' }}</span></a>@endforeach</div>@endif
            </div>
        </section>

        <div class="row g-3 mt-0">
            <div class="col-xl-6">
                <section class="nx-card h-100">
                    <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="signature"></i>Customer sign-off</h3></div>
                    <div class="nx-card-body">
                        <div class="row g-3">
                            <div class="col-md-7"><label class="form-label" for="signedName">Customer name</label><input class="form-control" id="signedName" name="customer_signed_name" value="{{ $v('customer_signed_name') ?? $complaint->contact_person }}" maxlength="120" data-v="required_with:signature_data"></div>
                            <div class="col-md-5"><label class="form-label d-block">Customer rating</label><div class="nx-rating">@for($star = 5; $star >= 1; $star--)<input type="radio" id="vr{{ $star }}" name="customer_rating" value="{{ $star }}" @checked((int) $v('customer_rating') === $star)><label for="vr{{ $star }}" title="{{ $star }} star">★</label>@endfor</div></div>
                            <div class="col-12">
                                <label class="form-label">Customer signature</label>
                                @if($visit->customer_signature_path)<div class="mb-2"><img src="{{ asset('storage/'.$visit->customer_signature_path) }}" alt="Saved signature" style="height:56px;border:1px solid var(--nx-border);border-radius:6px"> <span class="small text-secondary">Saved. Draw again to replace.</span></div>@endif
                                <canvas class="nx-signature" data-signature-pad aria-label="Signature pad"></canvas>
                                <input type="hidden" name="signature_data" data-signature-input>
                                <div class="d-flex justify-content-between mt-1"><span class="form-text">Ask the customer to sign with a finger or mouse.</span><button type="button" class="btn btn-sm btn-link" data-signature-clear>Clear</button></div>
                            </div>
                            <div class="col-12"><label class="form-label" for="custRemarks">Customer remarks</label><textarea class="form-control" id="custRemarks" name="customer_remarks" rows="2" maxlength="2000">{{ $v('customer_remarks') }}</textarea></div>
                        </div>
                    </div>
                </section>
            </div>
            <div class="col-xl-6">
                <section class="nx-card h-100">
                    <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="message-square-text"></i>Remarks</h3></div>
                    <div class="nx-card-body">
                        <label class="form-label" for="techRemarks">Technician remarks</label><textarea class="form-control mb-3" id="techRemarks" name="technician_remarks" rows="2" maxlength="2000">{{ $v('technician_remarks') }}</textarea>
                        <label class="form-label" for="supRemarks">Supervisor remarks</label><textarea class="form-control mb-3" id="supRemarks" name="supervisor_remarks" rows="2" maxlength="2000">{{ $v('supervisor_remarks') }}</textarea>
                        <label class="form-label" for="storeRemarks">Store remarks</label><textarea class="form-control" id="storeRemarks" name="store_remarks" rows="2" maxlength="2000">{{ $v('store_remarks') }}</textarea>
                    </div>
                </section>
            </div>
        </div>

        <section class="nx-card mt-3">
            <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="flag"></i>Visit outcome</h3><p class="nx-card-subtitle">Choosing an outcome completes this visit. The complaint itself stays open until it is closed.</p></div></div>
            <div class="nx-card-body">
                <div class="nx-option-cards" role="radiogroup" aria-label="Visit outcome">
                    <label class="nx-option-card tone-secondary"><input type="radio" name="outcome" value="" @checked($outcome === '') data-outcome><span><i data-lucide="save"></i><span><strong>Not yet</strong><small>Save progress, finish the report later</small></span></span></label>
                    <label class="nx-option-card tone-success"><input type="radio" name="outcome" value="resolved" @checked($outcome === 'resolved') data-outcome><span><i data-lucide="circle-check"></i><span><strong>Resolved</strong><small>Unit working OK — ready for closure checks</small></span></span></label>
                    <label class="nx-option-card tone-danger"><input type="radio" name="outcome" value="pending" @checked($outcome === 'pending') data-outcome><span><i data-lucide="hourglass"></i><span><strong>Pending</strong><small>Parts, approval or more work needed</small></span></span></label>
                    <label class="nx-option-card tone-purple"><input type="radio" name="outcome" value="observation" @checked($outcome === 'observation') data-outcome><span><i data-lucide="eye"></i><span><strong>Under observation</strong><small>Working now, check again later</small></span></span></label>
                </div>
                <fieldset class="mt-3 {{ $outcome === 'pending' ? '' : 'd-none' }}" data-outcome-panel="pending" @disabled($outcome !== 'pending')>
                    <div class="alert alert-warning small"><i data-lucide="triangle-alert" style="width:14px"></i> A pending complaint must have a reason, an owner, the next action and dates. It appears on the pending board and today’s follow-ups.</div>
                    <x-complaints.pending-fields prefix="pending." :values="$complaint->status === 'pending' ? $complaint : []" :pending-tree="$pendingTree" :staff="$staff" />
                    <div class="nx-subhead">Parts to be replaced — recommendation & follow-up</div>
                    <x-complaints.parts-table field="recommended_parts" kind="recommended" :rows="$recommendedRows" />
                </fieldset>
                <fieldset class="mt-3 {{ $outcome === 'observation' ? '' : 'd-none' }}" data-outcome-panel="observation" @disabled($outcome !== 'observation')>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label" for="obsDate">Check again on <span class="text-danger">*</span></label><input type="date" class="form-control" id="obsDate" name="observation_follow_up_date" value="{{ old('observation_follow_up_date', now()->addDays(2)->toDateString()) }}" min="{{ today()->toDateString() }}" required></div>
                        <div class="col-md-8"><label class="form-label" for="obsAction">What to check</label><input class="form-control" id="obsAction" name="observation_next_action" value="{{ old('observation_next_action') }}" maxlength="255" placeholder="e.g. Check temperature log after 48 hours"></div>
                    </div>
                </fieldset>
            </div>
        </section>

        <datalist id="productList">@foreach($products as $product)<option value="{{ $product->name }}">{{ $product->code }}</option>@endforeach</datalist>
        <script type="application/json" data-product-map>@json($productMap)</script>

        <div class="nx-sticky-actions">
            <span class="small text-secondary me-auto" data-outcome-hint>Saving without an outcome keeps the visit open.</span>
            <a class="btn btn-light border" href="{{ route('complaints.show', [$complaint, 'tab' => 'visits']) }}">Cancel</a>
            <button class="btn btn-primary" type="submit" data-report-submit><i data-lucide="save"></i><span>Save progress</span></button>
        </div>
    </form>
    @push('scripts')@vite('resources/js/complaints.js')@endpush
</x-app-layout>
