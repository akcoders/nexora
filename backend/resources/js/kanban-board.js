/**
 * Interactive Kanban board for internal workflows and customer tickets.
 *
 *   <section data-kanban data-feed-url="/tasks/board"> … <div data-board></div> </section>
 *
 * Cards are loaded as JSON from the feed URL and grouped client-side by owner, status or
 * priority. Dropping a card on another lane maps onto the existing task rules:
 *
 *  - owner lane    → "assign" action (hand-over to that person, remark mandatory)
 *  - Closed lane   → "close" action (remark mandatory)
 *  - Closed → open → reopen by assigning again (only users with tasks.update)
 *  - priority lane → priority change (logged on the timeline, note optional)
 *
 * Dragging uses pointer events so it works with a mouse and with touch (long-press to pick
 * a card up). Every move is also reachable from the card's "…" menu for keyboard users.
 */

const PRIORITIES = [
    { key: 'very_high', label: 'Very high', icon: 'flame', tone: '#dc2626' },
    { key: 'high', label: 'High', icon: 'flag', tone: '#d97706' },
    { key: 'normal', label: 'Normal', icon: 'flag', tone: '#2563eb' },
];
const PRIORITY_LABEL = Object.fromEntries(PRIORITIES.map((p) => [p.key, p.label]));
const MODES = ['owner', 'status', 'priority'];
const QUICK = ['mine', 'watching', 'created', 'overdue'];
const HINTS = {
    owner: 'Drag a card to another owner to hand it over, or onto Closed to finish it. A remark is always required.',
    status: 'Drag a card onto Closed to finish it. Users who can update tasks may drag closed cards back to reopen them.',
    priority: 'Drag an active card between lanes to change its priority. The change is recorded on the timeline.',
};
const REFRESH_MS = 60_000;
const TOUCH_HOLD_MS = 220;

const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
const initials = (name) => String(name ?? '?').trim().split(/\s+/).slice(0, 2).map((part) => part[0] ?? '').join('').toUpperCase() || '?';
const isToday = (iso) => iso && new Date(iso).toDateString() === new Date().toDateString();

function readState(root) {
    const params = new URLSearchParams(window.location.search);
    let storedMode = null;
    try { storedMode = window.localStorage.getItem('nx.kanban.mode'); } catch { /* storage unavailable */ }
    const mode = MODES.includes(params.get('group')) ? params.get('group') : (MODES.includes(storedMode) ? storedMode : 'owner');

    return {
        feedUrl: root.dataset.feedUrl,
        mode,
        type: ['workflow', 'ticket'].includes(params.get('type')) ? params.get('type') : '',
        search: params.get('search') ?? '',
        quick: new Set((params.get('quick') ?? '').split(',').filter((key) => QUICK.includes(key))),
        tasks: new Map(),
        users: [],
        me: null,
        canReopen: false,
        closedLimit: 40,
        extraLanes: new Set(),
        loadedAt: null,
        loading: false,
        error: null,
    };
}

function init(root) {
    const state = readState(root);
    const board = root.querySelector('[data-board]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const toast = window.Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2800, timerProgressBar: true });
    let drag = null;
    let suppressClickUntil = 0;

    /* ---------- data ---------- */

    async function request(url, { method = 'GET', body } = {}) {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, ...(body ? { 'Content-Type': 'application/json' } : {}) },
            body: body ? JSON.stringify(body) : undefined,
        });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) {
            if (response.status === 419) throw new Error('Your session expired. Reload the page and try again.');
            const firstError = json.errors ? Object.values(json.errors).flat()[0] : null;
            throw new Error(firstError || json.message || `Request failed (${response.status}).`);
        }

        return json;
    }

    async function load({ quiet = false } = {}) {
        if (state.loading) return;
        state.loading = true;
        root.querySelector('[data-refresh]')?.classList.add('is-spinning');
        try {
            const json = await request(state.feedUrl);
            state.tasks = new Map(json.tasks.map((task) => [task.id, task]));
            state.users = json.users;
            state.me = json.me;
            state.canReopen = json.can_reopen;
            state.closedLimit = json.closed_limit;
            state.loadedAt = new Date();
            state.error = null;
        } catch (error) {
            state.error = error.message;
            if (quiet) toast.fire({ icon: 'error', title: error.message });
        } finally {
            state.loading = false;
            root.querySelector('[data-refresh]')?.classList.remove('is-spinning');
            render();
        }
    }

    function upsert(task) {
        state.tasks.set(task.id, task);
        render();
        flash(task.id);
    }

    /* ---------- filtering & lanes ---------- */

    function matches(task) {
        if (state.type && task.type !== state.type) return false;
        if (state.quick.has('mine') && !task.mine) return false;
        if (state.quick.has('watching') && !task.watching) return false;
        if (state.quick.has('created') && !task.created_by_me) return false;
        if (state.quick.has('overdue') && !task.overdue) return false;
        const query = state.search.trim().toLowerCase();
        if (!query) return true;

        return [task.task_no, task.title, task.customer, task.category, task.assignee?.name]
            .some((value) => String(value ?? '').toLowerCase().includes(query));
    }

    function laneOf(task) {
        if (state.mode === 'priority') return task.status === 'closed' ? null : `priority:${task.priority}`;
        if (task.status === 'closed') return 'closed';

        return state.mode === 'owner' ? `owner:${task.assignee?.id ?? 0}` : 'pending';
    }

    function lanes(visible) {
        if (state.mode === 'priority') {
            return PRIORITIES.map((p) => ({ key: `priority:${p.key}`, title: `${p.label} priority`, subtitle: 'Active items', icon: p.icon, tone: p.tone }));
        }
        const closedLane = { key: 'closed', title: 'Closed', subtitle: `Latest ${state.closedLimit} closed`, icon: 'circle-check-big', tone: '#16a34a' };
        if (state.mode === 'status') {
            return [{ key: 'pending', title: 'Active', subtitle: 'Pending until closed', icon: 'activity', tone: '#d97706' }, closedLane];
        }

        const ownerIds = new Set(visible.filter((task) => task.status !== 'closed' && task.assignee).map((task) => task.assignee.id));
        state.extraLanes.forEach((id) => ownerIds.add(id));
        const owners = [...ownerIds].map((id) => {
            const user = state.users.find((u) => u.id === id) ?? visible.find((task) => task.assignee?.id === id)?.assignee ?? { id, name: 'Unknown user' };
            return { key: `owner:${id}`, userId: id, title: id === state.me ? `${user.name} (you)` : user.name, subtitle: user.designation || 'Current owner', avatar: user.name, tone: id === state.me ? '#7c3aed' : '#2563eb' };
        }).sort((a, b) => (b.userId === state.me) - (a.userId === state.me) || a.title.localeCompare(b.title));

        return [...owners, closedLane];
    }

    /** Resolves what dropping `task` on `laneKey` means, or null when the move is not allowed. */
    function intentFor(task, laneKey) {
        if (!task || !laneKey || laneKey === laneOf(task)) return null;
        const isClosed = task.status === 'closed';
        if (laneKey.startsWith('priority:')) {
            return !isClosed && task.can_prioritise ? { kind: 'priority', priority: laneKey.split(':')[1] } : null;
        }
        if (!task.can_act) return null;
        if (laneKey === 'closed') return isClosed ? null : { kind: 'close' };
        if (!isClosed) {
            return laneKey.startsWith('owner:') ? { kind: 'assign', userId: Number(laneKey.split(':')[1]) } : null;
        }
        if (!state.canReopen) return null;

        return laneKey.startsWith('owner:') ? { kind: 'assign', userId: Number(laneKey.split(':')[1]), reopen: true } : { kind: 'assign', userId: null, reopen: true };
    }

    function canDrag(task) {
        if (state.mode === 'priority') return task.status !== 'closed' && task.can_prioritise;
        if (task.status === 'closed') return task.can_act && state.canReopen;

        return task.can_act;
    }

    /* ---------- rendering ---------- */

    function menuFor(task) {
        const items = [`<li><a class="dropdown-item" href="${esc(task.url)}"><i data-lucide="external-link"></i>Open details</a></li>`];
        if (task.status !== 'closed' && task.can_act) {
            items.push(`<li><button class="dropdown-item" type="button" data-act="assign"><i data-lucide="forward"></i>Hand over to…</button></li>`);
            items.push(`<li><button class="dropdown-item" type="button" data-act="close"><i data-lucide="circle-check-big"></i>Close with remark</button></li>`);
        }
        if (task.status !== 'closed' && task.can_prioritise) {
            items.push('<li><hr class="dropdown-divider"></li><li><h6 class="dropdown-header">Priority</h6></li>');
            PRIORITIES.filter((p) => p.key !== task.priority).forEach((p) => items.push(`<li><button class="dropdown-item" type="button" data-act="priority" data-priority="${p.key}"><i data-lucide="${p.icon}" style="color:${p.tone}"></i>${p.label}</button></li>`));
        }
        if (task.status === 'closed' && task.can_act && state.canReopen) {
            items.push(`<li><button class="dropdown-item" type="button" data-act="reopen"><i data-lucide="rotate-ccw"></i>Reopen & assign…</button></li>`);
        }

        return `<ul class="dropdown-menu dropdown-menu-end shadow nx-kanban-menu">${items.join('')}</ul>`;
    }

    function cardHtml(task) {
        const isTicket = task.type === 'ticket';
        const locked = !canDrag(task);
        const classes = ['nx-kanban-card', `type-${task.type}`, `prio-${task.priority}`, task.overdue ? 'is-overdue' : '', task.status === 'closed' ? 'is-closed' : '', locked ? 'is-locked' : ''].filter(Boolean).join(' ');
        const due = task.status === 'closed'
            ? `<span class="nx-kc-due done"><i data-lucide="circle-check"></i>Closed</span>`
            : `<span class="nx-kc-due ${task.overdue ? 'overdue' : (isToday(task.due_at) ? 'today' : '')}" title="Due date"><i data-lucide="${task.overdue ? 'alarm-clock' : 'calendar-clock'}"></i>${esc(task.due_label ?? 'No due date')}</span>`;
        const latest = task.latest && task.latest.action !== 'created'
            ? `<div class="nx-kc-latest" title="${esc(task.latest.remark)}"><i data-lucide="message-square-text"></i><span><strong>${esc(task.latest.by)}</strong> ${esc(task.latest.remark)}</span></div>`
            : '';
        const avatar = state.mode !== 'owner' || task.status === 'closed'
            ? `<span class="nx-kc-avatar" title="Owner: ${esc(task.assignee?.name ?? 'Unassigned')}">${esc(initials(task.assignee?.name))}</span>`
            : '';

        return `<article class="${classes}" data-id="${task.id}" aria-label="${esc(task.task_no)}: ${esc(task.title)}">
            <div class="nx-kc-top">
                <span class="nx-kc-no">${esc(task.task_no)}</span>
                <span class="badge rounded-pill ${isTicket ? 'badge-soft-info' : 'badge-soft-primary'}">${isTicket ? 'Ticket' : 'Workflow'}</span>
                ${task.status !== 'closed' ? `<span class="nx-kc-prio prio-${task.priority}">${esc(PRIORITY_LABEL[task.priority] ?? task.priority)}</span>` : ''}
                <div class="dropdown ms-auto">
                    <button class="nx-kc-menu" type="button" data-bs-toggle="dropdown" data-bs-popper-config='{"strategy":"fixed"}' aria-expanded="false" aria-label="Actions for ${esc(task.task_no)}"><i data-lucide="ellipsis"></i></button>
                    ${menuFor(task)}
                </div>
            </div>
            <a class="nx-kc-title" href="${esc(task.url)}">${esc(task.title)}</a>
            <div class="nx-kc-meta"><i data-lucide="${isTicket ? 'building-2' : 'git-branch'}"></i><span>${esc(task.customer ?? 'Internal operation')} · ${esc(task.category || 'General')}</span></div>
            ${latest}
            <div class="nx-kc-foot">
                ${due}
                <span class="nx-kc-count" title="Timeline entries"><i data-lucide="history"></i>${task.actions_count}</span>
                ${task.observers_count ? `<span class="nx-kc-count" title="Observers"><i data-lucide="eye"></i>${task.observers_count}</span>` : ''}
                ${avatar}
            </div>
        </article>`;
    }

    function laneHtml(lane, cards) {
        const overdue = cards.filter((task) => task.overdue).length;
        const urgent = cards.filter((task) => task.priority === 'very_high' && task.status !== 'closed').length;
        const icon = lane.avatar
            ? `<span class="nx-kanban-col-icon is-avatar">${esc(initials(lane.avatar))}</span>`
            : `<span class="nx-kanban-col-icon"><i data-lucide="${lane.icon}"></i></span>`;
        const signals = [overdue ? `<span class="text-danger">${overdue} overdue</span>` : '', urgent && !lane.key.startsWith('priority:') ? `<span>${urgent} very high</span>` : ''].filter(Boolean).join('<span aria-hidden="true">·</span>');
        const empty = `<div class="nx-kanban-empty"><i data-lucide="${lane.key === 'closed' ? 'archive' : 'inbox'}"></i><span>${lane.key === 'closed' ? 'Drop here to close' : (lane.userId ? 'Drop here to hand over' : 'Nothing here')}</span></div>`;

        return `<section class="nx-kanban-col" data-lane="${esc(lane.key)}" style="--lane:${lane.tone}">
            <header class="nx-kanban-col-head">
                ${icon}
                <div class="min-w-0 flex-grow-1"><strong class="d-block text-truncate">${esc(lane.title)}</strong><small class="text-secondary d-block text-truncate">${esc(lane.subtitle)}</small></div>
                <span class="nx-kanban-count">${cards.length}</span>
                ${lane.userId && state.extraLanes.has(lane.userId) && !cards.length ? `<button class="nx-kc-menu" type="button" data-remove-lane="${lane.userId}" aria-label="Remove lane"><i data-lucide="x"></i></button>` : ''}
            </header>
            ${signals ? `<div class="nx-kanban-signals">${signals}</div>` : ''}
            <div class="nx-kanban-list">${cards.length ? cards.map(cardHtml).join('') : empty}</div>
        </section>`;
    }

    function addLaneHtml(shownIds) {
        const options = state.users.filter((user) => !shownIds.has(user.id)).map((user) => `<option value="${user.id}">${esc(user.name)}${user.designation ? ` · ${esc(user.designation)}` : ''}</option>`).join('');
        if (!options) return '';

        return `<section class="nx-kanban-col nx-kanban-add">
            <span class="nx-kanban-col-icon"><i data-lucide="user-plus"></i></span>
            <strong>Add an owner lane</strong>
            <small class="text-secondary">Show someone with no active work so you can drag items to them.</small>
            <select class="form-select form-select-sm" data-add-lane aria-label="Add owner lane"><option value="">Select person</option>${options}</select>
        </section>`;
    }

    function sortCards(cards) {
        const weight = { very_high: 0, high: 1, normal: 2 };
        return cards.sort((a, b) => {
            if (a.status === 'closed' && b.status === 'closed') return String(b.closed_at).localeCompare(String(a.closed_at));
            return (b.overdue - a.overdue) || (weight[a.priority] - weight[b.priority]) || String(a.due_at ?? '9999').localeCompare(String(b.due_at ?? '9999'));
        });
    }

    function renderStats(visible) {
        const open = visible.filter((task) => task.status !== 'closed');
        const stats = [
            ['activity', `${open.length} active`, ''],
            ['alarm-clock', `${open.filter((task) => task.overdue).length} overdue`, 'danger'],
            ['calendar-check', `${open.filter((task) => isToday(task.due_at)).length} due today`, 'warning'],
            ['user-round-check', `${open.filter((task) => task.mine).length} mine`, ''],
        ];
        const updated = state.loadedAt ? `<span class="text-secondary small">Updated ${state.loadedAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>` : '';
        root.querySelector('[data-stats]').innerHTML = stats.map(([icon, label, tone]) => `<span class="nx-kanban-stat ${tone}"><i data-lucide="${icon}"></i>${label}</span>`).join('') + updated;
    }

    function syncControls() {
        root.querySelectorAll('[data-mode-btn]').forEach((btn) => btn.classList.toggle('active', btn.dataset.modeBtn === state.mode));
        root.querySelectorAll('[data-type-btn]').forEach((btn) => btn.classList.toggle('active', btn.dataset.typeBtn === state.type));
        root.querySelectorAll('[data-quick]').forEach((btn) => { btn.classList.toggle('active', state.quick.has(btn.dataset.quick)); btn.setAttribute('aria-pressed', state.quick.has(btn.dataset.quick)); });
        const hint = root.querySelector('[data-hint-text]');
        if (hint) hint.textContent = HINTS[state.mode];

        const params = new URLSearchParams();
        if (state.mode !== 'owner') params.set('group', state.mode);
        if (state.type) params.set('type', state.type);
        if (state.search.trim()) params.set('search', state.search.trim());
        if (state.quick.size) params.set('quick', [...state.quick].join(','));
        const query = params.toString();
        window.history.replaceState(null, '', `${window.location.pathname}${query ? `?${query}` : ''}`);
        try { window.localStorage.setItem('nx.kanban.mode', state.mode); } catch { /* storage unavailable */ }

        const typeSelect = document.getElementById('workItemType');
        if (typeSelect && state.type && typeSelect.value !== state.type && !document.getElementById('taskModal')?.classList.contains('show')) {
            typeSelect.value = state.type;
            typeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function render() {
        syncControls();
        board.setAttribute('aria-busy', 'false');
        if (state.error && !state.tasks.size) {
            board.innerHTML = `<div class="nx-empty-panel w-100"><span class="nx-empty-icon"><i data-lucide="cloud-off"></i></span><div><div class="fw-bold">Board could not be loaded</div><div class="small text-secondary">${esc(state.error)}</div></div><button class="btn btn-outline-primary ms-auto" type="button" data-retry>Try again</button></div>`;
            window.refreshIcons?.();
            return;
        }

        const scroll = board.scrollLeft;
        const visible = [...state.tasks.values()].filter(matches);
        const laneList = lanes(visible);
        const grouped = new Map(laneList.map((lane) => [lane.key, []]));
        visible.forEach((task) => grouped.get(laneOf(task))?.push(task));
        const shownOwners = new Set(laneList.filter((lane) => lane.userId).map((lane) => lane.userId));

        board.innerHTML = laneList.map((lane) => laneHtml(lane, sortCards(grouped.get(lane.key)))).join('')
            + (state.mode === 'owner' ? addLaneHtml(shownOwners) : '');
        if (!visible.some((task) => task.status !== 'closed') && state.tasks.size) {
            board.insertAdjacentHTML('afterbegin', '<div class="nx-kanban-nomatch"><i data-lucide="search-x"></i><span>No active items match these filters.</span></div>');
        }
        board.scrollLeft = scroll;
        renderStats(visible);
        window.refreshIcons?.();
    }

    function flash(id) {
        const card = board.querySelector(`.nx-kanban-card[data-id="${id}"]`);
        if (!card) return;
        card.classList.add('is-flash');
        card.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' });
        setTimeout(() => card.classList.remove('is-flash'), 1600);
    }

    /* ---------- actions ---------- */

    function userOptions(selectedId, excludeId) {
        return state.users.filter((user) => user.id !== excludeId)
            .map((user) => `<option value="${user.id}" ${user.id === selectedId ? 'selected' : ''}>${esc(user.name)}${user.designation ? ` · ${esc(user.designation)}` : ''}</option>`).join('');
    }

    async function perform(task, intent) {
        const userName = (id) => state.users.find((user) => user.id === id)?.name ?? 'the selected user';
        const needsPicker = intent.kind === 'assign' && !intent.userId;
        let title;
        let confirmText;
        let remarkLabel = 'Remark *';
        let remarkRequired = true;
        let icon = 'question';
        if (intent.kind === 'close') {
            title = `Close ${task.task_no}?`;
            confirmText = 'Close item';
            remarkLabel = 'Closing remark *';
            icon = 'success';
        } else if (intent.kind === 'priority') {
            title = `Set ${task.task_no} to ${PRIORITY_LABEL[intent.priority]} priority?`;
            confirmText = 'Update priority';
            remarkLabel = 'Note (optional)';
            remarkRequired = false;
            icon = 'info';
        } else {
            title = intent.reopen ? `Reopen ${task.task_no}` : (needsPicker ? `Hand over ${task.task_no}` : `Hand ${task.task_no} to ${userName(intent.userId)}?`);
            confirmText = intent.reopen ? 'Reopen & assign' : 'Hand over';
        }

        const picker = needsPicker
            ? `<label class="form-label text-start d-block small fw-semibold mt-1" for="nxKanbanUser">${intent.reopen ? 'Assign to *' : 'Next owner *'}</label><select class="form-select mb-3" id="nxKanbanUser"><option value="">Select user</option>${userOptions(intent.reopen ? task.assignee?.id : null, intent.reopen ? null : task.assignee?.id)}</select>`
            : '';
        const result = await window.Swal.fire({
            title,
            icon,
            html: `<div class="text-start"><div class="nx-kanban-dialog-card"><span class="small fw-bold text-primary">${esc(task.task_no)}</span><div class="fw-semibold">${esc(task.title)}</div></div>${picker}<label class="form-label small fw-semibold" for="nxKanbanRemark">${remarkLabel}</label><textarea class="form-control" id="nxKanbanRemark" rows="3" maxlength="3000" placeholder="${intent.kind === 'close' ? 'Outcome, work completed or customer confirmation' : (intent.kind === 'priority' ? 'Why the priority changed' : 'Context and next step for the new owner')}"></textarea></div>`,
            showCancelButton: true,
            confirmButtonText: confirmText,
            focusConfirm: false,
            reverseButtons: true,
            showLoaderOnConfirm: true,
            allowOutsideClick: () => !window.Swal.isLoading(),
            didOpen: (popup) => popup.querySelector(needsPicker ? '#nxKanbanUser' : '#nxKanbanRemark')?.focus(),
            preConfirm: async () => {
                const popup = window.Swal.getPopup();
                const remark = popup.querySelector('#nxKanbanRemark').value.trim();
                const userId = needsPicker ? Number(popup.querySelector('#nxKanbanUser').value) : intent.userId;
                if (needsPicker && !userId) return window.Swal.showValidationMessage('Select who should own this item.');
                if (remarkRequired && remark.length < 3) return window.Swal.showValidationMessage('A remark of at least 3 characters is mandatory.');
                try {
                    return intent.kind === 'priority'
                        ? await request(task.priority_url, { method: 'POST', body: { priority: intent.priority, remark } })
                        : await request(task.action_url, { method: 'POST', body: { action: intent.kind === 'close' ? 'close' : 'assign', remark, assigned_to: intent.kind === 'assign' ? userId : null } });
                } catch (error) {
                    return window.Swal.showValidationMessage(error.message);
                }
            },
        });

        if (result.isConfirmed && result.value?.task) {
            if (intent.kind === 'assign' && state.mode === 'owner') state.extraLanes.delete(result.value.task.assignee?.id);
            upsert(result.value.task);
            toast.fire({ icon: 'success', title: result.value.message });
        }
    }

    function intentFromMenu(task, button) {
        switch (button.dataset.act) {
            case 'close': return { kind: 'close' };
            case 'assign': return { kind: 'assign', userId: null };
            case 'reopen': return { kind: 'assign', userId: null, reopen: true };
            case 'priority': return { kind: 'priority', priority: button.dataset.priority };
            default: return null;
        }
    }

    /* ---------- drag & drop (pointer events: mouse, pen and touch) ---------- */

    const laneAt = (x, y) => document.elementFromPoint(x, y)?.closest('[data-lane]');

    function startDrag() {
        const { card, task, startX, startY } = drag;
        const rect = card.getBoundingClientRect();
        const ghost = card.cloneNode(true);
        ghost.classList.add('nx-kanban-ghost');
        ghost.style.width = `${rect.width}px`;
        document.body.appendChild(ghost);
        drag.ghost = ghost;
        drag.offsetX = startX - rect.left;
        drag.offsetY = startY - rect.top;
        drag.active = true;
        card.classList.add('is-drag-source');
        board.classList.add('is-dragging');
        document.body.classList.add('nx-kanban-grabbing');
        board.querySelectorAll('[data-lane]').forEach((lane) => {
            lane.classList.toggle('can-drop', Boolean(intentFor(task, lane.dataset.lane)));
            lane.classList.toggle('is-source', lane.dataset.lane === laneOf(task));
        });
        navigator.vibrate?.(12);
        moveGhost(startX, startY);
        autoScroll();
    }

    function moveGhost(x, y) {
        drag.x = x;
        drag.y = y;
        drag.ghost.style.transform = `translate(${x - drag.offsetX}px, ${y - drag.offsetY}px) rotate(2deg)`;
        const lane = laneAt(x, y);
        const target = lane && intentFor(drag.task, lane.dataset.lane) ? lane : null;
        if (target !== drag.over) {
            drag.over?.classList.remove('is-over');
            target?.classList.add('is-over');
            drag.over = target;
        }
    }

    function autoScroll() {
        if (!drag?.active) return;
        const rect = board.getBoundingClientRect();
        const edge = 70;
        if (drag.x < rect.left + edge) board.scrollLeft -= Math.ceil((rect.left + edge - drag.x) / 6);
        else if (drag.x > rect.right - edge) board.scrollLeft += Math.ceil((drag.x - rect.right + edge) / 6);
        const list = drag.over?.querySelector('.nx-kanban-list');
        if (list) {
            const listRect = list.getBoundingClientRect();
            if (drag.y < listRect.top + 40) list.scrollTop -= 8;
            else if (drag.y > listRect.bottom - 40) list.scrollTop += 8;
        }
        if (drag.y < 90) window.scrollBy(0, -10);
        else if (drag.y > window.innerHeight - 50) window.scrollBy(0, 10);
        drag.frame = requestAnimationFrame(autoScroll);
    }

    function endDrag(drop) {
        if (!drag) return;
        clearTimeout(drag.holdTimer);
        const { active, task, over } = drag;
        if (active) {
            cancelAnimationFrame(drag.frame);
            drag.ghost.remove();
            drag.card.classList.remove('is-drag-source');
            board.classList.remove('is-dragging');
            document.body.classList.remove('nx-kanban-grabbing');
            board.querySelectorAll('[data-lane]').forEach((lane) => lane.classList.remove('can-drop', 'is-over', 'is-source'));
            suppressClickUntil = Date.now() + 250;
        }
        drag = null;
        const intent = active && drop && over ? intentFor(task, over.dataset.lane) : null;
        if (intent) perform(task, intent);
    }

    board.addEventListener('pointerdown', (event) => {
        const card = event.target.closest('.nx-kanban-card');
        if (!card || card.classList.contains('is-locked') || event.button > 0 || event.target.closest('button, .dropdown-menu, select')) return;
        const task = state.tasks.get(Number(card.dataset.id));
        if (!task || !canDrag(task)) return;
        drag = { card, task, pointerId: event.pointerId, startX: event.clientX, startY: event.clientY, x: event.clientX, y: event.clientY, touch: event.pointerType !== 'mouse', active: false, over: null };
        if (drag.touch) {
            drag.holdTimer = setTimeout(() => { if (drag && !drag.active) startDrag(); }, TOUCH_HOLD_MS);
        }
    });

    document.addEventListener('pointermove', (event) => {
        if (!drag || event.pointerId !== drag.pointerId) return;
        if (drag.active) {
            event.preventDefault();
            moveGhost(event.clientX, event.clientY);
            return;
        }
        const distance = Math.hypot(event.clientX - drag.startX, event.clientY - drag.startY);
        if (drag.touch) {
            if (distance > 8) endDrag(false);
        } else if (distance > 6) {
            startDrag();
        }
    });
    document.addEventListener('pointerup', (event) => { if (drag && event.pointerId === drag.pointerId) endDrag(true); });
    document.addEventListener('pointercancel', () => endDrag(false));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && drag?.active) endDrag(false); });
    // Once a touch drag has started, stop the page from scrolling under the finger.
    document.addEventListener('touchmove', (event) => { if (drag?.active) event.preventDefault(); }, { passive: false });
    board.addEventListener('contextmenu', (event) => { if (drag) event.preventDefault(); });
    board.addEventListener('dragstart', (event) => event.preventDefault());
    // A drag that ends over the card's own link must not also navigate.
    board.addEventListener('click', (event) => {
        if (Date.now() < suppressClickUntil) { event.preventDefault(); event.stopPropagation(); }
    }, { capture: true });

    /* ---------- controls ---------- */

    board.addEventListener('click', (event) => {
        const menuButton = event.target.closest('[data-act]');
        if (menuButton) {
            const task = state.tasks.get(Number(menuButton.closest('.nx-kanban-card')?.dataset.id));
            const intent = task && intentFromMenu(task, menuButton);
            if (intent) perform(task, intent);
            return;
        }
        const remove = event.target.closest('[data-remove-lane]');
        if (remove) {
            state.extraLanes.delete(Number(remove.dataset.removeLane));
            render();
            return;
        }
        if (event.target.closest('[data-retry]')) load();
    });
    board.addEventListener('change', (event) => {
        if (!event.target.matches('[data-add-lane]') || !event.target.value) return;
        state.extraLanes.add(Number(event.target.value));
        render();
        board.scrollTo({ left: board.scrollWidth, behavior: 'smooth' });
    });

    root.querySelectorAll('[data-mode-btn]').forEach((btn) => btn.addEventListener('click', () => { state.mode = btn.dataset.modeBtn; render(); }));
    root.querySelectorAll('[data-type-btn]').forEach((btn) => btn.addEventListener('click', (event) => { event.preventDefault(); state.type = btn.dataset.typeBtn; render(); }));
    root.querySelectorAll('[data-quick]').forEach((btn) => btn.addEventListener('click', () => {
        const key = btn.dataset.quick;
        if (state.quick.has(key)) state.quick.delete(key); else state.quick.add(key);
        render();
    }));
    let searchTimer;
    root.querySelector('[data-search-input]')?.addEventListener('input', (event) => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { state.search = event.target.value; render(); }, 160);
    });
    root.querySelector('[data-refresh]')?.addEventListener('click', () => load({ quiet: true }));

    setInterval(() => {
        const menuOpen = board.querySelector('.dropdown-menu.show');
        if (document.visibilityState === 'visible' && !drag && !window.Swal.isVisible() && !menuOpen) load({ quiet: true });
    }, REFRESH_MS);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && state.loadedAt && Date.now() - state.loadedAt > REFRESH_MS) load({ quiet: true });
    });

    load();
}

document.querySelectorAll('[data-kanban]').forEach(init);
