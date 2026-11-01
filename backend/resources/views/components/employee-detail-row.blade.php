@props(['name', 'index', 'row' => [], 'fields', 'nestedAssets' => false])
<div class="nx-detail-row" data-detail-row>
    <div class="row g-2 align-items-end">
        @foreach($fields as $field)
            @php
                $key = $field['key'];
                $fieldId = 'detail-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $name).'-'.$index.'-'.$key;
                $fieldValue = is_scalar($row[$key] ?? null) ? $row[$key] : '';
            @endphp
            <div class="{{ $field['column'] ?? 'col-md-5' }}"><label class="form-label small" for="{{ $fieldId }}">{{ $field['label'] }}{{ ($field['required'] ?? false) ? ' *' : '' }}</label>
                @if(isset($field['options']))
                    <select class="form-select" name="{{ $name }}[{{ $index }}][{{ $key }}]" id="{{ $fieldId }}" @required($field['required'] ?? false)>
                        <option value="">Select</option>@foreach($field['options'] as $value => $label)<option value="{{ $value }}" @selected((string)$fieldValue === (string)$value)>{{ $label }}</option>@endforeach
                        @if(filled($fieldValue) && !array_key_exists($fieldValue, $field['options']))<option value="{{ $fieldValue }}" selected>{{ $fieldValue }}</option>@endif
                    </select>
                @else
                    <input class="form-control" type="{{ $field['type'] ?? 'text' }}" name="{{ $name }}[{{ $index }}][{{ $key }}]" id="{{ $fieldId }}" value="{{ $fieldValue }}" maxlength="{{ $field['max'] ?? 160 }}" placeholder="{{ $field['placeholder'] ?? '' }}" data-v="{{ $field['validation'] ?? '' }}" @required($field['required'] ?? false)>
                @endif
            </div>
        @endforeach
        <div class="col-md-auto ms-md-auto"><button class="btn btn-light border nx-detail-remove" type="button" data-detail-remove aria-label="Remove this row" title="Remove this row"><i data-lucide="trash-2" aria-hidden="true"></i></button></div>
    </div>
    @if($nestedAssets)
        <div class="nx-detail-nested"><x-employee-detail-list :name="$name.'['.$index.'][items]'" :rows="$row['items'] ?? []" :fields="[['key'=>'name','label'=>'Tool / item name','required'=>true,'max'=>120,'column'=>'col-md-10','placeholder'=>'e.g. Spanner, tester or pliers']]" title="Contents / sub-tools (optional)" add-label="Add item name" :minimum="0" :maximum="50" index-token="__ITEM_INDEX__" /></div>
    @endif
</div>
