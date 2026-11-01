@props(['name', 'rows' => [], 'fields', 'title' => 'Details', 'addLabel' => 'Add more', 'minimum' => 1, 'maximum' => 20, 'indexToken' => '__INDEX__', 'nestedAssets' => false])
@php
    $detailRows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
    if (!$detailRows && $minimum > 0) $detailRows = [[]];
@endphp
<div class="nx-detail-list" data-detail-list data-minimum="{{ $minimum }}" data-maximum="{{ $maximum }}" data-next-index="{{ count($detailRows) }}" data-index-token="{{ $indexToken }}">
    <div class="nx-detail-list-head"><div><strong>{{ $title }}</strong><span class="small text-secondary ms-2" data-detail-count></span></div><button type="button" class="btn btn-sm btn-outline-primary" data-detail-add><i data-lucide="plus" aria-hidden="true"></i>{{ $addLabel }}</button></div>
    <div data-detail-rows>
        @foreach($detailRows as $index => $row)<x-employee-detail-row :name="$name" :index="$index" :row="$row" :fields="$fields" :nested-assets="$nestedAssets" />@endforeach
    </div>
    <template data-detail-template><x-employee-detail-row :name="$name" :index="$indexToken" :row="[]" :fields="$fields" :nested-assets="$nestedAssets" /></template>
</div>
