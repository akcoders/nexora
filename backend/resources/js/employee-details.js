function initList(root) {
    if (root.dataset.detailReady) return;
    root.dataset.detailReady = '1';
    const rows = root.querySelector(':scope > [data-detail-rows]');
    const template = root.querySelector(':scope > [data-detail-template]');
    const head = root.querySelector(':scope > .nx-detail-list-head');
    const minimum = Number(root.dataset.minimum);
    const maximum = Number(root.dataset.maximum);
    let nextIndex = Number(root.dataset.nextIndex);
    const sync = () => {
        const count = rows.children.length;
        head.querySelector('[data-detail-count]').textContent = `${count} ${count === 1 ? 'entry' : 'entries'}`;
        head.querySelector('[data-detail-add]').disabled = count >= maximum;
        [...rows.children].forEach(row => { row.querySelector('[data-detail-remove]').disabled = count <= minimum; });
    };
    root.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button || button.closest('[data-detail-list]') !== root) return;
        if (button.hasAttribute('data-detail-add') && rows.children.length < maximum) {
            const fragment = document.createElement('template');
            fragment.innerHTML = template.innerHTML.replaceAll(root.dataset.indexToken, String(nextIndex++));
            const row = fragment.content.firstElementChild;
            rows.append(row);
            row.querySelectorAll('[data-detail-list]').forEach(initList);
            window.refreshIcons?.();
            sync();
            row.querySelector('input,select')?.focus();
        } else if (button.hasAttribute('data-detail-remove') && rows.children.length > minimum) {
            const row = button.closest('[data-detail-row]');
            const focusRow = row.nextElementSibling || row.previousElementSibling;
            row.remove();
            sync();
            (focusRow?.querySelector('input,select') || head.querySelector('[data-detail-add]')).focus();
        }
    });
    sync();
}

function init() {
    document.querySelectorAll('[data-detail-list]').forEach(initList);
    document.querySelectorAll('[data-detail-section]').forEach(root => {
        const toggle = root.querySelector('[data-detail-toggle]');
        const fields = root.querySelector('[data-detail-fields]');
        const sync = () => { fields.hidden = toggle.value !== '1'; fields.disabled = fields.hidden; };
        toggle.addEventListener('change', sync);
        sync();
    });
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
