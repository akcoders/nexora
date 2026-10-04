<x-app-layout>
    <x-slot name="title">New Service Job</x-slot>
    <x-slot name="pageTitle">Create service job</x-slot>
    <x-slot name="breadcrumb">Service Jobs / New</x-slot>

    <div class="mb-4"><a href="{{ route('service-jobs.index') }}" class="small d-inline-flex align-items-center gap-1"><i data-lucide="arrow-left" style="width:15px"></i>Service jobs</a></div>
    @if($errors->any())<div class="alert alert-danger"><strong>Please correct the highlighted fields.</strong><ul class="mb-0 mt-2 small">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form method="POST" action="{{ route('service-jobs.store') }}" id="serviceJobForm">
        @csrf
        <div class="row g-4">
            <div class="col-xl-8">
                <section class="nx-card p-3 p-lg-4 mb-4">
                    <div class="d-flex align-items-center gap-3 mb-4"><span class="nx-metric-icon"><i data-lucide="building-2"></i></span><div><h2 class="nx-section-title mb-1">Customer & equipment</h2><div class="small text-secondary">Choose the service location and AC/equipment.</div></div></div>
                    <div class="row g-3">
                        <div class="col-md-7"><label class="form-label">Customer *</label><select class="form-select @error('customer_id') is-invalid @enderror" name="customer_id" id="customerId" required><option value="">Select customer</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }} · {{ $customer->code }}</option>@endforeach</select></div>
                        <div class="col-md-5"><label class="form-label">Branch</label><select class="form-select" name="customer_branch_id" id="branchId"><option value="">Head office / main address</option></select></div>
                        <div class="col-md-7"><label class="form-label">Equipment / AC</label><select class="form-select" name="customer_equipment_id" id="equipmentId"><option value="">Not selected</option></select></div>
                        <div class="col-md-5"><label class="form-label">Service type</label><select class="form-select" name="service_type_id"><option value="">General service</option>@foreach($serviceTypes as $serviceType)<option value="{{ $serviceType->id }}" @selected(old('service_type_id') == $serviceType->id)>{{ $serviceType->name }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">Customer phone *</label><input class="form-control @error('customer_phone') is-invalid @enderror" name="customer_phone" id="customerPhone" value="{{ old('customer_phone') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Alternate phone</label><input class="form-control" name="alternate_phone" value="{{ old('alternate_phone') }}"></div>
                        <div class="col-12"><label class="form-label">Service address *</label><textarea class="form-control @error('service_address') is-invalid @enderror" rows="3" name="service_address" id="serviceAddress" required>{{ old('service_address') }}</textarea></div>
                        <input type="hidden" name="latitude" id="serviceLatitude" value="{{ old('latitude') }}"><input type="hidden" name="longitude" id="serviceLongitude" value="{{ old('longitude') }}">
                    </div>
                </section>

                <section class="nx-card p-3 p-lg-4">
                    <div class="d-flex align-items-center gap-3 mb-4"><span class="nx-metric-icon"><i data-lucide="clipboard-plus"></i></span><div><h2 class="nx-section-title mb-1">Job details</h2><div class="small text-secondary">Complaint, schedule, priority and technician assignment.</div></div></div>
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Customer complaint *</label><textarea class="form-control @error('complaint') is-invalid @enderror" rows="4" name="complaint" placeholder="Describe the issue reported by the customer" required>{{ old('complaint') }}</textarea></div>
                        <div class="col-md-4"><label class="form-label">Origin *</label><select class="form-select" name="origin" required>@foreach(['manual','complaint','crm','amc','sales','project','preventive_maintenance'] as $origin)<option value="{{ $origin }}" @selected(old('origin', 'manual') === $origin)>{{ str($origin)->headline() }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">Priority *</label><select class="form-select" name="priority" required>@foreach(['normal','high','very_high','emergency'] as $priority)<option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ str($priority)->headline() }}</option>@endforeach</select></div>
                        <div class="col-md-4"><label class="form-label">Assigned technician *</label><select class="form-select @error('assigned_to') is-invalid @enderror" name="assigned_to" required><option value="">Select technician</option>@foreach($technicians as $technician)<option value="{{ $technician->id }}" @selected(old('assigned_to') == $technician->id)>{{ $technician->name }}{{ $technician->designation ? ' · '.$technician->designation : '' }}</option>@endforeach</select></div>
                        <div class="col-md-6"><label class="form-label">Scheduled date & time *</label><input type="datetime-local" class="form-control @error('scheduled_at') is-invalid @enderror" name="scheduled_at" value="{{ old('scheduled_at', now()->addDay()->format('Y-m-d\TH:i')) }}" required></div>
                        <div class="col-md-3"><label class="form-label">Preferred date</label><input type="date" class="form-control" name="preferred_visit_date" value="{{ old('preferred_visit_date') }}"></div>
                        <div class="col-md-3"><label class="form-label">Preferred time</label><input type="time" class="form-control" name="preferred_visit_time" value="{{ old('preferred_visit_time') }}"></div>
                        <div class="col-12"><label class="form-label">Internal notes</label><textarea class="form-control" rows="3" name="notes">{{ old('notes') }}</textarea></div>
                    </div>
                </section>
            </div>
            <div class="col-xl-4">
                <aside class="nx-card p-4 nx-summary"><div class="nx-metric-icon mb-3"><i data-lucide="route"></i></div><h3 class="h6 fw-bold">Technician workflow</h3><div class="small text-secondary mb-4">Status moves automatically as the technician completes each step.</div>@foreach(['Visit confirmed','Journey & arrival','Pre-service inspection','Service estimate approval','Work & materials','Payment & completion'] as $index => $step)<div class="d-flex gap-3 align-items-center py-2"><span class="badge rounded-circle bg-primary" style="width:25px;height:25px;display:grid;place-items:center">{{ $index + 1 }}</span><span class="small fw-semibold">{{ $step }}</span></div>@endforeach<div class="d-grid gap-2 mt-4"><button class="btn btn-primary" type="submit"><i data-lucide="send" style="width:17px"></i>Create & assign job</button><a class="btn btn-light border" href="{{ route('service-jobs.index') }}">Cancel</a></div></aside>
            </div>
        </div>
    </form>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const customers = {{ Js::from($customers) }};
            const customerSelect = document.getElementById('customerId');
            const branchSelect = document.getElementById('branchId');
            const equipmentSelect = document.getElementById('equipmentId');
            const oldBranch = @json(old('customer_branch_id'));
            const oldEquipment = @json(old('customer_equipment_id'));
            const address = customer => [customer.address_line_1, customer.address_line_2, customer.area, customer.city, customer.state, customer.pin_code].filter(Boolean).join(', ');
            const syncCustomer = () => {
                const customer = customers.find(item => String(item.id) === customerSelect.value);
                branchSelect.innerHTML = '<option value="">Head office / main address</option>';
                equipmentSelect.innerHTML = '<option value="">Not selected</option>';
                if (!customer) return;
                customer.branches.forEach(branch => branchSelect.add(new Option(branch.name, branch.id, false, String(branch.id) === String(oldBranch))));
                customer.equipments.forEach(equipment => equipmentSelect.add(new Option([equipment.equipment_type, equipment.brand, equipment.model, equipment.capacity, equipment.location].filter(Boolean).join(' · '), equipment.id, false, String(equipment.id) === String(oldEquipment))));
                if (!document.getElementById('customerPhone').value) document.getElementById('customerPhone').value = customer.contact_no_1 || '';
                if (!document.getElementById('serviceAddress').value) document.getElementById('serviceAddress').value = address(customer);
                document.getElementById('serviceLatitude').value ||= customer.latitude || '';
                document.getElementById('serviceLongitude').value ||= customer.longitude || '';
            };
            branchSelect.addEventListener('change', () => {
                const customer = customers.find(item => String(item.id) === customerSelect.value);
                const branch = customer?.branches.find(item => String(item.id) === branchSelect.value);
                if (branch) {
                    document.getElementById('serviceAddress').value = address(branch);
                    document.getElementById('customerPhone').value = branch.contact_no_1 || document.getElementById('customerPhone').value;
                    document.getElementById('serviceLatitude').value = branch.latitude || '';
                    document.getElementById('serviceLongitude').value = branch.longitude || '';
                }
            });
            customerSelect.addEventListener('change', syncCustomer);
            syncCustomer();
        });
    </script>
    @endpush
</x-app-layout>
