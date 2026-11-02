/**
 * Complaint screens: registration auto-fill, SLA preview, pending reason cascade,
 * parts repeater, visit report helpers (time stamps, Delta T, signature pad, outcome),
 * commercial totals and quick follow-ups.
 */
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const pad = (n) => String(n).padStart(2, '0');
const localDateTime = (date = new Date()) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
const formatWhen = (date) => date.toLocaleString('en-IN', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char]));
const fire = (el) => el?.dispatchEvent(new Event('change', { bubbles: true }));

function setValue(el, value, { onlyIfEmpty = false } = {}) {
    if (!el || value === undefined || value === null || value === '') return;
    if (onlyIfEmpty && el.value) return;
    if (el.tagName === 'SELECT' && ![...el.options].some((option) => option.value === String(value))) return;
    el.value = value;
    fire(el);
}

/* ------------------------------------------------------------------ */
/* Registration form                                                   */
/* ------------------------------------------------------------------ */
function initRegistration(form) {
    const sla = JSON.parse(form.dataset.sla || '{}');
    const contextUrl = form.dataset.contextUrl;
    const customerSelect = $('#customerId', form);
    const branchSelect = $('#branchId', form);
    const equipmentSelect = $('#equipmentId', form);
    let context = null;

    // Existing / new customer
    const syncMode = () => {
        const mode = $('input[name="customer_mode"]:checked', form)?.value || 'existing';
        $$('[data-mode-panel]', form).forEach((panel) => {
            const active = panel.dataset.modePanel === mode;
            panel.disabled = !active;
            panel.classList.toggle('d-none', !active);
        });
        if (mode === 'new') {
            context = null;
            fillBranches([]);
            fillEquipment([]);
            $('[data-context-card]', form)?.classList.add('d-none');
            $('[data-summary="customer"]', form).textContent = $('#ncName', form)?.value || 'New customer';
        }
    };
    $$('input[name="customer_mode"]', form).forEach((radio) => radio.addEventListener('change', syncMode));
    $('#ncName', form)?.addEventListener('input', (event) => { $('[data-summary="customer"]', form).textContent = event.target.value || 'New customer'; });

    function fillBranches(branches) {
        const old = branchSelect.dataset.old || branchSelect.value;
        branchSelect.replaceChildren(new Option('Main site', ''), ...branches.map((branch) => new Option(`${branch.name}${branch.code ? ' · ' + branch.code : ''}`, branch.id)));
        if (old && branches.some((branch) => String(branch.id) === String(old))) branchSelect.value = old;
        branchSelect.dataset.old = '';
    }

    function fillEquipment(units) {
        const old = equipmentSelect.dataset.old || equipmentSelect.value;
        const branchId = branchSelect.value;
        const visible = branchId ? units.filter((unit) => !unit.branch_id || String(unit.branch_id) === branchId) : units;
        equipmentSelect.replaceChildren(
            new Option(units.length ? 'Select the unit (or enter details manually)' : 'Not in the equipment register / enter manually', ''),
            ...visible.map((unit) => new Option(`${unit.label || 'Unit'}${unit.serial_no ? ' · SN ' + unit.serial_no : ''}${unit.location ? ' · ' + unit.location : ''}`, unit.id)),
        );
        if (old && visible.some((unit) => String(unit.id) === String(old))) {
            equipmentSelect.value = old;
            fire(equipmentSelect);
        }
        equipmentSelect.dataset.old = '';
    }

    function fillContacts(contacts) {
        const list = $('#contactList', form);
        if (list) list.replaceChildren(...contacts.map((contact) => Object.assign(document.createElement('option'), { value: contact.name, label: [contact.designation, contact.phone].filter(Boolean).join(' · ') })));
    }

    function renderContext(data) {
        const card = $('[data-context-card]', form);
        const body = $('[data-context-body]', form);
        const alertBox = $('[data-open-complaints]', form);
        const open = data.open_complaints || [];
        if (alertBox) {
            alertBox.classList.toggle('d-none', open.length === 0);
            alertBox.innerHTML = open.length ? `<strong>${open.length} open complaint${open.length > 1 ? 's' : ''} for this customer.</strong> Check before registering a duplicate: ${open.map((item) => `<a href="${escapeHtml(item.url)}" target="_blank" rel="noopener" class="notranslate">${escapeHtml(item.no)}</a> (${escapeHtml(item.nature)})`).join(', ')}` : '';
        }
        if (!card || !body) return;
        const rows = [
            ['Customer ID', data.customer.code],
            ['Phone', data.customer.phone],
            ['Branches', String(data.branches.length)],
            ['Units in register', String(data.equipment.length)],
            data.contract ? ['Last contract', `${data.contract.contract_no}${data.contract.contract_expiry ? ' · till ' + data.contract.contract_expiry : ''}`] : null,
            ['Open complaints', String(open.length)],
        ].filter(Boolean);
        body.innerHTML = `<dl class="nx-dl-rows">${rows.map(([dt, dd]) => `<div><dt>${escapeHtml(dt)}</dt><dd class="notranslate">${escapeHtml(dd || '—')}</dd></div>`).join('')}</dl>`;
        card.classList.remove('d-none');
    }

    async function loadContext(customerId) {
        if (!customerId) return;
        try {
            const response = await fetch(contextUrl.replace('__ID__', customerId), { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            context = await response.json();
        } catch {
            return;
        }
        $('[data-fill="customer_code"]', form).value = context.customer.code || '';
        $('[data-summary="customer"]', form).textContent = context.customer.name;
        fillBranches(context.branches);
        fillContacts(context.contacts);
        fillEquipment(context.equipment);
        setValue($('#address', form), context.customer.address, { onlyIfEmpty: true });
        const contact = context.contacts.find((item) => item.is_service) || context.contacts.find((item) => item.is_primary) || context.contacts[0];
        if (contact) {
            setValue($('#contactPerson', form), contact.name, { onlyIfEmpty: true });
            setValue($('#contactNo', form), contact.phone, { onlyIfEmpty: true });
        } else {
            setValue($('#contactNo', form), context.customer.phone, { onlyIfEmpty: true });
        }
        if (context.contract) {
            setValue($('#contractNo', form), context.contract.contract_no, { onlyIfEmpty: true });
            setValue($('#contractExpiry', form), context.contract.contract_expiry, { onlyIfEmpty: true });
            setValue($('#category', form), context.contract.service_category, { onlyIfEmpty: true });
        }
        renderContext(context);
    }

    customerSelect?.addEventListener('change', () => {
        $('#address', form).value = '';
        $('#contactPerson', form).value = '';
        $('#contactNo', form).value = '';
        loadContext(customerSelect.value);
    });

    branchSelect?.addEventListener('change', () => {
        if (!context) return;
        const branch = context.branches.find((item) => String(item.id) === branchSelect.value);
        if (branch) {
            setValue($('#address', form), branch.address);
            setValue($('#siteLocation', form), branch.name, { onlyIfEmpty: true });
            const contact = context.contacts.find((item) => String(item.branch_id) === String(branch.id));
            if (contact) {
                setValue($('#contactPerson', form), contact.name);
                setValue($('#contactNo', form), contact.phone);
            }
        }
        fillEquipment(context.equipment);
    });

    $('#contactPerson', form)?.addEventListener('change', (event) => {
        const contact = context?.contacts.find((item) => item.name === event.target.value);
        if (contact?.phone) setValue($('#contactNo', form), contact.phone);
    });

    equipmentSelect?.addEventListener('change', () => {
        const unit = context?.equipment.find((item) => String(item.id) === equipmentSelect.value);
        const badge = $('[data-warranty-badge]', form);
        if (!unit) {
            badge?.classList.add('d-none');
            return;
        }
        setValue($('#equipmentTag', form), unit.tag);
        setValue($('#equipmentType', form), unit.type);
        setValue($('#make', form), unit.make);
        setValue($('#model', form), unit.model);
        setValue($('#capacity', form), unit.capacity);
        setValue($('#serialNo', form), unit.serial_no);
        setValue($('#installDate', form), unit.installation_date);
        setValue($('#lastService', form), unit.last_service_date);
        setValue($('#lastPm', form), unit.last_pm_date);
        setValue($('#refrigerant', form), unit.refrigerant);
        setValue($('#technology', form), unit.technology);
        setValue($('#unitLocation', form), unit.location, { onlyIfEmpty: true });
        if (badge) {
            badge.classList.remove('d-none', 'badge-soft-success', 'badge-soft-danger', 'badge-soft-secondary');
            if (unit.warranty_ends_at) {
                badge.classList.add(unit.in_warranty ? 'badge-soft-success' : 'badge-soft-danger');
                badge.textContent = unit.in_warranty ? `In warranty till ${unit.warranty_ends_at}` : `Warranty ended ${unit.warranty_ends_at}`;
                if (unit.in_warranty) {
                    setValue($('#category', form), 'warranty', { onlyIfEmpty: true });
                    setValue($('#contractExpiry', form), unit.warranty_ends_at, { onlyIfEmpty: true });
                }
            } else {
                badge.classList.add('badge-soft-secondary');
                badge.textContent = 'No warranty date on record';
            }
        }
    });

    // Priority -> SLA hours and due times
    const syncSla = (fromPriority = false) => {
        const priority = $('input[name="priority"]:checked', form)?.value;
        const level = sla[priority];
        if (level && fromPriority) {
            $('#slaResponse', form).value = level.response;
            $('#slaResolution', form).value = level.resolution;
        }
        const response = Number($('#slaResponse', form).value || 0);
        const resolution = Number($('#slaResolution', form).value || 0);
        const now = Date.now();
        $('[data-summary="respond"]', form).textContent = response ? formatWhen(new Date(now + response * 3600000)) : '—';
        $('[data-summary="resolve"]', form).textContent = resolution ? formatWhen(new Date(now + resolution * 3600000)) : '—';
    };
    $$('input[name="priority"]', form).forEach((radio) => radio.addEventListener('change', () => syncSla(true)));
    ['#slaResponse', '#slaResolution'].forEach((selector) => $(selector, form)?.addEventListener('input', () => syncSla(false)));

    // Nature quick picks
    $$('[data-nature-pick]', form).forEach((button) => button.addEventListener('click', () => {
        $('#nature', form).value = button.dataset.naturePick;
        $('#nature', form).dispatchEvent(new Event('input', { bubbles: true }));
    }));

    // Assign now toggle
    const assignToggle = $('[data-assign-toggle]', form);
    const assignPanel = $('[data-assign-panel]', form);
    assignToggle?.addEventListener('change', () => {
        assignPanel.disabled = !assignToggle.checked;
        assignPanel.classList.toggle('d-none', !assignToggle.checked);
        window.dispatchEvent(new Event('resize'));
    });

    // Live clock
    const tick = () => {
        const now = new Date();
        $('[data-live-date]', form).textContent = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        $('[data-live-time]', form).textContent = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
        syncSla(false);
    };
    tick();
    setInterval(tick, 30000);

    syncMode();
    if (customerSelect?.value && !customerSelect.disabled) loadContext(customerSelect.value);
}

/* ------------------------------------------------------------------ */
/* Pending reason cascade                                              */
/* ------------------------------------------------------------------ */
function initPendingFields(root) {
    const tree = JSON.parse(root.dataset.tree || '{}');
    const group = $('[data-pending-group]', root);
    const reason = $('[data-pending-reason]', root);
    const sub = $('[data-pending-sub]', root);
    if (!group || !reason || !sub) return;

    const fillReasons = () => {
        const current = reason.value || reason.dataset.current || '';
        const reasons = tree[group.value] || [];
        reason.replaceChildren(new Option(group.value ? 'Select reason' : 'Select a group first', ''), ...reasons.map((item) => new Option(item.label, item.label)));
        if (current && !reasons.some((item) => item.label === current)) reason.add(new Option(current, current));
        reason.value = current && [...reason.options].some((option) => option.value === current) ? current : '';
        reason.dataset.current = '';
        fillSubs();
    };
    const fillSubs = () => {
        const current = sub.value || sub.dataset.current || '';
        const reasons = tree[group.value] || [];
        const subs = reasons.find((item) => item.label === reason.value)?.subs || [];
        sub.replaceChildren(new Option(subs.length ? 'Select sub category' : 'None', ''), ...subs.map((label) => new Option(label, label)));
        if (current && !subs.includes(current)) sub.add(new Option(current, current));
        sub.value = current;
        sub.dataset.current = '';
    };
    group.addEventListener('change', () => {
        reason.dataset.current = '';
        reason.value = '';
        fillReasons();
    });
    reason.addEventListener('change', () => {
        sub.dataset.current = '';
        sub.value = '';
        fillSubs();
    });
    fillReasons();
}

/* ------------------------------------------------------------------ */
/* Parts repeater                                                      */
/* ------------------------------------------------------------------ */
let productMap = null;
function products() {
    if (productMap === null) {
        try {
            productMap = JSON.parse($('[data-product-map]')?.textContent || '{}');
        } catch {
            productMap = {};
        }
    }
    return productMap;
}

function bindPartRow(row) {
    const name = $('[data-part-name]', row);
    name?.addEventListener('change', () => {
        const product = products()[name.value];
        if (!product) return;
        $('[data-product-id]', row).value = product.id || '';
        setValue($('[data-part-code]', row), product.code, { onlyIfEmpty: true });
        setValue($('[data-part-make]', row), product.make, { onlyIfEmpty: true });
        setValue($('[data-part-category]', row), product.category, { onlyIfEmpty: true });
    });
    $('[data-remove-row]', row)?.addEventListener('click', () => {
        const repeater = row.closest('[data-parts-repeater]');
        row.remove();
        const hint = $('[data-empty-hint]', repeater);
        if (hint) hint.hidden = $$('[data-row]', repeater).length > 0;
    });
}

function initPartsRepeater(repeater) {
    const body = $('[data-rows]', repeater);
    const template = $('template[data-row-template]', repeater);
    let next = $$('[data-row]', body).length;
    $$('[data-row]', body).forEach(bindPartRow);
    $('[data-add-row]', repeater)?.addEventListener('click', () => {
        const html = template.innerHTML.replaceAll('__INDEX__', String(next++));
        const holder = document.createElement('tbody');
        holder.innerHTML = html.trim();
        const row = holder.firstElementChild;
        body.appendChild(row);
        bindPartRow(row);
        $('[data-empty-hint]', repeater).hidden = true;
        window.refreshIcons?.();
        $('[data-part-name]', row)?.focus();
    });
}

/* ------------------------------------------------------------------ */
/* Visit report                                                        */
/* ------------------------------------------------------------------ */
function initVisitReport(form) {
    const field = (name) => $(`[data-time-field="${name}"]`, form);
    const minutesBetween = (a, b) => {
        const from = field(a)?.value;
        const to = field(b)?.value;
        if (!from || !to) return null;
        const diff = (new Date(to) - new Date(from)) / 60000;
        return diff >= 0 ? diff : null;
    };
    const travel = $('[data-auto-travel]', form);
    const labour = $('[data-auto-labour]', form);
    let travelTouched = Boolean(travel?.value);
    let labourTouched = Boolean(labour?.value);
    travel?.addEventListener('input', () => { travelTouched = true; });
    labour?.addEventListener('input', () => { labourTouched = true; });
    const syncDurations = () => {
        const t = minutesBetween('en_route_at', 'arrived_at');
        if (travel && !travelTouched && t !== null) travel.value = Math.round(t);
        const l = minutesBetween('started_at', 'ended_at');
        if (labour && !labourTouched && l !== null) labour.value = (l / 60).toFixed(2);
    };
    $$('[data-time-field]', form).forEach((input) => input.addEventListener('change', syncDurations));
    $$('[data-now]', form).forEach((button) => button.addEventListener('click', () => {
        const input = field(button.dataset.now);
        input.value = localDateTime();
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }));

    // Delta T from supply / return readings
    const reading = (key) => $(`[data-reading="${key}"]`, form);
    const deltaT = reading('delta_t');
    let deltaTouched = Boolean(deltaT?.value);
    deltaT?.addEventListener('input', () => { deltaTouched = true; });
    const pairs = [['idu_return_temp', 'idu_supply_temp'], ['return_air_temp', 'supply_air_temp'], ['water_in_temp', 'water_out_temp']];
    const syncDelta = () => {
        if (!deltaT || deltaTouched) return;
        for (const [high, low] of pairs) {
            const a = reading(high)?.value;
            const b = reading(low)?.value;
            if (a !== undefined && b !== undefined && a !== '' && b !== '') {
                deltaT.value = (Number(a) - Number(b)).toFixed(1);
                return;
            }
        }
    };
    pairs.flat().forEach((key) => reading(key)?.addEventListener('input', syncDelta));

    // Outcome panels and submit label
    const submit = $('[data-report-submit] span', form);
    const hint = $('[data-outcome-hint]', form);
    const visitLabel = document.querySelector('.nx-page-title')?.textContent?.trim().split(' ')[0] || 'visit';
    const syncOutcome = () => {
        const outcome = $('input[name="outcome"]:checked', form)?.value || '';
        $$('[data-outcome-panel]', form).forEach((panel) => {
            const active = panel.dataset.outcomePanel === outcome;
            panel.disabled = !active;
            panel.classList.toggle('d-none', !active);
        });
        $$('[data-outcome-required]', form).forEach((input) => { input.required = outcome !== ''; });
        if (outcome === 'resolved' && !$('input[name="final_result"]:checked', form)) {
            $('#frOk', form).checked = true;
        }
        if (submit) submit.textContent = outcome ? `Submit & complete ${visitLabel}` : 'Save progress';
        if (hint) hint.textContent = outcome ? 'Submitting completes this visit and updates the complaint status.' : 'Saving without an outcome keeps the visit open.';
        window.dispatchEvent(new Event('resize'));
    };
    $$('input[name="outcome"]', form).forEach((radio) => radio.addEventListener('change', syncOutcome));
    syncOutcome();

    initSignaturePad(form);
}

function initSignaturePad(form) {
    const canvas = $('[data-signature-pad]', form);
    const input = $('[data-signature-input]', form);
    if (!canvas || !input) return;
    const context = canvas.getContext('2d');
    let drawing = false;
    let dirty = false;

    const resize = () => {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width * ratio;
        canvas.height = rect.height * ratio;
        context.scale(ratio, ratio);
        context.lineWidth = 2.2;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.strokeStyle = '#0f172a';
        dirty = false;
    };
    const point = (event) => {
        const rect = canvas.getBoundingClientRect();
        return [event.clientX - rect.left, event.clientY - rect.top];
    };
    canvas.addEventListener('pointerdown', (event) => {
        drawing = true;
        canvas.setPointerCapture(event.pointerId);
        context.beginPath();
        context.moveTo(...point(event));
    });
    canvas.addEventListener('pointermove', (event) => {
        if (!drawing) return;
        context.lineTo(...point(event));
        context.stroke();
        dirty = true;
    });
    const stop = () => { drawing = false; };
    canvas.addEventListener('pointerup', stop);
    canvas.addEventListener('pointerleave', stop);
    $('[data-signature-clear]', form)?.addEventListener('click', () => {
        context.clearRect(0, 0, canvas.width, canvas.height);
        dirty = false;
        input.value = '';
    });
    form.addEventListener('submit', () => {
        input.value = dirty ? canvas.toDataURL('image/png') : '';
    });
    resize();
    window.addEventListener('resize', () => { if (!dirty) resize(); });
}

/* ------------------------------------------------------------------ */
/* Workspace helpers                                                   */
/* ------------------------------------------------------------------ */
function initCommercial(form) {
    const money = (value) => `₹${(Number(value) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const sync = () => {
        const get = (key) => Number($(`[data-charge="${key}"]`, form)?.value || 0);
        const subtotal = Math.max(0, get('visit_charges') + get('labour_charges') + get('parts_charges') + get('travel_charges') + get('other_charges') - get('discount'));
        const gst = Math.round(subtotal * get('gst_percent')) / 100;
        $('[data-gst-amount]', form).value = money(gst);
        $('[data-total-amount]', form).value = money(subtotal + gst);
    };
    $$('[data-charge]', form).forEach((input) => input.addEventListener('input', sync));
}

function initToggles(root = document) {
    $$('[data-toggle-target]', root).forEach((toggle) => {
        const target = document.querySelector(toggle.dataset.toggleTarget);
        const sync = () => {
            if (!target) return;
            target.classList.toggle('opacity-50', !toggle.checked);
            $$('input, select, textarea', target).forEach((el) => { el.disabled = !toggle.checked; });
        };
        toggle.addEventListener('change', sync);
        sync();
    });
}

function initTabMemory() {
    $$('[data-tab-key]').forEach((button) => button.addEventListener('shown.bs.tab', () => {
        const url = new URL(window.location.href);
        url.searchParams.set('tab', button.dataset.tabKey);
        window.history.replaceState({}, '', url);
    }));
}

function initQuickFollowUp() {
    const modal = document.getElementById('quickFollowUp');
    const form = $('[data-quick-follow-form]');
    if (!modal || !form) return;
    modal.addEventListener('show.bs.modal', (event) => {
        const trigger = event.relatedTarget;
        if (!trigger) return;
        form.action = trigger.dataset.followUrl;
        $('[data-quick-follow-no]', form).textContent = trigger.dataset.followNo || '';
        $('[data-quick-follow-action]', form).value = trigger.dataset.followAction || '';
        $('[data-quick-follow-target]', form).value = trigger.dataset.followTarget || '';
        $('textarea[name="remarks"]', form).value = '';
    });
}

function initMasterModal() {
    const type = $('[data-master-type]');
    if (!type) return;
    const sync = () => $$('[data-master-extra]').forEach((wrap) => {
        const active = wrap.dataset.masterExtra === type.value;
        wrap.classList.toggle('d-none', !active);
        $$('select', wrap).forEach((select) => {
            select.disabled = !active;
            select.required = active;
        });
    });
    type.addEventListener('change', sync);
    sync();
}

document.addEventListener('DOMContentLoaded', () => {
    $$('[data-complaint-form]').forEach(initRegistration);
    $$('[data-pending-fields]').forEach(initPendingFields);
    $$('[data-parts-repeater]').forEach(initPartsRepeater);
    $$('[data-visit-report]').forEach(initVisitReport);
    $$('[data-commercial-form]').forEach(initCommercial);
    initToggles();
    initTabMemory();
    initQuickFollowUp();
    initMasterModal();
});
