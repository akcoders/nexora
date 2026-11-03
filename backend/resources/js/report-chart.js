/**
 * Shared ApexCharts renderer for dashboard widgets and the report builder.
 * Takes a report result ({ chart, labels, series, columns, rows }) and draws it.
 */
export const PALETTE = ['#1f5fbf', '#0f8b8d', '#e0a100', '#c2410c', '#6d28d9', '#15803d', '#b91c1c', '#475569', '#0369a1', '#9d174d', '#4d7c0f', '#7c2d12'];

const BASE = {
    chart: { fontFamily: 'Inter, system-ui, sans-serif', toolbar: { show: true, tools: { download: true, selection: false, zoom: false, zoomin: false, zoomout: false, pan: false, reset: false } }, animations: { speed: 300 }, parentHeightOffset: 0 },
    colors: PALETTE,
    dataLabels: { enabled: false },
    grid: { borderColor: '#edf0f5', strokeDashArray: 4, padding: { left: 6, right: 10 } },
    legend: { position: 'bottom', fontSize: '12px', markers: { size: 6 } },
    tooltip: { theme: 'light' },
    noData: { text: 'No data for this selection', style: { color: '#94a3b8', fontSize: '13px' } },
};

const number = (value) => (typeof value === 'number' ? value.toLocaleString('en-IN', { maximumFractionDigits: 2 }) : value);

export function chartOptions(result, { height = 300, width = 0, onClick = null, toolbar = true } = {}) {
    const type = result.chart || 'bar';
    const labels = result.labels || [];
    const series = result.series || [];
    const events = onClick ? { dataPointSelection: (event, ctx, config) => onClick(config.dataPointIndex, config.seriesIndex) } : {};
    const chart = { ...BASE.chart, toolbar: { ...BASE.chart.toolbar, show: toolbar }, height, events };

    if (type === 'pie' || type === 'donut') {
        const first = series[0]?.data || [];
        return {
            ...BASE,
            chart: { ...chart, type },
            series: first,
            labels,
            legend: { ...BASE.legend, position: 'bottom', horizontalAlign: 'center' },
            // Thin slices keep their percentage in the tooltip instead of an overlapping label.
            plotOptions: { pie: { dataLabels: { minAngleToShowLabel: 25 }, donut: { size: '68%', labels: { show: type === 'donut', total: { show: true, label: 'Total', formatter: (w) => number(w.globals.seriesTotals.reduce((a, b) => a + b, 0)) } } } } },
            dataLabels: { enabled: true, formatter: (val) => `${val.toFixed(0)}%`, dropShadow: { enabled: false } },
            tooltip: { y: { formatter: number } },
        };
    }

    const horizontal = type === 'hbar';
    const stacked = type === 'stacked';
    const apexType = type === 'line' ? 'line' : type === 'area' ? 'area' : 'bar';

    // Counts get whole-number ticks (no "0.5 visits"); small counts step by one.
    const values = series.flatMap((serie) => serie.data || []).map(Number);
    const whole = values.every(Number.isInteger);
    const totals = stacked ? labels.map((_, index) => series.reduce((sum, serie) => sum + (Number(serie.data?.[index]) || 0), 0)) : values;
    const max = Math.max(0, ...totals);
    const stepSize = whole && max <= 5 ? 1 : undefined;
    const tick = (val) => (whole && typeof val === 'number' ? number(Math.round(val)) : number(val));
    // Long date series show level labels about every 80px instead of a rotated fringe.
    const dense = !horizontal && labels.length > 12;
    const tickAmount = dense ? Math.max(2, Math.min(8, Math.floor((width || 640) / 80))) : undefined;
    // Bars keep a steady thickness: one or two categories get slim bars, not blocks filling the plot.
    const slot = (horizontal ? height - 60 : (width || 640) - 70) / Math.max(1, labels.length);
    const groups = stacked ? 1 : Math.max(1, series.length);
    const barSize = (target, cap) => `${Math.round(Math.max(4, Math.min(cap, (target * groups * 100) / Math.max(1, slot))))}%`;
    // Weekday labels ("Wed 28") that don't fit side by side (phones) show the day number; the tooltip keeps the full label.
    const dayOnly = !horizontal && !dense && slot < 46 && labels.length > 0 && labels.every((label) => /^(Mon|Tue|Wed|Thu|Fri|Sat|Sun) \d{1,2}$/.test(label));
    const dayLabels = dayOnly ? { formatter: (val) => String(val).slice(4) } : {};

    return {
        ...BASE,
        chart: { ...chart, type: apexType, stacked },
        series: series.map((serie) => ({ name: serie.name, data: serie.data })),
        xaxis: {
            categories: labels,
            tickAmount,
            stepSize: horizontal ? stepSize : undefined,
            // Full category names: rotated when they collide rather than cut to "Under observat…".
            labels: { trim: false, hideOverlappingLabels: true, rotate: -45, ...(dense ? { rotate: 0, rotateAlways: false } : {}), ...(horizontal ? { formatter: tick } : dayLabels), style: { fontSize: '11px' } },
            axisBorder: { show: false },
            axisTicks: { show: false },
        },
        yaxis: { stepSize: horizontal ? undefined : stepSize, labels: { formatter: (val) => (horizontal ? val : tick(val)), style: { fontSize: '11px' }, maxWidth: 180 } },
        plotOptions: { bar: { horizontal, borderRadius: 3, columnWidth: barSize(32, 55), barHeight: barSize(20, 62), distributed: false } },
        stroke: { curve: 'smooth', width: apexType === 'bar' ? 0 : 3 },
        fill: apexType === 'area' ? { type: 'gradient', gradient: { opacityFrom: 0.3, opacityTo: 0.03 } } : { opacity: 1 },
        markers: { size: apexType === 'line' ? 4 : 0 },
        legend: { ...BASE.legend, show: series.length > 1 },
        tooltip: { y: { formatter: number }, ...(dayOnly ? { x: { formatter: (val, { dataPointIndex } = {}) => labels[dataPointIndex] ?? val } } : {}) },
    };
}

export function renderTable(container, result) {
    const columns = result.columns || [];
    const rows = result.rows || [];
    const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
    container.innerHTML = `<div class="table-responsive"><table class="table nx-table nx-table-sm mb-0"><thead><tr>${columns.map((column) => `<th>${escape(column)}</th>`).join('')}</tr></thead><tbody>${rows.length ? rows.map((row) => `<tr>${row.map((cell) => `<td class="${typeof cell === 'number' ? 'text-end notranslate' : ''}">${escape(typeof cell === 'number' ? number(cell) : cell)}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${columns.length || 1}" class="text-center text-secondary py-4">No data for this selection</td></tr>`}</tbody>${result.total !== null && result.total !== undefined && result.mode === 'summary' ? `<tfoot><tr><th>Total</th>${columns.slice(1).map((_, index) => `<th class="text-end notranslate">${index === columns.length - 2 ? escape(number(result.total)) : ''}</th>`).join('')}</tr></tfoot>` : ''}</table></div>`;
}

export function renderResult(container, result, options = {}) {
    container._nxChart?.destroy?.();
    container.innerHTML = '';
    if (result.chart === 'table' || result.mode === 'list') {
        renderTable(container, result);
        return null;
    }
    const chart = new window.ApexCharts(container, chartOptions(result, { width: container.clientWidth, ...options }));
    chart.render();
    container._nxChart = chart;
    return chart;
}
