/**
 * Self-service report builder: builds a report configuration from the left panel,
 * previews it (chart + exportable data table) and saves / shares / pins it.
 */
import { PALETTE, renderResult } from './report-chart.js';

const root = document.querySelector('[data-report-builder]');
const boot = JSON.parse(document.getElementById('reportBuilderData')?.textContent || '{}');
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
const $ = (selector, scope = root) => scope.querySelector(selector);
const $$ = (selector, scope = root) => [...scope.querySelectorAll(selector)];

if (root) {
    const catalogue = boot.catalogue || {};
    const operators = boot.operators || {};
    let saved = boot.selected; // { id, name, owned, pinned, ... }
    let timer = null;
    let lastResult = null;
    let request = null;

    const control = (name) => $(`[data-cfg="${name}"]`);
    const dataset = () => catalogue[control('dataset').value];
    const fields = () => Object.entries(dataset()?.fields || {});
    const mode = () => $('[data-cfg-mode]:checked')?.value || 'summary';

    function options(select, entries, { blank = null, value = null } = {}) {
        const current = value ?? select.value;
        select.replaceChildren(...(blank !== null ? [new Option(blank, '')] : []), ...entries.map(([key, label]) => new Option(label, key)));
        if ([...select.options].some((option) => option.value === current)) select.value = current;
    }

    function fillFieldControls() {
        const all = fields();
        const label = ([key, field]) => [key, field.label];
        options(control('group_by'), all.map(label));
        options(control('split_by'), all.filter(([, field]) => field.type !== 'date').map(label), { blank: 'No split' });
        options(control('measure_field'), all.filter(([, field]) => field.type === 'number').map(label));
        options(control('date_field'), all.filter(([, field]) => field.type === 'date').map(label), { blank: 'No date filter', value: dataset()?.default_date });
        options(control('list_sort'), all.map(label));
        $('[data-dataset-description]').textContent = dataset()?.description || '';
        const columns = $('[data-columns]');
        columns.innerHTML = all.map(([key, field], index) => `<label class="form-check m-0 small" data-column-option><input class="form-check-input" type="checkbox" value="${escapeHtml(key)}" ${index < 6 ? 'checked' : ''}><span class="form-check-label">${escapeHtml(field.label)}</span></label>`).join('');
        $('[data-filters]').innerHTML = '';
        $('[data-cfg="measure"]').querySelector('option[value="sum"]').disabled = !all.some(([, field]) => field.type === 'number');
        syncControls();
    }

    function syncControls() {
        const isSummary = mode() === 'summary';
        $$('[data-mode-section]').forEach((section) => section.classList.toggle('d-none', section.dataset.modeSection !== mode()));
        const groupField = dataset()?.fields[control('group_by').value];
        control('granularity').classList.toggle('d-none', !isSummary || groupField?.type !== 'date');
        const measure = control('measure').value;
        const hasNumbers = fields().some(([, field]) => field.type === 'number');
        if (!hasNumbers && measure !== 'count') control('measure').value = 'count';
        control('measure_field').classList.toggle('d-none', control('measure').value === 'count');
        $$('[data-custom-range]').forEach((el) => el.classList.toggle('d-none', control('preset').value !== 'custom'));
    }

    function filterRow(filter = {}) {
        const row = document.createElement('div');
        row.className = 'nx-filter-row';
        row.innerHTML = `<select class="form-select form-select-sm" data-filter-field>${fields().map(([key, field]) => `<option value="${escapeHtml(key)}">${escapeHtml(field.label)}</option>`).join('')}</select><select class="form-select form-select-sm" data-filter-op></select><div data-filter-value></div><button type="button" class="btn btn-sm btn-light border btn-icon" data-filter-remove title="Remove filter"><i data-lucide="x"></i></button>`;
        const fieldSelect = row.querySelector('[data-filter-field]');
        const opSelect = row.querySelector('[data-filter-op]');
        const valueBox = row.querySelector('[data-filter-value]');
        if (filter.field) fieldSelect.value = filter.field;
        const renderValue = (value) => {
            const field = dataset().fields[fieldSelect.value];
            const op = opSelect.value;
            const needsValue = !['empty', 'not_empty', 'is_true', 'is_false'].includes(op);
            if (!needsValue) {
                valueBox.innerHTML = '';
                return;
            }
            if (field.type === 'enum' && field.options) {
                const selected = Array.isArray(value) ? value.map(String) : (value ? [String(value)] : []);
                valueBox.innerHTML = `<select class="form-select form-select-sm" ${op === 'in' ? 'multiple size="3"' : ''} data-filter-input>${Object.entries(field.options).map(([key, label]) => `<option value="${escapeHtml(key)}" ${selected.includes(key) ? 'selected' : ''}>${escapeHtml(label)}</option>`).join('')}</select>`;
            } else {
                const type = field.type === 'number' ? 'number' : field.type === 'date' ? 'date' : 'text';
                valueBox.innerHTML = `<input class="form-control form-control-sm" type="${type}" data-filter-input value="${escapeHtml(Array.isArray(value) ? value[0] : value ?? '')}" placeholder="Value">`;
            }
        };
        const renderOps = () => {
            const field = dataset().fields[fieldSelect.value];
            opSelect.replaceChildren(...Object.entries(operators[field.type] || {}).map(([key, label]) => new Option(label, key)));
            if (filter.op && [...opSelect.options].some((option) => option.value === filter.op)) opSelect.value = filter.op;
            renderValue(filter.value);
            filter = {};
        };
        fieldSelect.addEventListener('change', () => { renderOps(); schedule(); });
        opSelect.addEventListener('change', () => { renderValue(); schedule(); });
        valueBox.addEventListener('change', schedule);
        valueBox.addEventListener('input', schedule);
        row.querySelector('[data-filter-remove]').addEventListener('click', () => { row.remove(); schedule(); });
        renderOps();
        $('[data-filters]').appendChild(row);
        window.refreshIcons?.();
    }

    function readConfig() {
        const isSummary = mode() === 'summary';
        const config = {
            dataset: control('dataset').value,
            mode: mode(),
            date_field: control('date_field').value || null,
            preset: control('preset').value,
            from: control('from').value || null,
            to: control('to').value || null,
            filters: $$('.nx-filter-row').map((row) => {
                const input = row.querySelector('[data-filter-input]');
                const value = input ? (input.multiple ? [...input.selectedOptions].map((option) => option.value) : input.value) : null;
                return { field: row.querySelector('[data-filter-field]').value, op: row.querySelector('[data-filter-op]').value, value };
            }),
        };
        if (isSummary) {
            Object.assign(config, {
                group_by: control('group_by').value,
                granularity: control('granularity').value,
                split_by: control('split_by').value || null,
                measure: control('measure').value,
                measure_field: control('measure').value === 'count' ? null : control('measure_field').value,
                sort: control('sort').value,
                limit: Number(control('limit').value),
                chart: $('[data-cfg-chart]:checked')?.value || 'bar',
            });
        } else {
            Object.assign(config, {
                columns: $$('[data-columns] input:checked').map((input) => input.value),
                list_sort: control('list_sort').value,
                list_dir: control('list_dir').value,
                limit: Number(control('list_limit').value),
            });
        }
        return config;
    }

    function applyConfig(config) {
        if (!config || !catalogue[config.dataset]) return;
        control('dataset').value = config.dataset;
        fillFieldControls();
        const modeInput = $(`[data-cfg-mode][value="${config.mode || 'summary'}"]`);
        if (modeInput) modeInput.checked = true;
        const set = (name, value) => { if (value !== undefined && value !== null && control(name)) control(name).value = value; };
        set('group_by', config.group_by);
        set('granularity', config.granularity || 'month');
        set('split_by', config.split_by || '');
        set('measure', config.measure || 'count');
        set('measure_field', config.measure_field);
        set('sort', config.sort);
        if (config.mode === 'list') set('list_limit', config.limit); else set('limit', config.limit);
        set('date_field', config.date_field ?? catalogue[config.dataset].default_date);
        set('preset', config.preset || 'all');
        set('from', config.from);
        set('to', config.to);
        set('list_sort', config.list_sort);
        set('list_dir', config.list_dir);
        if (config.columns) $$('[data-columns] input').forEach((input) => { input.checked = config.columns.includes(input.value); });
        const chart = $(`[data-cfg-chart][value="${config.chart || 'bar'}"]`);
        if (chart) chart.checked = true;
        (config.filters || []).forEach((filter) => filterRow(filter));
        syncControls();
    }

    function schedule() {
        clearTimeout(timer);
        timer = setTimeout(run, 400);
    }

    async function run() {
        syncControls();
        const config = readConfig();
        const canvas = $('[data-report-canvas]');
        const errorBox = $('[data-report-error]');
        request?.abort();
        request = new AbortController();
        canvas.style.opacity = '.55';
        try {
            const response = await fetch(boot.urls.preview, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify(config),
                signal: request.signal,
            });
            const payload = await response.json();
            if (!response.ok) {
                errorBox.textContent = Object.values(payload.errors || {}).flat()[0] || payload.message || 'The report could not be run.';
                errorBox.classList.remove('d-none');
                return;
            }
            errorBox.classList.add('d-none');
            lastResult = payload.result;
            draw(payload.result);
        } catch (error) {
            if (error.name !== 'AbortError') {
                errorBox.textContent = 'The report could not be run. Check your connection and try again.';
                errorBox.classList.remove('d-none');
            }
        } finally {
            canvas.style.opacity = '';
        }
    }

    function draw(result) {
        const canvas = $('[data-report-canvas]');
        const meta = result.meta || {};
        $('[data-report-meta]').textContent = [meta.dataset, meta.group ? `by ${meta.group}` : null, meta.split ? `split by ${meta.split}` : null, meta.measure, meta.period].filter(Boolean).join(' · ');
        $('[data-report-chips]').innerHTML = [
            ...(meta.filters || []).map((text) => `<span class="nx-tag"><i data-lucide="filter"></i>${escapeHtml(text)}</span>`),
            meta.truncated ? '<span class="badge badge-soft-warning">Showing the top results only</span>' : '',
            result.total !== null && result.total !== undefined ? `<span class="badge badge-soft-primary">Total ${escapeHtml(result.total.toLocaleString('en-IN'))}</span>` : '',
        ].join('');
        if (result.mode === 'list' || result.chart === 'table') {
            canvas.innerHTML = `<div class="nx-report-empty"><div><i data-lucide="table" style="width:36px;height:36px;color:#b6c1d1"></i><div class="mt-2">${result.mode === 'list' ? 'Detailed list' : 'Table view'} — ${escapeHtml(meta.row_count ?? 0)} rows shown below.</div></div></div>`;
        } else {
            renderResult(canvas, result, { height: 380 });
        }
        renderDataTable(result);
        window.refreshIcons?.();
    }

    function renderDataTable(result) {
        const holder = $('[data-report-table]');
        const title = $('[data-report-title]').textContent.trim() || 'Report';
        const columns = result.columns || [];
        const rows = result.rows || [];
        $('[data-report-count]').textContent = `${rows.length} row${rows.length === 1 ? '' : 's'}`;
        holder.innerHTML = `<table class="table nx-table" data-datatable data-dt-title="${escapeHtml(title)}" data-dt-page-length="25" data-dt-empty="No data for this selection."><thead><tr>${columns.map((column) => `<th>${escapeHtml(column)}</th>`).join('')}</tr></thead><tbody>${rows.map((row) => `<tr>${row.map((cell) => `<td ${typeof cell === 'number' ? `data-order="${cell}" class="text-end"` : ''}>${escapeHtml(typeof cell === 'number' ? cell.toLocaleString('en-IN', { maximumFractionDigits: 2 }) : cell)}</td>`).join('')}</tr>`).join('')}</tbody></table>`;
        window.NxDataTable?.init(holder);
    }

    function updateSavedUi() {
        const title = $('[data-report-title]');
        title.innerHTML = `<i data-lucide="chart-no-axes-combined"></i>${escapeHtml(saved?.name || 'Untitled report')}`;
        $('[data-save-label]').textContent = saved?.owned ? 'Update report' : 'Save report';
        const pin = $('[data-pin-toggle]');
        pin.classList.toggle('d-none', !saved?.owned);
        pin.querySelector('span').textContent = saved?.pinned ? 'Unpin from dashboard' : 'Pin to dashboard';
        window.refreshIcons?.();
    }

    async function save(asNew = false) {
        const form = document.querySelector('[data-save-form]');
        const errorBox = form.querySelector('[data-save-error]');
        const body = {
            name: form.name.value.trim(),
            description: form.description.value.trim() || null,
            is_shared: form.is_shared.checked,
            pinned: form.pinned.checked,
            config: readConfig(),
        };
        if (!body.name) {
            errorBox.textContent = 'Give the report a name.';
            errorBox.classList.remove('d-none');
            return;
        }
        const updating = saved?.owned && !asNew;
        const response = await fetch(updating ? boot.urls.update.replace('__ID__', saved.id) : boot.urls.store, {
            method: updating ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(body),
        });
        const payload = await response.json();
        if (!response.ok) {
            errorBox.textContent = Object.values(payload.errors || {}).flat()[0] || payload.message || 'Could not save the report.';
            errorBox.classList.remove('d-none');
            return;
        }
        window.location.href = boot.urls.open.replace('__ID__', payload.report.id);
    }

    // Wire up controls
    control('dataset').addEventListener('change', () => { fillFieldControls(); schedule(); });
    $$('[data-cfg]').forEach((el) => {
        if (el.dataset.cfg === 'dataset') return;
        el.addEventListener('change', schedule);
    });
    $$('[data-cfg-mode], [data-cfg-chart]').forEach((el) => el.addEventListener('change', () => {
        if (lastResult && el.matches('[data-cfg-chart]') && mode() === 'summary') {
            lastResult = { ...lastResult, chart: el.value };
            draw(lastResult);
            schedule();
            return;
        }
        schedule();
    }));
    $('[data-columns]').addEventListener('change', schedule);
    $('[data-column-search]').addEventListener('input', (event) => {
        const term = event.target.value.toLowerCase();
        $$('[data-column-option]').forEach((option) => option.classList.toggle('d-none', !option.textContent.toLowerCase().includes(term)));
    });
    $('[data-add-filter]').addEventListener('click', () => filterRow());
    $('[data-run]').addEventListener('click', run);
    document.querySelector('[data-builder-reset]')?.addEventListener('click', () => { window.location.href = boot.urls.open.replace('?report=__ID__', '').replace('__ID__', ''); });
    $$('[data-template]', document).forEach((button) => button.addEventListener('click', () => {
        $('[data-filters]').innerHTML = '';
        saved = null;
        updateSavedUi();
        applyConfig(boot.templates[Number(button.dataset.template)]);
        run();
    }));

    const saveForm = document.querySelector('[data-save-form]');
    saveForm?.addEventListener('submit', (event) => { event.preventDefault(); save(false); });
    saveForm?.querySelector('[data-save-as-new]')?.addEventListener('click', () => save(true));
    document.getElementById('saveReportModal')?.addEventListener('show.bs.modal', () => {
        saveForm.querySelector('[data-save-error]').classList.add('d-none');
        saveForm.name.value = saved?.name || '';
        saveForm.description.value = saved?.description || '';
        saveForm.is_shared.checked = Boolean(saved?.is_shared);
        saveForm.pinned.checked = Boolean(saved?.pinned);
        saveForm.querySelector('[data-save-as-new]').classList.toggle('d-none', !saved?.owned);
        saveForm.querySelector('[data-save-submit]').textContent = saved?.owned ? 'Update' : 'Save';
    });
    $('[data-pin-toggle]').addEventListener('click', async () => {
        if (!saved?.owned) return;
        const response = await fetch(boot.urls.pin.replace('__ID__', saved.id), { method: 'PATCH', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
        if (response.ok) {
            saved.pinned = (await response.json()).report.pinned;
            updateSavedUi();
            window.Swal?.fire({ toast: true, position: 'top-end', timer: 2200, showConfirmButton: false, icon: 'success', title: saved.pinned ? 'Pinned to your dashboard' : 'Removed from your dashboard' });
        }
    });

    // Start
    window.Apex = { colors: PALETTE };
    const starter = boot.starter || {};
    if (saved?.config) {
        applyConfig(saved.config);
    } else if (starter.dataset && catalogue[starter.dataset]) {
        applyConfig({ dataset: starter.dataset, group_by: starter.group_by, chart: starter.chart || 'bar', preset: 'all' });
    } else {
        fillFieldControls();
        const defaults = { complaints: 'status', attendance: 'employee', employees: 'department' };
        const preferred = defaults[control('dataset').value];
        if (preferred && dataset().fields[preferred]) control('group_by').value = preferred;
    }
    updateSavedUi();
    run();
}
