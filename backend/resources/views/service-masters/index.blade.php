<x-app-layout>
    <x-slot name="title">Service Masters</x-slot>
    <x-slot name="pageTitle">Service masters</x-slot>
    <x-slot name="breadcrumb">Masters / Service Management</x-slot>

    <div class="nx-page-header">
        <div><div class="nx-page-kicker">Service & catalog</div><h2>HVAC service configuration</h2><p>Master prices, inspection responses and technician quick-select options.</p></div>
        @can('checklists.update')
            <div class="nx-page-actions">
                <div class="dropdown">
                    <button class="btn btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i data-lucide="plus"></i>Add master</button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#typeModal"><i data-lucide="layers"></i>Service type</button></li>
                        <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#conditionModal"><i data-lucide="clipboard-check"></i>Inspection condition</button></li>
                        <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#optionModal"><i data-lucide="list-plus"></i>Quick option</button></li>
                    </ul>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catalogModal"><i data-lucide="plus"></i>Catalog service</button>
            </div>
        @endcan
    </div>
    @if($errors->any())<div class="alert alert-danger"><strong>Could not save master.</strong> {{ $errors->first() }}</div>@endif

    <section class="nx-card nx-datatable-card mb-4">
        <div class="nx-card-header">
            <div><h3 class="nx-card-title"><i data-lucide="list-checks"></i>Service catalog</h3><p class="nx-card-subtitle">Backend-controlled rates used in estimates and service execution.</p></div>
            <span class="nx-count">{{ $catalogItems->where('active', true)->count() }} active</span>
        </div>
        <table class="table nx-table align-middle" data-datatable data-dt-title="Service catalog" data-dt-page-length="10">
            <thead><tr><th data-priority="1">Code / Service</th><th>Category</th><th>Equipment</th><th class="text-end">Price</th><th class="text-end">Tax</th><th data-priority="2">Status</th></tr></thead>
            <tbody>
                @foreach($catalogItems as $item)
                    <tr>
                        <td><span class="nx-row-title">{{ $item->name }}</span><span class="nx-row-sub notranslate">{{ $item->code }}</span></td>
                        <td>{{ $item->category ?: 'General' }}</td>
                        <td>{{ $item->equipment_type ?: 'All' }}</td>
                        <td class="text-end">₹{{ number_format((float) $item->standard_price, 2) }}</td>
                        <td class="text-end">{{ number_format((float) $item->tax_percent, 2) }}%</td>
                        <td><form method="POST" action="{{ route('service-masters.toggle', ['group' => 'catalog', 'id' => $item->id]) }}">@csrf @method('PATCH')<button class="nx-pill border-0 tone-{{ $item->active ? 'success' : 'secondary' }}" title="{{ $item->active ? 'Click to deactivate' : 'Click to activate' }}">{{ $item->active ? 'Active' : 'Inactive' }}</button></form></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <div class="row g-4 mb-4">
        <div class="col-xl-7">
            <section class="nx-card nx-datatable-card">
                <div class="nx-card-header">
                    <div><h3 class="nx-card-title"><i data-lucide="layers"></i>Service types</h3><p class="nx-card-subtitle">Job classification, default value and expected duration.</p></div>
                    <span class="nx-count">{{ $serviceTypes->count() }}</span>
                </div>
                <table class="table nx-table align-middle" data-datatable data-dt-title="Service types" data-dt-paging="false" data-dt-search="false">
                    <thead><tr><th data-priority="1">Service type</th><th class="text-end">Default</th><th>Duration</th><th data-priority="2">Status</th></tr></thead>
                    <tbody>
                        @foreach($serviceTypes as $type)
                            <tr>
                                <td><span class="nx-row-title">{{ $type->name }}</span><span class="nx-row-sub notranslate">{{ $type->code }}</span></td>
                                <td class="text-end">₹{{ number_format((float) $type->default_price, 2) }}</td>
                                <td>{{ $type->estimated_minutes ? $type->estimated_minutes.' min' : '—' }}</td>
                                <td><form method="POST" action="{{ route('service-masters.toggle', ['group' => 'service-types', 'id' => $type->id]) }}">@csrf @method('PATCH')<button class="nx-pill border-0 tone-{{ $type->active ? 'success' : 'secondary' }}" title="{{ $type->active ? 'Click to deactivate' : 'Click to activate' }}">{{ $type->active ? 'Active' : 'Inactive' }}</button></form></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        </div>
        <div class="col-xl-5">
            <section class="nx-card h-100">
                <div class="nx-card-header">
                    <div><h3 class="nx-card-title"><i data-lucide="clipboard-check"></i>Inspection conditions</h3><p class="nx-card-subtitle">Large tap choices shown to technicians.</p></div>
                    <span class="nx-count">{{ $conditions->count() }}</span>
                </div>
                <div class="nx-card-body d-flex flex-column gap-2">
                    @foreach($conditions as $condition)
                        @php($class = match($condition->color) { 'success' => 'badge-soft-success', 'warning' => 'badge-soft-warning', 'danger' => 'badge-soft-danger', default => 'bg-light text-secondary' })
                        <div class="d-flex align-items-center justify-content-between gap-2 border rounded-3 p-2"><div><span class="badge {{ $class }}">{{ $condition->name }}</span><span class="small text-secondary ms-2">{{ $condition->is_issue ? 'Issue' : 'Normal' }}</span></div><form method="POST" action="{{ route('service-masters.toggle', ['group' => 'conditions', 'id' => $condition->id]) }}">@csrf @method('PATCH')<button class="nx-pill border-0 tone-{{ $condition->active ? 'success' : 'secondary' }}" title="{{ $condition->active ? 'Click to deactivate' : 'Click to activate' }}">{{ $condition->active ? 'Active' : 'Inactive' }}</button></form></div>
                    @endforeach
                </div>
            </section>
        </div>
    </div>

    <section class="nx-card">
        <div class="nx-card-header">
            <div><h3 class="nx-card-title"><i data-lucide="smartphone"></i>Technician quick options</h3><p class="nx-card-subtitle">Payment methods, equipment types, reasons and units used in mobile forms. Click an option to switch it on or off.</p></div>
        </div>
        <div class="nx-card-body" style="columns:3 280px;column-gap:32px;padding-bottom:2px">
            @foreach($options as $type => $group)
                <div style="break-inside:avoid;margin-bottom:22px">
                    <div class="nx-subhead d-flex align-items-center justify-content-between gap-2" style="margin-top:0">{{ str($type)->replace('_', ' ') }}<span class="nx-count">{{ $group->count() }}</span></div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($group as $option)
                            <form method="POST" action="{{ route('service-masters.toggle', ['group' => 'options', 'id' => $option->id]) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-light border py-0 fw-medium {{ $option->active ? '' : 'bg-light text-secondary text-decoration-line-through' }}" style="min-height:26px" title="{{ $option->active ? 'Click to deactivate' : 'Click to activate' }}">{{ $option->label }}</button></form>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="modal fade" id="typeModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('service-masters.service-types.store') }}">@csrf<div class="modal-header"><h5 class="modal-title">Add service type</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-sm-4"><label class="form-label">Code <span class="text-danger">*</span></label><input class="form-control" name="code" placeholder="ST-INSTALL" required data-v="alpha_dash" maxlength="30"></div><div class="col-sm-8"><label class="form-label">Name <span class="text-danger">*</span></label><input class="form-control" name="name" required></div><div class="col-sm-6"><label class="form-label">Default price <span class="text-danger">*</span></label><input type="number" step="0.01" min="0" class="form-control" name="default_price" value="0" required></div><div class="col-sm-6"><label class="form-label">Tax % <span class="text-danger">*</span></label><input type="number" step="0.01" min="0" max="100" class="form-control" name="tax_percent" value="18" required></div><div class="col-sm-6"><label class="form-label">Estimated minutes</label><input type="number" min="1" class="form-control" name="estimated_minutes" max="1440"></div><div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2" maxlength="2000"></textarea></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save service type</button></div></form></div></div>

    <div class="modal fade" id="catalogModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('service-masters.catalog.store') }}">@csrf<div class="modal-header"><h5 class="modal-title">Add catalog service</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-md-4"><label class="form-label">Code <span class="text-danger">*</span></label><input class="form-control" name="code" placeholder="SVC-INSTALL" required data-v="alpha_dash" maxlength="30"></div><div class="col-md-8"><label class="form-label">Service name <span class="text-danger">*</span></label><input class="form-control" name="name" required></div><div class="col-md-4"><label class="form-label">Category</label><input class="form-control" name="category" placeholder="Installation" maxlength="100"></div><div class="col-md-4"><label class="form-label">Equipment type</label><input class="form-control" name="equipment_type" placeholder="Split AC" maxlength="100"></div><div class="col-md-4"><label class="form-label">Estimated minutes</label><input type="number" min="1" class="form-control" name="estimated_minutes" max="1440"></div><div class="col-md-6"><label class="form-label">Standard price <span class="text-danger">*</span></label><input type="number" step="0.01" min="0" class="form-control" name="standard_price" required></div><div class="col-md-6"><label class="form-label">Tax % <span class="text-danger">*</span></label><input type="number" step="0.01" min="0" max="100" class="form-control" name="tax_percent" value="18" required></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save catalog service</button></div></form></div></div>

    <div class="modal fade" id="conditionModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('service-masters.conditions.store') }}">@csrf<div class="modal-header"><h5 class="modal-title">Add inspection condition</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-sm-5"><label class="form-label">Code <span class="text-danger">*</span></label><input class="form-control" name="code" placeholder="LOW_PRESSURE" required data-v="alpha_dash" maxlength="40"></div><div class="col-sm-7"><label class="form-label">Label <span class="text-danger">*</span></label><input class="form-control" name="name" required></div><div class="col-sm-7"><label class="form-label">Colour</label><select class="form-select" name="color"><option value="success">Green</option><option value="warning">Yellow</option><option value="danger">Red</option><option value="secondary">Grey</option></select></div><div class="col-sm-5 d-flex align-items-end"><label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_issue" value="1"><span class="form-check-label">Marks an issue</span></label></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save condition</button></div></form></div></div>

    <div class="modal fade" id="optionModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="POST" action="{{ route('service-masters.options.store') }}">@csrf<div class="modal-header"><h5 class="modal-title">Add quick option</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3"><div class="col-12"><label class="form-label">Option group <span class="text-danger">*</span></label><select class="form-select" name="type" required><option value="equipment_type">Equipment Type</option><option value="ac_capacity">AC Capacity</option><option value="reschedule_reason">Reschedule Reason</option><option value="cancellation_reason">Cancellation Reason</option><option value="payment_method">Payment Method</option><option value="unit">Unit</option></select></div><div class="col-sm-5"><label class="form-label">Code <span class="text-danger">*</span></label><input class="form-control" name="code" required maxlength="50"></div><div class="col-sm-7"><label class="form-label">Label <span class="text-danger">*</span></label><input class="form-control" name="label" required></div></div></div><div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save option</button></div></form></div></div>
</x-app-layout>
