<x-app-layout>
    <x-slot name="title">Customers</x-slot>
    <x-slot name="pageTitle">Customer master</x-slot>
    <x-slot name="breadcrumb">Masters / Customers</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div><h2 class="h5 mb-1">All customers</h2><div class="text-secondary small">Manage parent customers, branches, contacts and commercial terms.</div></div>
        <a href="{{ route('customers.create') }}" class="btn btn-primary d-inline-flex align-items-center gap-2"><i data-lucide="plus" style="width:18px"></i>New customer</a>
    </div>

    <section class="nx-card overflow-hidden">
        <div class="p-3 border-bottom">
            <form class="row g-2 align-items-center" method="GET">
                <div class="col-md-5"><div class="input-group"><span class="input-group-text bg-white border-end-0"><i data-lucide="search" style="width:17px"></i></span><input class="form-control border-start-0 ps-0" name="search" value="{{ request('search') }}" placeholder="Search name, code or city"></div></div>
                <div class="col-auto"><button class="btn btn-outline-primary px-4">Filter</button></div>
                @if(request('search'))<div class="col-auto"><a class="btn btn-light border" href="{{ route('customers.index') }}">Clear</a></div>@endif
            </form>
        </div>
        <div class="table-responsive">
            <table class="table nx-table mb-0">
                <thead><tr><th class="ps-4">Customer</th><th>Type</th><th>Location</th><th>Contacts</th><th>Branches</th><th>Priority</th><th>Status</th><th class="text-end pe-4">Action</th></tr></thead>
                <tbody>
                @forelse($customers as $customer)
                    <tr>
                        <td class="ps-4"><div class="d-flex align-items-center gap-3"><span class="nx-avatar">{{ strtoupper(substr($customer->name, 0, 1)) }}</span><div><div class="fw-semibold">{{ $customer->name }}</div><div class="small text-secondary">{{ $customer->code }}</div></div></div></td>
                        <td><span class="small">{{ collect($customer->types)->map(fn($type) => str($type)->headline())->join(', ') }}</span></td>
                        <td><div>{{ $customer->city ?: '—' }}</div><div class="small text-secondary">{{ $customer->state }}</div></td>
                        <td>{{ $customer->contacts_count }}</td><td>{{ $customer->branches_count }}</td>
                        <td><span class="badge rounded-pill {{ $customer->priority === 'very_high' ? 'badge-soft-danger' : ($customer->priority === 'high' ? 'badge-soft-warning' : 'badge-soft-primary') }}">{{ str($customer->priority)->headline() }}</span></td>
                        <td><span class="badge rounded-pill {{ $customer->status === 'active' ? 'badge-soft-success' : ($customer->status === 'blacklisted' ? 'badge-soft-danger' : 'bg-light text-secondary') }}">{{ ucfirst($customer->status) }}</span></td>
                        <td class="text-end pe-4"><button class="nx-icon-btn ms-auto"><i data-lucide="more-horizontal" style="width:18px"></i></button></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center py-5"><div class="mx-auto mb-3 nx-metric-icon"><i data-lucide="building-2"></i></div><div class="fw-semibold">No customers found</div><div class="small text-secondary mb-3">Create your first customer to get started.</div><a href="{{ route('customers.create') }}" class="btn btn-primary btn-sm">New customer</a></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($customers->hasPages())<div class="p-3 border-top">{{ $customers->links() }}</div>@endif
    </section>
</x-app-layout>
