/**
 * Classic data tables for every admin list.
 *
 * Any <table data-datatable> is upgraded with search, sorting, paging, column visibility
 * and Copy / Excel / CSV / PDF / Print exports. Options come from data attributes:
 *
 *   data-dt-title="Complaint register"   export file name / print heading
 *   data-dt-order='[[1,"desc"]]'          initial order
 *   data-dt-page-length="25"              rows per page (10, 25, 50, 100, All)
 *   data-dt-empty="No complaints found"   message when there are no rows
 *   data-dt-buttons="false"               hide the export toolbar
 *   data-dt-paging="false"                show every row (small tables)
 *   data-dt-search="false"                hide the search box
 *
 * On header cells: data-orderable="false" (no sorting), class "no-export" (left out of
 * exports, e.g. action buttons; kept visible as long as the first column), data-priority="2"
 * (lower numbers stay visible longer on small screens).
 * On body cells: data-order / data-search give the sort or search value (dates, amounts).
 * A column sorted through data-order stays left-aligned (header and cells) unless its header
 * asks for an alignment (text-end, text-center, dt-right, data-class-name …) or it shows plain
 * numbers or amounts, which stay right-aligned like every other numeric column.
 *
 * Placeholder rows such as <tr><td colspan="7">No data</td></tr> are removed before the
 * table starts, and their content becomes the empty-table message.
 */
import DataTable from 'datatables.net-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/buttons.html5.mjs';
import 'datatables.net-buttons/js/buttons.print.mjs';
import 'datatables.net-buttons/js/buttons.colVis.mjs';
import 'datatables.net-responsive-bs5';
import JSZip from 'jszip';

DataTable.Buttons.jszip(JSZip);

// Use DataTables' own layout classes instead of the Bootstrap grid (.row has negative
// gutters that pushed "Show … rows" outside the card). theme.css styles these classes.
DataTable.ext.classes.layout = {
    row: 'dt-layout-row',
    cell: 'dt-layout-cell',
    tableRow: 'dt-layout-table',
    tableCell: '',
    start: 'dt-layout-start',
    end: 'dt-layout-end',
    full: 'dt-layout-full',
};

let pdfMakeReady = null;

/** pdfmake (with fonts) is large, so it is only downloaded the first time someone exports a PDF. */
function ensurePdfMake() {
    pdfMakeReady ??= Promise.all([import('pdfmake/build/pdfmake'), import('pdfmake/build/vfs_fonts')]).then(([pdfMakeModule, fontsModule]) => {
        const pdfMake = pdfMakeModule.default ?? pdfMakeModule;
        const fonts = fontsModule.default ?? fontsModule;
        if (typeof pdfMake.addVirtualFileSystem === 'function') {
            pdfMake.addVirtualFileSystem(fonts);
        }
        DataTable.Buttons.pdfMake(pdfMake);

        return pdfMake;
    });

    return pdfMakeReady;
}

DataTable.ext.buttons.nxPdf = {
    extend: 'pdfHtml5',
    available: () => true,
    async: 50,
    action(event, dt, button, config, done) {
        ensurePdfMake()
            .then(() => DataTable.ext.buttons.pdfHtml5.action.call(this, event, dt, button, config, done))
            .catch(() => {
                done();
                window.Swal?.fire({ icon: 'error', title: 'PDF export failed', text: 'The PDF export could not be loaded. Please check your connection and try again.' });
            });
    },
};

const icon = (name) => `<i data-lucide="${name}" aria-hidden="true"></i>`;

function parseJson(value, fallback) {
    if (!value) return fallback;
    try {
        return JSON.parse(value);
    } catch {
        return fallback;
    }
}

function exportTitle(table) {
    return table.dataset.dtTitle || document.querySelector('.nx-page-title')?.textContent?.trim() || document.title;
}

function buttonsFor(table) {
    const title = exportTitle(table);
    const stamp = new Date().toLocaleString('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    // Exports leave out row actions and columns the user switched off, but keep columns that are
    // only hidden by default (th data-visible="false"), so the file still holds the full register.
    const exportOptions = { columns: (index, data, node) => !node.classList.contains('no-export') && (node.isConnected || node.dataset.visible === 'false') };
    const fileName = `${title} ${new Date().toISOString().slice(0, 10)}`.replace(/[\\/:*?"<>|]+/g, '-');
    const item = (iconName, label, hint) => `${icon(iconName)}<span class="dt-menu-text"><span>${label}</span><small>${hint}</small></span>`;

    return [
        {
            extend: 'collection',
            text: `${icon('download')}<span>Export</span>`,
            titleAttr: 'Export or print this list',
            className: 'btn-dt btn-dt-export',
            align: 'button-right',
            autoClose: true,
            fade: 0,
            popoverTitle: 'Export visible rows',
            buttons: [
                { extend: 'excelHtml5', text: item('file-spreadsheet', 'Excel', '.xlsx workbook'), className: 'dt-menu-item', exportOptions, title, filename: fileName },
                { extend: 'csvHtml5', text: item('file-text', 'CSV', 'Comma separated values'), className: 'dt-menu-item', exportOptions, filename: fileName },
                {
                    extend: 'nxPdf',
                    text: item('file-down', 'PDF', 'Printable A4 document'),
                    className: 'dt-menu-item',
                    exportOptions,
                    title,
                    filename: fileName,
                    orientation: table.querySelectorAll('thead th').length > 6 ? 'landscape' : 'portrait',
                    pageSize: 'A4',
                    messageTop: `Generated ${stamp}`,
                    customize(doc) {
                        doc.defaultStyle = { ...(doc.defaultStyle || {}), fontSize: 8 };
                        doc.styles.tableHeader = { ...(doc.styles.tableHeader || {}), fillColor: '#16233f', color: '#ffffff', fontSize: 8, bold: true };
                        doc.styles.title = { fontSize: 13, bold: true, color: '#16233f' };
                        doc.pageMargins = [24, 30, 24, 30];
                    },
                },
                { extend: 'print', text: item('printer', 'Print', 'Open the print dialog'), className: 'dt-menu-item', exportOptions, title, messageTop: `Generated ${stamp}`, autoPrint: true },
                { extend: 'copyHtml5', text: item('copy', 'Copy', 'Copy rows to clipboard'), className: 'dt-menu-item', exportOptions, title },
            ],
        },
        {
            extend: 'colvis',
            text: `${icon('columns-3')}<span>Columns</span>`,
            titleAttr: 'Show or hide columns',
            className: 'btn-dt btn-dt-columns',
            align: 'button-right',
            fade: 0,
            popoverTitle: 'Show columns',
            // Row actions (.no-export) and header-less columns have no label to toggle.
            columns: (index, data, node) => !node.matches('.no-colvis, .no-export') && node.textContent.trim() !== '',
            postfixButtons: [{ extend: 'colvisRestore', text: `${icon('rotate-ccw')}<span>Reset columns</span>`, className: 'dt-menu-reset' }],
        },
    ];
}

function removePlaceholderRows(table) {
    const columns = table.querySelectorAll('thead tr:last-child th').length;
    let emptyText = table.dataset.dtEmpty || '';
    table.querySelectorAll('tbody tr').forEach((row) => {
        const cells = row.children;
        if (cells.length === 1 && Number(cells[0].getAttribute('colspan') || 1) > 1 && columns > 1) {
            // Keep the markup (icon, title, hint) of rich empty states; it is server-rendered and escaped.
            emptyText ||= cells[0].innerHTML.trim();
            row.remove();
        }
    });

    emptyText ||= 'No records found';

    // Plain messages get the same icon-and-text empty state as the rich ones.
    return emptyText.includes('<') ? emptyText : `<div class="nx-empty py-4">${icon('inbox')}<div>${emptyText}</div></div>`;
}

const ALIGNMENT_CLASSES = ['text-start', 'text-end', 'text-center', 'dt-left', 'dt-right', 'dt-center', 'dt-head-left', 'dt-head-right', 'dt-head-center', 'dt-body-left', 'dt-body-right', 'dt-body-center'];
const asksForAlignment = (cell) => cell.hasAttribute('data-class-name') || ALIGNMENT_CLASSES.some((name) => cell.classList.contains(name));
// Cells that show a plain number or amount (1,250 · ₹1,250.00 · 12.5%) stay right-aligned like any numeric column.
const NUMBER_TEXT = /^[-+]?[₹$€£]?\s?[-+]?\d[\d,]*(\.\d+)?\s?%?$/;

/**
 * DataTables types a column from its data-order values, so complaint numbers, dates or priorities
 * sorted by a timestamp or rank become "numeric": right-aligned, with the sort arrow on the far side
 * of the label. Those columns are kept left-aligned (header and cells) unless the header or the
 * cells ask for an alignment themselves, or the cells show plain numbers.
 */
function leftAlignedColumns(table) {
    const headers = [...(table.tHead.rows[table.tHead.rows.length - 1]?.cells ?? [])];
    const rows = [...table.tBodies].flatMap((body) => [...body.rows]).filter((row) => row.cells.length === headers.length);

    return headers.flatMap((header, index) => {
        if (asksForAlignment(header)) return [];
        const cells = rows.map((row) => row.cells[index]).filter((cell) => cell.hasAttribute('data-order'));
        if (!cells.length || cells.some(asksForAlignment)) return [];
        const texts = cells.map((cell) => cell.textContent.trim()).filter((text) => !/^[—–-]?$/.test(text));

        return texts.length && texts.every((text) => NUMBER_TEXT.test(text)) ? [] : [index];
    });
}

/**
 * Buttons places its Export / Columns menus inside the table card, where the card's clipped overflow
 * cuts them off, and on phones it can push them past the left edge of the screen. Move the open
 * menu to <body> and place it under its button, inside the viewport. It opens upwards only when
 * there is clearly more room above, and its list scrolls when space is short.
 */
function placeMenu(button) {
    const menu = document.querySelector('div.dt-button-collection');
    if (!menu || !button?.isConnected) return;
    if (menu.parentElement !== document.body) document.body.appendChild(menu);

    const gap = 6;
    const margin = 8;
    const list = menu.querySelector('.dropdown-menu');
    menu.style.margin = '0';
    if (list) list.style.maxHeight = '';

    const rect = button.getBoundingClientRect();
    const below = window.innerHeight - rect.bottom - gap - margin;
    const above = rect.top - gap - margin;
    const openUp = menu.offsetHeight > below && above > below;
    const room = openUp ? above : below;
    if (list && menu.offsetHeight > room) {
        list.style.maxHeight = `${Math.max(160, list.offsetHeight - (menu.offsetHeight - room))}px`;
    }

    const width = menu.offsetWidth;
    const left = Math.min(Math.max(rect.right - width, margin), Math.max(margin, window.innerWidth - width - margin));
    const top = openUp ? rect.top - gap - menu.offsetHeight : rect.bottom + gap;
    menu.style.left = `${left + window.scrollX}px`;
    menu.style.top = `${top + window.scrollY}px`;
}

window.addEventListener('resize', () => placeMenu(document.querySelector('.buttons-collection[aria-expanded="true"]')));

/** Marks the container while every row fits on one page, so the pager can be hidden (theme.css). */
function markSinglePage(api) {
    api.table().container()?.classList.toggle('dt-single-page', api.page.info().pages <= 1);
}

function initTable(table) {
    if (table.dataset.dtReady || !table.tHead) return;
    table.dataset.dtReady = '1';
    const emptyText = removePlaceholderRows(table);
    const showButtons = table.dataset.dtButtons !== 'false';
    const paging = table.dataset.dtPaging !== 'false';
    const searching = table.dataset.dtSearch !== 'false';
    const pageLength = Number(table.dataset.dtPageLength || 25);
    const leftColumns = leftAlignedColumns(table);

    // One toolbar row: rows-per-page on the left; search, Export and Columns menus on the right.
    const tools = [searching ? 'search' : null, showButtons ? 'buttons' : null].filter(Boolean);
    const layout = {
        topStart: paging ? 'pageLength' : null,
        topEnd: tools.length ? tools : null,
        bottomStart: 'info',
        bottomEnd: paging ? 'paging' : null,
    };

    const instance = new DataTable(table, {
        layout,
        buttons: showButtons ? buttonsFor(table) : [],
        order: parseJson(table.dataset.dtOrder, []),
        paging,
        searching,
        pageLength,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
        autoWidth: false,
        responsive: table.dataset.dtResponsive === 'false' ? false : { details: { type: 'inline', target: 'tr' } },
        stateSave: false,
        // The first column and the row actions (.no-export) stay on screen; other columns
        // fold into the row details first, in data-priority order.
        columnDefs: [
            { targets: 'no-sort', orderable: false },
            { targets: 0, responsivePriority: 1 },
            { targets: 'no-export', responsivePriority: 1 },
            ...(leftColumns.length ? [{ targets: leftColumns, className: 'dt-left' }] : []),
        ],
        language: {
            search: '',
            searchPlaceholder: 'Search in this list…',
            lengthMenu: 'Show _MENU_ rows',
            info: 'Showing _START_–_END_ of _TOTAL_',
            infoEmpty: 'No records',
            infoFiltered: '(filtered from _MAX_)',
            zeroRecords: 'No matching records. Try a different search.',
            emptyTable: emptyText,
            paginate: { first: '«', last: '»', next: '›', previous: '‹' },
            buttons: { copyTitle: 'Copied', copySuccess: { _: '%d rows copied', 1: '1 row copied' }, colvisRestore: 'Reset columns' },
        },
        initComplete(settings) {
            markSinglePage(new DataTable.Api(settings));
            window.refreshIcons?.();
        },
        drawCallback(settings) {
            markSinglePage(new DataTable.Api(settings));
            window.refreshIcons?.();
        },
    });

    instance.on('buttons-action', (event, button, dt, node) => {
        window.refreshIcons?.();
        const element = node?.get?.(0) ?? node;
        if (element?.classList?.contains('buttons-collection')) placeMenu(element);
    });
    // Responsive first measures the columns before the row toggles add their left padding, so a
    // close fit can overflow the card by the toggle's width. Measure once more after the first paint.
    requestAnimationFrame(() => {
        if (table.classList.contains('collapsed')) instance.columns.adjust().responsive.recalc();
    });
    table.closest('.nx-datatable-card')?.classList.add('is-ready');
    table.dispatchEvent(new CustomEvent('nx:datatable-ready', { bubbles: true, detail: { table: instance } }));

    return instance;
}

export function initDataTables(root = document) {
    root.querySelectorAll('table[data-datatable]').forEach(initTable);
}

window.NxDataTable = { init: initDataTables, DataTable };

document.addEventListener('DOMContentLoaded', () => initDataTables());

// Tables inside tabs or collapsed panels need their widths recalculated once visible.
document.addEventListener('shown.bs.tab', () => DataTable.tables({ visible: true, api: true }).columns.adjust().responsive?.recalc?.());
document.addEventListener('shown.bs.collapse', () => DataTable.tables({ visible: true, api: true }).columns.adjust());
