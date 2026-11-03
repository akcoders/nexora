<x-app-layout>
    <x-slot name="title">Report builder</x-slot>
    <x-slot name="pageTitle">Report builder</x-slot>
    <x-slot name="breadcrumb">Reports / Builder</x-slot>
    @php
        $templates = collect([
            ['label' => 'Complaints by status', 'icon' => 'chart-column', 'config' => ['dataset' => 'complaints', 'group_by' => 'status', 'chart' => 'bar', 'preset' => 'this_month']],
            ['label' => 'Calls by type per month', 'icon' => 'chart-bar-stacked', 'config' => ['dataset' => 'complaints', 'group_by' => 'registered_at', 'granularity' => 'month', 'split_by' => 'call_type', 'chart' => 'stacked', 'preset' => 'this_year']],
            ['label' => 'Pending by reason', 'icon' => 'hourglass', 'config' => ['dataset' => 'complaints', 'group_by' => 'pending_reason', 'chart' => 'hbar', 'filters' => [['field' => 'status', 'op' => 'in', 'value' => ['pending', 'under_observation']]]]],
            ['label' => 'Complaints per technician', 'icon' => 'hard-hat', 'config' => ['dataset' => 'complaints', 'group_by' => 'technician', 'split_by' => 'status', 'chart' => 'stacked', 'preset' => 'this_month']],
            ['label' => 'Average age by priority', 'icon' => 'timer', 'config' => ['dataset' => 'complaints', 'group_by' => 'priority', 'measure' => 'avg', 'measure_field' => 'age_days', 'chart' => 'bar']],
            ['label' => 'Root causes', 'icon' => 'search-check', 'config' => ['dataset' => 'complaints', 'group_by' => 'root_cause', 'chart' => 'donut', 'preset' => 'this_year']],
            ['label' => 'Parts used', 'icon' => 'cog', 'config' => ['dataset' => 'complaint_parts', 'group_by' => 'part_name', 'measure' => 'sum', 'measure_field' => 'quantity', 'chart' => 'hbar', 'filters' => [['field' => 'kind', 'op' => 'in', 'value' => ['used']]]]],
            ['label' => 'Visits by outcome', 'icon' => 'route', 'config' => ['dataset' => 'complaint_visits', 'group_by' => 'technician', 'split_by' => 'outcome', 'chart' => 'stacked', 'preset' => 'last_30']],
            ['label' => 'Attendance by employee', 'icon' => 'map-pin-check', 'config' => ['dataset' => 'attendance', 'group_by' => 'employee', 'chart' => 'hbar', 'preset' => 'this_month']],
            ['label' => 'Leave days by type', 'icon' => 'calendar-heart', 'config' => ['dataset' => 'leave_requests', 'group_by' => 'leave_type', 'measure' => 'sum', 'measure_field' => 'total_days', 'chart' => 'pie', 'preset' => 'this_year']],
            ['label' => 'Payroll by department', 'icon' => 'wallet-cards', 'config' => ['dataset' => 'payroll', 'group_by' => 'department', 'measure' => 'sum', 'measure_field' => 'net_amount', 'chart' => 'bar']],
        ])->filter(fn ($template) => isset($catalogue[$template['config']['dataset']]))->values();
    @endphp

    <div class="nx-page-header">
        <div>
            <div class="nx-page-kicker">Reports</div>
            <h2>Report builder</h2>
            <p>Pick any data, group it, filter it and see the chart instantly. Save it, share it with the team or pin it to your dashboard.</p>
        </div>
        <div class="nx-page-actions">
            <a class="btn btn-light border" href="{{ route('reports.index') }}"><i data-lucide="chart-no-axes-combined"></i>Standard reports</a>
            <button class="btn btn-light border" type="button" data-builder-reset><i data-lucide="rotate-ccw"></i>New report</button>
        </div>
    </div>

    @if($catalogue === [])
        <div class="nx-card"><div class="nx-empty"><i data-lucide="lock"></i><strong>No data sources available</strong><div>Your role does not have access to any reportable module.</div></div></div>
    @else
    <div class="nx-card mb-3">
        <div class="nx-card-body py-2 d-flex align-items-start gap-2">
            <span class="small fw-semibold text-secondary text-nowrap pt-1">Quick start:</span>
            {{-- Wraps under the first chip on desktop; one swipeable row on phones. --}}
            <div class="d-flex flex-nowrap flex-md-wrap gap-2 min-w-0 flex-grow-1 overflow-auto">
                @foreach($templates as $index => $template)
                    <button type="button" class="btn btn-sm btn-light border flex-shrink-0" data-template="{{ $index }}"><i data-lucide="{{ $template['icon'] }}"></i>{{ $template['label'] }}</button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="nx-builder" data-report-builder>
        <aside class="nx-card nx-builder-panel">
            <div class="nx-builder-step">
                <div class="nx-builder-step-title"><span>1</span>Data source</div>
                <select class="form-select" data-cfg="dataset" aria-label="Data source">@foreach($catalogue as $key => $dataset)<option value="{{ $key }}">{{ $dataset['label'] }}</option>@endforeach</select>
                <div class="form-text" data-dataset-description></div>
            </div>
            <div class="nx-builder-step">
                <div class="nx-builder-step-title"><span>2</span>Report type</div>
                <div class="nx-segment w-100"><input type="radio" name="cfg_mode" id="modeSummary" value="summary" data-cfg-mode checked><label for="modeSummary" class="flex-fill justify-content-center"><i data-lucide="chart-column"></i>Summary chart</label><input type="radio" name="cfg_mode" id="modeList" value="list" data-cfg-mode><label for="modeList" class="flex-fill justify-content-center"><i data-lucide="table"></i>Detailed list</label></div>
            </div>
            <div class="nx-builder-step" data-mode-section="summary">
                <div class="nx-builder-step-title"><span>3</span>Group & measure</div>
                <label class="form-label">Group by</label>
                <div class="d-flex gap-2 mb-2"><select class="form-select" data-cfg="group_by"></select><select class="form-select d-none" style="max-width:120px" data-cfg="granularity">@foreach($ui['granularities'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                <label class="form-label">Split by <span class="text-secondary fw-normal">(optional)</span></label>
                <select class="form-select mb-2" data-cfg="split_by"></select>
                <label class="form-label">Measure</label>
                <div class="d-flex gap-2 mb-2"><select class="form-select" data-cfg="measure">@foreach($ui['measures'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><select class="form-select d-none" data-cfg="measure_field"></select></div>
                <div class="row g-2">
                    <div class="col-7"><label class="form-label">Sort</label><select class="form-select" data-cfg="sort"><option value="value_desc">Highest first</option><option value="value_asc">Lowest first</option><option value="label_asc">A → Z / oldest first</option><option value="label_desc">Z → A / newest first</option></select></div>
                    <div class="col-5"><label class="form-label">Show top</label><select class="form-select" data-cfg="limit">@foreach([5, 10, 20, 50, 100] as $limit)<option value="{{ $limit }}" @selected($limit === 20)>{{ $limit }}</option>@endforeach</select></div>
                </div>
            </div>
            <div class="nx-builder-step d-none" data-mode-section="list">
                <div class="nx-builder-step-title"><span>3</span>Columns</div>
                <input type="search" class="form-control form-control-sm mb-2" placeholder="Find a column…" data-column-search>
                <div class="d-grid gap-1" style="max-height:220px;overflow:auto" data-columns></div>
                <div class="row g-2 mt-1">
                    <div class="col-6"><label class="form-label">Sort by</label><select class="form-select" data-cfg="list_sort"></select></div>
                    <div class="col-3"><label class="form-label">Order</label><select class="form-select" data-cfg="list_dir"><option value="desc">↓</option><option value="asc">↑</option></select></div>
                    <div class="col-3"><label class="form-label">Rows</label><select class="form-select" data-cfg="list_limit">@foreach([100, 500, 1000, 2000] as $limit)<option value="{{ $limit }}" @selected($limit === 500)>{{ $limit }}</option>@endforeach</select></div>
                </div>
            </div>
            <div class="nx-builder-step">
                <div class="nx-builder-step-title"><span>4</span>Period</div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label">Date field</label><select class="form-select" data-cfg="date_field"></select></div>
                    <div class="col-6"><label class="form-label">Range</label><select class="form-select" data-cfg="preset">@foreach($ui['presets'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
                    <div class="col-6 d-none" data-custom-range><label class="form-label">From</label><input type="date" class="form-control" data-cfg="from"></div>
                    <div class="col-6 d-none" data-custom-range><label class="form-label">To</label><input type="date" class="form-control" data-cfg="to"></div>
                </div>
            </div>
            <div class="nx-builder-step">
                <div class="nx-builder-step-title"><span>5</span>Filters</div>
                <div data-filters></div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-filter><i data-lucide="plus"></i>Add filter</button>
            </div>
            <div class="nx-builder-step" data-mode-section="summary">
                <div class="nx-builder-step-title"><span>6</span>Chart</div>
                <div class="nx-chart-types">
                    @foreach(['bar' => 'chart-column', 'hbar' => 'chart-bar', 'stacked' => 'chart-column-stacked', 'line' => 'chart-line', 'area' => 'chart-area', 'pie' => 'chart-pie', 'donut' => 'circle-dot', 'table' => 'table'] as $key => $icon)
                        <label><input type="radio" name="cfg_chart" value="{{ $key }}" data-cfg-chart @checked($key === 'bar')><span><i data-lucide="{{ $icon }}"></i>{{ $ui['charts'][$key] }}</span></label>
                    @endforeach
                </div>
            </div>
        </aside>

        <div class="min-w-0">
            <section class="nx-card mb-3">
                <div class="nx-card-header">
                    <div class="min-w-0"><h3 class="nx-card-title" data-report-title><i data-lucide="chart-no-axes-combined"></i>Untitled report</h3><p class="nx-card-subtitle" data-report-meta>Choose options on the left — the preview updates automatically.</p></div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-light border" data-run><i data-lucide="refresh-cw"></i>Refresh</button>
                        <button type="button" class="btn btn-sm btn-light border d-none" data-pin-toggle><i data-lucide="pin"></i><span>Pin to dashboard</span></button>
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#saveReportModal"><i data-lucide="save"></i><span data-save-label>Save report</span></button>
                    </div>
                </div>
                <div class="nx-card-body">
                    <div class="alert alert-danger d-none" data-report-error></div>
                    <div class="nx-report-canvas" data-report-canvas><div class="nx-report-empty"><div><i data-lucide="chart-spline" style="width:40px;height:40px;color:#b6c1d1"></i><div class="mt-2">Your chart will appear here.</div></div></div></div>
                    <div class="d-flex flex-wrap gap-2 mt-2" data-report-chips></div>
                </div>
            </section>
            <section class="nx-card nx-datatable-card mb-3">
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="table"></i>Data</h3><span class="badge badge-soft-secondary" data-report-count></span></div>
                <div data-report-table><div class="nx-empty py-4">Run a report to see the rows. You can export them to Excel, CSV, PDF or print.</div></div>
            </section>

            <section class="nx-card nx-datatable-card">
                <div class="nx-card-header"><div><h3 class="nx-card-title"><i data-lucide="folder-open"></i>Saved reports</h3><p class="nx-card-subtitle">Your reports and reports shared with everyone</p></div></div>
                <table class="table nx-table" data-datatable data-dt-title="Saved reports" data-dt-page-length="10" data-dt-empty="No saved reports yet. Build one above and press Save.">
                    <thead><tr><th>Report</th><th>Data source</th><th>Owner</th><th>Shared</th><th>On dashboard</th><th>Updated</th><th class="no-export no-sort" data-orderable="false"></th></tr></thead>
                    <tbody>
                        @foreach($saved as $report)
                            <tr>
                                <td><a class="nx-row-title" href="{{ route('reports.builder', ['report' => $report->id]) }}">{{ $report->name }}</a><span class="nx-row-sub">{{ $report->description }}</span></td>
                                <td>{{ $catalogue[$report->dataset]['label'] ?? $report->dataset }}</td>
                                <td class="notranslate">{{ $report->user?->name }}</td>
                                <td>@if($report->is_shared)<x-pill tone="info" label="Everyone" />@else<x-pill tone="secondary" label="Only me" />@endif</td>
                                <td>{{ $report->pinned && $report->user_id === auth()->id() ? 'Pinned' : '—' }}</td>
                                <td data-order="{{ $report->updated_at->timestamp }}">{{ $report->updated_at->format('d M Y, h:i A') }}</td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-light border" href="{{ route('reports.builder', ['report' => $report->id]) }}">Open</a>
                                    @if($report->user_id === auth()->id())
                                        <form class="d-inline" method="POST" action="{{ route('reports.saved.pin', $report) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-light border" title="{{ $report->pinned ? 'Unpin from dashboard' : 'Pin to dashboard' }}"><i data-lucide="{{ $report->pinned ? 'pin-off' : 'pin' }}"></i></button></form>
                                        <form class="d-inline" method="POST" action="{{ route('reports.saved.destroy', $report) }}" data-confirm="Delete the report “{{ $report->name }}”?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" title="Delete"><i data-lucide="trash-2"></i></button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        </div>
    </div>

    <div class="modal fade" id="saveReportModal" tabindex="-1" aria-labelledby="saveReportTitle">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" data-save-form data-v-ignore>
                <div class="modal-header"><h5 class="modal-title" id="saveReportTitle">Save report</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none" data-save-error></div>
                    <div class="mb-3"><label class="form-label" for="reportName">Report name <span class="text-danger">*</span></label><input class="form-control" id="reportName" name="name" required maxlength="120" placeholder="e.g. Pending complaints by reason"></div>
                    <div class="mb-3"><label class="form-label" for="reportDescription">Description</label><input class="form-control" id="reportDescription" name="description" maxlength="500"></div>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="reportShared" name="is_shared" value="1"><label class="form-check-label" for="reportShared">Share with everyone who can see this data</label></div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="reportPinned" name="pinned" value="1"><label class="form-check-label" for="reportPinned">Pin to my dashboard</label></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-outline-primary d-none" data-save-as-new>Save as new</button>
                    <button type="submit" class="btn btn-primary" data-save-submit>Save</button>
                </div>
            </form>
        </div>
    </div>

    @php
        $builderData = [
        'catalogue' => $catalogue,
        'operators' => $ui['operators'],
        'templates' => $templates->pluck('config'),
        'selected' => $selected ? ['id' => $selected->id, 'name' => $selected->name, 'description' => $selected->description, 'is_shared' => $selected->is_shared, 'pinned' => $selected->pinned, 'owned' => $selected->user_id === auth()->id(), 'config' => $selected->config] : null,
        'starter' => $starter,
        'urls' => ['preview' => route('reports.builder.preview'), 'store' => route('reports.saved.store'), 'update' => route('reports.saved.update', ['savedReport' => '__ID__']), 'pin' => route('reports.saved.pin', ['savedReport' => '__ID__']), 'open' => route('reports.builder', ['report' => '__ID__'])],
    ];
    @endphp
    <script type="application/json" id="reportBuilderData">@json($builderData)</script>
    @push('scripts')@vite('resources/js/report-builder.js')@endpush
    @endif
</x-app-layout>
