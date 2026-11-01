@props(['users', 'selected' => []])
@php
    $selectedIds = collect($selected ?? [])->map(fn ($value) => (string) $value)->all();
    $pickerId = 'observers-'.\Illuminate\Support\Str::uuid();
@endphp
<div class="nx-observer-picker" data-observer-picker>
    <span class="form-label d-block" id="{{ $pickerId }}-label">Observers</span>
    <button class="nx-observer-toggle" type="button" aria-expanded="false" aria-controls="{{ $pickerId }}-panel" aria-labelledby="{{ $pickerId }}-label {{ $pickerId }}-summary" data-observer-toggle>
        <span class="nx-avatar-stack" data-observer-selection></span>
        <span class="flex-grow-1 text-start" id="{{ $pickerId }}-summary" data-observer-summary>Choose observers</span>
        <i data-lucide="chevron-down" aria-hidden="true"></i>
    </button>
    <div class="nx-observer-panel" id="{{ $pickerId }}-panel" data-observer-panel hidden>
        <div class="nx-observer-search"><i data-lucide="search" aria-hidden="true"></i><input type="search" class="form-control" placeholder="Search people by name or role" aria-label="Search observers" data-observer-search></div>
        <div class="nx-observer-options" role="group" aria-label="Select observers">
            @foreach($users as $person)
                <label class="nx-observer-option" data-observer-option data-search="{{ mb_strtolower($person->name.' '.$person->designation) }}">

                    <x-person-avatar :user="$person" :size="34" :focusable="false" />
                    <span class="min-w-0 flex-grow-1"><strong>{{ $person->name }}</strong><small>{{ $person->designation ?: 'Team member' }}</small></span>
                    <span class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" name="observers[]" value="{{ $person->id }}" @checked(in_array((string) $person->id, $selectedIds, true))></span>
                </label>
            @endforeach
        </div>
        <p class="small text-secondary mb-0 py-3 text-center" data-observer-empty hidden>No matching people.</p>
        <div class="nx-observer-footer"><button class="btn btn-sm btn-link text-secondary" type="button" data-observer-clear>Clear selection</button><button class="btn btn-sm btn-primary" type="button" data-observer-done>Done</button></div>
    </div>
    <div class="form-text">Selected people can follow progress and completion.</div>
</div>
