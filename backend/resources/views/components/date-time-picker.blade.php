@props(['name' => 'due_at', 'value' => null, 'id' => null, 'label' => 'Due date & time'])
@php
    $pickerId = $id ?? 'schedule-'.\Illuminate\Support\Str::uuid();
    try {
        $schedule = filled($value) ? \Illuminate\Support\Carbon::parse($value) : null;
    } catch (\Throwable) {
        $schedule = null;
    }
@endphp
<div class="nx-date-time-picker" data-date-time-picker>
    <label class="form-label" for="{{ $pickerId }}-date">{{ $label }}</label>
    <input type="hidden" name="{{ $name }}" value="{{ $schedule?->format('Y-m-d\TH:i') }}" data-schedule-value>
    <div class="nx-schedule-fields">
        <div class="nx-schedule-input"><i data-lucide="calendar-days" aria-hidden="true"></i><div><label for="{{ $pickerId }}-date">Date</label><input type="date" id="{{ $pickerId }}-date" min="{{ today()->toDateString() }}" value="{{ $schedule?->format('Y-m-d') }}" aria-label="{{ $label }}: date" data-schedule-date></div></div>
        <div class="nx-schedule-input"><i data-lucide="clock-3" aria-hidden="true"></i><div><label for="{{ $pickerId }}-time">Time</label><input type="time" id="{{ $pickerId }}-time" value="{{ $schedule?->format('H:i') ?? '09:00' }}" step="60" aria-label="{{ $label }}: time" data-schedule-time></div></div>
    </div>
    <div class="nx-schedule-caption"><span data-schedule-summary aria-live="polite">Choose today or a later date</span><button type="button" class="btn btn-link btn-sm p-0" data-schedule-clear>Clear</button></div>
</div>
