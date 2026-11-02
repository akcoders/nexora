@use('App\Services\ComplaintOptions')
@props(['prefix' => '', 'values' => [], 'pendingTree' => [], 'staff' => collect(), 'required' => true])
@php
    $name = fn (string $field) => $prefix === '' ? $field : rtrim($prefix, '.').'['.$field.']';
    $key = fn (string $field) => $prefix.$field;
    $value = fn (string $field, $default = null) => old($key($field), data_get($values, $field, $default));
    $star = $required ? ' *' : '';
    $group = $value('pending_group');
    $dateValue = fn (string $field) => ($date = $value($field)) instanceof \Carbon\CarbonInterface ? $date->toDateString() : $date;
@endphp
<div class="row g-3" data-pending-fields data-tree='@json($pendingTree)'>
    <div class="col-md-4"><label class="form-label">Pending group{{ $star }}</label><select class="form-select" name="{{ $name('pending_group') }}" data-pending-group @if($required) required @endif><option value="">Select group</option>@foreach(ComplaintOptions::options('pending_groups') as $option => $label)<option value="{{ $option }}" @selected($group === $option)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Reason for pending{{ $star }}</label><select class="form-select" name="{{ $name('pending_reason') }}" data-pending-reason data-current="{{ $value('pending_reason') }}" @if($required) required @endif><option value="">Select reason</option>@if($value('pending_reason'))<option value="{{ $value('pending_reason') }}" selected>{{ $value('pending_reason') }}</option>@endif</select></div>
    <div class="col-md-4"><label class="form-label">Sub category</label><select class="form-select" name="{{ $name('pending_sub_reason') }}" data-pending-sub data-current="{{ $value('pending_sub_reason') }}"><option value="">None</option>@if($value('pending_sub_reason'))<option value="{{ $value('pending_sub_reason') }}" selected>{{ $value('pending_sub_reason') }}</option>@endif</select></div>
    <div class="col-md-4"><label class="form-label">Pending owner{{ $star }}</label><select class="form-select" name="{{ $name('pending_owner') }}" @if($required) required @endif><option value="">Who must act?</option>@foreach(ComplaintOptions::options('pending_owners') as $option => $label)<option value="{{ $option }}" @selected($value('pending_owner') === $option)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Responsible person</label><select class="form-select" name="{{ $name('responsible_user_id') }}"><option value="">Not set</option>@foreach($staff as $person)<option value="{{ $person->id }}" @selected((int) $value('responsible_user_id') === $person->id)>{{ $person->name }}</option>@endforeach</select></div>
    <div class="col-md-4"><label class="form-label">Reason for delay{{ $star }}</label><select class="form-select" name="{{ $name('delay_reason') }}" @if($required) required @endif><option value="">Select</option>@foreach(ComplaintOptions::options('delay_reasons') as $option => $label)<option value="{{ $option }}" @selected($value('delay_reason') === $option)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-md-8"><label class="form-label">Next action{{ $star }}</label><input class="form-control" name="{{ $name('next_action') }}" value="{{ $value('next_action') }}" maxlength="255" placeholder="e.g. Arrange PCB / follow up with Samsung" @if($required) required @endif></div>
    <div class="col-md-4"><label class="form-label">Escalation point</label><input class="form-control" name="{{ $name('escalation_point') }}" value="{{ $value('escalation_point') }}" maxlength="120" placeholder="e.g. Service manager"></div>
    <div class="col-md-4"><label class="form-label">Next follow-up date{{ $star }}</label><input type="date" class="form-control" name="{{ $name('follow_up_date') }}" value="{{ $dateValue('follow_up_date') }}" min="{{ today()->toDateString() }}" @if($required) required @endif></div>
    <div class="col-md-4"><label class="form-label">Expected material date</label><input type="date" class="form-control" name="{{ $name('expected_material_date') }}" value="{{ $dateValue('expected_material_date') }}" min="{{ today()->toDateString() }}"></div>
    <div class="col-md-4"><label class="form-label">Targeted closing date{{ $star }}</label><input type="date" class="form-control" name="{{ $name('target_date') }}" value="{{ $dateValue('target_date') }}" min="{{ today()->toDateString() }}" data-v="after_or_equal:follow_up_date" @if($required) required @endif></div>
    <div class="col-12"><label class="form-label">Remarks</label><textarea class="form-control" name="{{ $name('remarks') }}" rows="2" maxlength="2000" placeholder="Anything the next person should know">{{ old($key('remarks')) }}</textarea></div>
</div>
