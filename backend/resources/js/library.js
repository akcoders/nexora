/**
 * Knowledge library pages (browse, guide reader, settings).
 *
 *  - Browse: filters submit as soon as a select or content pill changes (the Apply button is
 *    only needed without JavaScript), and the search box gets a working clear button.
 *  - Reader: the video playlist swaps the embedded player, an "On this page" list is built
 *    from the guide headings, and the print button is enabled.
 *  - Settings: the category modal switches between add and edit with a live preview of the
 *    icon and colour; the rename modal is pointed at the chosen entry.
 */

import { followViewport } from './library-layout';

const refreshIcons = () => window.refreshIcons?.();

const RESTORE_KEY = 'nx-library-restore';

const storage = {
    read() {
        try { return JSON.parse(window.sessionStorage.getItem(RESTORE_KEY) || 'null'); } catch { return null; }
    },
    write(value) {
        try { window.sessionStorage.setItem(RESTORE_KEY, JSON.stringify(value)); } catch { /* private mode */ }
    },
    clear() {
        try { window.sessionStorage.removeItem(RESTORE_KEY); } catch { /* private mode */ }
    },
};

/**
 * Filters reload the page. Remember where the reader was (scroll position and the control they
 * used) so the results do not jump back to the top and keyboard users keep their place.
 */
function rememberPosition(focusId = null) {
    storage.write({ path: window.location.pathname, y: Math.round(window.scrollY), focus: focusId, at: Date.now() });
}

function restorePosition() {
    const saved = storage.read();
    storage.clear();
    if (!saved || saved.path !== window.location.pathname || Date.now() - saved.at > 15000) return;
    window.scrollTo(0, Math.min(saved.y, document.documentElement.scrollHeight));
    const field = saved.focus ? document.getElementById(saved.focus) : null;
    // A dropdown inside the folded phone filter panel: unfold it so focus has somewhere to go.
    if (field?.closest('.lib-filterbar.is-collapsed')) document.querySelector('[data-lib-filter-toggle]')?.click();
    field?.focus({ preventScroll: true });
}

function initFilters() {
    const form = document.querySelector('[data-lib-filters]');
    if (!form) return;

    form.querySelectorAll('[data-lib-apply]').forEach((button) => { button.hidden = true; });

    // Empty filters (and the default sort) are left out of the address so shared links stay short.
    const isDefault = (field) => field.value === '' || (field.name === 'sort' && field.value === 'latest');
    form.addEventListener('submit', () => {
        const active = document.activeElement;
        rememberPosition(form.contains(active) && active.id && active.type !== 'search' ? active.id : null);
        form.querySelectorAll('input[name], select[name]').forEach((field) => {
            if ((field.type !== 'radio' || field.checked) && isDefault(field)) field.disabled = true;
        });
        form.setAttribute('aria-busy', 'true');
    });
    // Category tiles, filter chips and "Clear all" are plain links; keep the position for them too.
    document.querySelectorAll('.lib-cats a, .lib-chips a').forEach((link) => link.addEventListener('click', (event) => {
        if (!event.metaKey && !event.ctrlKey && !event.shiftKey) rememberPosition();
    }));
    window.addEventListener('pageshow', () => {
        form.querySelectorAll('[disabled]').forEach((field) => { field.disabled = false; });
        form.removeAttribute('aria-busy');
    });

    const submit = () => (form.requestSubmit ? form.requestSubmit() : form.submit());
    form.addEventListener('change', (event) => {
        if (event.target.matches('select, input[type="radio"]')) submit();
    });

    // Phones: fold the dropdown filters away behind a "Filters" button that shows how many are in use.
    const bar = form.querySelector('[data-lib-filterbar]');
    const toggle = form.querySelector('[data-lib-filter-toggle]');
    if (bar && toggle && window.matchMedia('(max-width: 767.98px)').matches) {
        toggle.hidden = false;
        const setOpen = (open) => {
            bar.classList.toggle('is-collapsed', !open);
            toggle.setAttribute('aria-expanded', String(open));
        };
        // Active filters are listed as removable chips above the results, so the panel can stay folded.
        setOpen(false);
        toggle.addEventListener('click', () => setOpen(bar.classList.contains('is-collapsed')));
    }

    // Bring the selected category tile into view in the scrolling row.
    const cats = document.querySelector('.lib-cats');
    const activeTile = cats?.querySelector('.lib-cat.is-active');
    if (cats && activeTile && cats.scrollWidth > cats.clientWidth) {
        cats.scrollLeft = Math.max(0, activeTile.offsetLeft - cats.offsetLeft - 14);
    }

    const search = form.querySelector('[data-lib-search]');
    if (search?.dataset.shortPlaceholder && window.matchMedia('(max-width: 575.98px)').matches) {
        search.placeholder = search.dataset.shortPlaceholder;
    }
    const clear = form.querySelector('[data-lib-clear]');
    if (search && clear) {
        search.addEventListener('input', () => { clear.hidden = search.value === ''; });
        clear.addEventListener('click', (event) => {
            event.preventDefault();
            search.value = '';
            submit();
        });
    }

    restorePosition();
}

function initPlayer() {
    const player = document.querySelector('[data-lib-player]');
    if (!player) return;
    const frame = player.querySelector('[data-lib-frame]');
    const now = player.querySelector('[data-lib-now]');
    const watch = player.querySelector('[data-lib-watch]');

    player.querySelectorAll('[data-lib-video]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (event.metaKey || event.ctrlKey || event.shiftKey) return;
            event.preventDefault();
            const src = new URL(link.dataset.libVideo);
            src.searchParams.set('autoplay', '1');
            frame.src = src.toString();
            frame.title = link.dataset.libTitle;
            if (now) now.textContent = link.dataset.libTitle;
            if (watch) watch.href = link.dataset.libWatchUrl;
            player.querySelectorAll('[data-lib-video]').forEach((other) => {
                other.classList.toggle('is-active', other === link);
                if (other === link) other.setAttribute('aria-current', 'true'); else other.removeAttribute('aria-current');
            });
            if (player.getBoundingClientRect().top < 0) player.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
}

function slugify(text) {
    return text.toLowerCase().normalize('NFKD').replace(/[^\w\s-]/g, '').trim().replace(/[\s_]+/g, '-').slice(0, 60) || 'section';
}

function initToc() {
    const prose = document.querySelector('[data-lib-prose]');
    const card = document.querySelector('[data-lib-toc-card]');
    const list = document.querySelector('[data-lib-toc]');
    if (!prose || !card || !list) return;

    const headings = [...prose.querySelectorAll('h1, h2, h3')].filter((heading) => heading.textContent.trim() !== '');
    if (headings.length < 2) return;
    const used = new Set();
    headings.forEach((heading) => {
        let id = `step-${slugify(heading.textContent)}`;
        for (let n = 2; used.has(id) || document.getElementById(id); n++) id = `step-${slugify(heading.textContent)}-${n}`;
        used.add(id);
        heading.id = id;
        heading.style.scrollMarginTop = 'calc(var(--nx-topbar) + 16px)';
        const item = document.createElement('li');
        if (heading.tagName === 'H3') item.className = 'is-sub';
        const link = document.createElement('a');
        link.href = `#${id}`;
        link.textContent = heading.textContent.trim();
        item.appendChild(link);
        list.appendChild(item);
    });
    card.hidden = false;
    document.querySelector('.lib-layout')?.classList.remove('is-single');
}

function initPrint() {
    document.querySelectorAll('[data-lib-print]').forEach((button) => {
        button.hidden = false;
        button.addEventListener('click', () => window.print());
    });
}

function initSettings() {
    const root = document.querySelector('[data-lib-settings]');
    if (!root) return;

    // Keep the open tab in the address so a redirect after saving comes back to it.
    root.querySelectorAll('[data-lib-tab]').forEach((tab) => {
        tab.addEventListener('shown.bs.tab', () => {
            const url = new URL(window.location.href);
            url.searchParams.set('tab', tab.dataset.libTab);
            window.history.replaceState(null, '', url);
        });
    });

    const modal = document.querySelector('[data-lib-category-modal]');
    if (modal) {
        const form = modal.querySelector('form');
        const method = form.querySelector('[data-lib-method]');
        const idField = form.querySelector('[data-lib-category-id]');
        const title = modal.querySelector('[data-lib-category-title]');
        const submitLabel = modal.querySelector('[data-lib-category-submit]');
        const preview = modal.querySelector('[data-lib-preview]');
        const previewIcon = modal.querySelector('[data-lib-preview-icon]');
        const previewLabel = modal.querySelector('[data-lib-preview-label]');
        const previewCount = modal.querySelector('[data-lib-preview-count]');
        const name = form.querySelector('[data-lib-preview-name]');

        const setIcon = (icon) => {
            previewIcon.innerHTML = `<i data-lucide="${icon.replace(/[^a-z0-9-]/g, '')}"></i>`;
            refreshIcons();
        };
        const setColor = (color) => {
            [...preview.classList].filter((name) => name.startsWith('tone-')).forEach((name) => preview.classList.remove(name));
            preview.classList.add(`tone-${color}`);
        };
        const check = (selector, value) => {
            const input = [...form.querySelectorAll(selector)].find((radio) => radio.value === value) ?? form.querySelector(selector);
            if (input) input.checked = true;
            return input?.value;
        };
        const clearErrors = () => {
            if (window.NxValidate?.clearAll) window.NxValidate.clearAll(form);
            form.querySelectorAll('.nx-server-errors').forEach((box) => box.remove());
        };

        root.querySelectorAll('[data-lib-category-add]').forEach((button) => button.addEventListener('click', () => {
            clearErrors();
            form.reset();
            form.action = modal.dataset.storeUrl;
            method.disabled = true;
            idField.value = '';
            form.querySelectorAll('input[name="name"], textarea[name="description"], input[name="sort_order"]').forEach((field) => { field.value = ''; });
            title.textContent = 'Add category';
            submitLabel.textContent = 'Add category';
            setIcon(check('[data-lib-icon]', 'book-open'));
            setColor(check('[data-lib-color]', 'primary'));
            previewLabel.textContent = 'Category name';
            previewCount.textContent = '0 guides';
        }));
        root.querySelectorAll('[data-lib-category-edit]').forEach((button) => button.addEventListener('click', () => {
            const data = JSON.parse(button.dataset.libCategoryEdit);
            clearErrors();
            form.action = data.action;
            method.disabled = false;
            idField.value = data.id;
            form.querySelector('input[name="name"]').value = data.name ?? '';
            form.querySelector('textarea[name="description"]').value = data.description ?? '';
            form.querySelector('input[name="sort_order"]').value = data.sort_order ?? '';
            title.textContent = 'Edit category';
            submitLabel.textContent = 'Save changes';
            setIcon(check('[data-lib-icon]', data.icon));
            setColor(check('[data-lib-color]', data.color));
            previewLabel.textContent = data.name || 'Category name';
            previewCount.textContent = `${data.count} ${data.count === 1 ? 'guide' : 'guides'}`;
        }));
        form.addEventListener('change', (event) => {
            if (event.target.matches('[data-lib-icon]')) setIcon(event.target.value);
            if (event.target.matches('[data-lib-color]')) setColor(event.target.value);
        });
        name?.addEventListener('input', () => { previewLabel.textContent = name.value.trim() || 'Category name'; });
    }

    const masterType = document.querySelector('[data-lib-master-type]');
    root.querySelectorAll('[data-lib-master-add]').forEach((button) => button.addEventListener('click', () => {
        if (masterType) masterType.value = button.dataset.libMasterAdd;
    }));

    const renameForm = document.querySelector('[data-lib-rename-form]');
    root.querySelectorAll('[data-lib-master-rename]').forEach((button) => button.addEventListener('click', () => {
        const data = JSON.parse(button.dataset.libMasterRename);
        if (window.NxValidate?.clearAll) window.NxValidate.clearAll(renameForm);
        renameForm.querySelectorAll('.nx-server-errors').forEach((box) => box.remove());
        renameForm.action = data.action;
        renameForm.querySelector('[data-lib-option-id]').value = data.id;
        renameForm.querySelector('[data-lib-option-tab]').value = data.tab;
        renameForm.querySelector('[data-lib-option-label]').value = data.label;
    }));
}

function boot() {
    initFilters();
    initPlayer();
    initToc();
    initPrint();
    initSettings();
    followViewport(document.querySelector('.lib-side'));
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
