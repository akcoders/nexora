@use('App\Services\ComplaintOptions')
@props(['field', 'rows' => [], 'kind' => 'used', 'products' => collect()])
@php
    $rows = collect(old($field, $rows))->values();
    $isUsed = $kind === 'used';
@endphp
<div data-parts-repeater data-field="{{ $field }}" data-kind="{{ $kind }}">
    <div class="table-responsive">
        <table class="table nx-table nx-repeat-table mb-2">
            <thead>
                <tr>
                    <th style="min-width:200px">Part name *</th><th style="min-width:110px">Part code</th><th style="min-width:110px">Category</th><th style="min-width:100px">Make</th>
                    <th style="width:80px">Qty</th><th style="width:90px">UOM</th><th style="min-width:120px">Part type</th>
                    @if($isUsed)<th style="min-width:120px">Source</th><th style="min-width:110px">Part warranty</th><th style="min-width:110px">Stock location</th><th style="width:110px">Est. cost</th><th style="width:110px">Billable rate</th><th>OEM warranty</th><th>Old part returned</th><th style="min-width:110px">Old part no</th>
                    @else<th style="min-width:160px">Recommended action</th><th>In stock</th><th style="min-width:110px">Stock location</th><th style="width:110px">Est. cost</th><th style="width:110px">Billable rate</th><th>Customer approval req.</th><th>Quotation given</th><th style="min-width:120px">Customer approval</th><th>Manager approval req.</th><th>Higher approval req.</th><th style="min-width:120px">Approval status</th><th style="min-width:130px">Purchase status</th><th style="width:90px">Est. days</th><th style="min-width:110px">Old part no</th>
                    @endif
                    <th style="min-width:160px">Additional supporting parts</th><th style="min-width:150px">Remarks</th><th></th>
                </tr>
            </thead>
            <tbody data-rows>
                @foreach($rows as $index => $row)
                    <x-complaints.part-row :field="$field" :index="$index" :row="$row" :kind="$kind" />
                @endforeach
            </tbody>
        </table>
    </div>
    <template data-row-template><x-complaints.part-row :field="$field" index="__INDEX__" :kind="$kind" /></template>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" data-add-row><i data-lucide="plus"></i>Add {{ $isUsed ? 'part used' : 'recommended part' }}</button>
        <span class="form-text m-0" data-empty-hint @if($rows->isNotEmpty()) hidden @endif>{{ $isUsed ? 'No parts replaced on this visit.' : 'No parts recommended for follow-up.' }}</span>
    </div>
</div>
