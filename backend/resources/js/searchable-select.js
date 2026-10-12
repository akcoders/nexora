/**
 * Searchable dropdown for <select data-searchable>.
 *
 * The native <select> stays inside the form (visually hidden, not display:none)
 * so submission, drafts and the validation engine keep working on it. The
 * widget mirrors its options, disabled state and is-invalid/is-valid classes,
 * and fires a bubbling `change` on the select when the user picks an option.
 * Changing the select's options or `disabled` from code re-renders the widget.
 * `data-locked` greys the widget out without disabling the select itself, so
 * it is still submitted and validated (used for "pick a state first").
 *
 *   <select class="form-select" name="state" data-searchable data-placeholder="Select state">
 */

const widgets = new WeakMap();
let uid = 0;

const normalise = (text) => String(text ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

function placeholderOf(select) {
    return select.dataset.placeholder || [...select.options].find((o) => o.value === '')?.text || 'Select';
}

function labelOf(select, wrap) {
    if (select.id) {
        const label = document.querySelector(`label[for="${CSS.escape(select.id)}"]`);
        if (label) return label.textContent;
    }
    return wrap.parentElement?.querySelector(':scope > label, :scope > .form-label')?.textContent ?? select.dataset.placeholder ?? '';
}

/** Appends `text` to `parent`, wrapping the first occurrence of the query in <mark>. */
function appendHighlighted(parent, text, query) {
    const index = query ? text.toLowerCase().indexOf(query.toLowerCase()) : -1;
    if (index < 0) {
        parent.append(text);
        return;
    }
    const mark = document.createElement('mark');
    mark.textContent = text.slice(index, index + query.length);
    parent.append(text.slice(0, index), mark, text.slice(index + query.length));
}

function build(select) {
    const id = `nx-combo-${++uid}`;
    const wrap = document.createElement('div');
    wrap.className = 'nx-combo';
    select.parentNode.insertBefore(wrap, select);

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'form-select nx-combo-toggle';
    toggle.setAttribute('aria-haspopup', 'listbox');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-controls', `${id}-panel`);
    const value = document.createElement('span');
    value.className = 'nx-combo-value';
    toggle.append(value);

    const panel = document.createElement('div');
    panel.className = 'nx-combo-panel';
    panel.id = `${id}-panel`;
    panel.hidden = true;
    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'form-control form-control-sm nx-combo-search';
    search.placeholder = select.dataset.searchPlaceholder || 'Type to search…';
    search.autocomplete = 'off';
    search.spellcheck = false;
    search.setAttribute('data-v-skip', '');
    search.setAttribute('role', 'combobox');
    search.setAttribute('aria-autocomplete', 'list');
    search.setAttribute('aria-controls', `${id}-list`);
    const list = document.createElement('ul');
    list.className = 'nx-combo-list';
    list.id = `${id}-list`;
    list.setAttribute('role', 'listbox');
    panel.append(search, list);

    // The select goes last so validation messages (inserted after it) sit under the widget.
    wrap.append(toggle, panel, select);
    select.classList.add('nx-combo-native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    const ariaLabel = labelOf(select, wrap).replace('*', '').trim();
    if (ariaLabel) {
        toggle.setAttribute('aria-label', ariaLabel);
        search.setAttribute('aria-label', `Search ${ariaLabel.toLowerCase()}`);
    }

    let items = [];
    let active = -1;

    const options = () => [...select.options].filter((o) => o.value !== '' && !o.hidden);

    function sync() {
        const chosen = select.selectedOptions[0];
        const hasValue = Boolean(chosen && chosen.value !== '');
        value.textContent = hasValue ? chosen.text : placeholderOf(select);
        toggle.classList.toggle('nx-combo-empty', !hasValue);
        const locked = select.disabled || select.hasAttribute('data-locked');
        toggle.disabled = locked;
        toggle.title = !select.disabled && locked ? (select.dataset.locked || '') : '';
        toggle.classList.toggle('is-invalid', select.classList.contains('is-invalid'));
        toggle.classList.toggle('is-valid', select.classList.contains('is-valid'));
        if (locked) close(false);
        if (!panel.hidden) render();
    }

    function setActive(index) {
        const nodes = list.querySelectorAll('[role="option"]');
        if (!nodes.length) {
            active = -1;
            search.removeAttribute('aria-activedescendant');
            return;
        }
        active = Math.max(0, Math.min(index, nodes.length - 1));
        nodes.forEach((node, i) => node.classList.toggle('active', i === active));
        search.setAttribute('aria-activedescendant', nodes[active].id);
        nodes[active].scrollIntoView({ block: 'nearest' });
    }

    function render() {
        const query = search.value.trim();
        const needle = normalise(query);
        const all = options();
        items = needle
            ? all.filter((o) => normalise(o.text).includes(needle))
                .sort((a, b) => Number(!normalise(a.text).startsWith(needle)) - Number(!normalise(b.text).startsWith(needle)))
            : all;

        list.replaceChildren(...items.map((option, i) => {
            const li = document.createElement('li');
            li.id = `${id}-option-${i}`;
            li.setAttribute('role', 'option');
            li.setAttribute('aria-selected', String(option.selected));
            li.className = option.selected ? 'selected' : '';
            if (option.disabled) li.setAttribute('aria-disabled', 'true');
            appendHighlighted(li, option.text, query);
            li.addEventListener('mousedown', (e) => e.preventDefault());
            li.addEventListener('click', () => choose(option));
            return li;
        }));

        if (!items.length) {
            const empty = document.createElement('li');
            empty.className = 'nx-combo-empty-state';
            empty.textContent = all.length ? 'No matches found' : (select.dataset.emptyText || 'No options available');
            list.append(empty);
        }
        const selectedIndex = items.findIndex((o) => o.selected);
        setActive(needle ? 0 : Math.max(selectedIndex, 0));
    }

    function position() {
        const rect = toggle.getBoundingClientRect();
        const below = window.innerHeight - rect.bottom;
        const up = below < 280 && rect.top > below;
        panel.style.left = `${rect.left}px`;
        panel.style.width = `${rect.width}px`;
        panel.style.top = up ? '' : `${rect.bottom + 4}px`;
        panel.style.bottom = up ? `${window.innerHeight - rect.top + 4}px` : '';
        panel.classList.toggle('nx-combo-up', up);
    }

    function onOutside(e) {
        if (!wrap.contains(e.target)) close(false);
    }

    function open(seed = '') {
        if (toggle.disabled || !panel.hidden) return;
        panel.hidden = false;
        wrap.classList.add('open');
        toggle.setAttribute('aria-expanded', 'true');
        search.value = seed;
        position();
        render();
        search.focus({ preventScroll: true });
        document.addEventListener('pointerdown', onOutside, true);
        window.addEventListener('scroll', position, true);
        window.addEventListener('resize', position);
    }

    function close(refocus = true) {
        if (panel.hidden) return;
        panel.hidden = true;
        wrap.classList.remove('open');
        toggle.setAttribute('aria-expanded', 'false');
        document.removeEventListener('pointerdown', onOutside, true);
        window.removeEventListener('scroll', position, true);
        window.removeEventListener('resize', position);
        if (refocus) toggle.focus({ preventScroll: true });
    }

    function choose(option) {
        if (!option || option.disabled) return;
        const changed = select.value !== option.value;
        select.value = option.value;
        close();
        sync();
        if (changed) {
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    toggle.addEventListener('click', () => (panel.hidden ? open() : close()));
    toggle.addEventListener('keydown', (e) => {
        if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) {
            e.preventDefault();
            open();
        } else if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
            e.preventDefault();
            open(e.key);
        }
    });
    search.addEventListener('input', render);
    search.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowDown') { e.preventDefault(); setActive(active + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive(active - 1); }
        else if (e.key === 'Home' && !search.value) { e.preventDefault(); setActive(0); }
        else if (e.key === 'End' && !search.value) { e.preventDefault(); setActive(items.length - 1); }
        else if (e.key === 'Enter') { e.preventDefault(); choose(items[active]); }
        else if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
        else if (e.key === 'Tab') close(false);
    });
    // Validation focuses the native select on error; hand focus to the visible control.
    select.addEventListener('focus', () => toggle.focus());
    select.addEventListener('change', sync);
    select.form?.addEventListener('reset', () => window.setTimeout(sync, 0));
    new MutationObserver(sync).observe(select, { attributes: true, attributeFilter: ['disabled', 'class', 'data-locked'], childList: true, subtree: true });

    sync();
    return { refresh: sync, open, close };
}

/** Turns a <select> into a searchable dropdown (idempotent) and returns its controller. */
export function enhance(select) {
    if (!(select instanceof HTMLSelectElement) || select.multiple) return null;
    if (!widgets.has(select)) widgets.set(select, build(select));
    return widgets.get(select);
}

function scan(root) {
    if (root.matches?.('select[data-searchable]')) enhance(root);
    root.querySelectorAll?.('select[data-searchable]').forEach(enhance);
}

function start() {
    scan(document.body);
    new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
        if (node.nodeType === 1) scan(node);
    }))).observe(document.body, { childList: true, subtree: true });
    window.NxSearchableSelect = { enhance };
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
