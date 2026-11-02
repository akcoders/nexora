@props(['label', 'value', 'icon' => 'activity', 'tone' => 'primary', 'href' => null, 'foot' => null, 'active' => false])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->class(['nx-metric', 'tone-'.$tone, 'is-active' => $active]) }}>
    <div><div class="nx-metric-value notranslate">{{ $value }}</div><div class="nx-metric-label">{{ $label }}</div>@if($foot)<div class="nx-metric-foot">{{ $foot }}</div>@endif</div>
    <span class="nx-metric-icon"><i data-lucide="{{ $icon }}"></i></span>
</{{ $tag }}>
