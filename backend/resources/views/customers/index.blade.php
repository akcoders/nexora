<x-app-layout>
    <x-slot name="title">Customers</x-slot>
    <x-slot name="pageTitle">Customers</x-slot>
    <x-slot name="breadcrumb">Customers / All customers</x-slot>

    <div class="nx-page-header">
        <div><div class="nx-page-kicker">Customers</div><h2>All customers</h2><p>Parent customers, branches, contacts, equipment and commercial terms.</p></div>
        <div class="nx-page-actions">
            <a href="{{ route('customer-groups.index') }}" class="btn btn-light border"><i data-lucide="tags"></i>Customer groups</a>
            @can('customers.create')<a href="{{ route('customers.create') }}" class="btn btn-primary"><i data-lucide="plus"></i>New customer</a>@endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-kpi label="Customers" :value="$customers->count()" icon="building-2" tone="primary" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Active" :value="$customers->where('status', 'active')->count()" icon="badge-check" tone="success" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Branches" :value="$customers->sum('branches_count')" icon="git-fork" tone="info" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Equipment units" :value="$customers->sum('equipments_count')" icon="air-vent" tone="purple" /></div>
    </div>

    <section class="nx-card nx-datatable-card">
        <table class="table nx-table" data-datatable data-dt-title="Customers" data-dt-empty="No customers found. Create your first customer to get started.">
            <thead><tr><th data-priority="1">Customer</th><th data-priority="4">Code</th><th data-priority="5">Group</th><th>Type</th><th data-priority="6">Location</th><th>Contacts</th><th>Branches</th><th>Units</th><th data-priority="3">Priority</th><th data-priority="2">Status</th><th class="all no-export no-sort text-end" data-orderable="false"></th></tr></thead>
            <tbody>
            @foreach($customers as $customer)
                <tr>
                    <td style="min-width:200px"><a href="{{ route('customers.show', $customer) }}" class="d-flex align-items-center gap-2"><span class="nx-avatar notranslate" style="width:32px;height:32px;font-size:12px">{{ strtoupper(substr($customer->name, 0, 1)) }}</span><span class="nx-row-title notranslate">{{ $customer->name }}</span></a></td>
                    <td class="nx-code notranslate">{{ $customer->code }}</td>
                    <td>@if($customer->group)<span class="nx-group-badge nx-group-badge-sm"><i data-lucide="tag"></i>{{ $customer->group->name }}</span>@else — @endif</td>
                    <td>{{ collect($customer->types)->map(fn($type) => str($type)->headline())->join(', ') }}</td>
                    <td>{{ $customer->city ?: '—' }} <span class="nx-row-sub">{{ $customer->state }}</span></td>
                    <td class="notranslate">{{ $customer->contacts_count }}</td>
                    <td class="notranslate">{{ $customer->branches_count }}</td>
                    <td class="notranslate">{{ $customer->equipments_count }}</td>
                    <td><x-pill :tone="$customer->priority === 'very_high' ? 'danger' : ($customer->priority === 'high' ? 'warning' : 'primary')" :label="str($customer->priority)->headline()" /></td>
                    <td><x-pill :tone="$customer->status === 'active' ? 'success' : ($customer->status === 'blacklisted' ? 'danger' : 'secondary')" :label="ucfirst($customer->status)" /></td>
                    <td class="text-end text-nowrap"><div class="d-inline-flex gap-1">@can('customers.update')<a class="btn btn-sm btn-light border btn-icon" href="{{ route('customers.edit', $customer) }}" aria-label="Edit {{ $customer->name }}" title="Edit"><i data-lucide="pencil"></i></a>@endcan<a class="btn btn-sm btn-light border btn-icon" href="{{ route('customers.show', $customer) }}" aria-label="Open {{ $customer->name }}" title="Open"><i data-lucide="arrow-right"></i></a></div></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</x-app-layout>
