<x-app-layout>
    <x-slot name="title">New Customer</x-slot>
    <x-slot name="pageTitle">Create customer</x-slot>
    <x-slot name="breadcrumb">Customers / New</x-slot>

    @php
        $contactRows = old('contacts', [['name' => '', 'is_primary' => '1']]);
        $branchRows = old('branches', [[
            'name' => '', 'country' => 'India',
            'contacts' => [['name' => '']],
        ]]);
    @endphp

    <form id="customerWizard" action="{{ route('customers.store') }}" method="POST" enctype="multipart/form-data" novalidate>
        @csrf
        <input type="hidden" id="draftUuid" value="">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <a href="{{ route('customers.index') }}" class="small d-inline-flex align-items-center gap-1 mb-2"><i data-lucide="arrow-left" style="width:15px"></i>Customers</a>
                <div class="text-secondary small">Complete the guided form. Your progress auto-saves every 30 seconds.</div>
            </div>
            <button type="button" class="btn btn-light border" id="saveDraft"><i data-lucide="save" style="width:17px"></i>Save draft</button>
        </div>

        @if($errors->any())
            <div class="alert alert-danger nx-validation-summary" role="alert">
                <div class="d-flex gap-2 align-items-start"><i data-lucide="circle-alert" style="width:19px"></i><div><strong>Please review the highlighted steps.</strong><div class="small">The first section containing an error has been opened automatically.</div></div></div>
            </div>
        @endif

        <section class="nx-card p-3 p-lg-4 mb-4">
            <div class="nx-stepper" id="stepper">
                @foreach(['Basic','Address','Contacts','Regulatory','Branches','Credit','Review'] as $index => $label)
                    <button type="button" class="nx-step {{ $index === 0 ? 'active' : '' }}" data-step="{{ $index + 1 }}">
                        <span class="nx-step-number">{{ $index + 1 }}</span><span>{{ $label }}</span>
                    </button>
                @endforeach
            </div>
        </section>

        <div class="row g-4">
            <div class="col-xl-9">
                <section class="nx-card p-3 p-lg-4 nx-wizard-card">
                    <div class="nx-step-pane active" data-pane="1">
                        <div class="mb-4"><h2 class="nx-section-title mb-1">Basic information</h2><p class="small text-secondary mb-0">Customer identity, relationship type and account status.</p></div>
                        <div class="row g-3">
                            <div class="col-md-8"><label class="form-label">Customer name *</label><input class="form-control" name="name" value="{{ old('name') }}" placeholder="e.g. Tata AIA Life Insurance" required></div>
                            <div class="col-md-4"><label class="form-label">Customer group</label><select class="form-select" name="customer_group_id"><option value="">Select group</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(old('customer_group_id') == $group->id)>{{ $group->name }}</option>@endforeach</select></div>
                            <div class="col-12 nx-field-group" data-field-group="types"><label class="form-label d-block">Customer type *</label><div class="d-flex flex-wrap gap-2">@foreach(['individual','corporate','architect','consultant','agent','dealer','government'] as $type)<label class="nx-choice"><input type="checkbox" name="types[]" value="{{ $type }}" @checked(in_array($type, old('types', [])))><span>{{ str($type)->headline() }}</span></label>@endforeach</div></div>
                            <div class="col-12"><label class="form-label d-block">Segments</label><div class="d-flex flex-wrap gap-2">@foreach(['healthcare','education','industrials','builder','hospitality','office'] as $segment)<label class="nx-choice"><input type="checkbox" name="segments[]" value="{{ $segment }}" @checked(in_array($segment, old('segments', [])))><span>{{ str($segment)->headline() }}</span></label>@endforeach</div></div>
                            <div class="col-md-4"><label class="form-label">Priority *</label><select class="form-select" name="priority" required>@foreach(['normal','high','very_high'] as $priority)<option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ str($priority)->headline() }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label">Status *</label><select class="form-select" name="status" id="customerStatus" required>@foreach(['active','inactive','blacklisted'] as $status)<option value="{{ $status }}" @selected(old('status', 'active') === $status)>{{ str($status)->headline() }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label">Branch type *</label><select class="form-select" name="branch_type" id="branchType" required><option value="single" @selected(old('branch_type', 'single') === 'single')>Single</option><option value="multi" @selected(old('branch_type') === 'multi')>Multiple branches</option></select></div>
                        </div>
                    </div>

                    <div class="nx-step-pane" data-pane="2">
                        <div class="mb-4"><h2 class="nx-section-title mb-1">Registered address & location</h2><p class="small text-secondary mb-0">Complete primary address and communication information.</p></div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Address line 1 *</label><input class="form-control" name="address_line_1" value="{{ old('address_line_1') }}" required></div>
                            <div class="col-md-6"><label class="form-label">Address line 2</label><input class="form-control" name="address_line_2" value="{{ old('address_line_2') }}"></div>
                            <div class="col-md-4"><label class="form-label">Landmark</label><input class="form-control" name="landmark" value="{{ old('landmark') }}"></div>
                            <div class="col-md-4"><label class="form-label">Area</label><input class="form-control" name="area" value="{{ old('area') }}"></div>
                            <div class="col-md-4"><label class="form-label">City *</label><input class="form-control" name="city" id="city" value="{{ old('city') }}" required></div>
                            <div class="col-md-3"><label class="form-label">District</label><input class="form-control" name="district" value="{{ old('district') }}"></div>
                            <div class="col-md-3"><label class="form-label">State *</label><input class="form-control" name="state" value="{{ old('state') }}" required></div>
                            <div class="col-md-3"><label class="form-label">PIN code *</label><input class="form-control" name="pin_code" value="{{ old('pin_code') }}" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required></div>
                            <div class="col-md-3"><label class="form-label">Country *</label><input class="form-control" name="country" value="{{ old('country', 'India') }}" required></div>
                            <div class="col-md-6"><label class="form-label">Contact number *</label><input class="form-control" name="contact_no_1" value="{{ old('contact_no_1') }}" required></div>
                            <div class="col-md-6"><label class="form-label">Alternate number</label><input class="form-control" name="contact_no_2" value="{{ old('contact_no_2') }}"></div>
                            <div class="col-md-6"><label class="form-label">Email *</label><input class="form-control" type="email" name="email_1" value="{{ old('email_1') }}" required></div>
                            <div class="col-md-6"><label class="form-label">Alternate email</label><input class="form-control" type="email" name="email_2" value="{{ old('email_2') }}"></div>
                            <div class="col-md-6"><label class="form-label">Site access instructions</label><textarea class="form-control" rows="3" name="site_access_instructions" placeholder="Gate entry, PPE, parking or contact instructions">{{ old('site_access_instructions') }}</textarea></div>
                            <div class="col-md-6"><label class="form-label">Billing address</label><textarea class="form-control" rows="3" name="billing_address">{{ old('billing_address') }}</textarea></div>
                            <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude') }}"><input type="hidden" name="longitude" id="longitude" value="{{ old('longitude') }}">
                            <div class="col-12"><div class="nx-location-box"><i data-lucide="map-pin"></i><div><div class="fw-semibold">Location coordinates</div><div class="small text-secondary" id="locationStatus">Capture the customer location for service planning.</div></div><button class="btn btn-sm btn-outline-primary ms-auto" id="captureLocation" type="button">Use current location</button></div></div>
                        </div>
                    </div>

                    <div class="nx-step-pane" data-pane="3">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-4"><div><h2 class="nx-section-title mb-1">Contact persons</h2><p class="small text-secondary mb-0">Add service, billing and escalation contacts.</p></div><button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" id="addContact"><i data-lucide="user-plus" style="width:16px"></i>Add contact</button></div>
                        <div class="d-grid gap-3" id="contactList">
                            @foreach($contactRows as $index => $contact)
                                <div class="nx-repeat-card" data-contact="{{ $index }}">
                                    <div class="nx-repeat-header"><div><strong>{{ $index === 0 ? 'Primary contact' : 'Additional contact' }}</strong><div class="small text-secondary">Customer-level contact</div></div>@if($index > 0)<button type="button" class="btn btn-sm btn-link text-danger remove-card">Remove</button>@else<span class="badge badge-soft-primary">Contact 1</span>@endif</div>
                                    <div class="row g-3">
                                        <div class="col-md-4"><label class="form-label">Name *</label><input class="form-control" name="contacts[{{ $index }}][name]" value="{{ $contact['name'] ?? '' }}" required></div>
                                        <div class="col-md-4"><label class="form-label">Designation</label><input class="form-control" name="contacts[{{ $index }}][designation]" value="{{ $contact['designation'] ?? '' }}"></div>
                                        <div class="col-md-4"><label class="form-label">Department</label><input class="form-control" name="contacts[{{ $index }}][department]" value="{{ $contact['department'] ?? '' }}"></div>
                                        <div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="contacts[{{ $index }}][phone]" value="{{ $contact['phone'] ?? '' }}"></div>
                                        <div class="col-md-4"><label class="form-label">WhatsApp</label><input class="form-control" name="contacts[{{ $index }}][whatsapp]" value="{{ $contact['whatsapp'] ?? '' }}"></div>
                                        <div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" name="contacts[{{ $index }}][email]" value="{{ $contact['email'] ?? '' }}"></div>
                                        <div class="col-12 d-flex flex-wrap gap-4"><label class="form-check"><input class="form-check-input primary-contact" type="checkbox" name="contacts[{{ $index }}][is_primary]" value="1" @checked(!empty($contact['is_primary']))> Primary</label><label class="form-check"><input class="form-check-input" type="checkbox" name="contacts[{{ $index }}][is_service]" value="1" @checked(!empty($contact['is_service']))> Service</label><label class="form-check"><input class="form-check-input" type="checkbox" name="contacts[{{ $index }}][is_billing]" value="1" @checked(!empty($contact['is_billing']))> Billing</label><label class="form-check"><input class="form-check-input" type="checkbox" name="contacts[{{ $index }}][is_escalation]" value="1" @checked(!empty($contact['is_escalation']))> Escalation</label></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="nx-step-pane" data-pane="4">
                        <div class="mb-4"><h2 class="nx-section-title mb-1">Regulatory details</h2><p class="small text-secondary mb-0">Tax registrations and MSME classification.</p></div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">GSTIN</label><input class="form-control text-uppercase" name="gstin" value="{{ old('gstin') }}" maxlength="15" pattern="[0-9A-Za-z]{15}"></div>
                            <div class="col-md-6"><label class="form-label">PAN</label><input class="form-control text-uppercase" name="pan" value="{{ old('pan') }}" maxlength="10" pattern="[0-9A-Za-z]{10}"></div>
                            <div class="col-md-4"><label class="form-label">GST registration type</label><select class="form-select" name="gst_registration_type"><option value="">Select</option>@foreach(['Regular','Composition','Unregistered'] as $type)<option value="{{ $type }}" @selected(old('gst_registration_type') === $type)>{{ $type }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label">TAN</label><input class="form-control" name="tan" value="{{ old('tan') }}"></div>
                            <div class="col-md-4"><label class="form-label">TDS %</label><input class="form-control" type="number" name="tds_percent" value="{{ old('tds_percent') }}" min="0" max="100" step="0.01"></div>
                            <div class="col-md-6"><label class="form-label">MSME / Udyam no.</label><input class="form-control" name="udyam_no" value="{{ old('udyam_no') }}"></div>
                            <div class="col-md-6"><label class="form-label">MSME type</label><select class="form-select" name="msme_type"><option value="">Not applicable</option>@foreach(['micro','small','medium'] as $type)<option value="{{ $type }}" @selected(old('msme_type') === $type)>{{ str($type)->headline() }}</option>@endforeach</select></div>
                        </div>
                    </div>

                    <div class="nx-step-pane" data-pane="5">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-4"><div><h2 class="nx-section-title mb-1">Branch details</h2><p class="small text-secondary mb-0">Each branch can have its own GST, complete address and contacts.</p></div><button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" id="addBranch"><i data-lucide="plus" style="width:16px"></i>Add branch</button></div>
                        <div class="d-grid gap-4" id="branchList">
                            @foreach($branchRows as $branchIndex => $branch)
                                <div class="nx-repeat-card nx-branch-card" data-branch="{{ $branchIndex }}">
                                    <div class="nx-repeat-header"><div><strong>Branch {{ $branchIndex + 1 }}</strong><div class="small text-secondary">Code will be generated automatically</div></div>@if($branchIndex > 0)<button type="button" class="btn btn-sm btn-link text-danger remove-card">Remove branch</button>@else<span class="badge badge-soft-primary">Branch 1</span>@endif</div>
                                    <div class="row g-3">
                                        <div class="col-md-6"><label class="form-label">Branch name *</label><input class="form-control branch-required" data-branch-input name="branches[{{ $branchIndex }}][name]" value="{{ $branch['name'] ?? '' }}"></div>
                                        <div class="col-md-3"><label class="form-label">GSTIN</label><input class="form-control text-uppercase" data-branch-input name="branches[{{ $branchIndex }}][gstin]" value="{{ $branch['gstin'] ?? '' }}" maxlength="15" pattern="[0-9A-Za-z]{15}"></div>
                                        <div class="col-md-3"><label class="form-label">GST type</label><select class="form-select" data-branch-input name="branches[{{ $branchIndex }}][gst_registration_type]"><option value="">Select</option>@foreach(['regular','composition','unregistered'] as $type)<option value="{{ $type }}" @selected(($branch['gst_registration_type'] ?? '') === $type)>{{ str($type)->headline() }}</option>@endforeach</select></div>
                                        <div class="col-md-6"><label class="form-label">Address line 1 *</label><input class="form-control branch-required" data-branch-input name="branches[{{ $branchIndex }}][address_line_1]" value="{{ $branch['address_line_1'] ?? '' }}"></div>
                                        <div class="col-md-6"><label class="form-label">Address line 2</label><input class="form-control" data-branch-input name="branches[{{ $branchIndex }}][address_line_2]" value="{{ $branch['address_line_2'] ?? '' }}"></div>
                                        <div class="col-md-4"><label class="form-label">Landmark</label><input class="form-control" data-branch-input name="branches[{{ $branchIndex }}][landmark]" value="{{ $branch['landmark'] ?? '' }}"></div>
                                        <div class="col-md-4"><label class="form-label">Area</label><input class="form-control" data-branch-input name="branches[{{ $branchIndex }}][area]" value="{{ $branch['area'] ?? '' }}"></div>
                                        <div class="col-md-4"><label class="form-label">City *</label><input class="form-control branch-required" data-branch-input name="branches[{{ $branchIndex }}][city]" value="{{ $branch['city'] ?? '' }}"></div>
                                        <div class="col-md-3"><label class="form-label">District</label><input class="form-control" data-branch-input name="branches[{{ $branchIndex }}][district]" value="{{ $branch['district'] ?? '' }}"></div>
                                        <div class="col-md-3"><label class="form-label">State *</label><input class="form-control branch-required" data-branch-input name="branches[{{ $branchIndex }}][state]" value="{{ $branch['state'] ?? '' }}"></div>
                                        <div class="col-md-3"><label class="form-label">PIN code *</label><input class="form-control branch-required" data-branch-input name="branches[{{ $branchIndex }}][pin_code]" value="{{ $branch['pin_code'] ?? '' }}" inputmode="numeric" maxlength="6" pattern="[0-9]{6}"></div>
                                        <div class="col-md-3"><label class="form-label">Country *</label><input class="form-control branch-required" data-branch-input name="branches[{{ $branchIndex }}][country]" value="{{ $branch['country'] ?? 'India' }}"></div>
                                        <div class="col-md-3"><label class="form-label">Phone</label><input class="form-control" data-branch-input name="branches[{{ $branchIndex }}][contact_no_1]" value="{{ $branch['contact_no_1'] ?? '' }}"></div>
                                        <div class="col-md-3"><label class="form-label">Alternate phone</label><input class="form-control" data-branch-input name="branches[{{ $branchIndex }}][contact_no_2]" value="{{ $branch['contact_no_2'] ?? '' }}"></div>
                                        <div class="col-md-3"><label class="form-label">Email</label><input type="email" class="form-control" data-branch-input name="branches[{{ $branchIndex }}][email_1]" value="{{ $branch['email_1'] ?? '' }}"></div>
                                        <div class="col-md-3"><label class="form-label">Alternate email</label><input type="email" class="form-control" data-branch-input name="branches[{{ $branchIndex }}][email_2]" value="{{ $branch['email_2'] ?? '' }}"></div>
                                        <div class="col-md-6"><label class="form-label">Billing address</label><textarea class="form-control" data-branch-input rows="2" name="branches[{{ $branchIndex }}][billing_address]">{{ $branch['billing_address'] ?? '' }}</textarea></div>
                                        <div class="col-md-6"><label class="form-label">Site access instructions</label><textarea class="form-control" data-branch-input rows="2" name="branches[{{ $branchIndex }}][site_access_instructions]">{{ $branch['site_access_instructions'] ?? '' }}</textarea></div>
                                    </div>
                                    <div class="nx-branch-contacts mt-4">
                                        <div class="d-flex justify-content-between align-items-center mb-3"><div><div class="fw-semibold">Branch contacts</div><div class="small text-secondary">Optional service, billing or escalation contacts</div></div><button type="button" class="btn btn-sm btn-light border add-branch-contact" data-branch-index="{{ $branchIndex }}"><i data-lucide="user-plus" style="width:15px"></i>Add contact</button></div>
                                        <div class="d-grid gap-3 branch-contact-list" data-branch-contacts="{{ $branchIndex }}">
                                            @foreach(($branch['contacts'] ?? [['name' => '']]) as $contactIndex => $contact)
                                                <div class="nx-branch-contact" data-branch-contact="{{ $contactIndex }}"><div class="row g-3"><div class="col-md-4"><label class="form-label">Name</label><input class="form-control" data-branch-input name="branches[{{ $branchIndex }}][contacts][{{ $contactIndex }}][name]" value="{{ $contact['name'] ?? '' }}"></div><div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" data-branch-input name="branches[{{ $branchIndex }}][contacts][{{ $contactIndex }}][phone]" value="{{ $contact['phone'] ?? '' }}"></div><div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" data-branch-input name="branches[{{ $branchIndex }}][contacts][{{ $contactIndex }}][email]" value="{{ $contact['email'] ?? '' }}"></div><div class="col-md-4"><label class="form-label">Designation</label><input class="form-control" data-branch-input name="branches[{{ $branchIndex }}][contacts][{{ $contactIndex }}][designation]" value="{{ $contact['designation'] ?? '' }}"></div><div class="col-md-4"><label class="form-label">Department</label><input class="form-control" data-branch-input name="branches[{{ $branchIndex }}][contacts][{{ $contactIndex }}][department]" value="{{ $contact['department'] ?? '' }}"></div><div class="col-md-4 d-flex align-items-end gap-3"><label class="form-check mb-2"><input class="form-check-input branch-primary-contact" data-branch="{{ $branchIndex }}" data-branch-input type="checkbox" name="branches[{{ $branchIndex }}][contacts][{{ $contactIndex }}][is_primary]" value="1" @checked(!empty($contact['is_primary']))> Primary</label>@if($contactIndex > 0)<button type="button" class="btn btn-sm btn-link text-danger remove-branch-contact mb-1">Remove</button>@endif</div></div></div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="nx-step-pane" data-pane="6">
                        <div class="mb-4"><h2 class="nx-section-title mb-1">Credit & payment terms</h2><p class="small text-secondary mb-0">Commercial defaults for future billing workflows.</p></div>
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label">Classification</label><select class="form-select" name="classification"><option value="">Select</option>@foreach(['strategic','regular','transactional','one_time'] as $classification)<option value="{{ $classification }}" @selected(old('classification') === $classification)>{{ str($classification)->headline() }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label">Customer owner</label><select class="form-select" name="owner_id"><option value="">Select</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('owner_id') == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
                            <div class="col-md-4"><label class="form-label">Manager</label><select class="form-select" name="manager_id"><option value="">Select</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old('manager_id') == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
                            <div class="col-12"><label class="form-label d-block">Source</label><div class="d-flex flex-wrap gap-2">@foreach(['direct','reference','existing','website','tender','architect','consultant','dealer','marketing','google'] as $source)<label class="nx-choice"><input type="checkbox" name="sources[]" value="{{ $source }}" @checked(in_array($source, old('sources', [])))><span>{{ str($source)->headline() }}</span></label>@endforeach</div></div>
                            <div class="col-md-4"><label class="form-label">Credit limit</label><input type="number" step="0.01" min="0" class="form-control" name="credit[credit_limit]" value="{{ old('credit.credit_limit', 0) }}"></div>
                            <div class="col-md-4"><label class="form-label">Credit days</label><input type="number" min="0" class="form-control" name="credit[credit_days]" value="{{ old('credit.credit_days', 0) }}"></div>
                            <div class="col-md-4"><label class="form-label">Payment mode</label><select class="form-select" name="credit[payment_mode]"><option value="">Select</option>@foreach(['Bank Transfer','Cheque','UPI','Cash'] as $mode)<option @selected(old('credit.payment_mode') === $mode)>{{ $mode }}</option>@endforeach</select></div>
                            <div class="col-12 d-flex flex-wrap gap-4 mt-4"><label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="credit[advance_required]" value="1" @checked(old('credit.advance_required'))> Advance required</label><label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="credit[tds_applicable]" value="1" @checked(old('credit.tds_applicable'))> TDS applicable</label><label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="credit[po_mandatory]" value="1" @checked(old('credit.po_mandatory'))> PO mandatory</label><label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="credit[eway_bill_applicable]" value="1" @checked(old('credit.eway_bill_applicable'))> E-way bill</label></div>
                        </div>
                    </div>

                    <div class="nx-step-pane" data-pane="7">
                        <div class="mb-4"><h2 class="nx-section-title mb-1">Documents & review</h2><p class="small text-secondary mb-0">Attach supporting documents and review before submission.</p></div>
                        <div class="row g-4">
                            <div class="col-md-6"><label class="form-label">Document type</label><select class="form-select" name="document_types[]"><option>GST Certificate</option><option>PAN Card</option><option>PO</option><option>AMC Agreement</option><option>Other</option></select></div>
                            <div class="col-md-6"><label class="form-label">File</label><input type="file" class="form-control" name="documents[]"></div>
                            <div class="col-12"><div class="nx-review-box"><h3 class="h6 fw-bold mb-3">Ready to create</h3><div class="row g-3 small"><div class="col-md-4"><span class="text-secondary d-block">Customer</span><strong data-review="name">Not entered</strong></div><div class="col-md-4"><span class="text-secondary d-block">Location</span><strong data-review="city">Not entered</strong></div><div class="col-md-4"><span class="text-secondary d-block">Branch type</span><strong data-review="branch_type">Single</strong></div></div></div></div>
                            <div class="col-12"><label class="form-label">Remarks</label><textarea class="form-control" name="remarks" rows="3">{{ old('remarks') }}</textarea></div>
                            <div class="col-12"><label class="form-check"><input type="checkbox" name="confirm_duplicate" value="1" class="form-check-input" @checked(old('confirm_duplicate'))> Allow creation if a duplicate name and city warning is detected</label></div>
                        </div>
                    </div>

                    <div class="nx-wizard-actions">
                        <div class="d-flex align-items-center gap-2 flex-nowrap">
                            <button type="button" class="btn btn-light border" id="prevStep" disabled><i data-lucide="arrow-left" style="width:17px"></i>Back</button>
                            <button type="button" class="btn btn-primary" id="nextStep">Next<i data-lucide="arrow-right" style="width:17px"></i></button>
                            <button type="submit" class="btn btn-success d-none" id="submitCustomer">Create customer<i data-lucide="check" style="width:17px"></i></button>
                        </div>
                        <span class="small text-secondary text-nowrap" id="stepCounter">Step 1 of 7</span>
                    </div>
                </section>
            </div>

            <div class="col-xl-3 d-none d-xl-block">
                <aside class="nx-card nx-summary p-4"><div class="nx-metric-icon mb-3"><i data-lucide="building-2"></i></div><h3 class="h6 fw-bold" id="summaryName">{{ old('name', 'New customer') }}</h3><div class="small text-secondary mb-4" id="summaryLocation">{{ old('city', 'Location not entered') }}</div><div class="d-flex justify-content-between small py-2 border-bottom"><span class="text-secondary">Progress</span><strong id="summaryProgress">14%</strong></div><div class="d-flex justify-content-between small py-2 border-bottom"><span class="text-secondary">Contacts</span><strong id="summaryContacts">{{ count($contactRows) }}</strong></div><div class="d-flex justify-content-between small py-2"><span class="text-secondary">Branches</span><strong id="summaryBranches">{{ old('branch_type', 'single') === 'multi' ? count($branchRows) : 'Single' }}</strong></div><div class="progress mt-3" style="height:6px"><div class="progress-bar" id="summaryBar" style="width:14%"></div></div><div class="alert alert-primary border-0 small mt-4 mb-0"><i data-lucide="info" style="width:15px"></i> Required fields are marked with an asterisk.</div></aside>
            </div>
        </div>
    </form>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('customerWizard');
        const panes = [...form.querySelectorAll('.nx-step-pane')];
        const stepButtons = [...form.querySelectorAll('.nx-step')];
        const branchType = document.getElementById('branchType');
        const branchList = document.getElementById('branchList');
        const contactList = document.getElementById('contactList');
        const nextButton = document.getElementById('nextStep');
        const previousButton = document.getElementById('prevStep');
        const submitButton = document.getElementById('submitCustomer');
        const saveDraftButton = document.getElementById('saveDraft');
        const serverErrors = @json($errors->messages());
        let step = 1;
        let dirty = false;
        let contactIndex = Math.max(0, ...[...document.querySelectorAll('[data-contact]')].map(el => Number(el.dataset.contact))) + 1;
        let branchIndex = Math.max(0, ...[...document.querySelectorAll('[data-branch]')].map(el => Number(el.dataset.branch))) + 1;

        const stepForKey = key => key.startsWith('contacts') ? 3 : key.startsWith('branches') ? 5 : ['gstin','pan','gst_registration_type','tan','tds_percent','udyam_no','msme_type'].includes(key.split('.')[0]) ? 4 : ['classification','sources','owner_id','manager_id','sales_person_id','credit'].includes(key.split('.')[0]) ? 6 : ['documents','document_types','remarks','confirm_duplicate'].includes(key.split('.')[0]) ? 7 : ['address_line_1','address_line_2','landmark','area','city','district','state','pin_code','country','contact_no_1','contact_no_2','email_1','email_2','site_access_instructions','billing_address','latitude','longitude'].includes(key.split('.')[0]) ? 2 : 1;
        const bracketName = key => { const parts = key.split('.'); return parts.shift() + parts.map(part => `[${part}]`).join(''); };
        const findField = key => {
            const name = bracketName(key);
            return [...form.elements].find(el => el.name === name || el.name === key || (key === 'types' && el.name === 'types[]'));
        };

        function clearFieldError(field) {
            field.classList.remove('is-invalid');
            const group = field.closest('.nx-field-group');
            group?.classList.remove('is-invalid-group');
            const container = group || field.parentElement;
            container?.querySelectorAll('.invalid-feedback[data-wizard-error]').forEach(el => el.remove());
        }

        function markError(key, message, targetStep = stepForKey(key)) {
            const field = findField(key);
            const group = field?.closest('.nx-field-group');
            if (field) {
                if (group) group.classList.add('is-invalid-group'); else field.classList.add('is-invalid');
                field.setAttribute('aria-invalid', 'true');
                const container = group || field.parentElement;
                if (container && !container.querySelector('.invalid-feedback[data-wizard-error]')) {
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback d-block';
                    feedback.dataset.wizardError = '1';
                    feedback.textContent = message;
                    container.appendChild(feedback);
                }
            }
            stepButtons[targetStep - 1]?.classList.add('error');
            return field;
        }

        function render(target, scroll = true) {
            step = Math.max(1, Math.min(7, target));
            panes.forEach(pane => pane.classList.toggle('active', Number(pane.dataset.pane) === step));
            stepButtons.forEach(button => {
                const number = Number(button.dataset.step);
                button.classList.toggle('active', number === step);
                button.classList.toggle('done', number < step && !button.classList.contains('error'));
            });
            previousButton.disabled = step === 1;
            nextButton.classList.toggle('d-none', step === 7);
            submitButton.classList.toggle('d-none', step !== 7);
            document.getElementById('stepCounter').textContent = `Step ${step} of 7`;
            document.getElementById('summaryProgress').textContent = `${Math.round(step / 7 * 100)}%`;
            document.getElementById('summaryBar').style.width = `${step / 7 * 100}%`;
            if (step === 7) {
                document.querySelector('[data-review=name]').textContent = form.elements.name.value || 'Not entered';
                document.querySelector('[data-review=city]').textContent = form.elements.city.value || 'Not entered';
                document.querySelector('[data-review=branch_type]').textContent = form.elements.branch_type.options[form.elements.branch_type.selectedIndex].text;
            }
            if (scroll) window.scrollTo({top: 0, behavior: 'smooth'});
        }

        function validateStep(number, focus = true) {
            const pane = form.querySelector(`[data-pane="${number}"]`);
            let firstInvalid = null;
            pane.querySelectorAll('input,select,textarea').forEach(field => {
                if (field.disabled) return;
                clearFieldError(field);
                if (!field.checkValidity()) {
                    firstInvalid ||= field;
                    markError(field.name || 'field', field.validationMessage, number);
                }
            });
            if (number === 1 && !form.querySelector('input[name="types[]"]:checked')) {
                firstInvalid ||= form.querySelector('input[name="types[]"]');
                markError('types', 'Select at least one customer type.', 1);
            }
            if (!firstInvalid) {
                stepButtons[number - 1]?.classList.remove('error');
                return true;
            }
            if (focus) {
                render(number);
                setTimeout(() => firstInvalid.focus({preventScroll: true}), 50);
                firstInvalid.scrollIntoView({behavior: 'smooth', block: 'center'});
            }
            return false;
        }

        function next() {
            if (!validateStep(step)) return;
            let target = step + 1;
            if (step === 4 && branchType.value === 'single') target = 6;
            render(target);
        }

        function previous() {
            let target = step - 1;
            if (step === 6 && branchType.value === 'single') target = 4;
            render(target);
        }

        function syncBranchMode() {
            const multi = branchType.value === 'multi';
            branchList.querySelectorAll('[data-branch-input]').forEach(field => field.disabled = !multi);
            branchList.querySelectorAll('.branch-required').forEach(field => field.required = multi);
            document.getElementById('summaryBranches').textContent = multi ? document.querySelectorAll('[data-branch]').length : 'Single';
        }

        function contactTemplate(index) {
            return `<div class="nx-repeat-card" data-contact="${index}"><div class="nx-repeat-header"><div><strong>Additional contact</strong><div class="small text-secondary">Customer-level contact</div></div><button type="button" class="btn btn-sm btn-link text-danger remove-card">Remove</button></div><div class="row g-3"><div class="col-md-4"><label class="form-label">Name *</label><input class="form-control" name="contacts[${index}][name]" required></div><div class="col-md-4"><label class="form-label">Designation</label><input class="form-control" name="contacts[${index}][designation]"></div><div class="col-md-4"><label class="form-label">Department</label><input class="form-control" name="contacts[${index}][department]"></div><div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="contacts[${index}][phone]"></div><div class="col-md-4"><label class="form-label">WhatsApp</label><input class="form-control" name="contacts[${index}][whatsapp]"></div><div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" name="contacts[${index}][email]"></div><div class="col-12 d-flex flex-wrap gap-4"><label class="form-check"><input class="form-check-input primary-contact" type="checkbox" name="contacts[${index}][is_primary]" value="1"> Primary</label><label class="form-check"><input class="form-check-input" type="checkbox" name="contacts[${index}][is_service]" value="1"> Service</label><label class="form-check"><input class="form-check-input" type="checkbox" name="contacts[${index}][is_billing]" value="1"> Billing</label><label class="form-check"><input class="form-check-input" type="checkbox" name="contacts[${index}][is_escalation]" value="1"> Escalation</label></div></div></div>`;
        }

        function branchContactTemplate(branch, contact) {
            return `<div class="nx-branch-contact" data-branch-contact="${contact}"><div class="row g-3"><div class="col-md-4"><label class="form-label">Name</label><input class="form-control" data-branch-input name="branches[${branch}][contacts][${contact}][name]"></div><div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" data-branch-input name="branches[${branch}][contacts][${contact}][phone]"></div><div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" data-branch-input name="branches[${branch}][contacts][${contact}][email]"></div><div class="col-md-4"><label class="form-label">Designation</label><input class="form-control" data-branch-input name="branches[${branch}][contacts][${contact}][designation]"></div><div class="col-md-4"><label class="form-label">Department</label><input class="form-control" data-branch-input name="branches[${branch}][contacts][${contact}][department]"></div><div class="col-md-4 d-flex align-items-end gap-3"><label class="form-check mb-2"><input class="form-check-input branch-primary-contact" data-branch="${branch}" data-branch-input type="checkbox" name="branches[${branch}][contacts][${contact}][is_primary]" value="1"> Primary</label><button type="button" class="btn btn-sm btn-link text-danger remove-branch-contact mb-1">Remove</button></div></div></div>`;
        }

        function branchTemplate(index) {
            return `<div class="nx-repeat-card nx-branch-card" data-branch="${index}"><div class="nx-repeat-header"><div><strong>Branch ${index + 1}</strong><div class="small text-secondary">Code will be generated automatically</div></div><button type="button" class="btn btn-sm btn-link text-danger remove-card">Remove branch</button></div><div class="row g-3"><div class="col-md-6"><label class="form-label">Branch name *</label><input class="form-control branch-required" data-branch-input name="branches[${index}][name]" required></div><div class="col-md-3"><label class="form-label">GSTIN</label><input class="form-control text-uppercase" data-branch-input name="branches[${index}][gstin]" maxlength="15" pattern="[0-9A-Za-z]{15}"></div><div class="col-md-3"><label class="form-label">GST type</label><select class="form-select" data-branch-input name="branches[${index}][gst_registration_type]"><option value="">Select</option><option value="regular">Regular</option><option value="composition">Composition</option><option value="unregistered">Unregistered</option></select></div><div class="col-md-6"><label class="form-label">Address line 1 *</label><input class="form-control branch-required" data-branch-input name="branches[${index}][address_line_1]" required></div><div class="col-md-6"><label class="form-label">Address line 2</label><input class="form-control" data-branch-input name="branches[${index}][address_line_2]"></div><div class="col-md-4"><label class="form-label">Landmark</label><input class="form-control" data-branch-input name="branches[${index}][landmark]"></div><div class="col-md-4"><label class="form-label">Area</label><input class="form-control" data-branch-input name="branches[${index}][area]"></div><div class="col-md-4"><label class="form-label">City *</label><input class="form-control branch-required" data-branch-input name="branches[${index}][city]" required></div><div class="col-md-3"><label class="form-label">District</label><input class="form-control" data-branch-input name="branches[${index}][district]"></div><div class="col-md-3"><label class="form-label">State *</label><input class="form-control branch-required" data-branch-input name="branches[${index}][state]" required></div><div class="col-md-3"><label class="form-label">PIN code *</label><input class="form-control branch-required" data-branch-input name="branches[${index}][pin_code]" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" required></div><div class="col-md-3"><label class="form-label">Country *</label><input class="form-control branch-required" data-branch-input name="branches[${index}][country]" value="India" required></div><div class="col-md-3"><label class="form-label">Phone</label><input class="form-control" data-branch-input name="branches[${index}][contact_no_1]"></div><div class="col-md-3"><label class="form-label">Alternate phone</label><input class="form-control" data-branch-input name="branches[${index}][contact_no_2]"></div><div class="col-md-3"><label class="form-label">Email</label><input type="email" class="form-control" data-branch-input name="branches[${index}][email_1]"></div><div class="col-md-3"><label class="form-label">Alternate email</label><input type="email" class="form-control" data-branch-input name="branches[${index}][email_2]"></div><div class="col-md-6"><label class="form-label">Billing address</label><textarea class="form-control" data-branch-input rows="2" name="branches[${index}][billing_address]"></textarea></div><div class="col-md-6"><label class="form-label">Site access instructions</label><textarea class="form-control" data-branch-input rows="2" name="branches[${index}][site_access_instructions]"></textarea></div></div><div class="nx-branch-contacts mt-4"><div class="d-flex justify-content-between align-items-center mb-3"><div><div class="fw-semibold">Branch contacts</div><div class="small text-secondary">Optional service, billing or escalation contacts</div></div><button type="button" class="btn btn-sm btn-light border add-branch-contact" data-branch-index="${index}"><i data-lucide="user-plus" style="width:15px"></i>Add contact</button></div><div class="d-grid gap-3 branch-contact-list" data-branch-contacts="${index}">${branchContactTemplate(index, 0)}</div></div></div>`;
        }

        nextButton.addEventListener('click', next);
        previousButton.addEventListener('click', previous);
        stepButtons.forEach(button => button.addEventListener('click', () => {
            let target = Number(button.dataset.step);
            if (target > step && !validateStep(step)) return;
            if (target === 5 && branchType.value === 'single') target = 6;
            render(target);
        }));
        branchType.addEventListener('change', syncBranchMode);
        document.getElementById('customerStatus').addEventListener('change', event => { if (event.target.value === 'blacklisted') Swal.fire({icon:'warning',title:'Blacklisted customer',text:'New work may be restricted for this account.',confirmButtonColor:'#1e40af'}); });
        document.getElementById('addContact').addEventListener('click', () => { contactList.insertAdjacentHTML('beforeend', contactTemplate(contactIndex++)); document.getElementById('summaryContacts').textContent = document.querySelectorAll('[data-contact]').length; window.refreshIcons?.(); });
        document.getElementById('addBranch').addEventListener('click', () => { branchList.insertAdjacentHTML('beforeend', branchTemplate(branchIndex++)); syncBranchMode(); window.refreshIcons?.(); });
        document.addEventListener('change', event => {
            if (event.target.classList.contains('primary-contact') && event.target.checked) document.querySelectorAll('.primary-contact').forEach(input => { if (input !== event.target) input.checked = false; });
            if (event.target.classList.contains('branch-primary-contact') && event.target.checked) document.querySelectorAll(`.branch-primary-contact[data-branch="${event.target.dataset.branch}"]`).forEach(input => { if (input !== event.target) input.checked = false; });
        });
        document.addEventListener('click', event => {
            const removeCard = event.target.closest('.remove-card');
            if (removeCard) { removeCard.closest('.nx-repeat-card').remove(); document.getElementById('summaryContacts').textContent = document.querySelectorAll('[data-contact]').length; syncBranchMode(); }
            const removeContact = event.target.closest('.remove-branch-contact');
            if (removeContact) removeContact.closest('.nx-branch-contact').remove();
            const addBranchContact = event.target.closest('.add-branch-contact');
            if (addBranchContact) { const branch = addBranchContact.dataset.branchIndex; const list = document.querySelector(`[data-branch-contacts="${branch}"]`); const contact = Math.max(-1, ...[...list.querySelectorAll('[data-branch-contact]')].map(row => Number(row.dataset.branchContact))) + 1; list.insertAdjacentHTML('beforeend', branchContactTemplate(branch, contact)); window.refreshIcons?.(); }
        });
        form.addEventListener('input', event => { dirty = true; clearFieldError(event.target); const pane = event.target.closest('.nx-step-pane'); if (pane && !pane.querySelector('.is-invalid,.is-invalid-group')) stepButtons[Number(pane.dataset.pane) - 1]?.classList.remove('error'); if (event.target.name === 'name') document.getElementById('summaryName').textContent = event.target.value || 'New customer'; if (event.target.name === 'city') document.getElementById('summaryLocation').textContent = event.target.value || 'Location not entered'; });
        form.addEventListener('change', () => dirty = true);
        form.addEventListener('submit', event => { const sequence = branchType.value === 'multi' ? [1,2,3,4,5,6,7] : [1,2,3,4,6,7]; const invalidStep = sequence.find(number => !validateStep(number, false)); if (invalidStep) { event.preventDefault(); render(invalidStep); validateStep(invalidStep, true); Swal.fire({icon:'error',title:'Please complete highlighted fields',confirmButtonColor:'#1e40af'}); } });

        document.getElementById('captureLocation').addEventListener('click', event => {
            if (!navigator.geolocation) return;
            event.target.disabled = true;
            navigator.geolocation.getCurrentPosition(position => { document.getElementById('latitude').value = position.coords.latitude; document.getElementById('longitude').value = position.coords.longitude; document.getElementById('locationStatus').textContent = 'Location captured successfully.'; event.target.textContent = 'Captured'; event.target.disabled = false; }, () => { event.target.disabled = false; Swal.fire({icon:'error',title:'Location unavailable',text:'Allow browser location access and try again.'}); });
        });

        function draftData() { const data = {}; new FormData(form).forEach((value, key) => { if (value instanceof File) return; if (data[key] !== undefined) data[key] = Array.isArray(data[key]) ? [...data[key], value] : [data[key], value]; else data[key] = value; }); return data; }
        async function saveDraft(silent = false) { const uuid = document.getElementById('draftUuid').value; const url = uuid ? `{{ url('/customers/draft') }}/${uuid}` : `{{ route('customers.draft.store') }}`; try { const response = await fetch(url, {method: uuid ? 'PUT' : 'POST', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body: JSON.stringify({current_step:step,data:draftData()})}); if (!response.ok) throw new Error(); const result = await response.json(); document.getElementById('draftUuid').value = result.draft.uuid; dirty = false; if (!silent) Swal.fire({toast:true,position:'top-end',icon:'success',title:'Draft saved',showConfirmButton:false,timer:1800}); } catch { if (!silent) Swal.fire({icon:'error',title:'Draft could not be saved',confirmButtonColor:'#1e40af'}); } }
        saveDraftButton.addEventListener('click', () => saveDraft(false));
        setInterval(() => { if (dirty) saveDraft(true); }, 30000);
        document.addEventListener('keydown', event => { if (event.altKey && event.key === 'ArrowRight') { event.preventDefault(); next(); } if (event.altKey && event.key === 'ArrowLeft') { event.preventDefault(); previous(); } if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') { event.preventDefault(); saveDraft(false); } });

        syncBranchMode();
        const errorSteps = Object.entries(serverErrors).map(([key, messages]) => { markError(key, messages[0]); return stepForKey(key); });
        if (errorSteps.length) render(Math.min(...errorSteps), false); else render(1, false);
    });
    </script>
    @endpush
</x-app-layout>
