@use('App\Services\ComplaintOptions')
<x-app-layout>
    <x-slot name="title">Complaint masters</x-slot>
    <x-slot name="pageTitle">Complaint masters</x-slot>
    <x-slot name="breadcrumb">Complaints / Masters</x-slot>
    @php $tab = array_key_exists(request('tab'), $groups) ? request('tab') : array_key_first($groups); @endphp

    <div class="nx-page-header">
        <div>
            <div class="nx-page-kicker">Configuration</div>
            <h2>Complaint masters</h2>
            <p>Lists used while registering complaints, writing visit reports and managing pending calls. Inactive entries stay on old records but are no longer offered.</p>
        </div>
        @if($canManage)<div class="nx-page-actions"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#masterModal"><i data-lucide="plus"></i>Add entry</button></div>@endif
    </div>

    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <section class="nx-card nx-datatable-card">
        <div class="nx-card-tabs">
            <ul class="nav nav-tabs nx-tabs-scroll border-0" role="tablist">
                @foreach($groups as $key => $group)
                    <li class="nav-item"><button class="nav-link {{ $tab === $key ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#master-{{ $key }}" type="button" role="tab">{{ $group['label'] }}<span class="nx-count notranslate">{{ ($items[$key] ?? collect())->count() }}</span></button></li>
                @endforeach
            </ul>
        </div>
        <div class="tab-content">
            @foreach($groups as $key => $group)
                <div class="tab-pane fade {{ $tab === $key ? 'show active' : '' }}" id="master-{{ $key }}" role="tabpanel">
                    <div class="px-3 pt-3 small text-secondary">{{ $group['hint'] }}.</div>
                    <table class="table nx-table" data-datatable data-dt-title="{{ $group['label'] }}" data-dt-page-length="50" data-dt-empty="No entries yet.">
                        <thead><tr><th>Label</th>@if(in_array($key, ['pending_reason', 'pending_sub_reason'], true))<th>{{ $key === 'pending_reason' ? 'Pending group' : 'Under reason' }}</th>@endif<th>Code</th><th>Status</th><th class="no-export no-sort" data-orderable="false"></th></tr></thead>
                        <tbody>
                            @foreach($items[$key] ?? [] as $item)
                                <tr>
                                    <td class="fw-semibold">{{ $item->label }}</td>
                                    @if($key === 'pending_reason')<td>{{ $pendingGroups[data_get($item->metadata, 'group')] ?? '—' }}</td>@endif
                                    @if($key === 'pending_sub_reason')<td>{{ $pendingReasons[data_get($item->metadata, 'reason')] ?? '—' }}</td>@endif
                                    <td class="nx-code notranslate">{{ $item->code }}</td>
                                    <td><x-pill :tone="$item->active ? 'success' : 'secondary'" :label="$item->active ? 'Active' : 'Inactive'" /></td>
                                    <td class="text-end">@if($canManage)<form method="POST" action="{{ route('complaint-masters.toggle', $item) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-light border">{{ $item->active ? 'Deactivate' : 'Activate' }}</button></form>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </section>

    @if($canManage)
    <div class="modal fade" id="masterModal" tabindex="-1" aria-labelledby="masterTitle">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('complaint-masters.store') }}">
                @csrf <input type="hidden" name="_modal" value="masterModal">
                <div class="modal-header"><h5 class="modal-title" id="masterTitle">Add master entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label" for="mType">List *</label><select class="form-select" id="mType" name="type" required data-master-type>@foreach($groups as $key => $group)<option value="{{ $key }}" @selected(old('type', $tab) === $key)>{{ $group['label'] }}</option>@endforeach</select></div>
                        <div class="col-12"><label class="form-label" for="mLabel">Label *</label><input class="form-control" id="mLabel" name="label" value="{{ old('label') }}" required maxlength="120"></div>
                        <div class="col-12" data-master-extra="pending_reason"><label class="form-label" for="mGroup">Pending group *</label><select class="form-select" id="mGroup" name="group"><option value="">Select group</option>@foreach($pendingGroups as $key => $label)<option value="{{ $key }}" @selected(old('group') === $key)>{{ $label }}</option>@endforeach</select></div>
                        <div class="col-12" data-master-extra="pending_sub_reason"><label class="form-label" for="mReason">Pending reason *</label><select class="form-select" id="mReason" name="reason"><option value="">Select reason</option>@foreach($pendingReasons as $code => $label)<option value="{{ $code }}" @selected(old('reason') === $code)>{{ $label }}</option>@endforeach</select></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i data-lucide="save"></i>Save</button></div>
            </form>
        </div>
    </div>
    @endif
    @push('scripts')@vite('resources/js/complaints.js')@endpush
</x-app-layout>
