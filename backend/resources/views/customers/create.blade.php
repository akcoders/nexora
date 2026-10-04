<x-app-layout>
    <x-slot name="title">New Customer</x-slot>
    <x-slot name="pageTitle">Create customer</x-slot>
    <x-slot name="breadcrumb">Customers / New</x-slot>

    <form id="customerWizard" action="{{ route('customers.store') }}" method="POST" enctype="multipart/form-data">@csrf
        <input type="hidden" id="draftUuid" value="">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div><a href="{{ route('customers.index') }}" class="small d-inline-flex align-items-center gap-1 mb-2"><i data-lucide="arrow-left" style="width:15px"></i>Customers</a><div class="text-secondary small">Complete the guided form. Your progress auto-saves every 30 seconds.</div></div>
            <button type="button" class="btn btn-light border d-inline-flex align-items-center gap-2" id="saveDraft"><i data-lucide="save" style="width:17px"></i>Save draft</button>
        </div>

        @if($errors->any())<div class="alert alert-danger"><strong>Please review the form.</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <section class="nx-card p-3 p-lg-4 mb-4">
            <div class="nx-stepper" id="stepper">
                @foreach(['Basic','Address','Contacts','Regulatory','Branches','Credit','Review'] as $index => $label)
                    <button type="button" class="nx-step {{ $index === 0 ? 'active' : '' }}" data-step="{{ $index + 1 }}"><span class="nx-step-number">{{ $index + 1 }}</span><span>{{ $label }}</span></button>
                @endforeach
            </div>
        </section>

        <div class="row g-4">
            <div class="col-xl-9">
                <section class="nx-card p-3 p-lg-4">
                    <div class="nx-step-pane active" data-pane="1">
                        <div class="mb-4"><h2 class="nx-section-title mb-1">Basic information</h2><p class="small text-secondary mb-0">Customer identity, relationship type and account status.</p></div>
                        <div class="row g-3">
                            <div class="col-md-8"><label class="form-label">Customer name *</label><input class="form-control" name="name" value="{{ old('name') }}" placeholder="e.g. Tata AIA Life Insurance" required></div>
                            <div class="col-md-4"><label class="form-label">Customer group</label><select class="form-select" name="customer_group_id"><option value="">Select group</option>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select></div>
                            <div class="col-12"><label class="form-label d-block">Customer type *</label><div class="d-flex flex-wrap gap-2">@foreach(['individual','corporate','architect','consultant','agent','dealer','government'] as $type)<label class="nx-choice"><input type="checkbox" name="types[]" value="{{ $type }}"><span>{{ str($type)->headline() }}</span></label>@endforeach</div></div>
                            <div class="col-12"><label class="form-label d-block">Segments</label><div class="d-flex flex-wrap gap-2">@foreach(['healthcare','education','industrials','builder','hospitality','office'] as $segment)<label class="nx-choice"><input type="checkbox" name="segments[]" value="{{ $segment }}"><span>{{ str($segment)->headline() }}</span></label>@endforeach</div></div>
                            <div class="col-md-4"><label class="form-label">Priority *</label><select class="form-select" name="priority" required><option value="normal">Normal</option><option value="high">High</option><option value="very_high">Very high</option></select></div>
                            <div class="col-md-4"><label class="form-label">Status *</label><select class="form-select" name="status" id="customerStatus" required><option value="active">Active</option><option value="inactive">Inactive</option><option value="blacklisted">Blacklisted</option></select></div>
                            <div class="col-md-4"><label class="form-label">Branch type *</label><select class="form-select" name="branch_type" id="branchType" required><option value="single">Single</option><option value="multi">Multiple branches</option></select></div>
                        </div>
                    </div>

                    <div class="nx-step-pane" data-pane="2">
                        <div class="mb-4"><h2 class="nx-section-title mb-1">Address & location</h2><p class="small text-secondary mb-0">Service location and primary communication details.</p></div>
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Address line 1</label><input class="form-control" name="address_line_1" value="{{ old('address_line_1') }}"></div><div class="col-md-6"><label class="form-label">Address line 2</label><input class="form-control" name="address_line_2"></div>
                            <div class="col-md-4"><label class="form-label">Landmark</label><input class="form-control" name="landmark"></div><div class="col-md-4"><label class="form-label">Area</label><input class="form-control" name="area"></div><div class="col-md-4"><label class="form-label">City</label><input class="form-control" name="city" id="city"></div>
                            <div class="col-md-4"><label class="form-label">District</label><input class="form-control" name="district"></div><div class="col-md-4"><label class="form-label">State</label><input class="form-control" name="state"></div><div class="col-md-2"><label class="form-label">PIN code</label><input class="form-control" name="pin_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"></div><div class="col-md-2"><label class="form-label">Country</label><input class="form-control" name="country" value="India"></div>
                            <div class="col-md-6"><label class="form-label">Contact number *</label><input class="form-control" name="contact_no_1" required></div><div class="col-md-6"><label class="form-label">Alternate number</label><input class="form-control" name="contact_no_2"></div>
                            <div class="col-md-6"><label class="form-label">Email *</label><input class="form-control" type="email" name="email_1" required></div><div class="col-md-6"><label class="form-label">Alternate email</label><input class="form-control" type="email" name="email_2"></div>
                            <div class="col-md-6"><label class="form-label">Site access instructions</label><textarea class="form-control" rows="3" name="site_access_instructions" placeholder="Gate entry, PPE, parking or contact instructions"></textarea></div><div class="col-md-6"><label class="form-label">Billing address</label><textarea class="form-control" rows="3" name="billing_address"></textarea></div>
                            <input type="hidden" name="latitude" id="latitude"><input type="hidden" name="longitude" id="longitude">
                            <div class="col-12"><div class="rounded-3 border bg-light d-flex align-items-center justify-content-center text-secondary" style="min-height:150px"><div class="text-center"><i data-lucide="map-pin" class="mb-2"></i><div class="small">Map picker integration point</div><button class="btn btn-sm btn-outline-primary mt-2" type="button" onclick="navigator.geolocation?.getCurrentPosition(p=>{latitude.value=p.coords.latitude;longitude.value=p.coords.longitude;this.textContent='Location captured'})">Use current location</button></div></div></div>
                        </div>
                    </div>

                    <div class="nx-step-pane" data-pane="3">
                        <div class="d-flex justify-content-between align-items-start mb-4"><div><h2 class="nx-section-title mb-1">Contact persons</h2><p class="small text-secondary mb-0">Add service, billing and escalation contacts.</p></div><button type="button" class="btn btn-sm btn-outline-primary" id="addContact"><i data-lucide="user-plus" style="width:16px"></i> Add contact</button></div>
                        <div class="d-grid gap-3" id="contactList">
                            <div class="nx-repeat-card" data-contact="0"><div class="d-flex justify-content-between mb-3"><strong>Primary contact</strong><span class="badge badge-soft-primary">Contact 1</span></div><div class="row g-3"><div class="col-md-4"><label class="form-label">Name *</label><input class="form-control" name="contacts[0][name]" required></div><div class="col-md-4"><label class="form-label">Designation</label><input class="form-control" name="contacts[0][designation]"></div><div class="col-md-4"><label class="form-label">Department</label><input class="form-control" name="contacts[0][department]"></div><div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="contacts[0][phone]"></div><div class="col-md-4"><label class="form-label">WhatsApp</label><input class="form-control" name="contacts[0][whatsapp]"></div><div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" name="contacts[0][email]"></div><div class="col-12 d-flex flex-wrap gap-4"><label class="form-check"><input class="form-check-input primary-contact" type="checkbox" name="contacts[0][is_primary]" value="1" checked> Primary</label><label class="form-check"><input class="form-check-input" type="checkbox" name="contacts[0][is_service]" value="1"> Service</label><label class="form-check"><input class="form-check-input" type="checkbox" name="contacts[0][is_billing]" value="1"> Billing</label><label class="form-check"><input class="form-check-input" type="checkbox" name="contacts[0][is_escalation]" value="1"> Escalation</label></div></div></div>
                        </div>
                    </div>

                    <div class="nx-step-pane" data-pane="4">
                        <div class="mb-4"><h2 class="nx-section-title mb-1">Regulatory details</h2><p class="small text-secondary mb-0">Tax registrations and MSME classification.</p></div>
                        <div class="row g-3"><div class="col-md-6"><label class="form-label">GSTIN</label><input class="form-control text-uppercase" name="gstin" maxlength="15" pattern="[0-9A-Za-z]{15}"></div><div class="col-md-6"><label class="form-label">PAN</label><input class="form-control text-uppercase" name="pan" maxlength="10" pattern="[0-9A-Za-z]{10}"></div><div class="col-md-4"><label class="form-label">GST registration type</label><select class="form-select" name="gst_registration_type"><option value="">Select</option><option>Regular</option><option>Composition</option><option>Unregistered</option></select></div><div class="col-md-4"><label class="form-label">TAN</label><input class="form-control" name="tan"></div><div class="col-md-4"><label class="form-label">TDS %</label><input class="form-control" type="number" name="tds_percent" min="0" max="100" step="0.01"></div><div class="col-md-6"><label class="form-label">MSME / Udyam no.</label><input class="form-control" name="udyam_no"></div><div class="col-md-6"><label class="form-label">MSME type</label><select class="form-select" name="msme_type"><option value="">Not applicable</option><option value="micro">Micro</option><option value="small">Small</option><option value="medium">Medium</option></select></div></div>
                    </div>

                    <div class="nx-step-pane" data-pane="5">
                        <div class="d-flex justify-content-between align-items-start mb-4"><div><h2 class="nx-section-title mb-1">Branch details</h2><p class="small text-secondary mb-0">Add at least one branch for a multi-branch customer.</p></div><button type="button" class="btn btn-sm btn-outline-primary" id="addBranch"><i data-lucide="plus" style="width:16px"></i> Add branch</button></div>
                        <div class="d-grid gap-3" id="branchList"><div class="nx-repeat-card" data-branch="0"><div class="d-flex justify-content-between mb-3"><strong>Branch 1</strong><span class="badge badge-soft-primary">Code auto-generated</span></div><div class="row g-3"><div class="col-md-6"><label class="form-label">Branch name *</label><input class="form-control branch-required" name="branches[0][name]"></div><div class="col-md-6"><label class="form-label">GSTIN override</label><input class="form-control" name="branches[0][gstin]" maxlength="15"></div><div class="col-md-8"><label class="form-label">Address</label><input class="form-control" name="branches[0][address_line_1]"></div><div class="col-md-4"><label class="form-label">City</label><input class="form-control" name="branches[0][city]"></div></div></div></div>
                    </div>

                    <div class="nx-step-pane" data-pane="6">
                        <div class="mb-4"><h2 class="nx-section-title mb-1">Credit & payment terms</h2><p class="small text-secondary mb-0">Commercial defaults for future billing workflows.</p></div>
                        <div class="row g-3"><div class="col-md-4"><label class="form-label">Classification</label><select class="form-select" name="classification"><option value="">Select</option><option value="strategic">Strategic</option><option value="regular">Regular</option><option value="transactional">Transactional</option><option value="one_time">One-time</option></select></div><div class="col-md-4"><label class="form-label">Customer owner</label><select class="form-select" name="owner_id"><option value="">Select</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Manager</label><select class="form-select" name="manager_id"><option value="">Select</option>@foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach</select></div><div class="col-12"><label class="form-label d-block">Source</label><div class="d-flex flex-wrap gap-2">@foreach(['direct','reference','existing','website','tender','architect','consultant','dealer','marketing','google'] as $source)<label class="nx-choice"><input type="checkbox" name="sources[]" value="{{ $source }}"><span>{{ str($source)->headline() }}</span></label>@endforeach</div></div><div class="col-md-4"><label class="form-label">Credit limit</label><input type="number" step="0.01" min="0" class="form-control" name="credit[credit_limit]" value="0"></div><div class="col-md-4"><label class="form-label">Credit days</label><input type="number" min="0" class="form-control" name="credit[credit_days]" value="0"></div><div class="col-md-4"><label class="form-label">Payment mode</label><select class="form-select" name="credit[payment_mode]"><option value="">Select</option><option>Bank Transfer</option><option>Cheque</option><option>UPI</option><option>Cash</option></select></div><div class="col-12 d-flex flex-wrap gap-4 mt-4"><label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="credit[advance_required]" value="1"> Advance required</label><label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="credit[tds_applicable]" value="1"> TDS applicable</label><label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="credit[po_mandatory]" value="1"> PO mandatory</label><label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="credit[eway_bill_applicable]" value="1"> E-way bill</label></div></div>
                    </div>

                    <div class="nx-step-pane" data-pane="7">
                        <div class="mb-4"><h2 class="nx-section-title mb-1">Documents & review</h2><p class="small text-secondary mb-0">Attach supporting documents and review before submission.</p></div>
                        <div class="row g-4"><div class="col-md-6"><label class="form-label">Document type</label><select class="form-select" name="document_types[]"><option>GST Certificate</option><option>PAN Card</option><option>PO</option><option>AMC Agreement</option><option>Other</option></select></div><div class="col-md-6"><label class="form-label">File</label><input type="file" class="form-control" name="documents[]"></div><div class="col-12"><div class="rounded-3 border bg-light p-4"><h3 class="h6 fw-bold mb-3">Ready to create</h3><div class="row g-3 small" id="reviewSummary"><div class="col-md-4"><span class="text-secondary d-block">Customer</span><strong data-review="name">Not entered</strong></div><div class="col-md-4"><span class="text-secondary d-block">Location</span><strong data-review="city">Not entered</strong></div><div class="col-md-4"><span class="text-secondary d-block">Branch type</span><strong data-review="branch_type">Single</strong></div></div></div></div><div class="col-12"><label class="form-label">Remarks</label><textarea class="form-control" name="remarks" rows="3"></textarea></div><div class="col-12"><label class="form-check"><input type="checkbox" name="confirm_duplicate" value="1" class="form-check-input"> Allow creation if a duplicate name and city warning is detected</label></div></div>
                    </div>

                    <hr class="my-4">
                    <div class="d-flex justify-content-between align-items-center"><button type="button" class="btn btn-light border" id="prevStep" disabled><i data-lucide="arrow-left" style="width:17px"></i> Back</button><span class="small text-secondary" id="stepCounter">Step 1 of 7</span><div><button type="button" class="btn btn-primary" id="nextStep">Next <i data-lucide="arrow-right" style="width:17px"></i></button><button type="submit" class="btn btn-success d-none" id="submitCustomer">Create customer <i data-lucide="check" style="width:17px"></i></button></div></div>
                </section>
            </div>
            <div class="col-xl-3 d-none d-xl-block"><aside class="nx-card nx-summary p-4"><div class="nx-metric-icon mb-3"><i data-lucide="building-2"></i></div><h3 class="h6 fw-bold" id="summaryName">New customer</h3><div class="small text-secondary mb-4" id="summaryLocation">Location not entered</div><div class="d-flex justify-content-between small py-2 border-bottom"><span class="text-secondary">Progress</span><strong id="summaryProgress">14%</strong></div><div class="d-flex justify-content-between small py-2 border-bottom"><span class="text-secondary">Contacts</span><strong id="summaryContacts">1</strong></div><div class="d-flex justify-content-between small py-2"><span class="text-secondary">Branches</span><strong id="summaryBranches">Single</strong></div><div class="progress mt-3" style="height:6px"><div class="progress-bar" id="summaryBar" style="width:14%"></div></div><div class="alert alert-primary border-0 small mt-4 mb-0"><i data-lucide="info" style="width:15px"></i> Required fields are marked with an asterisk.</div></aside></div>
        </div>
    </form>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('customerWizard');
        let step = 1, contactIndex = 1, branchIndex = 1, dirty = false;
        const panes = [...document.querySelectorAll('.nx-step-pane')], steps = [...document.querySelectorAll('.nx-step')];
        form.addEventListener('change', () => dirty = true);
        form.addEventListener('input', event => { dirty = true; if (event.target.name === 'name') summaryName.textContent = event.target.value || 'New customer'; if (event.target.name === 'city') summaryLocation.textContent = event.target.value || 'Location not entered'; });

        function render(target) {
            step = Math.max(1, Math.min(7, target));
            panes.forEach(p => p.classList.toggle('active', Number(p.dataset.pane) === step));
            steps.forEach(s => { const n = Number(s.dataset.step); s.classList.toggle('active', n === step); s.classList.toggle('done', n < step); });
            prevStep.disabled = step === 1; nextStep.classList.toggle('d-none', step === 7); submitCustomer.classList.toggle('d-none', step !== 7); stepCounter.textContent = `Step ${step} of 7`; summaryProgress.textContent = `${Math.round(step / 7 * 100)}%`; summaryBar.style.width = `${step / 7 * 100}%`;
            if (step === 7) { document.querySelector('[data-review=name]').textContent = form.elements.name.value || 'Not entered'; document.querySelector('[data-review=city]').textContent = form.elements.city.value || 'Not entered'; document.querySelector('[data-review=branch_type]').textContent = form.elements.branch_type.options[form.elements.branch_type.selectedIndex].text; }
            window.scrollTo({top:0,behavior:'smooth'});
        }
        function validStep() {
            const pane = document.querySelector(`[data-pane="${step}"]`); let valid = true;
            if (step === 1 && !form.querySelector('input[name="types[]"]:checked')) { Swal.fire({icon:'warning',title:'Select a customer type',confirmButtonColor:'#1e40af'}); return false; }
            [...pane.querySelectorAll('input,select,textarea')].forEach(el => { if (!el.checkValidity()) { el.reportValidity(); valid = false; } });
            return valid;
        }
        function next() { if (!validStep()) return; let target = step + 1; if (step === 4 && branchType.value === 'single') target = 6; render(target); }
        function previous() { let target = step - 1; if (step === 6 && branchType.value === 'single') target = 4; render(target); }
        nextStep.addEventListener('click', next); prevStep.addEventListener('click', previous);
        steps.forEach(s => s.addEventListener('click', () => { const target = Number(s.dataset.step); if (target <= step || validStep()) render(target === 5 && branchType.value === 'single' ? 6 : target); }));
        branchType.addEventListener('change', () => { const multi = branchType.value === 'multi'; document.querySelectorAll('.branch-required').forEach(el => el.required = multi); summaryBranches.textContent = multi ? document.querySelectorAll('[data-branch]').length : 'Single'; });
        customerStatus.addEventListener('change', () => { if (customerStatus.value === 'blacklisted') Swal.fire({icon:'warning',title:'Blacklisted customer',text:'New work may be restricted for this account.',confirmButtonColor:'#1e40af'}); });
        document.addEventListener('change', e => { if (e.target.classList.contains('primary-contact') && e.target.checked) document.querySelectorAll('.primary-contact').forEach(c => { if (c !== e.target) c.checked = false; }); });
        addContact.addEventListener('click', () => { const i = contactIndex++; contactList.insertAdjacentHTML('beforeend', `<div class="nx-repeat-card" data-contact="${i}"><div class="d-flex justify-content-between mb-3"><strong>Additional contact</strong><button type="button" class="btn btn-sm btn-link text-danger remove-card">Remove</button></div><div class="row g-3"><div class="col-md-4"><label class="form-label">Name *</label><input class="form-control" name="contacts[${i}][name]" required></div><div class="col-md-4"><label class="form-label">Phone</label><input class="form-control" name="contacts[${i}][phone]"></div><div class="col-md-4"><label class="form-label">Email</label><input type="email" class="form-control" name="contacts[${i}][email]"></div><div class="col-12"><label class="form-check"><input class="form-check-input primary-contact" type="checkbox" name="contacts[${i}][is_primary]" value="1"> Primary contact</label></div></div></div>`); summaryContacts.textContent = document.querySelectorAll('[data-contact]').length; });
        addBranch.addEventListener('click', () => { const i = branchIndex++; branchList.insertAdjacentHTML('beforeend', `<div class="nx-repeat-card" data-branch="${i}"><div class="d-flex justify-content-between mb-3"><strong>Branch ${i + 1}</strong><button type="button" class="btn btn-sm btn-link text-danger remove-card">Remove</button></div><div class="row g-3"><div class="col-md-6"><label class="form-label">Branch name *</label><input class="form-control branch-required" name="branches[${i}][name]" required></div><div class="col-md-6"><label class="form-label">City</label><input class="form-control" name="branches[${i}][city]"></div></div></div>`); summaryBranches.textContent = document.querySelectorAll('[data-branch]').length; });
        document.addEventListener('click', e => { if (e.target.classList.contains('remove-card')) { e.target.closest('.nx-repeat-card').remove(); summaryContacts.textContent = document.querySelectorAll('[data-contact]').length; summaryBranches.textContent = branchType.value === 'multi' ? document.querySelectorAll('[data-branch]').length : 'Single'; } });

        function draftData() { const data = {}; new FormData(form).forEach((value,key) => { if (value instanceof File) return; if (data[key] !== undefined) data[key] = Array.isArray(data[key]) ? [...data[key],value] : [data[key],value]; else data[key] = value; }); return data; }
        async function saveDraft(silent = false) {
            const uuid = draftUuid.value, url = uuid ? `{{ url('/customers/draft') }}/${uuid}` : `{{ route('customers.draft.store') }}`;
            try { const response = await fetch(url,{method:uuid?'PUT':'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body:JSON.stringify({current_step:step,data:draftData()})}); if(!response.ok) throw new Error(); const result=await response.json(); draftUuid.value=result.draft.uuid; dirty=false; if(!silent) Swal.fire({toast:true,position:'top-end',icon:'success',title:'Draft saved',showConfirmButton:false,timer:1800}); }
            catch { if(!silent) Swal.fire({icon:'error',title:'Draft could not be saved',confirmButtonColor:'#1e40af'}); }
        }
        saveDraft.addEventListener('click', () => saveDraft(false));
        setInterval(() => { if (dirty) saveDraft(true); }, 30000);
        document.addEventListener('keydown', e => { if (e.altKey && e.key === 'ArrowRight') { e.preventDefault(); next(); } if (e.altKey && e.key === 'ArrowLeft') { e.preventDefault(); previous(); } if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); saveDraft(false); } });
    });
    </script>
    @endpush
</x-app-layout>
