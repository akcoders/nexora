document.addEventListener('DOMContentLoaded', () => {
    const editor = document.querySelector('[data-role-editor]');
    if (!editor) return;

    const form = editor.querySelector('[data-permission-form]');
    const roleSelect = editor.querySelector('[data-role-select]');
    const selectedRoleUrl = roleSelect?.value;
    const status = editor.querySelector('[data-save-status]');
    const count = editor.querySelector('[data-granted-count]');
    let dirty = false;

    const permissionsForModule = (module) => [...editor.querySelectorAll('[data-permission-input]')]
        .filter((input) => input.value.startsWith(`${module}.`));

    const sync = () => {
        editor.querySelectorAll('[data-permission-row]').forEach((row) => {
            const inputs = permissionsForModule(row.dataset.module);
            const all = row.querySelector('[data-row-all]');
            all.checked = inputs.length > 0 && inputs.every((input) => input.checked);
            all.indeterminate = !all.checked && inputs.some((input) => input.checked);
        });

        editor.querySelectorAll('[data-permission-section]').forEach((section) => {
            const inputs = [...section.querySelectorAll('[data-permission-input]')];
            const all = section.querySelector('[data-section-all]');
            all.checked = inputs.length > 0 && inputs.every((input) => input.checked);
            all.indeterminate = !all.checked && inputs.some((input) => input.checked);
        });

        if (count) count.textContent = editor.querySelectorAll('[data-permission-input]:checked').length;
        if (status && dirty) {
            status.classList.add('is-dirty');
            status.textContent = 'Unsaved changes';
        }
    };

    editor.addEventListener('change', (event) => {
        const target = event.target;
        if (target === roleSelect) {
            if (dirty && !window.confirm('You have unsaved permission changes. Switch roles without saving?')) {
                roleSelect.value = selectedRoleUrl;
                return;
            }
            dirty = false;
            window.location.assign(roleSelect.value);
            return;
        }

        if (target.matches('[data-section-all]')) {
            target.closest('[data-permission-section]').querySelectorAll('[data-permission-input]:not(:disabled)').forEach((input) => { input.checked = target.checked; });
        } else if (target.matches('[data-row-all]')) {
            permissionsForModule(target.closest('[data-permission-row]').dataset.module).filter((input) => !input.disabled).forEach((input) => { input.checked = target.checked; });
        } else if (!target.matches('[data-permission-input], #roleName')) {
            return;
        }
        dirty = true;
        sync();
    });

    form?.addEventListener('input', (event) => {
        if (event.target.matches('#roleName')) { dirty = true; sync(); }
    });
    form?.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
    sync();
});
