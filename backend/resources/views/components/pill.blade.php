@props(['tone' => 'secondary', 'label' => '', 'icon' => null, 'dot' => true])
<span {{ $attributes->class(['nx-pill', 'tone-'.$tone, 'no-dot' => ! $dot || $icon]) }}>@if($icon)<i data-lucide="{{ $icon }}"></i>@endif{{ $label }}{{ $slot }}</span>
