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
 * exports, e.g. action buttons), data-priority="1" (kept visible on small screens).
 * On body cells: data-order / data-search give the sort or search value (dates, amounts).
 *
 * Placeholder rows such as <tr><td colspan="7">No data</td></tr> are removed before the
 * table starts, and their text becomes the empty-table message.
 */
import DataTable from 'datatables.net-bs5';
import 'datatables.net-buttons-bs5';
import 'datatables.net-buttons/js/buttons.html5.mjs';
import 'datatables.net-buttons/js/buttons.print.mjs';
import 'datatables.net-buttons/js/buttons.colVis.mjs';
import 'datatables.net-responsive-bs5';
import JSZip from 'jszip';

DataTable.Buttons.jszip(JSZip);

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
    const exportOptions = { columns: ':visible:not(.no-export)' };
    const fileName = `${title} ${new Date().toISOString().slice(0, 10)}`.replace(/[\\/:*?"<>|]+/g, '-');

    return [
        { extend: 'copyHtml5', text: `${icon('copy')}<span>Copy</span>`, titleAttr: 'Copy to clipboard', className: 'btn-dt', exportOptions, title },
        { extend: 'excelHtml5', text: `${icon('file-spreadsheet')}<span>Excel</span>`, titleAttr: 'Download Excel', className: 'btn-dt', exportOptions, title, filename: fileName },
        { extend: 'csvHtml5', text: `${icon('file-text')}<span>CSV</span>`, titleAttr: 'Download CSV', className: 'btn-dt', exportOptions, filename: fileName },
        {
            extend: 'nxPdf',
            text: `${icon('file-down')}<span>PDF</span>`,
            titleAttr: 'Download PDF',
            className: 'btn-dt',
            exportOptions,
            title,
            filename: fileName,
            orientation: table.querySelectorAll('thead th').length > 6 ? 'landscape' : 'portrait',
            pageSize: 'A4',
            messageTop: `Generated ${stamp}`,
            customize(doc) {
                doc.defaultStyle = { ...(doc.defaultStyle || {}), fontSize: 8 };
                doc.styles.tableHeader = { ...(doc.styles.tableHeader || {}), fillColor: '#1b2a4a', color: '#ffffff', fontSize: 8, bold: true };
                doc.styles.title = { fontSize: 13, bold: true, color: '#1b2a4a' };
                doc.pageMargins = [24, 30, 24, 30];
            },
        },
        {
            extend: 'print',
            text: `${icon('printer')}<span>Print</span>`,
            titleAttr: 'Print',
            className: 'btn-dt',
            exportOptions,
            title,
            messageTop: `Generated ${stamp}`,
            autoPrint: true,
        },
        {
            extend: 'colvis',
            text: `${icon('columns-3')}<span>Columns</span>`,
            titleAttr: 'Show or hide columns',
            className: 'btn-dt',
            columns: ':not(.no-colvis)',
            postfixButtons: ['colvisRestore'],
        },
    ];
}

function removePlaceholderRows(table) {
    const columns = table.querySelectorAll('thead tr:last-child th').length;
    let emptyText = table.dataset.dtEmpty || '';
    table.querySelectorAll('tbody tr').forEach((row) => {
        const cells = row.children;
        if (cells.length === 1 && Number(cells[0].getAttribute('colspan') || 1) > 1 && columns > 1) {
            emptyText ||= cells[0].textContent.trim();
            row.remove();
        }
    });

    return emptyText || 'No records found';
}

function initTable(table) {
    if (table.dataset.dtReady || !table.tHead) return;
    table.dataset.dtReady = '1';
    const emptyText = removePlaceholderRows(table);
    const showButtons = table.dataset.dtButtons !== 'false';
    const paging = table.dataset.dtPaging !== 'false';
    const searching = table.dataset.dtSearch !== 'false';
    const pageLength = Number(table.dataset.dtPageLength || 25);

    const layout = {
        topStart: paging ? 'pageLength' : null,
        topEnd: searching ? 'search' : null,
        bottomStart: 'info',
        bottomEnd: paging ? 'paging' : null,
    };
    if (showButtons) {
        layout.top = 'buttons';
    }

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
        columnDefs: [{ targets: 'no-sort', orderable: false }],
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
            buttons: { copyTitle: 'Copied', copySuccess: { _: '%d rows copied', 1: '1 row copied' }, colvisRestore: 'Show all columns' },
        },
        initComplete() {
            window.refreshIcons?.();
        },
        drawCallback() {
            window.refreshIcons?.();
        },
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
