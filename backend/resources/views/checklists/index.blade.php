<x-app-layout>
    <x-slot name="title">Service Checklists</x-slot>
    <x-slot name="pageTitle">Service checklists</x-slot>
    <x-slot name="breadcrumb">Masters / Checklists</x-slot>

    <div class="nx-page-header">
        <div><div class="nx-page-kicker">Service & catalog</div><h2>Product service checklists</h2><p>Standardise preventive maintenance and safety checks used by technicians.</p></div>
        @can('checklists.create')<div class="nx-page-actions"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#checklistModal"><i data-lucide="plus"></i>New checklist</button></div>@endcan
    </div>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <section class="nx-card nx-datatable-card">
        <table class="table nx-table" data-datatable data-dt-title="Service checklists" data-dt-empty="No service checklists created.">
            <thead><tr><th data-priority="1">Checklist</th><th>Service / category</th><th>Equipment</th><th>Phase</th><th>Frequency</th><th>Duration</th><th>Checks</th><th>Sample items</th><th>Status</th><th class="no-export no-sort text-end" data-orderable="false"></th></tr></thead>
            <tbody>
                @foreach($checklists as $checklist)
                    <tr>
                        <td class="nx-row-title" style="min-width:180px">{{ $checklist->name }}</td>
                        <td>{{ $checklist->serviceType?->name ?? $checklist->category?->name ?? 'All services' }}</td>
                        <td>{{ $checklist->equipment_type ?: '—' }}</td>
                        <td><span class="badge badge-soft-primary text-uppercase">{{ $checklist->phase }}</span></td>
                        <td>{{ $checklist->frequency ?: 'On demand' }}</td>
                        <td class="notranslate" data-order="{{ (int) $checklist->estimated_minutes }}">{{ $checklist->estimated_minutes ?: 0 }} min</td>
                        <td class="notranslate">{{ $checklist->items->count() }}</td>
                        <td style="min-width:220px"><span class="nx-row-sub text-body">{{ $checklist->items->take(3)->pluck('label')->join(' · ') }}{{ $checklist->items->count() > 3 ? ' …' : '' }}</span></td>
                        <td><x-pill :tone="$checklist->active ? 'success' : 'secondary'" :label="$checklist->active ? 'Active' : 'Inactive'" /></td>
                        <td class="text-end text-nowrap">
                            @can('checklists.update')<button class="btn btn-sm btn-light border btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#editChecklist{{ $checklist->id }}" aria-label="Edit {{ $checklist->name }}" title="Edit"><i data-lucide="pencil"></i></button>@endcan
                            @can('checklists.delete')<form class="d-inline" method="POST" action="{{ route('checklists.destroy', $checklist) }}" data-confirm="Remove the checklist “{{ $checklist->name }}”?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border btn-icon text-danger" aria-label="Delete {{ $checklist->name }}" title="Delete"><i data-lucide="trash-2"></i></button></form>@endcan
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    <div class="modal fade" id="checklistModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="POST" action="{{ route('checklists.store') }}" class="modal-content">
                @csrf
                <div class="modal-header"><div><h5 class="modal-title">New service checklist</h5><small class="text-secondary">Enter one item per line. Add <strong>[photo]</strong> when evidence is mandatory.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                @include('checklists.partials.form', ['checklist' => null])
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create checklist</button></div>
            </form>
        </div>
    </div>

    @foreach($checklists as $checklist)
        <div class="modal fade" id="editChecklist{{ $checklist->id }}" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <form method="POST" action="{{ route('checklists.update', $checklist) }}" class="modal-content">
                    @csrf
                    @method('PUT')
                    <div class="modal-header"><div><h5 class="modal-title">Edit service checklist</h5><small class="text-secondary">Changes apply to future inspections; completed inspection records are preserved.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    @include('checklists.partials.form', ['checklist' => $checklist])
                    <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i data-lucide="save" style="width:17px"></i>Save changes</button></div>
                </form>
            </div>
        </div>
    @endforeach
</x-app-layout>
