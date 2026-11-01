@props(['id', 'label', 'icon', 'active' => false, 'badge' => 0, 'badgeTone' => 'danger'])
<details {{ $attributes->class(['nx-nav-group', 'is-current' => $active]) }} @if($active) open @endif>
    <summary class="nx-nav-group-toggle" id="{{ $id }}-heading" aria-controls="{{ $id }}-menu">
        <i data-lucide="{{ $icon }}" class="nx-nav-group-icon" aria-hidden="true"></i>
        <span class="nx-nav-group-label">{{ $label }}</span>
        @if($badge)<span class="badge bg-{{ $badgeTone }} {{ $badgeTone === 'warning' ? 'text-dark' : '' }} notranslate">{{ $badge }}</span>@endif
        <i data-lucide="chevron-down" class="nx-nav-group-chevron" aria-hidden="true"></i>
    </summary>
    <div class="nx-nav-submenu" id="{{ $id }}-menu" aria-labelledby="{{ $id }}-heading">{{ $slot }}</div>
</details>
