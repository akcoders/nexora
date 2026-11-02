/**
 * Dashboard charts, pinned custom reports and the per-user widget layout.
 */
import { PALETTE, renderResult } from './report-chart.js';

const data = JSON.parse(document.getElementById('dashboardData')?.textContent || '{}');
const urls = data.urls || {};

function draw(selector, result, options = {}) {
    const el = document.querySelector(selector);
    if (!el || !result) return;
    renderResult(el, result, options);
}

function initCharts() {
    const charts = data.charts || {};
    if (charts.trend) {
        draw('#chartTrend', { chart: 'area', labels: charts.trend.labels, series: [{ name: 'Registered', data: charts.trend.registered }, { name: 'Closed', data: charts.trend.closed }] }, { height: 290 });
    }
    draw('#chartCallTypes', { chart: 'donut', labels: charts.callTypes?.labels, series: [{ name: 'Calls', data: charts.callTypes?.series || [] }] }, { height: 290 });
    draw('#chartStatus', { chart: 'bar', labels: charts.status?.labels, series: [{ name: 'Complaints', data: charts.status?.series || [] }] }, {
        height: 260,
        onClick: (index) => {
            const key = charts.status?.keys?.[index];
            if (key && urls.register) window.location.href = `${urls.register}?status=${encodeURIComponent(key)}`;
        },
    });
    draw('#chartTechnicians', { chart: 'hbar', labels: charts.technicians?.labels, series: [{ name: 'Open visits', data: charts.technicians?.series || [] }] }, { height: 260 });
    draw('#chartCustomers', { chart: 'hbar', labels: charts.customers?.labels, series: [{ name: 'Complaints', data: charts.customers?.series || [] }] }, { height: 280 });
    draw('#chartRootCauses', { chart: 'hbar', labels: charts.rootCauses?.labels, series: [{ name: 'Closed complaints', data: charts.rootCauses?.series || [] }] }, { height: 280 });
    if (charts.attendance) {
        draw('#chartAttendance', { chart: 'bar', labels: charts.attendance.labels, series: [{ name: 'Check-ins', data: charts.attendance.series }] }, { height: 250 });
    }
}

async function loadPinnedReports() {
    document.querySelectorAll('[data-pinned-report]').forEach(async (card) => {
        const body = card.querySelector('[data-report-body]');
        try {
            const response = await fetch(card.dataset.url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('failed');
            const payload = await response.json();
            const meta = payload.result.meta || {};
            card.querySelector('[data-report-meta]').textContent = [meta.period, meta.measure, meta.filters?.length ? `${meta.filters.length} filter(s)` : null].filter(Boolean).join(' · ');
            renderResult(body, payload.result, { height: 280 });
        } catch {
            body.innerHTML = '<div class="nx-empty py-4">This report could not be loaded.</div>';
        }
    });
}

function initCustomizer() {
    const panel = document.getElementById('dashboardCustomize');
    if (!panel) return;
    let timer = null;
    const save = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const hidden = [...panel.querySelectorAll('[data-widget-toggle]')].filter((input) => !input.checked).map((input) => input.value);
            const status = panel.querySelector('[data-save-status]');
            try {
                const response = await fetch(urls.preferences, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ hidden }),
                });
                if (status) status.textContent = response.ok ? 'Saved' : 'Could not save';
            } catch {
                if (status) status.textContent = 'Could not save';
            }
        }, 350);
    };
    panel.querySelectorAll('[data-widget-toggle]').forEach((input) => input.addEventListener('change', () => {
        document.querySelectorAll(`[data-widget="${input.value}"]`).forEach((widget) => { widget.dataset.hidden = input.checked ? '0' : '1'; });
        window.dispatchEvent(new Event('resize'));
        save();
    }));
    panel.querySelector('[data-widgets-reset]')?.addEventListener('click', () => {
        panel.querySelectorAll('[data-widget-toggle]').forEach((input) => {
            input.checked = true;
            input.dispatchEvent(new Event('change'));
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (!window.ApexCharts) return;
    window.Apex = { colors: PALETTE };
    initCharts();
    loadPinnedReports();
    initCustomizer();
});
