function initObserverPicker(root) {
    const toggle = root.querySelector('[data-observer-toggle]');
    const panel = root.querySelector('[data-observer-panel]');
    const search = root.querySelector('[data-observer-search]');
    const options = [...root.querySelectorAll('[data-observer-option]')];
    const close = () => { panel.hidden = true; toggle.setAttribute('aria-expanded', 'false'); };
    const sync = () => {
        const selected = options.filter(option => option.querySelector('input').checked);
        root.querySelector('[data-observer-summary]').textContent = selected.length ? `${selected.length} observer${selected.length === 1 ? '' : 's'} selected` : 'Choose observers';
        const selection = root.querySelector('[data-observer-selection]');
        selection.replaceChildren();
        for (const option of selected.slice(0, 4)) selection.append(option.querySelector('.nx-person-avatar').cloneNode(true));
        options.forEach(option => option.classList.toggle('is-selected', option.querySelector('input').checked));
    };
    toggle.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        toggle.setAttribute('aria-expanded', String(!panel.hidden));
        if (!panel.hidden) search.focus();
    });
    root.addEventListener('change', sync);
    search.addEventListener('input', () => {
        const term = search.value.trim().toLocaleLowerCase();
        options.forEach(option => { option.hidden = !option.dataset.search.includes(term); });
        root.querySelector('[data-observer-empty]').hidden = options.some(option => !option.hidden);
    });
    root.querySelector('[data-observer-clear]').addEventListener('click', () => {
        options.forEach(option => { option.querySelector('input').checked = false; });
        sync();
    });
    root.querySelector('[data-observer-done]').addEventListener('click', () => { close(); toggle.focus(); });
    root.addEventListener('keydown', event => { if (event.key === 'Escape') { event.preventDefault(); close(); toggle.focus(); } });
    document.addEventListener('click', event => { if (!root.contains(event.target)) close(); });
    sync();
}

function initDateTimePicker(root) {
    const date = root.querySelector('[data-schedule-date]');
    const time = root.querySelector('[data-schedule-time]');
    const value = root.querySelector('[data-schedule-value]');
    const summary = root.querySelector('[data-schedule-summary]');
    const sync = () => {
        time.disabled = !date.value;
        date.setCustomValidity(date.value && date.value < date.min ? 'Choose today or a later date.' : '');
        value.value = date.value ? `${date.value}T${time.value || '09:00'}` : '';
        const scheduled = value.value ? new Date(value.value) : null;
        summary.textContent = scheduled && !Number.isNaN(scheduled.getTime())
            ? scheduled.toLocaleString('en-IN', { day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' })
            : 'Choose today or a later date';
        root.querySelector('[data-schedule-clear]').hidden = !date.value;
    };
    root.closest('form')?.addEventListener('submit', event => {
        sync();
        if (!date.matches(':disabled') && date.value && date.value < date.min) {
            event.preventDefault();
            event.stopImmediatePropagation();
            date.reportValidity();
        }
    }, true);
    date.addEventListener('input', sync);
    time.addEventListener('input', sync);
    root.querySelector('[data-schedule-clear]').addEventListener('click', () => { date.value = ''; sync(); date.dispatchEvent(new Event('change', { bubbles: true })); date.focus(); });
    sync();
}

const init = () => {
    document.querySelectorAll('[data-observer-picker]').forEach(initObserverPicker);
    document.querySelectorAll('[data-date-time-picker]').forEach(initDateTimePicker);
};
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
