@props(['label', 'value', 'icon' => 'activity', 'tone' => 'primary', 'href' => null, 'foot' => null, 'active' => false, 'text' => null])
@php
    $tag = $href ? 'a' : 'div';
    // Words and dates (or an explicit `text` tile) read a size smaller; figures such as "12", "₹4,500", "3 / 5" or "6.7 days" stay full size.
    $isText = $text ?? ! preg_match('/^[\d\s.,%\/₹+\-—]+(\s?(days?|h|hrs?|min))?$/u', (string) $value);
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['nx-metric', 'tone-'.$tone, 'is-active' => $active]) }}>
    <div><div @class(["nx-metric-value", "notranslate", "is-text" => $isText])>{{ $value }}</div><div class="nx-metric-label">{{ $label }}</div>@if($foot)<div class="nx-metric-foot">{{ $foot }}</div>@endif</div>
    <span class="nx-metric-icon"><i data-lucide="{{ $icon }}"></i></span>
</{{ $tag }}>
