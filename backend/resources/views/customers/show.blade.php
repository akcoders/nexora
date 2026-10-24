<x-app-layout>
    <x-slot name="title">{{ $customer->name }}</x-slot>
    <x-slot name="pageTitle">Customer workspace</x-slot>
    <x-slot name="breadcrumb">Customers / {{ $customer->code }}</x-slot>

    @php
        $openJobs = $customer->serviceJobs->whereNotIn('status', ['completed', 'cancelled'])->count();
        $unmappedEquipment = $customer->equipments->whereNull('customer_floor_plan_id');
        $address = collect([$customer->address_line_1, $customer->address_line_2, $customer->area, $customer->city, $customer->district, $customer->state, $customer->pin_code])->filter()->join(', ');
    @endphp

    <a href="{{ route('customers.index') }}" class="small d-inline-flex align-items-center gap-1 mb-3"><i data-lucide="arrow-left" style="width:15px"></i>All customers</a>

    @if($errors->any())
        <div class="alert alert-danger d-flex align-items-start gap-2"><i data-lucide="circle-alert" style="width:18px" class="mt-1 flex-shrink-0"></i><div><strong>Could not save.</strong><div class="small">{{ $errors->first() }}</div></div></div>
    @endif

    <section class="nx-card nx-customer-hero mb-4">
        <div class="p-4 p-xl-5 d-flex flex-column flex-lg-row justify-content-between gap-4">
            <div class="d-flex gap-3 gap-md-4 align-items-start">
                <span class="nx-customer-logo">{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
                <div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <span class="badge rounded-pill badge-soft-primary">{{ $customer->code }}</span>
                        @if($customer->group)<span class="nx-group-badge nx-group-badge-sm"><i data-lucide="tag"></i>{{ $customer->group->name }}</span>@endif
                        <span class="badge rounded-pill {{ $customer->status === 'active' ? 'badge-soft-success' : ($customer->status === 'blacklisted' ? 'badge-soft-danger' : 'bg-light text-secondary') }}">{{ str($customer->status)->headline() }}</span>
                        <span class="badge rounded-pill {{ $customer->priority === 'very_high' ? 'badge-soft-danger' : ($customer->priority === 'high' ? 'badge-soft-warning' : 'badge-soft-primary') }}">{{ str($customer->priority)->headline() }} priority</span>
                    </div>
                    <h2 class="h3 fw-bold mb-2">{{ $customer->name }}</h2>
                    <div class="text-secondary d-flex flex-wrap gap-3 small">
                        <span class="d-inline-flex align-items-center gap-1"><i data-lucide="map-pin" style="width:15px"></i>{{ $customer->city ?: 'Location not added' }}{{ $customer->state ? ', '.$customer->state : '' }}</span>
                        @if($customer->contact_no_1)<a class="text-secondary d-inline-flex align-items-center gap-1" href="tel:{{ $customer->contact_no_1 }}"><i data-lucide="phone" style="width:15px"></i>{{ $customer->contact_no_1 }}</a>@endif
                        @if($customer->email_1)<a class="text-secondary d-inline-flex align-items-center gap-1" href="mailto:{{ $customer->email_1 }}"><i data-lucide="mail" style="width:15px"></i>{{ $customer->email_1 }}</a>@endif
                    </div>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-start gap-2">
                @can('customers.update')<a class="btn btn-light border" href="{{ route('customers.edit', $customer) }}"><i data-lucide="pencil" style="width:17px"></i>Edit customer</a>@endcan
                <button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#floorPlanModal"><i data-lucide="building-2" style="width:17px"></i>Add building layout</button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#unitModal"><i data-lucide="plus" style="width:17px"></i>Add AC unit</button>
            </div>
        </div>
        <div class="nx-customer-stats">
            <div><strong>{{ $customer->branches->count() }}</strong><span>Branches</span></div>
            <div><strong>{{ $customer->contacts->count() }}</strong><span>Contacts</span></div>
            <div><strong>{{ $customer->equipments->count() }}</strong><span>AC units</span></div>
            <div><strong>{{ $customer->floorPlans->count() }}</strong><span>Floor plans</span></div>
            <div><strong>{{ $openJobs }}</strong><span>Open jobs</span></div>
        </div>
    </section>

    <ul class="nav nx-profile-tabs mb-4" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#customerOverview" type="button"><i data-lucide="layout-dashboard"></i>Overview</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerAssets" type="button"><i data-lucide="air-vent"></i>Units & floor plans <span class="badge bg-primary ms-1">{{ $customer->equipments->count() }}</span></button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerPeople" type="button"><i data-lucide="users"></i>Branches & contacts</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#customerService" type="button"><i data-lucide="history"></i>Service history</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="customerOverview">
            <div class="row g-4">
                <div class="col-xl-8">
                    <section class="nx-card p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-4"><div><div class="nx-overline">ACCOUNT PROFILE</div><h3 class="h6 fw-bold mb-0">Company information</h3></div><span class="nx-metric-icon"><i data-lucide="building-2"></i></span></div>
                        <div class="row g-4 small">
                            <div class="col-md-6"><span class="text-secondary d-block mb-1">Customer type</span><strong>{{ collect($customer->types)->map(fn($type) => str($type)->headline())->join(', ') }}</strong></div>
                            <div class="col-md-6"><span class="text-secondary d-block mb-1">Segment</span><strong>{{ collect($customer->segments)->map(fn($segment) => str($segment)->headline())->join(', ') ?: '—' }}</strong></div>
                            <div class="col-md-6"><span class="text-secondary d-block mb-1">Customer group</span><strong>{{ $customer->group?->name ?? '—' }}</strong></div>
                            <div class="col-md-6"><span class="text-secondary d-block mb-1">Mother tongue</span><strong>{{ $customer->mother_tongue ?: '—' }}</strong></div>
                            <div class="col-md-6"><span class="text-secondary d-block mb-1">Classification</span><strong>{{ $customer->classification ? str($customer->classification)->headline() : '—' }}</strong></div>
                            <div class="col-12"><span class="text-secondary d-block mb-1">Registered address</span><strong>{{ $address ?: 'Address not added' }}</strong></div>
                            @if($customer->site_access_instructions)<div class="col-12"><div class="nx-note"><i data-lucide="info" style="width:17px"></i><div><span class="fw-semibold d-block">Site access</span>{{ $customer->site_access_instructions }}</div></div></div>@endif
                        </div>
                    </section>
                    <section class="nx-card overflow-hidden">
                        <div class="p-4 border-bottom d-flex align-items-center justify-content-between"><div><div class="nx-overline">RECENT ACTIVITY</div><h3 class="h6 fw-bold mb-0">Latest service jobs</h3></div><a href="{{ route('service-jobs.index', ['customer_id' => $customer->id]) }}" class="small fw-semibold">View all</a></div>
                        <div class="table-responsive"><table class="table nx-table mb-0"><thead><tr><th class="ps-4">Job</th><th>Service</th><th>Technician</th><th>Status</th><th class="pe-4"></th></tr></thead><tbody>
                            @forelse($customer->serviceJobs->take(5) as $job)
                                <tr><td class="ps-4"><a class="fw-semibold text-dark" href="{{ route('service-jobs.show', $job) }}">{{ $job->job_no }}</a><small class="d-block text-secondary">{{ str($job->complaint)->limit(48) }}</small></td><td>{{ $job->serviceType?->name ?? 'General service' }}</td><td>{{ $job->technician?->name ?? '—' }}</td><td><span class="badge rounded-pill {{ $job->status === 'completed' ? 'badge-soft-success' : 'badge-soft-primary' }}">{{ str($job->status)->headline() }}</span></td><td class="pe-4"><a class="nx-icon-btn" href="{{ route('service-jobs.show', $job) }}"><i data-lucide="arrow-right" style="width:16px"></i></a></td></tr>
                            @empty<tr><td colspan="5" class="text-center py-5 text-secondary">No service history yet.</td></tr>@endforelse
                        </tbody></table></div>
                    </section>
                </div>
                <div class="col-xl-4">
                    <aside class="nx-card p-4 mb-4"><div class="nx-overline">REGULATORY</div><h3 class="h6 fw-bold mb-3">Tax details</h3><div class="nx-detail-list"><div><span>GSTIN</span><strong>{{ $customer->gstin ?: '—' }}</strong></div><div><span>PAN</span><strong>{{ $customer->pan ?: '—' }}</strong></div><div><span>GST type</span><strong>{{ $customer->gst_registration_type ? str($customer->gst_registration_type)->headline() : '—' }}</strong></div><div><span>Udyam</span><strong>{{ $customer->udyam_no ?: '—' }}</strong></div></div></aside>
                    <aside class="nx-card p-4 mb-4"><div class="nx-overline">COMMERCIAL</div><h3 class="h6 fw-bold mb-3">Credit terms</h3><div class="nx-detail-list"><div><span>Credit limit</span><strong>₹{{ number_format((float) ($customer->creditTerms?->credit_limit ?? 0), 2) }}</strong></div><div><span>Credit days</span><strong>{{ $customer->creditTerms?->credit_days ?? 0 }} days</strong></div><div><span>Payment mode</span><strong>{{ $customer->creditTerms?->payment_mode ? str($customer->creditTerms->payment_mode)->headline() : '—' }}</strong></div><div><span>PO mandatory</span><strong>{{ $customer->creditTerms?->po_mandatory ? 'Yes' : 'No' }}</strong></div></div></aside>
                    <aside class="nx-card p-4"><div class="nx-overline">DOCUMENTS</div><h3 class="h6 fw-bold mb-3">Customer files</h3>@forelse($customer->documents as $document)<a class="d-flex gap-2 align-items-center py-2 border-bottom" href="{{ asset('storage/'.$document->path) }}" target="_blank"><span class="nx-file-icon"><i data-lucide="file-text"></i></span><span class="min-w-0"><strong class="small d-block text-truncate">{{ $document->name }}</strong><small class="text-secondary">{{ $document->type }}</small></span></a>@empty<div class="text-secondary small">No documents uploaded.</div>@endforelse</aside>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="customerAssets">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><h3 class="h5 fw-bold mb-1">Building, floors & rooms</h3><div class="small text-secondary">Create every floor and room, then assign one or more AC units to any room.</div></div><div class="d-flex gap-2"><button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#floorPlanModal"><i data-lucide="building-2" style="width:17px"></i>Add layout</button><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#unitModal"><i data-lucide="plus" style="width:17px"></i>Add AC unit</button></div></div>

            @forelse($customer->floorPlans->whereNotNull('building_uuid')->groupBy('building_uuid') as $floors)
                @php($building = $floors->first())
                <section class="nx-card p-3 p-lg-4 mb-4 nx-building-layout">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4"><div><div class="nx-overline">BUILDING</div><h4 class="h5 fw-bold mb-1">{{ $building->building_name }}</h4><div class="small text-secondary">{{ $building->branch?->name ?? 'Head office' }} · {{ $floors->count() }} {{ Str::plural('floor', $floors->count()) }}</div></div><span class="badge rounded-pill badge-soft-primary">{{ $floors->sum(fn ($floor) => $floor->rooms->sum(fn ($room) => $room->equipments->count())) }} AC units</span></div>
                    <div class="d-grid gap-3">
                        @foreach($floors as $floor)
                            <article class="nx-floor-structure">
                                <div class="nx-floor-structure-head"><span class="nx-branch-icon"><i data-lucide="layers-3"></i></span><div><strong>{{ $floor->floor_label }}</strong><div class="small text-secondary">{{ $floor->rooms->count() }} {{ Str::plural('room', $floor->rooms->count()) }}</div></div></div>
                                <div class="nx-room-grid">
                                    @foreach($floor->rooms as $room)
                                        <div class="nx-room-card">
                                            <div class="d-flex align-items-center justify-content-between gap-2"><div class="d-flex align-items-center gap-2"><i data-lucide="door-open"></i><strong>{{ $room->name }}</strong></div><span class="badge bg-light text-secondary">{{ $room->equipments->count() }} AC</span></div>
                                            <div class="d-grid gap-2 mt-3">
                                                @forelse($room->equipments as $unit)
                                                    <div class="nx-room-unit"><span class="nx-unit-mini-icon"><i data-lucide="air-vent"></i></span><div class="min-w-0"><strong class="d-block text-truncate">{{ collect([$unit->brand, $unit->model])->filter()->join(' ') ?: $unit->equipment_type }}</strong><small class="text-secondary">{{ $unit->capacity ?: 'Capacity not set' }}{{ $unit->serial_no ? ' · '.$unit->serial_no : '' }}</small></div></div>
                                                @empty
                                                    <div class="small text-secondary">No AC units assigned.</div>
                                                @endforelse
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="nx-empty-panel mb-4"><span class="nx-empty-icon"><i data-lucide="building-2"></i></span><div><h4 class="h6 fw-bold mb-1">No building layout yet</h4><p class="small text-secondary mb-0">Define the number of floors and room names; no image upload is needed.</p></div><button class="btn btn-light border ms-lg-auto" data-bs-toggle="modal" data-bs-target="#floorPlanModal">Create building layout</button></div>
            @endforelse

            @foreach($customer->floorPlans->whereNull('building_uuid')->whereNotNull('image_path') as $plan)
                <section class="nx-card p-3 p-lg-4 mb-4"><div class="d-flex flex-wrap justify-content-between gap-2 mb-3"><div><div class="nx-overline">LEGACY IMAGE PLAN</div><h4 class="h6 fw-bold mb-1">{{ $plan->name }}</h4><div class="small text-secondary">{{ $plan->floor_label ?: 'Floor plan' }}</div></div><span class="badge rounded-pill bg-light text-secondary">{{ $plan->equipments->count() }} units</span></div><div class="nx-floor-plan"><img src="{{ asset('storage/'.$plan->image_path) }}" alt="{{ $plan->name }} floor plan">@foreach($plan->equipments as $unit)@if($unit->plan_x !== null && $unit->plan_y !== null)<span class="nx-unit-pin" style="left:{{ $unit->plan_x }}%;top:{{ $unit->plan_y }}%"><i data-lucide="air-vent"></i><span>{{ $unit->location }}</span></span>@endif @endforeach</div></section>
            @endforeach

            <div class="d-flex align-items-center justify-content-between mb-3"><div><div class="nx-overline">STANDALONE ASSETS</div><h4 class="h6 fw-bold mb-0">Units not assigned to a room</h4></div><span class="small text-secondary">{{ $unmappedEquipment->count() }} units</span></div>
            <div class="row g-4">
                @forelse($unmappedEquipment as $unit)
                    <div class="col-md-6 col-xl-4"><article class="nx-equipment-card h-100"><div class="nx-equipment-visual"><img src="{{ asset('images/ac-unit-card.png') }}" alt="AC unit"><span class="badge rounded-pill {{ $unit->active ? 'badge-soft-success' : 'bg-light text-secondary' }}">{{ $unit->active ? 'Active' : 'Inactive' }}</span></div><div class="p-3 p-lg-4"><div class="small text-primary fw-bold mb-1">{{ strtoupper($unit->equipment_type) }}</div><h5 class="h6 fw-bold mb-2">{{ collect([$unit->brand, $unit->model])->filter()->join(' ') ?: 'AC unit' }}</h5><div class="nx-unit-specs"><span><i data-lucide="map-pin"></i>{{ $unit->location }}</span><span><i data-lucide="gauge"></i>{{ $unit->capacity ?: 'Capacity not set' }}</span><span><i data-lucide="scan-barcode"></i>{{ $unit->serial_no ?: 'No serial number' }}</span><span><i data-lucide="building"></i>{{ $unit->branch?->name ?? 'Head office' }}</span></div></div></article></div>
                @empty<div class="col-12"><div class="nx-empty-panel"><span class="nx-empty-icon"><i data-lucide="air-vent"></i></span><div><h4 class="h6 fw-bold mb-1">Every unit has a room</h4><p class="small text-secondary mb-0">New standalone units will appear here until assigned.</p></div><button class="btn btn-primary ms-lg-auto" data-bs-toggle="modal" data-bs-target="#unitModal">Add another unit</button></div></div>@endforelse
            </div>
        </div>

        <div class="tab-pane fade" id="customerPeople">
            <div class="row g-4">
                <div class="col-xl-7"><section class="nx-card overflow-hidden"><div class="p-4 border-bottom"><div class="nx-overline">LOCATIONS</div><h3 class="h6 fw-bold mb-0">Branches</h3></div><div class="p-4 d-grid gap-3">
                    <article class="nx-branch-item"><span class="nx-branch-icon"><i data-lucide="landmark"></i></span><div><div class="d-flex flex-wrap gap-2 align-items-center"><strong>Head office</strong><span class="badge rounded-pill badge-soft-primary">HO</span></div><div class="small text-secondary mt-1">{{ $address ?: 'Address not added' }}</div><div class="small mt-2">{{ $customer->contact_no_1 ?: 'No phone' }} · {{ $customer->gstin ?: 'No GSTIN' }}</div></div></article>
                    @foreach($customer->branches as $branch)<article class="nx-branch-item"><span class="nx-branch-icon"><i data-lucide="building-2"></i></span><div class="flex-grow-1"><div class="d-flex flex-wrap justify-content-between gap-2"><div><strong>{{ $branch->name }}</strong><span class="small text-secondary ms-2">{{ $branch->code }}</span></div><span class="badge rounded-pill {{ $branch->active ? 'badge-soft-success' : 'bg-light text-secondary' }}">{{ $branch->active ? 'Active' : 'Inactive' }}</span></div><div class="small text-secondary mt-1">{{ collect([$branch->address_line_1, $branch->address_line_2, $branch->area, $branch->city, $branch->state, $branch->pin_code])->filter()->join(', ') }}</div><div class="small mt-2">{{ $branch->contact_no_1 ?: 'No phone' }} · {{ $branch->gstin ?: 'No GSTIN' }} · {{ $branch->contacts->count() }} contacts</div></div></article>@endforeach
                </div></section></div>
                    <div class="col-xl-5"><section class="nx-card overflow-hidden"><div class="p-4 border-bottom"><div class="nx-overline">PEOPLE</div><h3 class="h6 fw-bold mb-0">Contact persons</h3></div><div class="p-4 d-grid gap-3">@forelse($customer->contacts as $contact)<article class="nx-contact-item"><span class="nx-avatar">{{ strtoupper(substr($contact->name, 0, 1)) }}</span><div class="min-w-0 flex-grow-1"><div class="d-flex flex-wrap align-items-center gap-2"><strong>{{ $contact->name }}</strong>@if($contact->is_primary)<span class="badge rounded-pill badge-soft-primary">Primary</span>@endif @if($contact->is_service)<span class="badge rounded-pill badge-soft-success">Service</span>@endif</div><small class="text-secondary d-block">{{ collect([$contact->designation, $contact->department, $contact->branch?->name, $contact->mother_tongue ? $contact->mother_tongue.' speaking' : null])->filter()->join(' · ') ?: 'General contact' }}</small><div class="small mt-1">{{ $contact->phone ?: 'No phone' }}{{ $contact->email ? ' · '.$contact->email : '' }}</div></div></article>@empty<div class="text-secondary small">No contact persons available.</div>@endforelse</div></section></div>
            </div>
        </div>

        <div class="tab-pane fade" id="customerService">
            <section class="nx-card overflow-hidden"><div class="p-4 border-bottom d-flex flex-wrap justify-content-between gap-3"><div><div class="nx-overline">LIFETIME HISTORY</div><h3 class="h6 fw-bold mb-0">Service jobs</h3></div><a href="{{ route('service-jobs.create', ['customer_id' => $customer->id]) }}" class="btn btn-primary btn-sm"><i data-lucide="plus" style="width:16px"></i>New service job</a></div><div class="table-responsive"><table class="table nx-table mb-0"><thead><tr><th class="ps-4">Job</th><th>Equipment</th><th>Complaint</th><th>Technician</th><th>Status</th><th>Amount</th><th class="pe-4"></th></tr></thead><tbody>@forelse($customer->serviceJobs as $job)<tr><td class="ps-4"><strong>{{ $job->job_no }}</strong><small class="d-block text-secondary">{{ $job->created_at->format('d M Y') }}</small></td><td>{{ $job->equipment?->location ?? 'General site' }}</td><td>{{ str($job->complaint)->limit(55) }}</td><td>{{ $job->technician?->name ?? '—' }}</td><td><span class="badge rounded-pill {{ $job->status === 'completed' ? 'badge-soft-success' : ($job->status === 'cancelled' ? 'badge-soft-danger' : 'badge-soft-primary') }}">{{ str($job->status)->headline() }}</span></td><td>₹{{ number_format((float) $job->final_amount, 2) }}</td><td class="pe-4"><a class="nx-icon-btn" href="{{ route('service-jobs.show', $job) }}"><i data-lucide="arrow-right" style="width:16px"></i></a></td></tr>@empty<tr><td colspan="7" class="text-center py-5 text-secondary">No service jobs found.</td></tr>@endforelse</tbody></table></div></section>
        </div>
    </div>

    <div class="modal fade" id="floorPlanModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content" method="POST" action="{{ route('customers.floor-plans.store', $customer) }}">
                @csrf
                <input type="hidden" name="form_context" value="floor_plan">
                <div class="modal-header"><div><h5 class="modal-title">Create building layout</h5><small class="text-secondary">Set the floors, name every room, then assign as many AC units as needed.</small></div><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="row g-3 mb-4">
                        <div class="col-md-5"><label class="form-label">Building name *</label><input class="form-control" name="building_name" value="{{ old('building_name') }}" placeholder="Main building" required maxlength="150"></div>
                        <div class="col-md-4"><label class="form-label">Branch</label><select class="form-select" name="customer_branch_id"><option value="">Head office</option>@foreach($customer->branches as $branch)<option value="{{ $branch->id }}" @selected(old('customer_branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                        <div class="col-md-3"><label class="form-label">Number of floors *</label><input class="form-control" type="number" id="floorCount" value="{{ count(old('floors', [['name' => 'Ground floor', 'rooms' => ['Room 1']]])) }}" min="1" max="50" required></div>
                    </div>
                    <div class="d-grid gap-3" id="floorBuilder"></div>
                    <div class="mt-3"><label class="form-label">Layout notes</label><textarea class="form-control" name="notes" rows="2" placeholder="Optional building access or layout notes">{{ old('notes') }}</textarea></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i data-lucide="building-2" style="width:17px"></i>Create layout</button></div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="unitModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form class="modal-content" method="POST" action="{{ route('customers.equipment.store', $customer) }}">
                @csrf
                <input type="hidden" name="form_context" value="unit">
                <div class="modal-header"><div><h5 class="modal-title">Add AC unit</h5><small class="text-secondary">Assign the unit to a room or keep it as a standalone asset.</small></div><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
                <div class="modal-body"><div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Equipment type *</label><select class="form-select" name="equipment_type" required><option value="">Select type</option>@forelse($equipmentTypes as $type)<option value="{{ $type->label }}" @selected(old('equipment_type') === $type->label)>{{ $type->label }}</option>@empty @foreach(['Split AC','Window AC','Cassette AC','Ductable AC','VRV / VRF','Chiller'] as $type)<option value="{{ $type }}" @selected(old('equipment_type') === $type)>{{ $type }}</option>@endforeach @endforelse</select></div>
                    <div class="col-md-6"><label class="form-label">Product master</label><select class="form-select" name="product_id"><option value="">Not linked</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>{{ $product->name }}{{ $product->brand ? ' · '.$product->brand : '' }}{{ $product->model ? ' · '.$product->model : '' }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">Brand</label><input class="form-control" name="brand" value="{{ old('brand') }}" placeholder="Daikin" maxlength="120"></div>
                    <div class="col-md-4"><label class="form-label">Model</label><input class="form-control" name="model" value="{{ old('model') }}" placeholder="FTKF50" maxlength="120"></div>
                    <div class="col-md-4"><label class="form-label">Serial number</label><input class="form-control" name="serial_no" value="{{ old('serial_no') }}" maxlength="120"></div>
                    <div class="col-md-4"><label class="form-label">Capacity</label><select class="form-select" name="capacity"><option value="">Select capacity</option>@foreach($equipmentCapacities as $capacity)<option value="{{ $capacity->label }}" @selected(old('capacity') === $capacity->label)>{{ $capacity->label }}</option>@endforeach</select></div>
                    <div class="col-md-8"><label class="form-label">Building / floor / room</label><select class="form-select" name="customer_room_id" id="unitRoom"><option value="">Standalone unit (no room)</option>@foreach($customer->floorPlans->whereNotNull('building_uuid')->groupBy('building_uuid') as $floors)<optgroup label="{{ $floors->first()->building_name }}">@foreach($floors as $floor)@foreach($floor->rooms as $room)<option value="{{ $room->id }}" data-branch="{{ $floor->customer_branch_id }}" @selected(old('customer_room_id') == $room->id)>{{ $floor->floor_label }} · {{ $room->name }} ({{ $room->equipments->count() }} AC)</option>@endforeach @endforeach</optgroup>@endforeach</select><div class="form-text">Rooms can contain multiple AC units.</div></div>
                    <div class="col-md-6" id="standaloneLocation"><label class="form-label">Standalone location *</label><input class="form-control" name="location" value="{{ old('location') }}" placeholder="Reception or outdoor area" maxlength="180"></div>
                    <div class="col-md-6"><label class="form-label">Branch</label><select class="form-select" name="customer_branch_id" id="unitBranch"><option value="">Head office</option>@foreach($customer->branches as $branch)<option value="{{ $branch->id }}" @selected(old('customer_branch_id') == $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">Installed on</label><input class="form-control" type="date" name="installed_at" value="{{ old('installed_at') }}" data-v="not_future"></div>
                    <div class="col-md-6"><label class="form-label">Warranty until</label><input class="form-control" type="date" name="warranty_ends_at" value="{{ old('warranty_ends_at') }}" data-v="after_or_equal:installed_at"></div>
                </div></div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i data-lucide="plus" style="width:17px"></i>Add AC unit</button></div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(element => new bootstrap.Tooltip(element));

            const initialFloors = {{ Js::from(old('floors', [['name' => 'Ground floor', 'rooms' => ['Room 1']]])) }};
            const floorCount = document.getElementById('floorCount');
            const floorBuilder = document.getElementById('floorBuilder');
            const unitRoom = document.getElementById('unitRoom');
            const standaloneLocation = document.getElementById('standaloneLocation');
            const branch = document.getElementById('unitBranch');

            function escapeHtml(value) {
                const element = document.createElement('div');
                element.textContent = value ?? '';
                return element.innerHTML.replaceAll('"', '&quot;');
            }

            function currentFloors() {
                return Array.from(floorBuilder.querySelectorAll('[data-layout-floor]')).map(floor => ({
                    name: floor.querySelector('[data-floor-name]').value,
                    rooms: Array.from(floor.querySelectorAll('[data-room-name]')).map(room => room.value),
                }));
            }

            function renderFloors(floors) {
                const count = Math.max(1, Math.min(50, Number(floorCount.value) || 1));
                const values = Array.from({ length: count }, (_, index) => floors[index] || {
                    name: index === 0 ? 'Ground floor' : `Floor ${index}`,
                    rooms: ['Room 1'],
                });
                floorBuilder.innerHTML = values.map((floor, floorIndex) => {
                    const rooms = floor.rooms?.length ? floor.rooms : ['Room 1'];
                    return `<section class="nx-layout-floor" data-layout-floor="${floorIndex}">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3"><div class="d-flex align-items-center gap-2"><span class="nx-branch-icon"><i data-lucide="layers-3"></i></span><strong>Floor ${floorIndex + 1}</strong></div><button class="btn btn-sm btn-outline-primary" type="button" data-add-room="${floorIndex}"><i data-lucide="plus" style="width:15px"></i>Add room</button></div>
                        <label class="form-label">Floor name *</label><input class="form-control mb-3" data-floor-name name="floors[${floorIndex}][name]" value="${escapeHtml(floor.name)}" required maxlength="100">
                        <div class="d-grid gap-2" data-room-list>${rooms.map((room, roomIndex) => `<div class="input-group" data-room-row><span class="input-group-text"><i data-lucide="door-open" style="width:16px"></i></span><input class="form-control" data-room-name name="floors[${floorIndex}][rooms][${roomIndex}]" value="${escapeHtml(room)}" placeholder="Room name" required maxlength="150"><button class="btn btn-outline-danger" type="button" data-remove-room aria-label="Remove room"><i data-lucide="x" style="width:16px"></i></button></div>`).join('')}</div>
                    </section>`;
                }).join('');
                window.refreshIcons?.();
            }

            floorCount?.addEventListener('change', () => renderFloors(currentFloors()));
            floorBuilder?.addEventListener('click', event => {
                const addButton = event.target.closest('[data-add-room]');
                if (addButton) {
                    const floor = addButton.closest('[data-layout-floor]');
                    const floorIndex = Number(floor.dataset.layoutFloor);
                    const roomList = floor.querySelector('[data-room-list]');
                    const roomIndex = roomList.querySelectorAll('[data-room-row]').length;
                    roomList.insertAdjacentHTML('beforeend', `<div class="input-group" data-room-row><span class="input-group-text"><i data-lucide="door-open" style="width:16px"></i></span><input class="form-control" data-room-name name="floors[${floorIndex}][rooms][${roomIndex}]" placeholder="Room name" required maxlength="150"><button class="btn btn-outline-danger" type="button" data-remove-room aria-label="Remove room"><i data-lucide="x" style="width:16px"></i></button></div>`);
                    window.refreshIcons?.();
                }
                const removeButton = event.target.closest('[data-remove-room]');
                if (removeButton) {
                    const list = removeButton.closest('[data-room-list]');
                    if (list.querySelectorAll('[data-room-row]').length > 1) removeButton.closest('[data-room-row]').remove();
                }
            });

            function syncRoomSelection() {
                const option = unitRoom?.options[unitRoom.selectedIndex];
                const hasRoom = Boolean(option?.value);
                standaloneLocation?.classList.toggle('d-none', hasRoom);
                const locationInput = standaloneLocation?.querySelector('input');
                if (locationInput) locationInput.required = !hasRoom;
                if (hasRoom) branch.value = option.dataset.branch || '';
            }

            unitRoom?.addEventListener('change', syncRoomSelection);
            renderFloors(initialFloors);
            syncRoomSelection();

            @if($errors->any() && old('form_context'))
                new bootstrap.Modal(document.getElementById('{{ old('form_context') === 'floor_plan' ? 'floorPlanModal' : 'unitModal' }}')).show();
            @endif
        </script>
    @endpush
</x-app-layout>
