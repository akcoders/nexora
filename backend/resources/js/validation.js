/**
 * Nexora client-side form validation engine.
 *
 * Attaches itself to EVERY <form> on the page (including forms injected later
 * by scripts, modals and wizard "add row" buttons) and validates:
 *   - native attributes: required, minlength, maxlength, min, max, step, pattern, type, accept
 *   - data-v="rule|rule:arg,arg" rules (see validation-rules.js for the list)
 *   - rules inferred from the field name (email, mobile, pin_code, gstin, ifsc ...)
 *   - a "*" at the end of the field's label means required
 *
 * Opt-outs: <form data-v-ignore>, <input data-v-skip>, a submit button with
 * [formnovalidate] (e.g. "Save draft"). Messages can be overridden with
 * data-label, data-msg and data-msg-<rule>.
 *
 * Public API: window.NxValidate (validateForm, validateContainer, validateField,
 * setError, clearError, clearAll, reveal, extend, config).
 */
import {
    config,
    rules as RULES,
    CROSS_FIELD_RULES,
    UPPERCASE_RULES,
    DIGITS_ONLY_RULES,
    STRIP_SEPARATOR_RULES,
    checkFile,
    sniffKind,
    passwordScore,
    formatBytes,
} from './validation-rules.js';

const FIELD_TAGS = new Set(['INPUT', 'SELECT', 'TEXTAREA']);
const SKIP_TYPES = new Set(['hidden', 'submit', 'button', 'reset', 'image']);
const TEXTLIKE = new Set(['text', 'search', 'tel', 'email', 'url', 'password', '']);
const DEFAULT_MAX_FILE_KB = 10240;
const DEFAULT_TEXT_MAX = 255;
const DEFAULT_TEXTAREA_MAX = 5000;

const states = new WeakMap();
let uid = 0;
let pointerTarget = null;

const stateOf = (el) => {
    if (!states.has(el)) states.set(el, { touched: false, box: null, id: `nx-v-${++uid}`, fileToken: 0, fileKinds: null, fileKindsFor: 0, pending: null });
    return states.get(el);
};

/* ------------------------------------------------------------------ */
/* Field discovery                                                     */
/* ------------------------------------------------------------------ */

const isField = (el) => el && FIELD_TAGS.has(el.tagName) && !SKIP_TYPES.has(el.type);

/** Last bracket segment of a field name: branches[0][pin_code] -> pin_code, languages[] -> languages. */
export function keyOf(name = '') {
    const clean = name.replace(/\[\]$/, '');
    const m = /\[([^\]]+)\]$/.exec(clean);
    return (m ? m[1] : clean).toLowerCase();
}

const humanize = (key) => key.replace(/[_\-.]+/g, ' ').replace(/\s+/g, ' ').trim().replace(/^./, (c) => c.toUpperCase());

function groupOf(el) {
    const form = el.form;
    const scope = form ? [...form.elements] : [...document.querySelectorAll('input')];
    return scope.filter((e) => e.tagName === 'INPUT' && e.type === el.type && e.name === el.name);
}

/** Wraps an element into a "control": checkbox/radio groups validate as one unit. */
function controlOf(el) {
    if (el.type === 'radio') return { el: groupOf(el)[0] ?? el, group: groupOf(el), kind: 'radio' };
    if (el.type === 'checkbox') {
        const group = groupOf(el);
        if (el.name.endsWith('[]') || group.length > 1) return { el: group[0], group, kind: 'checkbox-group' };
        return { el, group: [el], kind: 'checkbox' };
    }
    if (el.type === 'file') return { el, group: [el], kind: 'file' };
    if (el.tagName === 'SELECT') return { el, group: [el], kind: el.multiple ? 'select-multiple' : 'select' };
    return { el, group: [el], kind: el.tagName === 'TEXTAREA' ? 'textarea' : 'text' };
}

function isApplicable(el, root) {
    if (el.disabled || el.closest('template,[data-v-skip],fieldset[disabled]') || el.hasAttribute('data-v-skip')) return false;
    for (let node = el; node && node !== root; node = node.parentElement) {
        if (node.matches?.('.nx-step-pane,.tab-pane,.modal,.collapse')) continue; // navigable containers, not "hidden by logic"
        if (node.hidden || node.classList.contains('d-none') || node.style?.display === 'none') return false;
    }
    return true;
}

/** All validatable controls inside a form or container, in DOM order, groups collapsed. */
function controlsIn(root) {
    const form = root instanceof HTMLFormElement ? root : root.closest('form');
    const all = root instanceof HTMLFormElement ? [...root.elements] : [...root.querySelectorAll('input,select,textarea')];
    const seen = new Set();
    const out = [];
    for (const el of all) {
        if (!isField(el) || !isApplicable(el, form || root)) continue;
        const ctrl = controlOf(el);
        if (seen.has(ctrl.el)) continue;
        seen.add(ctrl.el);
        out.push(ctrl);
    }
    return out;
}

/* ------------------------------------------------------------------ */
/* Labels                                                              */
/* ------------------------------------------------------------------ */

function commonAncestor(elements) {
    let node = elements[0];
    while (node && !elements.every((e) => node.contains(e))) node = node.parentElement;
    return node ?? elements[0];
}

function labelNode(ctrl) {
    const el = ctrl.el;
    const grouped = ctrl.kind === 'checkbox-group' || ctrl.kind === 'radio';
    if (!grouped && el.labels?.[0]) return el.labels[0];
    const memberIds = new Set(ctrl.group.map((g) => g.id).filter(Boolean));
    const belongsToOption = (c) => ctrl.group.some((g) => c.contains(g)) || (c.htmlFor && memberIds.has(c.htmlFor));
    // a label that is wired to some other control is that control's label, never ours
    const belongsToOther = (c) => c.control && !ctrl.group.includes(c.control);
    // Walk up from the field (or the group wrapper); the label is the nearest previous sibling at any level.
    let child = grouped ? commonAncestor(ctrl.group) : el;
    for (let depth = 0; child?.parentElement && depth < 4; depth++) {
        for (let sib = child.previousElementSibling; sib; sib = sib.previousElementSibling) {
            if (sib.matches('label,.form-label') && !belongsToOption(sib) && !belongsToOther(sib)) return sib;
        }
        if (child.parentElement.matches('form,.row,.modal-body,.nx-step-pane')) break;
        child = child.parentElement;
    }
    return null;
}

function labelInfo(ctrl) {
    const st = stateOf(ctrl.el);
    if (st.label) return st.label;
    const el = ctrl.el;
    const node = labelNode(ctrl);
    let text = (node?.textContent ?? '').replace(/\s+/g, ' ').trim();
    const star = /\*\s*$/.test(text);
    text = text.replace(/\s*\*\s*$/, '').replace(/[:：]\s*$/, '').trim();
    if (!text || (ctrl.kind === 'checkbox-group' && node?.querySelector?.('input'))) text = '';
    text = el.dataset.label || text || el.getAttribute('aria-label') || el.getAttribute('placeholder') || humanize(keyOf(el.name));
    const card = el.closest('.nx-repeat-card, .site-card');
    const context = card?.querySelector('strong')?.textContent?.trim() || '';
    st.label = { text, star, context: context && !text.startsWith(context) ? context : '' };
    return st.label;
}

/* ------------------------------------------------------------------ */
/* Rule collection                                                     */
/* ------------------------------------------------------------------ */

const parseRuleString = (str = '') =>
    str.split('|').map((s) => s.trim()).filter(Boolean).map((s) => {
        const i = s.indexOf(':');
        return i === -1 ? [s, []] : [s.slice(0, i), s.slice(i + 1).split(',').map((a) => a.trim())];
    });

/** [regex on the field key, rules]. First match wins per rule name. */
const INFERRED = [
    [/(^|_)e?mail(_\d)?$/, [['email', []]]],
    [/^(mobile|mobile_no|whatsapp|.*_mobile|.*_whatsapp)$/, [['mobile', []]]],
    [/^(phone|phone_\d|tel|telephone|alternate_phone|contact_no(_\d)?|.*_phone)$/, [['phone', []]]],
    [/^(pin|pin_code|pincode|postal_code|zip|zip_code)$/, [['pincode', []]]],
    [/^gstin$/, [['gstin', []]]],
    [/^pan$/, [['pan', []]]],
    [/^tan$/, [['tan', []]]],
    [/^cin$/, [['cin', []]]],
    [/^(udyam|udyam_no)$/, [['udyam', []]]],
    [/^ifsc(_code)?$/, [['ifsc', []]]],
    [/^swift(_code)?$/, [['swift', []]]],
    [/^upi(_id)?$/, [['upi', []]]],
    [/^account_number$/, [['account', []]]],
    [/^aadhaar(_no)?$/, [['aadhaar', []]]],
    [/^uan$/, [['uan', []]]],
    [/^esic$/, [['esic', []]]],
    [/^voter_id$/, [['voter', []]]],
    [/^passport$/, [['passport', []]]],
    [/^driving_licence$/, [['dl', []]]],
    [/^latitude$/, [['number', []], ['between', ['-90', '90']]]],
    [/^longitude$/, [['number', []], ['between', ['-180', '180']]]],
    [/(^|_)(percent|percentage)$/, [['between', ['0', '100']]]],
    [/(price|salary|amount|allowance|^hra$|limit|balance)$/, [['number', []], ['min', ['0']], ['decimals', ['2']], ['max', ['999999999']]]],
    [/^experience_years$/, [['number', []], ['between', ['0', '60']]]],
    [/^(first_name|middle_name|last_name|emergency_name)$/, [['person_name', []]]],
    [/^(website|url|.*_url|.*_link)$/, [['url', []]]],
];

const KEY_MAXLENGTH = { city: 120, district: 120, state: 120, country: 120 };
/** Exact/maximum lengths of structured identifiers (separators are stripped while typing). */
const RULE_MAXLENGTH = { pincode: 6, aadhaar: 12, gstin: 15, pan: 10, tan: 10, cin: 21, ifsc: 11, uan: 12, esic: 17, account: 18, swift: 11, voter: 10, passport: 8, dl: 15, udyam: 19 };
/** Name scopes holding free-text descriptions where key-based inference would be wrong (e.g. assets_access[mobile] = "Samsung A14"). */
const NO_INFER_SCOPES = new Set(['assets_access']);

function nativeRules(ctrl, add) {
    const el = ctrl.el;
    if (el.hasAttribute('minlength')) add('minlength', [el.getAttribute('minlength')]);
    if (el.type === 'email') add('email', []);
    if (el.type === 'url') add('url', []);
    if (el.type === 'number') {
        add('number', []);
        if (el.min !== '') add('min', [el.min]);
        if (el.max !== '') add('max', [el.max]);
        const step = el.getAttribute('step');
        if (step === null) add('integer', []);
        else if (step !== 'any') add('step', [step, el.min || '0']);
    } else if (['date', 'datetime-local', 'month', 'time'].includes(el.type)) {
        add('datetime', []);
        if (el.min) add('after_or_equal', [el.min]);
        if (el.max) add('before_or_equal', [el.max]);
    }
    if (el.pattern && TEXTLIKE.has(el.type)) add('pattern', [el.pattern]);
    if (el.hasAttribute('maxlength')) add('maxlength', [el.getAttribute('maxlength')]);
}

/** Returns { map: Map<rule,args>, cross: bool }. Explicit data-v beats inferred beats native. */
function rulesFor(ctrl) {
    const el = ctrl.el;
    const map = new Map();
    const add = (name, args = []) => { if (!map.has(name)) map.set(name, args); };

    parseRuleString(el.dataset.v).forEach(([name, args]) => add(name, args));

    if (el.dataset.vInfer !== 'off' && !NO_INFER_SCOPES.has(el.name.split('[')[0])) {
        const key = keyOf(el.name);
        if (/^contacts\[\d+\]\[name\]$|\[contacts\]\[\d+\]\[name\]$/.test(el.name)) add('person_name');
        const confirm = /^(.+)_confirmation$/.exec(key);
        if (confirm) add('same', [confirm[1]]);
        for (const [re, list] of INFERRED) {
            if (re.test(key)) list.forEach(([n, a]) => add(n, a));
        }
        const limit = KEY_MAXLENGTH[key];
        if (limit) add('maxlength', [String(limit)]);
        if (map.has('phone') || map.has('mobile')) add('maxlength', ['20']);
        for (const name of map.keys()) if (RULE_MAXLENGTH[name]) add('maxlength', [String(RULE_MAXLENGTH[name])]);
        if (el.autocomplete === 'new-password' || (key === 'password' && el.form?.elements?.password_confirmation)) add('new_password');
    }

    if (ctrl.kind === 'text' || ctrl.kind === 'textarea') nativeRules(ctrl, add);

    if (ctrl.kind === 'textarea' || (ctrl.kind === 'text' && TEXTLIKE.has(el.type) && el.type !== 'password')) add('safe_text');
    if (ctrl.kind === 'text' && TEXTLIKE.has(el.type) && el.type !== 'password') add('maxlength', [String(DEFAULT_TEXT_MAX)]);
    if (ctrl.kind === 'textarea') add('maxlength', [String(DEFAULT_TEXTAREA_MAX)]);

    // pan inside a form that also has a gstin sibling must match the gstin
    if (map.has('pan') && !map.has('pan_matches_gstin') && resolveRef(el, 'gstin')) add('pan_matches_gstin', ['gstin']);

    let cross = false;
    for (const name of map.keys()) if (CROSS_FIELD_RULES.has(name) || name.startsWith('required_')) cross = true;
    if (el.hasAttribute('required') || map.has('required')) map.set('required', []);
    return { map, cross };
}

/* ------------------------------------------------------------------ */
/* Values, references, context                                         */
/* ------------------------------------------------------------------ */

function resolveRef(el, name) {
    if (!name) return null;
    if (name.startsWith('#')) return document.getElementById(name.slice(1));
    const form = el.form;
    if (!form) return null;
    const scoped = /^(.*)\[[^\]]*\]$/.exec(el.name);
    const candidates = [...(scoped ? [`${scoped[1]}[${name}]`] : []), name, `${name}[]`];
    for (const wanted of candidates) {
        const found = [...form.elements].find((e) => isField(e) && e.name === wanted);
        if (found) return found;
    }
    return null;
}

function stringValue(ctrl) {
    const el = ctrl.el;
    switch (ctrl.kind) {
        case 'checkbox': return el.checked ? el.value || 'on' : '';
        case 'checkbox-group': return ctrl.group.filter((e) => e.checked).map((e) => e.value).join(',');
        case 'radio': return ctrl.group.find((e) => e.checked)?.value ?? '';
        case 'select-multiple': return [...el.selectedOptions].map((o) => o.value).filter(Boolean).join(',');
        case 'file': return [...(el.files ?? [])].map((f) => f.name).join(',');
        default: return el.type === 'password' ? el.value : el.value.trim();
    }
}

function valueFor(ctrl) {
    switch (ctrl.kind) {
        case 'checkbox-group': return ctrl.group.filter((e) => e.checked).map((e) => e.value);
        case 'select-multiple': return [...ctrl.el.selectedOptions].map((o) => o.value).filter((v) => v !== '');
        case 'file': return [...(ctrl.el.files ?? [])];
        default: return stringValue(ctrl);
    }
}

const isEmpty = (value) => (Array.isArray(value) ? value.length === 0 : value === '');

function refInfo(el, name) {
    const target = resolveRef(el, name);
    if (!target) return null;
    const ctrl = controlOf(target);
    return { el: target, value: stringValue(ctrl), label: labelInfo(ctrl).text, type: target.type, checked: ctrl.kind === 'checkbox' ? target.checked : stringValue(ctrl) !== '' };
}

function makeContext(ctrl) {
    const el = ctrl.el;
    return {
        el,
        label: labelInfo(ctrl).text,
        type: el.type,
        ref: (name) => refInfo(el, name),
        others: (group) => {
            const scope = el.form ? [...el.form.elements] : [];
            return scope
                .filter((e) => isField(e) && e !== el && e.dataset.v?.includes(`unique_in:${group}`) && isApplicable(e, el.form) && e.value.trim() !== '')
                .map((e) => e.value);
        },
    };
}

/* ------------------------------------------------------------------ */
/* Core validation                                                     */
/* ------------------------------------------------------------------ */

const lower = (list) => list.map((v) => v.toLowerCase());

function requiredNow(ctrl, map, ctx) {
    if (map.has('required') || ctrl.el.dataset.vOptional === undefined && labelInfo(ctrl).star) return true;
    for (const [name, args] of map) {
        if (!name.startsWith('required_')) continue;
        const [first, ...rest] = args;
        const ref = ctx.ref(first);
        switch (name) {
            case 'required_if': if (ref && lower(rest).includes(ref.value.toLowerCase())) return true; break;
            case 'required_unless': if (ref && !lower(rest).includes(ref.value.toLowerCase())) return true; break;
            case 'required_with': if (args.some((a) => ctx.ref(a)?.value)) return true; break;
            case 'required_without': if (args.some((a) => !ctx.ref(a)?.value)) return true; break;
            case 'required_if_checked': if (ref?.checked) return true; break;
            default: break;
        }
    }
    return false;
}

function requiredMessage(ctrl, label) {
    switch (ctrl.kind) {
        case 'select': case 'select-multiple': return `Please select ${label}.`;
        case 'radio': return `Please choose ${label}.`;
        case 'checkbox': return `Please tick “${label}” to continue.`;
        case 'checkbox-group': return `Select at least one option for ${label}.`;
        case 'file': return `Please choose a file for ${label}.`;
        default: return `${label} is required.`;
    }
}

const customMessage = (el, rule) => el.getAttribute(`data-msg-${rule.replace(/_/g, '-')}`) ?? el.getAttribute('data-msg');

function fileMessage(ctrl, files, label) {
    const el = ctrl.el;
    const st = stateOf(el);
    const accept = (el.getAttribute('accept') || '').split(',').map((t) => t.trim().toLowerCase()).filter(Boolean);
    const maxKb = Number(el.dataset.maxSize || DEFAULT_MAX_FILE_KB);
    const maxFiles = Number(el.dataset.maxFiles || (el.multiple ? 20 : 1));
    if (files.length > maxFiles) return `${label}: choose at most ${maxFiles} file${maxFiles === 1 ? '' : 's'} (you selected ${files.length}).`;
    const seen = new Set();
    for (const [i, file] of files.entries()) {
        const sig = `${file.name}|${file.size}|${file.lastModified}`;
        if (seen.has(sig)) return `${label}: “${file.name}” was added twice.`;
        seen.add(sig);
        const kind = st.fileKindsFor === st.fileToken && st.fileKinds ? st.fileKinds[i] : undefined;
        const problem = checkFile(file, { accept, maxKb, kind, label });
        if (problem) return problem;
    }
    const totalKb = Number(el.dataset.maxTotal || 0);
    if (totalKb && files.reduce((s, f) => s + f.size, 0) > totalKb * 1024) return `${label}: the files together are over ${formatBytes(totalKb * 1024)}.`;
    return null;
}

/** Returns an error message for a control, or null when it is valid. */
function messageFor(ctrl, map = rulesFor(ctrl).map) {
    const el = ctrl.el;
    const ctx = makeContext(ctrl);
    const label = ctx.label;
    const value = valueFor(ctrl);

    if (el.validity?.badInput) return el.type === 'number' ? `${label} must be a number.` : `${label} is not a valid ${el.type === 'time' ? 'time' : 'date'}.`;

    if (isEmpty(value)) {
        if (!requiredNow(ctrl, map, ctx)) return null;
        if (el.type !== 'password' && el.value && !el.value.trim()) return `${label} cannot be only spaces.`;
        const conditional = [...map.keys()].filter((k) => k.startsWith('required_')).map((k) => customMessage(el, k)).find(Boolean);
        return customMessage(el, 'required') ?? conditional ?? requiredMessage(ctrl, label);
    }

    if (ctrl.kind === 'file') return fileMessage(ctrl, value, label);

    for (const [name, args] of map) {
        if (name === 'required' || name.startsWith('required_')) continue;
        const rule = RULES[name];
        if (!rule) continue;
        const isArray = Array.isArray(value);
        if (isArray !== (name === 'min_checked' || name === 'max_checked')) continue;
        const problem = rule(value, args, ctx);
        if (problem) return customMessage(el, name) ?? problem;
    }
    return null;
}

/* ------------------------------------------------------------------ */
/* Rendering                                                           */
/* ------------------------------------------------------------------ */

function anchorFor(ctrl) {
    if (ctrl.kind === 'checkbox-group' || ctrl.kind === 'radio') return commonAncestor(ctrl.group);
    if (ctrl.kind === 'checkbox') return ctrl.el.closest('.form-check, label') ?? ctrl.el;
    return ctrl.el.closest('.input-group') ?? ctrl.el;
}

/** Removes server-rendered error text (Bootstrap + Breeze markup) once the user edits the field. */
function dismissServerError(ctrl) {
    const anchor = anchorFor(ctrl);
    for (let sib = anchor.nextElementSibling; sib && sib.matches('.invalid-feedback:not(.nx-error), ul.text-red-600, ul.text-sm, form > div.error'); sib = anchor.nextElementSibling) sib.remove();
}

function setBox(ctrl, message) {
    const st = stateOf(ctrl.el);
    const anchor = anchorFor(ctrl);
    let box = st.box;
    if (!box || !box.isConnected) {
        box = document.createElement('div');
        box.className = 'nx-error';
        box.id = `${st.id}-error`;
        box.setAttribute('aria-live', 'polite');
        st.box = box;
    }
    box.textContent = message;
    if (box.previousElementSibling !== anchor) anchor.insertAdjacentElement('afterend', box);
    return box;
}

function markInvalid(ctrl, message) {
    dismissServerError(ctrl);
    const box = setBox(ctrl, message);
    ctrl.group.forEach((e) => {
        e.classList.remove('is-valid');
        e.classList.add('is-invalid');
        e.setAttribute('aria-invalid', 'true');
        const ids = new Set((e.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
        ids.add(box.id);
        e.setAttribute('aria-describedby', [...ids].join(' '));
    });
    ctrl.el.closest('.nx-field-group')?.classList.add('is-invalid-group');
    stateOf(ctrl.el).message = message;
}

function markValid(ctrl, showSuccess) {
    const st = stateOf(ctrl.el);
    st.box?.remove();
    st.message = null;
    ctrl.group.forEach((e) => {
        e.classList.remove('is-invalid');
        e.removeAttribute('aria-invalid');
        const ids = (e.getAttribute('aria-describedby') || '').split(/\s+/).filter((i) => i && i !== `${st.id}-error`);
        if (ids.length) e.setAttribute('aria-describedby', ids.join(' ')); else e.removeAttribute('aria-describedby');
        e.classList.toggle('is-valid', Boolean(showSuccess));
    });
    ctrl.el.closest('.nx-field-group')?.classList.remove('is-invalid-group');
}

const successCandidate = (ctrl) => ['text', 'textarea', 'select'].includes(ctrl.kind) && ctrl.el.dataset.vSuccess !== 'off' && !isEmpty(valueFor(ctrl)) && ctrl.el.type !== 'password';

/** Validate one control and paint the result. Returns the error message or null. */
function checkControl(ctrl) {
    const st = stateOf(ctrl.el);
    const { map } = rulesFor(ctrl);
    const message = messageFor(ctrl, map);
    ctrl.el.setAttribute('aria-required', map.has('required') || labelInfo(ctrl).star ? 'true' : 'false');
    if (message) markInvalid(ctrl, message); else markValid(ctrl, st.touched && successCandidate(ctrl));
    return message;
}

function clearControl(ctrl) {
    stateOf(ctrl.el).touched = false;
    markValid(ctrl, false);
    ctrl.group.forEach((e) => e.classList.remove('is-valid'));
}

/* ------------------------------------------------------------------ */
/* Reveal / focus / summary                                            */
/* ------------------------------------------------------------------ */

export function reveal(el) {
    for (let n = el.parentElement; n; n = n.parentElement) if (n.tagName === 'DETAILS' && !n.open) n.open = true;
    const collapsed = el.closest('.collapse:not(.show)');
    if (collapsed && window.bootstrap?.Collapse) window.bootstrap.Collapse.getOrCreateInstance(collapsed, { toggle: false }).show();
    const tab = el.closest('.tab-pane:not(.active)');
    if (tab?.id && window.bootstrap?.Tab) {
        const trigger = document.querySelector(`[data-bs-target="#${tab.id}"],a[href="#${tab.id}"]`);
        if (trigger) window.bootstrap.Tab.getOrCreateInstance(trigger).show();
    }
    const pane = el.closest('.nx-step-pane');
    if (pane && !pane.classList.contains('active')) {
        const event = new CustomEvent('nx:reveal', { bubbles: true, cancelable: true, detail: { field: el, pane, step: pane.dataset.pane } });
        el.dispatchEvent(event);
        if (!event.defaultPrevented) document.querySelector(`.nx-step[data-step="${pane.dataset.pane}"]`)?.click();
    }
}

export function focusField(el) {
    reveal(el);
    window.setTimeout(() => {
        el.focus({ preventScroll: true });
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 60);
}

function markSteps(form) {
    const failedPanes = new Set(controlsIn(form).filter((c) => stateOf(c.el).message).map((c) => c.el.closest('.nx-step-pane')?.dataset.pane).filter(Boolean));
    form.querySelectorAll('.nx-step[data-step]').forEach((step) => {
        step.classList.toggle('error', failedPanes.has(step.dataset.step));
    });
}

const summaryWanted = (form, count) => form.hasAttribute('data-v-summary') || form.querySelector('.nx-step-pane') || count >= 8;

function renderSummary(form, failures) {
    form.querySelector(':scope > .nx-error-summary')?.remove();
    if (!failures.length || !summaryWanted(form, controlsIn(form).length)) return;
    const box = document.createElement('div');
    box.className = 'nx-error-summary alert alert-danger';
    box.setAttribute('role', 'alert');
    box.tabIndex = -1;
    const title = document.createElement('strong');
    title.textContent = failures.length === 1 ? 'Please fix 1 field before continuing:' : `Please fix ${failures.length} fields before continuing:`;
    const list = document.createElement('ul');
    list.className = 'mb-0 mt-2 ps-3 small';
    failures.slice(0, 8).forEach(({ ctrl, message }) => {
        const item = document.createElement('li');
        const link = document.createElement('a');
        link.href = '#';
        link.textContent = message;
        link.addEventListener('click', (e) => { e.preventDefault(); focusField(ctrl.el); });
        item.append(link);
        list.append(item);
    });
    if (failures.length > 8) {
        const more = document.createElement('li');
        more.textContent = `…and ${failures.length - 8} more highlighted below.`;
        list.append(more);
    }
    box.append(title, list);
    form.prepend(box);
}

function toast(count) {
    const title = count === 1 ? 'Please fix the highlighted field' : `Please fix ${count} highlighted fields`;
    if (window.Swal) window.Swal.fire({ toast: true, position: 'top-end', icon: 'error', title, showConfirmButton: false, timer: 3500, timerProgressBar: true });
}

/* ------------------------------------------------------------------ */
/* Public validation entry points                                      */
/* ------------------------------------------------------------------ */

function runOn(root, { focus = true, summary = true, notify = true } = {}) {
    const form = root instanceof HTMLFormElement ? root : root.closest('form');
    const failures = [];
    controlsIn(root).forEach((ctrl) => {
        stateOf(ctrl.el).touched = true;
        const message = checkControl(ctrl);
        if (message) failures.push({ ctrl, message: `${labelInfo(ctrl).context ? `${labelInfo(ctrl).context} › ` : ''}${message}` });
    });
    if (form) {
        if (root === form) form.dataset.nxAttempted = '1';
        markSteps(form);
        if (summary && root === form) renderSummary(form, failures);
    }
    if (failures.length) {
        if (focus) focusField(failures[0].ctrl.el);
        if (notify) toast(failures.length);
    }
    return failures.length === 0;
}

export const validateForm = (form, options) => runOn(form, options);
export const validateContainer = (container, options) => runOn(container, options);

export function validateField(el, { touch = true } = {}) {
    const ctrl = controlOf(el);
    if (touch) stateOf(ctrl.el).touched = true;
    return checkControl(ctrl) === null;
}

/** Show an arbitrary (e.g. server-side) message on a field. */
export function setError(el, message) {
    const ctrl = controlOf(el);
    stateOf(ctrl.el).touched = true;
    markInvalid(ctrl, message);
}

export function clearError(el) {
    clearControl(controlOf(el));
}

export function clearAll(root) {
    controlsIn(root).forEach(clearControl);
    root.querySelectorAll('.nx-error-summary').forEach((n) => n.remove());
    root.querySelectorAll('.nx-step.error').forEach((n) => n.classList.remove('error'));
    if (root.dataset) delete root.dataset.nxAttempted;
}

/** Lists the effective rules of a field, e.g. ['mobile', 'maxlength:20'] (handy in the console). */
export function describe(el) {
    return [...rulesFor(controlOf(el)).map].map(([name, args]) => (args.length ? `${name}:${args.join(',')}` : name));
}

/** Registers a custom rule: NxValidate.extend('even', (v, args, ctx) => v % 2 ? `${ctx.label} must be even.` : null) */
export function extend(name, fn, { cross = false } = {}) {
    RULES[name] = fn;
    if (cross) CROSS_FIELD_RULES.add(name);
}

/* ------------------------------------------------------------------ */
/* Input hygiene (normalise while typing / on blur)                    */
/* ------------------------------------------------------------------ */

const hasAny = (map, set) => [...map.keys()].some((k) => set.has(k));

function transformWhileTyping(text, map, upper = false) {
    let out = text;
    if (hasAny(map, UPPERCASE_RULES)) out = out.toUpperCase().replace(map.has('udyam') ? /\s+/g : /[\s-]+/g, '');
    else if (upper) out = out.toUpperCase();
    if (hasAny(map, DIGITS_ONLY_RULES)) out = out.replace(/\D/g, '');
    if (map.has('phone') || map.has('mobile')) out = out.replace(/[^\d+\s().-]/g, '');
    return out;
}

function normaliseOnBlur(el, map) {
    if (el.type === 'password' || el.type === 'file' || !(TEXTLIKE.has(el.type) || el.tagName === 'TEXTAREA')) return;
    let next = el.value;
    if (el.tagName === 'TEXTAREA') next = next.replace(/[ \t]+$/gm, '').trim();
    else {
        next = next.trim();
        if (el.type === 'email' || map.has('email')) next = next.replace(/\s+/g, '').toLowerCase();
        else if (el.type !== 'url') next = next.replace(/\s{2,}/g, ' ');
    }
    if (hasAny(map, STRIP_SEPARATOR_RULES)) next = next.replace(/[\s-]/g, (c) => (map.has('udyam') && c === '-' ? c : ''));
    if (hasAny(map, UPPERCASE_RULES) || el.dataset.upper !== undefined) next = next.toUpperCase();
    if (map.has('url') && next && !/^[a-z][a-z0-9+.-]*:/i.test(next)) next = `https://${next}`;
    if (next !== el.value) el.value = next;
}

function setValueKeepCaret(el, next, rawCaret) {
    el.value = next;
    if (rawCaret !== null && rawCaret !== undefined) {
        try { el.setSelectionRange(rawCaret, rawCaret); } catch { /* number/email inputs have no selection API */ }
    }
}

function handleInput(el) {
    const ctrl = controlOf(el);
    const { map } = rulesFor(ctrl);
    const upper = el.dataset.upper !== undefined;
    if (ctrl.kind === 'text' && el.type !== 'password' && (upper || hasAny(map, UPPERCASE_RULES) || hasAny(map, DIGITS_ONLY_RULES) || map.has('phone') || map.has('mobile'))) {
        const caret = (() => { try { return el.selectionStart; } catch { return null; } })();
        const next = transformWhileTyping(el.value, map, upper);
        if (next !== el.value) setValueKeepCaret(el, next, caret === null ? null : transformWhileTyping(el.value.slice(0, caret), map, upper).length);
    }
    dismissServerError(ctrl);
    const st = stateOf(ctrl.el);
    if (st.touched || ctrl.group.some((e) => e.classList.contains('is-invalid')) || el.form?.dataset.nxAttempted) {
        st.touched = true;
        checkControl(ctrl);
        revalidateDependants(el);
    }
}

function revalidateDependants(changed) {
    const form = changed.form;
    if (!form) return;
    controlsIn(form).forEach((ctrl) => {
        if (ctrl.el === changed || ctrl.group.includes(changed)) return;
        const st = stateOf(ctrl.el);
        if (!(st.touched || st.message || form.dataset.nxAttempted)) return;
        if (rulesFor(ctrl).cross) checkControl(ctrl);
    });
}

/* ------------------------------------------------------------------ */
/* Files                                                               */
/* ------------------------------------------------------------------ */

function inspectFiles(el) {
    const st = stateOf(el);
    const files = [...(el.files ?? [])];
    const token = ++st.fileToken;
    st.fileKinds = null;
    if (!files.length) return;
    st.pending = Promise.all(files.map(async (file) => {
        try {
            return sniffKind(new Uint8Array(await file.slice(0, 16).arrayBuffer()));
        } catch {
            return 'unreadable';
        }
    })).then((kinds) => {
        if (st.fileToken !== token) return;
        st.fileKinds = kinds;
        st.fileKindsFor = token;
        if (st.touched || el.form?.dataset.nxAttempted) checkControl(controlOf(el));
    }).finally(() => { if (st.fileToken === token) st.pending = null; });
}

const pendingChecks = (root) => controlsIn(root).map((c) => stateOf(c.el).pending).filter(Boolean);

/* ------------------------------------------------------------------ */
/* Submit handling                                                     */
/* ------------------------------------------------------------------ */

const LOCK_SAFETY_MS = 20000;
let bypass = false;

function lockForm(form, submitter) {
    if (form.target && form.target !== '_self') return;
    if (form.hasAttribute('data-v-nolock') || form.dataset.nxLocked) return;
    form.dataset.nxLocked = '1';
    const buttons = [...form.querySelectorAll('button[type=submit],button:not([type]),input[type=submit]')];
    if (submitter && !buttons.includes(submitter)) buttons.push(submitter);
    window.setTimeout(() => {
        buttons.forEach((b) => {
            if (b.disabled) return;
            b.dataset.nxWasDisabled = '0';
            b.disabled = true;
            if (b === submitter && b.tagName === 'BUTTON' && !b.querySelector('.nx-spinner')) {
                const spinner = document.createElement('span');
                spinner.className = 'nx-spinner spinner-border spinner-border-sm';
                spinner.setAttribute('aria-hidden', 'true');
                b.prepend(spinner);
            }
        });
    }, 0);
    window.setTimeout(() => unlockForm(form), LOCK_SAFETY_MS);
}

function unlockForm(form) {
    delete form.dataset.nxLocked;
    form.querySelectorAll('[data-nx-was-disabled]').forEach((b) => {
        b.disabled = false;
        delete b.dataset.nxWasDisabled;
        b.querySelector('.nx-spinner')?.remove();
    });
}

function onSubmitCapture(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.hasAttribute('data-v-ignore') || bypass) return;
    if (event.submitter?.hasAttribute('formnovalidate') || event.submitter?.formNoValidate) return;
    if (form.dataset.nxLocked) { event.preventDefault(); event.stopImmediatePropagation(); return; }

    const pending = pendingChecks(form);
    if (pending.length) {
        event.preventDefault();
        event.stopImmediatePropagation();
        const submitter = event.submitter;
        Promise.allSettled(pending).then(() => {
            if (!validateForm(form)) return;
            bypass = true;
            try { form.requestSubmit(submitter?.form === form ? submitter : undefined); } finally { bypass = false; }
        });
        return;
    }
    if (!validateForm(form)) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}

function onSubmitBubble(event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented || form.hasAttribute('data-v-ignore')) return;
    form.querySelector(':scope > .nx-error-summary')?.remove();
    lockForm(form, event.submitter);
}

/* ------------------------------------------------------------------ */
/* Enhancements applied to forms / fields as they appear               */
/* ------------------------------------------------------------------ */

function addStrengthMeter(el) {
    const st = stateOf(el);
    if (st.meter) return;
    const meter = document.createElement('div');
    meter.className = 'nx-strength';
    meter.setAttribute('aria-hidden', 'true');
    meter.innerHTML = '<span class="nx-strength-bar"><i></i><i></i><i></i><i></i></span><span class="nx-strength-text"></span>';
    (el.closest('.input-group') ?? el).insertAdjacentElement('afterend', meter);
    st.meter = meter;
    const labels = ['Too weak', 'Weak', 'Fair', 'Good', 'Strong'];
    const paint = () => {
        const score = el.value ? passwordScore(el.value) : -1;
        meter.dataset.score = String(score);
        meter.querySelector('.nx-strength-text').textContent = score < 0 ? '' : labels[score];
    };
    el.addEventListener('input', paint);
    paint();
}

function addCounter(el) {
    const st = stateOf(el);
    if (st.counter || !el.maxLength || el.maxLength < 50) return;
    const counter = document.createElement('div');
    counter.className = 'nx-counter';
    counter.hidden = true;
    el.insertAdjacentElement('afterend', counter);
    st.counter = counter;
    const paint = () => {
        const used = [...el.value].length;
        counter.hidden = used < el.maxLength * 0.8;
        counter.textContent = `${used} / ${el.maxLength}`;
        counter.classList.toggle('nx-counter-full', used >= el.maxLength);
    };
    el.addEventListener('input', paint);
    paint();
}

function prepareField(el) {
    if (!isField(el) || el.hasAttribute('data-v-skip') || el.closest('template')) return;
    const ctrl = controlOf(el);
    if (!['text', 'textarea'].includes(ctrl.kind)) return;
    const { map } = rulesFor(ctrl);
    if (ctrl.kind === 'text' && TEXTLIKE.has(el.type) && el.type !== 'password' && !el.hasAttribute('maxlength')) {
        el.maxLength = Number(map.get('maxlength')?.[0] ?? DEFAULT_TEXT_MAX);
    }
    if (ctrl.kind === 'textarea') {
        if (!el.hasAttribute('maxlength')) el.maxLength = Number(map.get('maxlength')?.[0] ?? DEFAULT_TEXTAREA_MAX);
        addCounter(el);
    }
    if (ctrl.kind === 'text') {
        if (hasAny(map, UPPERCASE_RULES)) { el.autocapitalize = 'characters'; el.spellcheck = false; if (!el.autocomplete) el.autocomplete = 'off'; }
        if (hasAny(map, DIGITS_ONLY_RULES) && !el.inputMode) el.inputMode = 'numeric';
        if ((map.has('phone') || map.has('mobile')) && el.type === 'text') { el.inputMode = 'tel'; if (!el.autocomplete) el.autocomplete = 'tel'; }
        if (map.has('email') && !el.inputMode) el.inputMode = 'email';
        if (map.has('new_password')) addStrengthMeter(el);
    }
}

function prepare(root) {
    const forms = root instanceof HTMLFormElement ? [root] : [...(root.querySelectorAll?.('form') ?? [])];
    if (root.closest?.('form') && !(root instanceof HTMLFormElement)) forms.push(root.closest('form'));
    forms.forEach((form) => { if (!form.hasAttribute('data-v-ignore')) form.noValidate = true; });
    const fields = root instanceof HTMLElement && isField(root) ? [root] : [...(root.querySelectorAll?.('input,select,textarea') ?? [])];
    fields.forEach(prepareField);
}

/* ------------------------------------------------------------------ */
/* Global listeners                                                    */
/* ------------------------------------------------------------------ */

const IGNORED_BLUR_TARGETS = 'a,[data-bs-dismiss],[data-v-cancel],.btn-close,[data-bs-toggle="tab"]';

function bind() {
    document.addEventListener('submit', onSubmitCapture, true);
    document.addEventListener('submit', onSubmitBubble, false);
    document.addEventListener('invalid', (e) => { if (!e.target.form?.hasAttribute('data-v-ignore')) e.preventDefault(); }, true);
    document.addEventListener('pointerdown', (e) => {
        pointerTarget = e.target;
        window.setTimeout(() => { pointerTarget = null; }, 80);
    }, true);

    document.addEventListener('input', (e) => { if (isField(e.target) && e.target.type !== 'file') handleInput(e.target); });

    document.addEventListener('change', (e) => {
        const el = e.target;
        if (!isField(el)) return;
        if (el.type === 'file') {
            stateOf(el).touched = true;
            inspectFiles(el);
        }
        if (el.type === 'checkbox' || el.type === 'radio' || el.tagName === 'SELECT' || el.type === 'file' || ['date', 'datetime-local', 'time', 'month'].includes(el.type)) {
            const ctrl = controlOf(el);
            const st = stateOf(ctrl.el);
            dismissServerError(ctrl);
            st.touched = true;
            checkControl(ctrl);
            revalidateDependants(el);
        }
    });

    document.addEventListener('focusout', (e) => {
        const el = e.target;
        if (!isField(el) || el.type === 'file' || !isApplicable(el, el.form || document)) return;
        const ctrl = controlOf(el);
        normaliseOnBlur(el, rulesFor(ctrl).map);
        if (pointerTarget?.closest?.(IGNORED_BLUR_TARGETS) && !stateOf(ctrl.el).message) return; // don't shift layout under a click on Cancel / links
        stateOf(ctrl.el).touched = true;
        checkControl(ctrl);
        revalidateDependants(el);
    });

    document.addEventListener('paste', (e) => {
        const el = e.target;
        if (!isField(el) || el.type === 'password' || !(TEXTLIKE.has(el.type))) return;
        const { map } = rulesFor(controlOf(el));
        if (!(hasAny(map, UPPERCASE_RULES) || hasAny(map, DIGITS_ONLY_RULES) || map.has('phone') || map.has('mobile'))) return;
        const text = e.clipboardData?.getData('text');
        if (text === undefined) return;
        e.preventDefault();
        const cleaned = transformWhileTyping(text.replace(/[\s-]+/g, (m) => (map.has('phone') || map.has('mobile') ? ' ' : (map.has('udyam') ? m : ''))), map);
        try {
            el.setRangeText(cleaned, el.selectionStart ?? el.value.length, el.selectionEnd ?? el.value.length, 'end');
        } catch {
            el.value = cleaned;
        }
        el.dispatchEvent(new Event('input', { bubbles: true }));
    });

    document.addEventListener('keydown', (e) => {
        const el = e.target;
        if (!(el instanceof HTMLInputElement) || el.type !== 'number' || e.ctrlKey || e.metaKey) return;
        if (e.key === 'e' || e.key === 'E' || e.key === '+') e.preventDefault();
        if (e.key === '-' && el.min !== '' && Number(el.min) >= 0) e.preventDefault();
    });

    document.addEventListener('wheel', (e) => {
        if (e.target instanceof HTMLInputElement && e.target.type === 'number' && document.activeElement === e.target) e.target.blur();
    }, { passive: true });

    document.addEventListener('reset', (e) => { if (e.target instanceof HTMLFormElement) window.setTimeout(() => clearAll(e.target), 0); }, true);
    document.addEventListener('hidden.bs.modal', (e) => { e.target.querySelectorAll('form').forEach((form) => { clearAll(form); unlockForm(form); }); });
    window.addEventListener('pageshow', (e) => { if (e.persisted) document.querySelectorAll('form').forEach(unlockForm); });

    new MutationObserver((records) => {
        for (const record of records) {
            record.addedNodes.forEach((node) => { if (node.nodeType === 1 && !(node instanceof SVGElement)) prepare(node); });
        }
    }).observe(document.body, { childList: true, subtree: true });
}

function start() {
    if (window.NxValidate) return;
    window.NxValidate = { config, rules: RULES, validateForm, validateContainer, validateField, setError, clearError, clearAll, reveal, focusField, extend, describe, keyOf };
    prepare(document.body);
    bind();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();

