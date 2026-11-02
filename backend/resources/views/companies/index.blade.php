<x-app-layout>
    <x-slot name="title">Company Master</x-slot><x-slot name="pageTitle">Company master</x-slot><x-slot name="breadcrumb">Administration / Company Master</x-slot>
    <div class="nx-page-header">
        <div><div class="nx-page-kicker">Administration</div><h2>Legal entities, branches & sites</h2><p>Own-company statutory, invoicing, banking and location master.</p></div>
        @can('companies.create')<div class="nx-page-actions"><a class="btn btn-primary" href="{{ route('companies.create') }}"><i data-lucide="building-2"></i>Add entity</a></div>@endcan
    </div>
    <section class="nx-card nx-datatable-card">
        <table class="table nx-table" data-datatable data-dt-title="Company master" data-dt-empty="No company master exists.">
            <thead><tr><th>Entity</th><th>Organisation ID</th><th>Code</th><th>Type</th><th>Industry</th><th>Branches</th><th>Sites</th><th>Registered address</th><th>Status</th><th class="no-export no-sort text-end" data-orderable="false"></th></tr></thead>
            <tbody>
                @foreach($companies as $company)
                    <tr>
                        <td style="min-width:220px"><div class="d-flex align-items-center gap-2"><span class="d-grid place-items-center border rounded-2 bg-white flex-shrink-0" style="width:36px;height:36px;overflow:hidden">@if($company->logo_path)<img src="{{ str_starts_with($company->logo_path, 'images/') ? asset($company->logo_path) : asset('storage/'.$company->logo_path) }}" class="mw-100 mh-100 object-fit-contain" alt="">@else<i data-lucide="building-2" style="width:16px;margin:auto"></i>@endif</span><span><span class="nx-row-title notranslate">{{ $company->legal_name }}</span><span class="nx-row-sub notranslate">{{ $company->short_name }}@if($company->parent) · under {{ $company->parent->short_name }}@endif</span></span></div></td>
                        <td class="notranslate">{{ $company->organization_id }}</td>
                        <td class="nx-code notranslate">{{ $company->code }}</td>
                        <td>{{ str($company->company_type)->headline() }}</td>
                        <td>{{ $company->industry }}</td>
                        <td class="notranslate">{{ $company->branches_count }}</td>
                        <td class="notranslate">{{ $company->sites_count }}</td>
                        <td style="min-width:220px">{{ collect(['line1', 'line2', 'landmark', 'city', 'district', 'state', 'pin', 'country'])->map(fn ($key) => data_get($company->address, $key))->filter()->join(', ') ?: 'Not configured' }}</td>
                        <td><x-pill :tone="$company->status === 'active' ? 'success' : 'warning'" :label="ucfirst($company->status)" /></td>
                        <td class="text-end">@can('companies.update')<a class="btn btn-sm btn-light border" href="{{ route('companies.edit', $company) }}">Edit</a>@endcan</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</x-app-layout>
