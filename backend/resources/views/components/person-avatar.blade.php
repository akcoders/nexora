@props(['user', 'size' => 36, 'detail' => null, 'focusable' => true])
@php
    $personName = $user->name ?? 'Unknown user';
    $initials = collect(preg_split('/\s+/', trim($personName)))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    $personLabel = $personName.($detail ? ' · '.$detail : '');
@endphp
<span {{ $attributes->class('nx-person-avatar') }} style="--person-size:{{ (int) $size }}px;--person-hue:{{ ((int) ($user->id ?? 0) * 47) % 360 }}" role="img" title="{{ $personLabel }}" aria-label="{{ $personLabel }}" @if($focusable) tabindex="0" @endif>
    <span aria-hidden="true">{{ $initials }}</span>
    <span class="nx-person-tooltip" role="tooltip">{{ $personLabel }}</span>
</span>
