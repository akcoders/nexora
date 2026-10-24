@php
    $itemText = $checklist?->items->map(fn ($item) => $item->label.($item->photo_required ? ' [photo]' : ''))->join("\n");
    $field = fn (string $name, mixed $default = null): mixed => old($name, $checklist?->{$name} ?? $default);
@endphp
<div class="modal-body">
    <div class="row g-3">
        <div class="col-md-8"><label class="form-label">Checklist name *</label><input class="form-control" name="name" value="{{ $field('name') }}" required maxlength="255"></div>
        <div class="col-md-4"><label class="form-label">Phase *</label><select class="form-select" name="phase" required>@foreach(['pre' => 'Pre-service','post' => 'Post-service','both' => 'Both'] as $value => $label)<option value="{{ $value }}" @selected($field('phase', 'pre') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Service type</label><select class="form-select" name="service_type_id"><option value="">All service types</option>@foreach($serviceTypes as $serviceType)<option value="{{ $serviceType->id }}" @selected($field('service_type_id') == $serviceType->id)>{{ $serviceType->name }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Equipment type</label><input class="form-control" name="equipment_type" value="{{ $field('equipment_type') }}" placeholder="e.g. Split AC" maxlength="100"></div>
        <div class="col-md-4"><label class="form-label">Product category</label><select class="form-select" name="product_category_id"><option value="">All products</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected($field('product_category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Frequency</label><select class="form-select" name="frequency"><option value="">On demand</option>@foreach(['Every Service','Monthly','Quarterly','Half-yearly','Yearly'] as $frequency)<option value="{{ $frequency }}" @selected($field('frequency') === $frequency)>{{ $frequency }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Estimated minutes</label><input type="number" class="form-control" name="estimated_minutes" value="{{ $field('estimated_minutes') }}" min="1" max="1440"></div>
        <div class="col-md-4"><label class="form-label">Required skill</label><input class="form-control" name="required_skill" value="{{ $field('required_skill') }}" maxlength="255"></div>
        <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="2" maxlength="1000">{{ $field('description') }}</textarea></div>
        <div class="col-12"><label class="form-label">Checklist items *</label><textarea class="form-control" name="items" rows="7" required placeholder="Inspect air filter [photo]&#10;Measure inlet temperature&#10;Check refrigerant pressure" data-v="lines" maxlength="20000">{{ old('items', $itemText) }}</textarea></div>
        <div class="col-12"><label class="form-label">Safety notes</label><textarea class="form-control" name="safety_notes" rows="2" maxlength="3000">{{ $field('safety_notes') }}</textarea></div>
    </div>
</div>
