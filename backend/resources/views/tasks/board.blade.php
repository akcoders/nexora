<x-app-layout>
    @php $isComplaint = $workType === 'ticket'; $moduleRoute = $isComplaint ? 'complaints' : 'workflows'; @endphp
    <x-slot name="title">{{ $isComplaint ? 'Complaint board' : 'Workflow board' }}</x-slot>
    <x-slot name="pageTitle">{{ $isComplaint ? 'Customer complaint board' : 'Internal workflow board' }}</x-slot>
    <x-slot name="breadcrumb">Operations / {{ $isComplaint ? 'Customer Complaints' : 'Internal Workflow' }} / Kanban</x-slot>

    <section class="nx-kanban-app" data-kanban data-type="{{ $workType }}" data-feed-url="{{ route($moduleRoute.'.board') }}">
        <div class="nx-card nx-kanban-toolbar mb-3">
            <div class="nx-kanban-toolbar-row">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <div class="nx-kanban-segment" role="group" aria-label="Group cards by">
                        <button type="button" data-mode-btn="owner"><i data-lucide="users-round"></i>Responsible Person</button>
                        <button type="button" data-mode-btn="status"><i data-lucide="columns-3"></i>Status</button>
                        <button type="button" data-mode-btn="priority"><i data-lucide="flag"></i>Priority</button>
                    </div>

                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 ms-lg-auto">
                    <div class="input-group nx-kanban-search"><span class="input-group-text bg-white border-end-0"><i data-lucide="search" style="width:16px"></i></span><input class="form-control border-start-0 ps-0" type="search" data-search-input placeholder="Search number, title, customer, owner" value="{{ request('search') }}" aria-label="Search cards"></div>
                    <a class="btn btn-light border" href="{{ route($moduleRoute.'.index', ['scope' => 'open']) }}" title="List view"><i data-lucide="list" style="width:17px"></i><span class="d-none d-xl-inline ms-1">List</span></a>
                    @if(auth()->user()->can('tasks.create') || auth()->user()->can('tasks.assign'))<button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#taskModal"><i data-lucide="plus" style="width:17px"></i> {{ $isComplaint ? 'New complaint' : 'New workflow' }}</button>@endif
                </div>
            </div>
            <div class="nx-kanban-toolbar-row border-top pt-3 mt-3">
                <div class="d-flex flex-wrap gap-2" role="group" aria-label="Quick filters">
                    <button type="button" class="nx-kanban-chip" data-quick="mine"><i data-lucide="user-round-check"></i>Assigned to me</button>
                    <button type="button" class="nx-kanban-chip" data-quick="watching"><i data-lucide="eye"></i>Watching</button>
                    <button type="button" class="nx-kanban-chip" data-quick="created"><i data-lucide="pen-line"></i>Created by me</button>
                    <button type="button" class="nx-kanban-chip danger" data-quick="overdue"><i data-lucide="alarm-clock"></i>Overdue</button>
                </div>
                <div class="nx-kanban-stats ms-lg-auto" data-stats aria-live="polite"></div>
                <button type="button" class="nx-icon-btn" data-refresh title="Refresh board" aria-label="Refresh board"><i data-lucide="refresh-cw" style="width:16px"></i></button>
            </div>
        </div>

        <p class="small text-secondary mb-2 d-flex align-items-center gap-2" data-hint><i data-lucide="mouse-pointer-2" style="width:15px"></i><span data-hint-text>Drag a card to another owner to hand it over, or onto Completed to finish it. A remark is always required.</span></p>

        <div class="nx-kanban" data-board aria-busy="true">
            @foreach(range(1, 4) as $placeholder)
                <div class="nx-kanban-col is-skeleton"><div class="nx-kanban-col-head"><span class="nx-skel" style="width:60%"></span></div><div class="nx-kanban-list">@foreach(range(1, 3) as $row)<div class="nx-kanban-card"><span class="nx-skel" style="width:40%"></span><span class="nx-skel mt-2"></span><span class="nx-skel mt-2" style="width:75%"></span></div>@endforeach</div></div>
            @endforeach
        </div>
    </section>

    @include('tasks.partials.create-modal', ['returnTo' => 'board'])

    @push('scripts')
        @vite('resources/js/kanban-board.js')
    @endpush
</x-app-layout>
