<x-app-layout>
    <x-slot name="title">Edit {{ $customer->name }}</x-slot>
    <x-slot name="pageTitle">Edit customer</x-slot>
    <x-slot name="breadcrumb">Customers / {{ $customer->code }} / Edit</x-slot>

    @php
        $languages = ['Hindi','English','Marathi','Gujarati','Punjabi','Bengali','Tamil','Telugu','Kannada','Malayalam','Odia','Urdu','Other'];
        $credit = $customer->creditTerms;
    @endphp

    <form method="POST" action="{{ route('customers.update', $customer) }}" class="d-grid gap-4" novalidate>
        @csrf
        @method('PUT')
        <input type="hidden" name="country" value="India">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <a href="{{ route('customers.show', $customer) }}" class="small d-inline-flex align-items-center gap-1 mb-2"><i data-lucide="arrow-left" style="width:15px"></i>Customer workspace</a>
                <h2 class="h4 fw-bold mb-1">{{ $customer->name }}</h2>
                <div class="small text-secondary">Update account, address, tax and commercial information.</div>
            </div>
            <button class="btn btn-primary px-4" type="submit"><i data-lucide="save" style="width:17px"></i>Save customer</button>
        </div>

        @if($errors->any())
            <div class="alert alert-danger mb-0"><strong>Customer could not be updated.</strong><div class="small mt-1">{{ $errors->first() }}</div></div>
        @endif

        <section class="nx-card p-3 p-lg-4">
            <div class="mb-4"><div class="nx-overline">ACCOUNT</div><h3 class="h6 fw-bold mb-1">Customer identity</h3><div class="small text-secondary">Core relationship and account settings.</div></div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Customer name *</label><input class="form-control" name="name" value="{{ old('name', $customer->name) }}" required maxlength="255"></div>
                <div class="col-md-3"><label class="form-label">Customer group *</label><select class="form-select" name="customer_group_id" required><option value="">Select group</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected(old('customer_group_id', $customer->customer_group_id) == $group->id)>{{ $group->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Mother tongue</label><select class="form-select" name="mother_tongue"><option value="">Select language</option>@foreach($languages as $language)<option value="{{ $language }}" @selected(old('mother_tongue', $customer->mother_tongue) === $language)>{{ $language }}</option>@endforeach</select></div>
                <div class="col-12"><label class="form-label d-block">Customer type *</label><div class="d-flex flex-wrap gap-2">@foreach(['individual','corporate','architect','consultant','agent','dealer','government'] as $type)<label class="nx-choice"><input type="checkbox" name="types[]" value="{{ $type }}" @checked(in_array($type, old('types', $customer->types ?? [])))><span>{{ str($type)->headline() }}</span></label>@endforeach</div></div>
                <div class="col-12"><label class="form-label d-block">Segments</label><div class="d-flex flex-wrap gap-2">@foreach(['healthcare','education','industrials','builder','hospitality','office'] as $segment)<label class="nx-choice"><input type="checkbox" name="segments[]" value="{{ $segment }}" @checked(in_array($segment, old('segments', $customer->segments ?? [])))><span>{{ str($segment)->headline() }}</span></label>@endforeach</div></div>
                <div class="col-md-3"><label class="form-label">Priority *</label><select class="form-select" name="priority" required>@foreach(['normal','high','very_high'] as $option)<option value="{{ $option }}" @selected(old('priority', $customer->priority) === $option)>{{ str($option)->headline() }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Status *</label><select class="form-select" name="status" required>@foreach(['active','inactive','blacklisted'] as $option)<option value="{{ $option }}" @selected(old('status', $customer->status) === $option)>{{ str($option)->headline() }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Classification</label><select class="form-select" name="classification"><option value="">Not set</option>@foreach(['strategic','regular','transactional','one_time'] as $option)<option value="{{ $option }}" @selected(old('classification', $customer->classification) === $option)>{{ str($option)->headline() }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Business potential</label><input class="form-control" name="business_potential" value="{{ old('business_potential', $customer->business_potential) }}" maxlength="255"></div>
            </div>
        </section>

        <section class="nx-card p-3 p-lg-4">
            <div class="mb-4"><div class="nx-overline">LOCATION & CONTACT</div><h3 class="h6 fw-bold mb-1">Registered address</h3></div>
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Address line 1 *</label><input class="form-control" name="address_line_1" value="{{ old('address_line_1', $customer->address_line_1) }}" required></div>
                <div class="col-md-6"><label class="form-label">Address line 2</label><input class="form-control" name="address_line_2" value="{{ old('address_line_2', $customer->address_line_2) }}"></div>
                <div class="col-md-3"><label class="form-label">Landmark</label><input class="form-control" name="landmark" value="{{ old('landmark', $customer->landmark) }}"></div>
                <div class="col-md-3"><label class="form-label">Area</label><input class="form-control" name="area" value="{{ old('area', $customer->area) }}"></div>
                <div class="col-md-3"><label class="form-label">State *</label><select class="form-select" name="state" data-india-state required><option value="{{ old('state', $customer->state) }}" selected>{{ old('state', $customer->state) }}</option></select></div>
                <div class="col-md-3"><label class="form-label">District *</label><select class="form-select" name="district" data-india-district required><option value="{{ old('district', $customer->district) }}" selected>{{ old('district', $customer->district) }}</option></select></div>
                <div class="col-md-4"><label class="form-label">City *</label><input class="form-control" name="city" value="{{ old('city', $customer->city) }}" required></div>
                <div class="col-md-2"><label class="form-label">PIN code *</label><input class="form-control" name="pin_code" value="{{ old('pin_code', $customer->pin_code) }}" inputmode="numeric" maxlength="6" required></div>
                <div class="col-md-3"><label class="form-label">Contact number *</label><input class="form-control" name="contact_no_1" value="{{ old('contact_no_1', $customer->contact_no_1) }}" required></div>
                <div class="col-md-3"><label class="form-label">Alternate number</label><input class="form-control" name="contact_no_2" value="{{ old('contact_no_2', $customer->contact_no_2) }}"></div>
                <div class="col-md-6"><label class="form-label">Email *</label><input class="form-control" type="email" name="email_1" value="{{ old('email_1', $customer->email_1) }}" required></div>
                <div class="col-md-6"><label class="form-label">Alternate email</label><input class="form-control" type="email" name="email_2" value="{{ old('email_2', $customer->email_2) }}"></div>
                <div class="col-md-6"><label class="form-label">Site access instructions</label><textarea class="form-control" name="site_access_instructions" rows="3" maxlength="2000">{{ old('site_access_instructions', $customer->site_access_instructions) }}</textarea></div>
                <div class="col-md-6"><label class="form-label">Billing address</label><textarea class="form-control" name="billing_address" rows="3" maxlength="2000">{{ old('billing_address', $customer->billing_address) }}</textarea></div>
                <input type="hidden" name="latitude" value="{{ old('latitude', $customer->latitude) }}"><input type="hidden" name="longitude" value="{{ old('longitude', $customer->longitude) }}">
            </div>
        </section>

        <section class="nx-card p-3 p-lg-4">
            <div class="mb-4"><div class="nx-overline">TAX & OWNERSHIP</div><h3 class="h6 fw-bold mb-1">Regulatory and relationship owners</h3></div>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">GST type</label><select class="form-select" name="gst_registration_type"><option value="">Not set</option>@foreach(['regular','composition','unregistered'] as $option)<option value="{{ $option }}" @selected(old('gst_registration_type', $customer->gst_registration_type) === $option)>{{ str($option)->headline() }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">GSTIN</label><input class="form-control text-uppercase" name="gstin" value="{{ old('gstin', $customer->gstin) }}" maxlength="15"></div>
                <div class="col-md-3"><label class="form-label">PAN</label><input class="form-control text-uppercase" name="pan" value="{{ old('pan', $customer->pan) }}" maxlength="10"></div>
                <div class="col-md-3"><label class="form-label">TAN</label><input class="form-control text-uppercase" name="tan" value="{{ old('tan', $customer->tan) }}"></div>
                <div class="col-md-4"><label class="form-label">TDS %</label><input class="form-control" type="number" name="tds_percent" value="{{ old('tds_percent', $customer->tds_percent) }}" min="0" max="100" step="0.01"></div>
                <div class="col-md-4"><label class="form-label">Udyam number</label><input class="form-control" name="udyam_no" value="{{ old('udyam_no', $customer->udyam_no) }}"></div>
                <div class="col-md-4"><label class="form-label">MSME type</label><select class="form-select" name="msme_type"><option value="">Not applicable</option>@foreach(['micro','small','medium'] as $option)<option value="{{ $option }}" @selected(old('msme_type', $customer->msme_type) === $option)>{{ str($option)->headline() }}</option>@endforeach</select></div>
                @foreach(['owner_id' => 'Account owner', 'sales_person_id' => 'Sales person', 'manager_id' => 'Manager'] as $field => $label)
                    <div class="col-md-4"><label class="form-label">{{ $label }}</label><select class="form-select" name="{{ $field }}"><option value="">Not assigned</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(old($field, $customer->{$field}) == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
                @endforeach
                <div class="col-12"><label class="form-label">Remarks</label><textarea class="form-control" name="remarks" rows="3" maxlength="5000">{{ old('remarks', $customer->remarks) }}</textarea></div>
            </div>
        </section>

        <section class="nx-card p-3 p-lg-4">
            <div class="mb-4"><div class="nx-overline">COMMERCIAL</div><h3 class="h6 fw-bold mb-1">Credit terms</h3></div>
            <div class="row g-3">
                <div class="col-md-3"><label class="form-label">Credit limit</label><input class="form-control" type="number" name="credit[credit_limit]" value="{{ old('credit.credit_limit', $credit?->credit_limit) }}" min="0" step="0.01"></div>
                <div class="col-md-3"><label class="form-label">Credit days</label><input class="form-control" type="number" name="credit[credit_days]" value="{{ old('credit.credit_days', $credit?->credit_days) }}" min="0"></div>
                <div class="col-md-3"><label class="form-label">Payment mode</label><input class="form-control" name="credit[payment_mode]" value="{{ old('credit.payment_mode', $credit?->payment_mode) }}"></div>
                <div class="col-md-3"><label class="form-label">Retention %</label><input class="form-control" type="number" name="credit[retention_percent]" value="{{ old('credit.retention_percent', $credit?->retention_percent) }}" min="0" max="100" step="0.01"></div>
                <div class="col-md-6"><label class="form-label">Billing cycle</label><input class="form-control" name="credit[billing_cycle]" value="{{ old('credit.billing_cycle', $credit?->billing_cycle) }}"></div>
                <div class="col-md-6"><label class="form-label">Invoice submission method</label><input class="form-control" name="credit[invoice_submission_method]" value="{{ old('credit.invoice_submission_method', $credit?->invoice_submission_method) }}"></div>
                <div class="col-12 d-flex flex-wrap gap-4">@foreach(['advance_required' => 'Advance required','tds_applicable' => 'TDS applicable','retention_applicable' => 'Retention applicable','po_mandatory' => 'PO mandatory','eway_bill_applicable' => 'E-way bill applicable'] as $field => $label)<label class="form-check"><input type="hidden" name="credit[{{ $field }}]" value="0"><input class="form-check-input" type="checkbox" name="credit[{{ $field }}]" value="1" @checked(old("credit.$field", $credit?->{$field}))> {{ $label }}</label>@endforeach</div>
                <div class="col-12"><label class="form-label">Default payment terms</label><textarea class="form-control" name="credit[default_payment_terms]" rows="3" maxlength="2000">{{ old('credit.default_payment_terms', $credit?->default_payment_terms) }}</textarea></div>
            </div>
        </section>

        <div class="d-flex justify-content-end gap-2"><a class="btn btn-light border" href="{{ route('customers.show', $customer) }}">Cancel</a><button class="btn btn-primary px-4" type="submit"><i data-lucide="save" style="width:17px"></i>Save customer</button></div>
    </form>
</x-app-layout>
