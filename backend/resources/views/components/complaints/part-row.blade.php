@use('App\Services\ComplaintOptions')
@props(['field', 'index', 'row' => [], 'kind' => 'used'])
@php
    $row = is_array($row) ? $row : $row->toArray();
    $isUsed = $kind === 'used';
    $n = fn ($key) => $field.'['.$index.']['.$key.']';
    $v = fn ($key) => $row[$key] ?? null;
    $chk = fn ($key) => filter_var($v($key), FILTER_VALIDATE_BOOLEAN);
    $select = function (string $list, $selected, string $blank = '—') {
        $html = '<option value="">'.e($blank).'</option>';
        foreach (ComplaintOptions::options($list) as $value => $label) {
            $html .= '<option value="'.e($value).'"'.((string) $selected === (string) $value ? ' selected' : '').'>'.e($label).'</option>';
        }
        return $html;
    };
@endphp
<tr data-row>
                        <td><input class="form-control" name="{{ $n('part_name') }}" value="{{ $v('part_name') }}" list="productList" maxlength="160" data-part-name><input type="hidden" name="{{ $n('product_id') }}" value="{{ $v('product_id') }}" data-product-id></td>
                        <td><input class="form-control notranslate" name="{{ $n('part_code') }}" value="{{ $v('part_code') }}" maxlength="60" data-part-code></td>
                        <td><input class="form-control" name="{{ $n('category') }}" value="{{ $v('category') }}" maxlength="80" data-part-category></td>
                        <td><input class="form-control" name="{{ $n('make') }}" value="{{ $v('make') }}" maxlength="80" data-part-make></td>
                        <td><input type="number" step="0.01" min="0.01" class="form-control" name="{{ $n('quantity') }}" value="{{ $v('quantity') ?? 1 }}"></td>
                        <td><select class="form-select" name="{{ $n('uom') }}">{!! $select('uoms', $v('uom') ?? 'nos', 'Nos') !!}</select></td>
                        <td><select class="form-select" name="{{ $n('part_type') }}">{!! $select('part_types', $v('part_type')) !!}</select></td>
                        @if($isUsed)
                            <td><select class="form-select" name="{{ $n('source') }}">{!! $select('part_sources', $v('source')) !!}</select></td>
                            <td><input class="form-control" name="{{ $n('part_warranty') }}" value="{{ $v('part_warranty') }}" maxlength="60" placeholder="e.g. 12 months"></td>
                            <td><input class="form-control" name="{{ $n('stock_location') }}" value="{{ $v('stock_location') }}" maxlength="80"></td>
                            <td><input type="number" step="0.01" min="0" class="form-control" name="{{ $n('estimated_cost') }}" value="{{ $v('estimated_cost') }}"></td>
                            <td><input type="number" step="0.01" min="0" class="form-control" name="{{ $n('billable_rate') }}" value="{{ $v('billable_rate') }}"></td>
                            <td class="text-center"><input type="hidden" name="{{ $n('oem_warranty') }}" value="0"><input class="form-check-input" type="checkbox" name="{{ $n('oem_warranty') }}" value="1" @checked($chk('oem_warranty'))></td>
                            <td class="text-center"><input type="hidden" name="{{ $n('old_part_returned') }}" value="0"><input class="form-check-input" type="checkbox" name="{{ $n('old_part_returned') }}" value="1" @checked($chk('old_part_returned'))></td>
                            <td><input class="form-control" name="{{ $n('old_part_no') }}" value="{{ $v('old_part_no') }}" maxlength="80"></td>
                        @else
                            <td><input class="form-control" name="{{ $n('recommended_action') }}" value="{{ $v('recommended_action') }}" maxlength="255" placeholder="e.g. Replace PCB"></td>
                            <td class="text-center"><input type="hidden" name="{{ $n('available_in_stock') }}" value="0"><input class="form-check-input" type="checkbox" name="{{ $n('available_in_stock') }}" value="1" @checked($chk('available_in_stock'))></td>
                            <td><input class="form-control" name="{{ $n('stock_location') }}" value="{{ $v('stock_location') }}" maxlength="80"></td>
                            <td><input type="number" step="0.01" min="0" class="form-control" name="{{ $n('estimated_cost') }}" value="{{ $v('estimated_cost') }}"></td>
                            <td><input type="number" step="0.01" min="0" class="form-control" name="{{ $n('billable_rate') }}" value="{{ $v('billable_rate') }}"></td>
                            <td class="text-center"><input type="hidden" name="{{ $n('customer_approval_required') }}" value="0"><input class="form-check-input" type="checkbox" name="{{ $n('customer_approval_required') }}" value="1" @checked($chk('customer_approval_required'))></td>
                            <td class="text-center"><input type="hidden" name="{{ $n('quotation_given') }}" value="0"><input class="form-check-input" type="checkbox" name="{{ $n('quotation_given') }}" value="1" @checked($chk('quotation_given'))></td>
                            <td><select class="form-select" name="{{ $n('customer_approval_status') }}">{!! $select('approval_statuses', $v('customer_approval_status') ?? 'not_required', 'Not required') !!}</select></td>
                            <td class="text-center"><input type="hidden" name="{{ $n('manager_approval_required') }}" value="0"><input class="form-check-input" type="checkbox" name="{{ $n('manager_approval_required') }}" value="1" @checked($chk('manager_approval_required'))></td>
                            <td class="text-center"><input type="hidden" name="{{ $n('higher_approval_required') }}" value="0"><input class="form-check-input" type="checkbox" name="{{ $n('higher_approval_required') }}" value="1" @checked($chk('higher_approval_required'))></td>
                            <td><select class="form-select" name="{{ $n('approval_status') }}">{!! $select('approval_statuses', $v('approval_status') ?? 'not_required', 'Not required') !!}</select></td>
                            <td><select class="form-select" name="{{ $n('purchase_status') }}">{!! $select('purchase_statuses', $v('purchase_status') ?? 'not_required', 'Not required') !!}</select></td>
                            <td><input type="number" min="0" max="365" class="form-control" name="{{ $n('estimated_days') }}" value="{{ $v('estimated_days') }}"></td>
                            <td><input class="form-control" name="{{ $n('old_part_no') }}" value="{{ $v('old_part_no') }}" maxlength="80"></td>
                        @endif
                        <td><input class="form-control" name="{{ $n('additional_parts') }}" value="{{ $v('additional_parts') }}" maxlength="255" placeholder="Adaptor, jack…"></td>
                        <td><input class="form-control" name="{{ $n('remarks') }}" value="{{ $v('remarks') }}" maxlength="1000"></td>
                        <td><button type="button" class="btn btn-sm btn-light border btn-icon" data-remove-row title="Remove part"><i data-lucide="trash-2"></i></button></td>
                    </tr>
