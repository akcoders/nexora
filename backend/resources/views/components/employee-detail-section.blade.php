@props(['flag', 'label', 'enabled' => false])
@php $sectionId = 'detail-section-'.md5($flag); $isEnabled = filter_var($enabled, FILTER_VALIDATE_BOOLEAN); @endphp
<div class="nx-detail-section mt-3" data-detail-section>
    <div class="nx-detail-section-toggle"><label class="form-label mb-0" for="{{ $sectionId }}">{{ $label }}</label><select class="form-select" name="{{ $flag }}" id="{{ $sectionId }}" data-detail-toggle aria-controls="{{ $sectionId }}-fields"><option value="0" @selected(!$isEnabled)>No</option><option value="1" @selected($isEnabled)>Yes</option></select></div>
    <fieldset id="{{ $sectionId }}-fields" data-detail-fields class="mt-3" @disabled(!$isEnabled) @if(!$isEnabled) hidden @endif>{{ $slot }}</fieldset>
</div>
