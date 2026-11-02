@use('App\Services\ComplaintOptions')
<x-app-layout>
    <x-slot name="title">Register complaint</x-slot>
    <x-slot name="pageTitle">Register complaint</x-slot>
    <x-slot name="breadcrumb">Complaints / New</x-slot>
    @php
        $mode = old('customer_mode', 'existing');
        $customerId = old('customer_id', $selectedCustomer);
        $opt = function (array $choices, mixed $selected, ?string $blank = 'Select'): string {
            $selected = (string) ($selected ?? '');
            $html = $blank !== null ? '<option value="">'.e($blank).'</option>' : '';
            foreach ($choices as $value => $label) {
                $html .= '<option value="'.e($value).'"'.((string) $value === $selected ? ' selected' : '').'>'.e($label).'</option>';
            }
            return $html;
        };
        $priority = old('priority', 'medium');
        $callType = old('call_type', 'breakdown');
        $callMode = old('call_mode', 'phone');
        $modeIcons = ['phone' => 'phone', 'whatsapp' => 'message-circle', 'email' => 'mail', 'portal' => 'globe', 'amc_visit' => 'calendar-check', 'other' => 'ellipsis'];
    @endphp

    @if($errors->any())
        <div class="alert alert-danger d-flex gap-2"><i data-lucide="circle-alert" style="width:18px;flex:0 0 auto"></i><div><strong>Please check the highlighted fields.</strong> {{ $errors->first() }}</div></div>
    @endif

    <form method="POST" action="{{ route('complaints.store') }}" id="complaintForm" data-complaint-form data-context-url="{{ route('complaints.customer-context', ['customer' => '__ID__']) }}" data-sla='@json(ComplaintOptions::PRIORITIES)' novalidate>
        @csrf
        <div class="row g-3">
            <div class="col-xl-8">
                {{-- 1. Customer & site --}}
                <section class="nx-card mb-3">
                    <div class="nx-card-header">
                        <div><h3 class="nx-card-title"><i data-lucide="building-2"></i>Customer & site</h3><p class="nx-card-subtitle">Pick the customer to auto-fill branch, contact, address and equipment.</p></div>
                        <div class="nx-segment" role="radiogroup" aria-label="Customer">
                            <input type="radio" name="customer_mode" id="modeExisting" value="existing" @checked($mode === 'existing')><label for="modeExisting"><i data-lucide="search"></i>Existing customer</label>
                            <input type="radio" name="customer_mode" id="modeNew" value="new" @checked($mode === 'new')><label for="modeNew"><i data-lucide="user-plus"></i>New customer</label>
                        </div>
                    </div>
                    <div class="nx-card-body">
                        <fieldset data-mode-panel="existing" @disabled($mode !== 'existing')>
                            <div class="row g-3">
                                <div class="col-md-8"><label class="form-label" for="customerId">Customer *</label><select class="form-select" id="customerId" name="customer_id" required data-searchable data-placeholder="Search by name, code or city">{!! $opt($customers->mapWithKeys(fn ($customer) => [$customer->id => $customer->name.' · '.$customer->code.($customer->city ? ' · '.$customer->city : '')])->all(), $customerId, 'Search by name, code or city') !!}</select></div>
                                <div class="col-md-4"><label class="form-label">Customer ID</label><input class="form-control notranslate" data-fill="customer_code" readonly placeholder="Auto"></div>
                            </div>
                            <div class="alert alert-warning mt-3 mb-0 d-none" data-open-complaints></div>
                        </fieldset>
                        <fieldset data-mode-panel="new" @disabled($mode !== 'new') class="{{ $mode === 'new' ? '' : 'd-none' }}">
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label" for="ncName">Customer name *</label><input class="form-control" id="ncName" name="new_customer[name]" value="{{ old('new_customer.name') }}" required maxlength="255"></div>
                                <div class="col-md-3"><label class="form-label" for="ncPhone">Phone *</label><input class="form-control" id="ncPhone" name="new_customer[phone]" value="{{ old('new_customer.phone') }}" required data-v="phone" inputmode="tel"></div>
                                <div class="col-md-3"><label class="form-label" for="ncType">Type</label><select class="form-select" id="ncType" name="new_customer[type]">{!! $opt(['individual' => 'Individual', 'corporate' => 'Corporate'], old('new_customer.type', 'individual'), null) !!}</select></div>
                                <div class="col-md-4"><label class="form-label" for="ncEmail">Email</label><input type="email" class="form-control" id="ncEmail" name="new_customer[email]" value="{{ old('new_customer.email') }}" data-v="email"></div>
                                <div class="col-md-3"><label class="form-label" for="ncCity">City</label><input class="form-control" id="ncCity" name="new_customer[city]" value="{{ old('new_customer.city') }}" maxlength="120"></div>
                                <div class="col-md-5"><label class="form-label" for="ncAddress">Address</label><input class="form-control" id="ncAddress" name="new_customer[address]" value="{{ old('new_customer.address') }}" maxlength="255"></div>
                                <div class="col-12"><div class="form-text"><i data-lucide="info" style="width:13px"></i> A customer record is created with these details. Complete the full customer profile later from Customers.</div></div>
                            </div>
                        </fieldset>
                        <div class="nx-subhead">Site & contact</div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label" for="branchId">Branch / area</label><select class="form-select" id="branchId" name="customer_branch_id" data-old="{{ old('customer_branch_id') }}"><option value="">Main site</option></select></div>
                            <div class="col-md-4"><label class="form-label" for="siteLocation">Site / location</label><input class="form-control" id="siteLocation" name="site_location" value="{{ old('site_location') }}" maxlength="255" placeholder="Building, wing or plant"></div>
                            <div class="col-md-4"><label class="form-label" for="unitLocation">Unit location</label><input class="form-control" id="unitLocation" name="unit_location" value="{{ old('unit_location') }}" maxlength="255" placeholder="e.g. Office no. 3"></div>
                            <div class="col-12"><label class="form-label" for="address">Address</label><textarea class="form-control" id="address" name="address" rows="2" maxlength="1000">{{ old('address') }}</textarea></div>
                            <div class="col-md-4"><label class="form-label" for="contactPerson">Contact person *</label><input class="form-control" id="contactPerson" name="contact_person" value="{{ old('contact_person') }}" required maxlength="120" list="contactList" autocomplete="off"><datalist id="contactList"></datalist></div>
                            <div class="col-md-4"><label class="form-label" for="contactNo">Contact no *</label><input class="form-control" id="contactNo" name="contact_no" value="{{ old('contact_no') }}" required data-v="phone" inputmode="tel"></div>
                            <div class="col-md-4"><label class="form-label" for="alternateNo">Alternate no</label><input class="form-control" id="alternateNo" name="alternate_no" value="{{ old('alternate_no') }}" data-v="phone|different:contact_no" inputmode="tel"></div>
                        </div>
                    </div>
                </section>

                {{-- 2. Call details --}}
                <section class="nx-card mb-3">
                    <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="phone-incoming"></i>Call details</h3><p class="nx-card-subtitle">Who called, how, and which team should handle it.</p></div></div>
                    <div class="nx-card-body">
                        <label class="form-label">Type of call *</label>
                        <div class="nx-option-cards mb-3" role="radiogroup" aria-label="Type of call">
                            @foreach(ComplaintOptions::CALL_TYPES as $key => $type)
                                <label class="nx-option-card"><input type="radio" name="call_type" value="{{ $key }}" @checked($callType === $key) required><span><i data-lucide="{{ $type['icon'] }}"></i><span><strong>{{ $type['label'] }}</strong><small>{{ $type['description'] }}</small></span></span></label>
                            @endforeach
                        </div>
                        <label class="form-label">Mode of call *</label>
                        <div class="nx-segment mb-3" role="radiogroup" aria-label="Mode of call">
                            @foreach(ComplaintOptions::options('call_modes') as $key => $label)
                                <input type="radio" name="call_mode" id="mode_{{ $key }}" value="{{ $key }}" @checked($callMode === $key)><label for="mode_{{ $key }}"><i data-lucide="{{ $modeIcons[$key] ?? 'circle' }}"></i>{{ $label }}</label>
                            @endforeach
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label" for="source">Complaint source *</label><select class="form-select" id="source" name="complaint_source" required>{!! $opt(ComplaintOptions::options('sources'), old('complaint_source', 'customer'), null) !!}</select></div>
                            <div class="col-md-4"><label class="form-label" for="givenBy">Complaint given by</label><input class="form-control" id="givenBy" name="complaint_given_by" value="{{ old('complaint_given_by') }}" maxlength="120" placeholder="Name of caller"></div>
                            <div class="col-md-4"><label class="form-label" for="takenBy">Call taken by</label><select class="form-select" id="takenBy" name="call_taken_by">{!! $opt($staff->pluck('name', 'id')->all(), old('call_taken_by', auth()->id()), null) !!}</select></div>
                        </div>
                    </div>
                </section>

                {{-- 3. Complaint & SLA --}}
                <section class="nx-card mb-3">
                    <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="message-square-warning"></i>Complaint & priority</h3><p class="nx-card-subtitle">Pick from the complaint master or type your own. Priority sets the SLA.</p></div></div>
                    <div class="nx-card-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label" for="nature">Nature of complaint *</label><input class="form-control" id="nature" name="nature_of_complaint" value="{{ old('nature_of_complaint') }}" list="natureList" required maxlength="120" autocomplete="off" placeholder="Start typing or choose"><datalist id="natureList">@foreach($natures as $label)<option value="{{ $label }}">@endforeach</datalist></div>
                            <div class="col-md-6"><label class="form-label">Quick pick</label><div class="d-flex flex-wrap gap-1">@foreach(array_slice($natures, 0, 6) as $label)<button type="button" class="btn btn-sm btn-light border" data-nature-pick="{{ $label }}">{{ $label }}</button>@endforeach</div></div>
                            <div class="col-12"><label class="form-label" for="description">Customer's description</label><textarea class="form-control" id="description" name="description" rows="3" maxlength="5000" placeholder="What exactly is the problem? Since when? Any error code?">{{ old('description') }}</textarea></div>
                        </div>
                        <label class="form-label mt-3">Priority *</label>
                        <div class="nx-option-cards mb-3" role="radiogroup" aria-label="Priority">
                            @foreach(ComplaintOptions::PRIORITIES as $key => $level)
                                <label class="nx-option-card tone-{{ $level['tone'] }}"><input type="radio" name="priority" value="{{ $key }}" @checked($priority === $key) required><span><i data-lucide="{{ ['critical' => 'flame', 'high' => 'arrow-up', 'medium' => 'minus', 'low' => 'arrow-down'][$key] }}"></i><span><strong>{{ $level['label'] }}</strong><small>Respond in {{ $level['response'] }}h · resolve in {{ $level['resolution'] >= 48 ? ($level['resolution'] / 24).' days' : $level['resolution'].'h' }}</small></span></span></label>
                            @endforeach
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label" for="slaResponse">SLA response time (hours)</label><input type="number" class="form-control" id="slaResponse" name="sla_response_hours" value="{{ old('sla_response_hours', ComplaintOptions::PRIORITIES[$priority]['response'] ?? 8) }}" min="1" max="720" data-v="integer"></div>
                            <div class="col-md-4"><label class="form-label" for="slaResolution">SLA resolution time (hours)</label><input type="number" class="form-control" id="slaResolution" name="sla_resolution_hours" value="{{ old('sla_resolution_hours', ComplaintOptions::PRIORITIES[$priority]['resolution'] ?? 72) }}" min="1" max="2160" data-v="integer|gte:sla_response_hours"></div>
                            <div class="col-md-4 d-flex align-items-end"><div class="form-text">SLA hours fill in from the priority. Change them for special contracts.</div></div>
                        </div>
                    </div>
                </section>

                {{-- 4. Equipment --}}
                <section class="nx-card mb-3">
                    <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="air-vent"></i>Equipment</h3><p class="nx-card-subtitle">Choose the customer's unit to auto-fill make, model, capacity and service history.</p></div></div>
                    <div class="nx-card-body">
                        <div class="row g-3">
                            <div class="col-md-8"><label class="form-label" for="equipmentId">Customer equipment</label><select class="form-select" id="equipmentId" name="customer_equipment_id" data-old="{{ old('customer_equipment_id') }}"><option value="">Not in the equipment register / enter manually</option></select></div>
                            <div class="col-md-4"><label class="form-label" for="equipmentTag">Equipment ID</label><input class="form-control notranslate" id="equipmentTag" name="equipment_tag" value="{{ old('equipment_tag') }}" maxlength="60" placeholder="Asset / tag no"></div>
                            <div class="col-md-4"><label class="form-label" for="equipmentType">Equipment type</label><select class="form-select" id="equipmentType" name="equipment_type">{!! $opt(ComplaintOptions::options('equipment_types'), old('equipment_type')) !!}</select></div>
                            <div class="col-md-4"><label class="form-label" for="make">Make</label><input class="form-control" id="make" name="make" value="{{ old('make') }}" maxlength="80" placeholder="Auto"></div>
                            <div class="col-md-4"><label class="form-label" for="model">Model</label><input class="form-control" id="model" name="model" value="{{ old('model') }}" maxlength="120" placeholder="Auto"></div>
                            <div class="col-md-4"><label class="form-label" for="capacity">Capacity</label><input class="form-control" id="capacity" name="capacity" value="{{ old('capacity') }}" maxlength="60" placeholder="Auto"></div>
                            <div class="col-md-4"><label class="form-label" for="serialNo">Unit serial no</label><input class="form-control notranslate" id="serialNo" name="serial_no" value="{{ old('serial_no') }}" maxlength="120" placeholder="Auto"></div>
                            <div class="col-md-4"><label class="form-label" for="criticality">Equipment criticality</label><select class="form-select" id="criticality" name="equipment_criticality">{!! $opt(ComplaintOptions::options('criticality'), old('equipment_criticality', 'normal'), null) !!}</select></div>
                        </div>
                        <details class="mt-3" @if(old('refrigerant') || old('compressor_type') || old('technology')) open @endif>
                            <summary class="small fw-semibold text-primary" style="cursor:pointer">Technical details & service history</summary>
                            <div class="row g-3 mt-1">
                                <div class="col-md-3"><label class="form-label" for="refrigerant">Refrigerant</label><select class="form-select" id="refrigerant" name="refrigerant">{!! $opt(ComplaintOptions::options('refrigerants'), old('refrigerant')) !!}</select></div>
                                <div class="col-md-3"><label class="form-label" for="compressor">Compressor type</label><select class="form-select" id="compressor" name="compressor_type">{!! $opt(ComplaintOptions::options('compressor_types'), old('compressor_type')) !!}</select></div>
                                <div class="col-md-3"><label class="form-label" for="technology">Technology</label><select class="form-select" id="technology" name="technology">{!! $opt(ComplaintOptions::options('technologies'), old('technology')) !!}</select></div>
                                <div class="col-md-3"><label class="form-label" for="power">Power supply</label><select class="form-select" id="power" name="power_supply">{!! $opt(ComplaintOptions::options('power_supplies'), old('power_supply')) !!}</select></div>
                                <div class="col-md-4"><label class="form-label" for="installDate">Installation date</label><input type="date" class="form-control" id="installDate" name="installation_date" value="{{ old('installation_date') }}" data-v="not_future"></div>
                                <div class="col-md-4"><label class="form-label" for="lastService">Last service attended</label><input type="date" class="form-control" id="lastService" name="last_service_date" value="{{ old('last_service_date') }}" data-v="not_future"><div class="form-text">Auto from earlier visits.</div></div>
                                <div class="col-md-4"><label class="form-label" for="lastPm">Last PM date</label><input type="date" class="form-control" id="lastPm" name="last_pm_date" value="{{ old('last_pm_date') }}" data-v="not_future"><div class="form-text">Auto from preventive visits.</div></div>
                            </div>
                        </details>
                    </div>
                </section>

                {{-- 5. Warranty / AMC --}}
                <section class="nx-card mb-3">
                    <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="shield-check"></i>Warranty & AMC</h3><p class="nx-card-subtitle">For continuous AMC contracts, record the AMC number and its expiry.</p></div><span class="badge badge-soft-secondary d-none" data-warranty-badge></span></div>
                    <div class="nx-card-body">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label" for="category">Category</label><select class="form-select" id="category" name="service_category">{!! $opt(ComplaintOptions::options('service_categories'), old('service_category')) !!}</select></div>
                            <div class="col-md-4"><label class="form-label" for="contractNo">AMC / contract no</label><input class="form-control notranslate" id="contractNo" name="contract_no" value="{{ old('contract_no') }}" maxlength="60" placeholder="e.g. AMC-25/26-014" data-v="required_if:service_category,amc,company_amc" data-msg-required-if="Enter the AMC / contract number."></div>
                            <div class="col-md-4"><label class="form-label" for="contractExpiry">Warranty / AMC expiry</label><input type="date" class="form-control" id="contractExpiry" name="contract_expiry" value="{{ old('contract_expiry') }}" data-v="required_with:contract_no"></div>
                        </div>
                    </div>
                </section>

                {{-- 6. Assign now --}}
                @can('complaints.assign')
                <section class="nx-card mb-3">
                    <div class="nx-card-header">
                        <div><h3 class="nx-card-title"><i data-lucide="user-cog"></i>Assign first visit <span class="badge badge-soft-secondary">Optional</span></h3><p class="nx-card-subtitle">Assign now, or leave it and assign from the complaint later.</p></div>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" id="assignNow" data-assign-toggle @checked(old('assign.technician_id'))><label class="form-check-label" for="assignNow">Assign now</label></div>
                    </div>
                    <fieldset class="nx-card-body {{ old('assign.technician_id') ? '' : 'd-none' }}" data-assign-panel @disabled(! old('assign.technician_id'))>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label" for="aTech">Technician *</label><select class="form-select" id="aTech" name="assign[technician_id]" required data-searchable data-placeholder="Select technician">{!! $opt($technicians->mapWithKeys(fn ($user) => [$user->id => $user->name.($user->designation ? ' · '.$user->designation : '')])->all(), old('assign.technician_id'), 'Select technician') !!}</select></div>
                            <div class="col-md-4"><label class="form-label" for="aOne">Assistant 1</label><select class="form-select" id="aOne" name="assign[assistant_one_id]" data-v="different:technician_id">{!! $opt($technicians->pluck('name', 'id')->all(), old('assign.assistant_one_id'), 'None') !!}</select></div>
                            <div class="col-md-4"><label class="form-label" for="aTwo">Assistant 2</label><select class="form-select" id="aTwo" name="assign[assistant_two_id]">{!! $opt($technicians->pluck('name', 'id')->all(), old('assign.assistant_two_id'), 'None') !!}</select></div>
                            <div class="col-md-4"><label class="form-label" for="aAt">Appointment date & time</label><input type="datetime-local" class="form-control" id="aAt" name="assign[appointment_at]" value="{{ old('assign.appointment_at') }}"></div>
                            <div class="col-md-4"><label class="form-label" for="aMethod">Assigned via</label><select class="form-select" id="aMethod" name="assign[assignment_method]">{!! $opt(ComplaintOptions::options('assignment_methods'), old('assign.assignment_method', 'app'), null) !!}</select></div>
                            <div class="col-md-4"><label class="form-label" for="aReason">Reason for visit</label><input class="form-control" id="aReason" name="assign[reason_for_visit]" value="{{ old('assign.reason_for_visit') }}" maxlength="255" placeholder="Diagnosis"></div>
                            <div class="col-12"><label class="form-label" for="aNotes">Special instructions</label><input class="form-control" id="aNotes" name="assign[special_instructions]" value="{{ old('assign.special_instructions') }}" maxlength="1000" placeholder="e.g. Call the customer at 1:30 before reaching site"></div>
                        </div>
                    </fieldset>
                </section>
                @endcan
            </div>

            <div class="col-xl-4">
                <aside class="nx-side-summary">
                    <section class="nx-card mb-3">
                        <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="file-text"></i>Complaint summary</h3></div>
                        <div class="nx-card-body">
                            <dl class="nx-dl-rows">
                                <div><dt>Complaint no</dt><dd class="nx-code notranslate">{{ $nextNumber }}</dd></div>
                                <div><dt>Date</dt><dd data-live-date>{{ now()->format('d M Y') }}</dd></div>
                                <div><dt>Time</dt><dd data-live-time>{{ now()->format('h:i A') }}</dd></div>
                                <div><dt>Customer</dt><dd data-summary="customer">—</dd></div>
                                <div><dt>Respond by</dt><dd data-summary="respond">—</dd></div>
                                <div><dt>Resolve by</dt><dd data-summary="resolve">—</dd></div>
                            </dl>
                            <p class="form-text mb-0 mt-2">The number is confirmed when you save. Date and time are recorded automatically.</p>
                        </div>
                    </section>
                    <section class="nx-card mb-3 d-none" data-context-card>
                        <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="history"></i>Customer history</h3></div>
                        <div class="nx-card-body" data-context-body></div>
                    </section>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-lg" type="submit"><i data-lucide="save"></i>Register complaint</button>
                        <a class="btn btn-light border" href="{{ route('complaints.index') }}">Cancel</a>
                    </div>
                </aside>
            </div>
        </div>
    </form>
    @push('scripts')@vite('resources/js/complaints.js')@endpush
</x-app-layout>
