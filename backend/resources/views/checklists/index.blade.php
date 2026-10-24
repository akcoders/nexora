<x-app-layout>
    <x-slot name="title">Service Checklists</x-slot>
    <x-slot name="pageTitle">Service checklists</x-slot>
    <x-slot name="breadcrumb">Masters / Checklists</x-slot>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4"><div><h2 class="h5 mb-1">Product service checklists</h2><div class="small text-secondary">Standardise preventive maintenance and safety checks.</div></div><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#checklistModal"><i data-lucide="plus" style="width:17px"></i>New checklist</button></div>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="row g-3">
        @forelse($checklists as $checklist)
            <div class="col-md-6 col-xl-4">
                <article class="nx-card p-4 h-100">
                    <div class="d-flex justify-content-between gap-3">
                        <span class="nx-metric-icon"><i data-lucide="clipboard-check"></i></span>
                        <div class="d-flex gap-1">
                            @can('checklists.update')<button class="btn btn-sm btn-link" type="button" data-bs-toggle="modal" data-bs-target="#editChecklist{{ $checklist->id }}" aria-label="Edit {{ $checklist->name }}"><i data-lucide="pencil" style="width:16px"></i></button>@endcan
                            @can('checklists.delete')<form method="POST" action="{{ route('checklists.destroy', $checklist) }}" onsubmit="return confirm('Remove checklist?')">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger" aria-label="Delete {{ $checklist->name }}"><i data-lucide="trash-2" style="width:16px"></i></button></form>@endcan
                        </div>
                    </div>
                    <h3 class="h6 fw-bold mt-3 mb-1">{{ $checklist->name }}</h3>
                    <div class="small text-secondary mb-3">{{ $checklist->serviceType?->name ?? $checklist->category?->name ?? 'All services' }}{{ $checklist->equipment_type ? ' · '.$checklist->equipment_type : '' }}</div>
                    <div class="d-flex flex-wrap gap-2 mb-3"><span class="badge badge-soft-primary text-uppercase">{{ $checklist->phase }}</span><span class="badge badge-soft-primary">{{ $checklist->frequency ?: 'On demand' }}</span><span class="badge bg-light text-secondary">{{ $checklist->estimated_minutes ?: 0 }} min</span></div>
                    <div class="small fw-semibold mb-2">{{ $checklist->items->count() }} checks</div>
                    <ul class="small text-secondary ps-3 mb-0">@foreach($checklist->items->take(4) as $item)<li class="mb-1">{{ $item->label }} @if($item->photo_required)<span class="text-primary">· photo</span>@endif</li>@endforeach @if($checklist->items->count() > 4)<li>+ {{ $checklist->items->count()-4 }} more</li>@endif</ul>
                </article>
            </div>
        @empty
            <div class="col-12"><div class="nx-card text-center py-5 text-secondary">No service checklists created.</div></div>
        @endforelse
    </div>
    <div class="mt-3">{{ $checklists->links() }}</div>

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
