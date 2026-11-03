/**
 * Application shell: collapsible sidebar (remembered per browser), collapsible menu
 * sections, menu search, mobile drawer, scrolling tab strips and the global search box (Ctrl / Cmd + K).
 */
const store = {
    get(key) {
        try {
            return window.localStorage.getItem(key);
        } catch {
            return null;
        }
    },
    set(key, value) {
        try {
            window.localStorage.setItem(key, value);
        } catch {
            // Private mode or blocked storage: the shell still works without remembering.
        }
    },
};

function initSidebar() {
    const sidebar = document.getElementById('nxSidebar');
    const backdrop = document.getElementById('nxSidebarBackdrop');
    if (!sidebar) return;

    const closeDrawer = () => {
        sidebar.classList.remove('open');
        backdrop?.classList.remove('show');
    };
    document.querySelector('[data-sidebar-open]')?.addEventListener('click', () => {
        sidebar.classList.add('open');
        backdrop?.classList.add('show');
    });
    document.querySelectorAll('[data-sidebar-close]').forEach((el) => el.addEventListener('click', closeDrawer));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeDrawer();
    });

    document.querySelector('[data-sidebar-collapse]')?.addEventListener('click', () => {
        const collapsed = document.body.classList.toggle('nx-sidebar-collapsed');
        store.set('nx:sidebar', collapsed ? 'collapsed' : 'expanded');
        window.dispatchEvent(new Event('resize'));
    });

    const collapsedSections = new Set(JSON.parse(store.get('nx:nav-collapsed') || '[]'));
    sidebar.querySelectorAll('[data-nav-section]').forEach((section) => {
        const key = section.dataset.navSection;
        const toggle = section.querySelector('[data-nav-toggle]');
        const hasActive = section.querySelector('.nx-nav-link.active');
        if (collapsedSections.has(key) && !hasActive) {
            section.classList.add('is-collapsed');
            toggle?.setAttribute('aria-expanded', 'false');
        }
        toggle?.addEventListener('click', () => {
            const collapsed = section.classList.toggle('is-collapsed');
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            collapsed ? collapsedSections.add(key) : collapsedSections.delete(key);
            store.set('nx:nav-collapsed', JSON.stringify([...collapsedSections]));
        });
    });

    const search = sidebar.querySelector('[data-sidebar-search]');
    const empty = sidebar.querySelector('[data-nav-empty]');
    search?.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        let visible = 0;
        sidebar.querySelectorAll('[data-nav-section]').forEach((section) => {
            let sectionVisible = 0;
            section.querySelectorAll('[data-nav-item]').forEach((item) => {
                const match = !term || item.textContent.toLowerCase().includes(term);
                item.classList.toggle('d-none', !match);
                if (match) sectionVisible++;
            });
            section.classList.toggle('d-none', sectionVisible === 0);
            if (term) section.classList.remove('is-collapsed');
            visible += sectionVisible;
        });
        empty.style.display = visible ? 'none' : 'block';
    });

    // After the icons render, so the menu has its final height.
    requestAnimationFrame(() => centerInScroller(sidebar.querySelector('.nx-nav'), sidebar.querySelector('.nx-nav-link.active'), 'y'));
}

/**
 * Scrolls only the given container (never the page, unlike scrollIntoView) so the item sits in its middle.
 */
function centerInScroller(scroller, item, axis, behavior = 'auto') {
    if (!scroller || !item) return;
    const box = scroller.getBoundingClientRect();
    const rect = item.getBoundingClientRect();
    if (axis === 'x') scroller.scrollBy({ left: rect.left - box.left - (box.width - rect.width) / 2, behavior });
    else scroller.scrollBy({ top: rect.top - box.top - (box.height - rect.height) / 2, behavior });
}

/** Tab strips that scroll sideways on phones keep the active tab in view (e.g. ?tab=closure). */
function initTabStrips() {
    const center = (strip, behavior) => centerInScroller(strip, strip.querySelector('.nav-link.active'), 'x', behavior);
    requestAnimationFrame(() => document.querySelectorAll('.nx-tabs-scroll').forEach((strip) => center(strip)));
    document.addEventListener('shown.bs.tab', (event) => {
        const strip = event.target.closest?.('.nx-tabs-scroll');
        if (strip) center(strip, 'smooth');
    });
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
}

function initGlobalSearch() {
    const wrap = document.querySelector('[data-global-search]');
    const input = wrap?.querySelector('[data-global-search-input]');
    const results = wrap?.querySelector('[data-global-search-results]');
    const url = document.querySelector('meta[name="nx-search-url"]')?.content;
    if (!wrap || !input || !results || !url) return;

    let timer = null;
    let controller = null;
    let activeIndex = -1;

    const hide = () => {
        results.classList.add('d-none');
        activeIndex = -1;
    };
    const items = () => [...results.querySelectorAll('.nx-search-item')];
    const highlight = (index) => {
        const list = items();
        list.forEach((el, i) => el.classList.toggle('is-active', i === index));
        list[index]?.scrollIntoView({ block: 'nearest' });
        activeIndex = index;
    };

    const render = (payload) => {
        const groups = payload.groups || [];
        if (!groups.length) {
            results.innerHTML = `<div class="nx-search-empty">No results for “${escapeHtml(input.value.trim())}”.</div>`;
        } else {
            results.innerHTML = groups.map((group) => `
                <div class="nx-search-group">${escapeHtml(group.label)}</div>
                ${group.items.map((item) => `<a class="nx-search-item" href="${escapeHtml(item.url)}" role="option"><i data-lucide="${escapeHtml(item.icon || 'circle')}"></i><span class="min-w-0"><span class="d-block text-truncate fw-semibold">${escapeHtml(item.title)}</span><small class="text-truncate">${escapeHtml(item.subtitle || '')}</small></span></a>`).join('')}
            `).join('');
        }
        results.classList.remove('d-none');
        window.refreshIcons?.();
    };

    input.addEventListener('input', () => {
        clearTimeout(timer);
        const term = input.value.trim();
        if (term.length < 2) {
            hide();
            return;
        }
        timer = setTimeout(async () => {
            controller?.abort();
            controller = new AbortController();
            try {
                const response = await fetch(`${url}?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
                if (response.ok) render(await response.json());
            } catch (error) {
                if (error.name !== 'AbortError') hide();
            }
        }, 220);
    });

    input.addEventListener('keydown', (event) => {
        const list = items();
        if (event.key === 'ArrowDown' && list.length) {
            event.preventDefault();
            highlight(Math.min(activeIndex + 1, list.length - 1));
        } else if (event.key === 'ArrowUp' && list.length) {
            event.preventDefault();
            highlight(Math.max(activeIndex - 1, 0));
        } else if (event.key === 'Enter' && list[activeIndex]) {
            event.preventDefault();
            window.location.href = list[activeIndex].href;
        } else if (event.key === 'Escape') {
            hide();
            input.blur();
        }
    });

    document.addEventListener('click', (event) => {
        if (!wrap.contains(event.target)) hide();
    });
    document.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            input.focus();
            input.select();
        }
    });
}

/** Forms with data-confirm="Question?" ask before submitting (used for deletes). */
function initConfirmations() {
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        const answer = window.Swal
            ? (await window.Swal.fire({ icon: 'warning', title: 'Are you sure?', text: form.dataset.confirm, showCancelButton: true, confirmButtonText: 'Yes, continue', cancelButtonText: 'Cancel', confirmButtonColor: '#b91c1c' })).isConfirmed
            : window.confirm(form.dataset.confirm);
        if (answer) {
            form.dataset.confirmed = '1';
            form.requestSubmit ? form.requestSubmit() : form.submit();
        }
    }, true);
}

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initTabStrips();
    initGlobalSearch();
    initConfirmations();
});
