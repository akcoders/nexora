/**
 * Modal form flow helpers.
 *
 *  - Every form inside a Bootstrap modal gets a hidden `_modal` field so that when the
 *    server rejects the submission and redirects back, the layout can tell which modal
 *    was open.
 *  - On load, if `window.nxOldModal` names a modal, it is reopened, the server validation
 *    errors (`window.nxServerErrors`) are rendered inside it, the invalid fields are
 *    highlighted and the user's previous input (`window.nxOldInput`) is restored.
 *  - Pages without a modal get a generic error summary when the view did not render one.
 *  - Modals get an accent colour from `data-nx-tone` and focus the first field on open.
 */

const SKIP_OLD = new Set(['_token', '_method', '_modal', 'password', 'password_confirmation', 'current_password', 'temporary_password']);

function fieldNameFromKey(key) {
    const parts = key.split('.');
    if (parts.length === 1) return [key, `${key}[]`];
    const [head, ...rest] = parts;
    const bracket = head + rest.map((part) => `[${part}]`).join('');
    return [bracket, `${bracket}[]`, head + rest.slice(0, -1).map((part) => `[${part}]`).join('') + '[]'];
}

function findField(root, key) {
    for (const name of fieldNameFromKey(key)) {
        const el = root.querySelector(`[name="${CSS.escape(name)}"]`);
        if (el) return el;
    }
    const head = key.split('.')[0];
    return root.querySelector(`[name^="${CSS.escape(head)}["]`) || root.querySelector(`[name="${CSS.escape(head)}"]`);
}

function markErrors(root, errors) {
    let first = null;
    Object.entries(errors).forEach(([key, messages]) => {
        const el = findField(root, key);
        if (!el) return;
        first ??= el;
        const message = Array.isArray(messages) ? messages[0] : String(messages);
        if (window.NxValidate?.setError) {
            window.NxValidate.setError(el, message);
        } else {
            el.classList.add('is-invalid');
            if (!el.nextElementSibling?.classList.contains('nx-error')) {
                const note = document.createElement('div');
                note.className = 'nx-error';
                note.textContent = message;
                el.insertAdjacentElement('afterend', note);
            }
        }
    });
    return first;
}

function renderSummary(container, errors, { compact = false } = {}) {
    const messages = Object.values(errors).flat().filter(Boolean);
    if (!messages.length) return null;
    const box = document.createElement('div');
    box.className = 'alert nx-error-summary nx-server-errors' + (compact ? ' py-2 px-3 mb-3' : ' mb-4');
    box.setAttribute('role', 'alert');
    const title = document.createElement('strong');
    title.className = 'd-flex align-items-center gap-2';
    title.innerHTML = '<i data-lucide="circle-alert" style="width:17px"></i>';
    title.append(messages.length === 1 ? 'Please fix the highlighted field.' : `Please fix the ${messages.length} highlighted fields.`);
    box.appendChild(title);
    const list = document.createElement('ul');
    list.className = 'mb-0 mt-2 small ps-3';
    messages.slice(0, 8).forEach((message) => {
        const item = document.createElement('li');
        item.textContent = message;
        list.appendChild(item);
    });
    if (messages.length > 8) {
        const more = document.createElement('li');
        more.textContent = `…and ${messages.length - 8} more`;
        list.appendChild(more);
    }
    box.appendChild(list);
    container.prepend(box);
    if (window.refreshIcons) window.refreshIcons();
    return box;
}

function restoreOldInput(root, oldInput) {
    if (!oldInput || typeof oldInput !== 'object') return;
    const walk = (value, prefix) => {
        if (value && typeof value === 'object' && !Array.isArray(value)) {
            Object.entries(value).forEach(([key, inner]) => walk(inner, prefix ? `${prefix}[${key}]` : key));
            return;
        }
        if (Array.isArray(value)) {
            const multi = root.querySelectorAll(`[name="${CSS.escape(prefix + '[]')}"]`);
            if (multi.length) {
                const values = value.map(String);
                multi.forEach((el) => {
                    if (el.tagName === 'SELECT') {
                        [...el.options].forEach((option) => { option.selected = values.includes(option.value); });
                    } else if (el.type === 'checkbox') {
                        el.checked = values.includes(el.value);
                    }
                });
                return;
            }
            value.forEach((inner, index) => walk(inner, `${prefix}[${index}]`));
            return;
        }
        if (SKIP_OLD.has(prefix)) return;
        const el = root.querySelector(`[name="${CSS.escape(prefix)}"]`);
        if (!el || el.type === 'file' || el.type === 'hidden') return;
        if (el.type === 'checkbox' || el.type === 'radio') {
            root.querySelectorAll(`[name="${CSS.escape(prefix)}"]`).forEach((input) => { input.checked = String(input.value) === String(value); });
            return;
        }
        el.value = value ?? '';
    };
    walk(oldInput, '');
}

function injectModalMarker() {
    document.querySelectorAll('.modal form').forEach((form) => {
        if (form.hasAttribute('data-modal-flow-ignore')) return;
        const modal = form.closest('.modal');
        if (!modal?.id || form.querySelector('input[name="_modal"]')) return;
        const marker = document.createElement('input');
        marker.type = 'hidden';
        marker.name = '_modal';
        marker.value = modal.id;
        form.appendChild(marker);
    });
}

function decorateModals() {
    document.querySelectorAll('.modal').forEach((modal) => {
        modal.classList.add('nx-modal');
        const dialog = modal.querySelector('.modal-dialog');
        if (dialog && !dialog.classList.contains('modal-dialog-scrollable')) dialog.classList.add('modal-dialog-scrollable');
        const header = modal.querySelector('.modal-header');
        if (header && !header.querySelector('.btn-close')) {
            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'btn-close';
            close.setAttribute('data-bs-dismiss', 'modal');
            close.setAttribute('aria-label', 'Close');
            header.appendChild(close);
        }
        const footer = modal.querySelector('.modal-footer');
        if (footer && !footer.querySelector('[data-bs-dismiss="modal"]')) {
            const cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'btn btn-light border';
            cancel.setAttribute('data-bs-dismiss', 'modal');
            cancel.textContent = 'Cancel';
            footer.prepend(cancel);
        }
        modal.addEventListener('shown.bs.modal', () => {
            const field = modal.querySelector('.is-invalid, input:not([type=hidden]):not([readonly]):not([disabled]), select:not([disabled]), textarea:not([disabled])');
            field?.focus({ preventScroll: true });
        });
        modal.addEventListener('hidden.bs.modal', () => {
            modal.querySelectorAll('.nx-server-errors').forEach((el) => el.remove());
        });
    });
}

function reopenModalWithErrors() {
    const errors = window.nxServerErrors || {};
    const hasErrors = Object.keys(errors).length > 0;
    const modalId = window.nxOldModal;
    const modal = modalId ? document.getElementById(modalId) : null;

    if (modal) {
        const form = modal.querySelector('form') || modal;
        restoreOldInput(form, window.nxOldInput);
        if (hasErrors) {
            const body = modal.querySelector('.modal-body') || form;
            renderSummary(body, errors, { compact: true });
            markErrors(form, errors);
        }
        // The page-level alert from the view would otherwise duplicate the in-modal summary.
        document.querySelectorAll('.nx-content > .alert-danger').forEach((el) => el.classList.add('d-none'));
        if (window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        }
        return;
    }

    if (!hasErrors) return;
    const content = document.querySelector('.nx-content');
    if (!content) return;
    const first = markErrors(content, errors);
    const hasSummary = content.querySelector('.alert-danger, .nx-error-summary');
    if (!hasSummary) renderSummary(content, errors);
    if (first && window.NxValidate?.focusField) {
        window.setTimeout(() => window.NxValidate.focusField(first), 60);
    } else if (first) {
        first.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
}

function boot() {
    injectModalMarker();
    decorateModals();
    reopenModalWithErrors();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
