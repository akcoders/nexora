/**
 * Knowledge library guide editor.
 *
 *  - Quill 2 (snow theme) for the written steps. Images picked from the toolbar, pasted or
 *    dropped are uploaded to the server and inserted by URL; the video button embeds a
 *    YouTube link. The HTML is copied into the hidden "body" field on submit (the server
 *    sanitises it).
 *  - "Videos, documents & links" builder: add / remove / reorder rows, a live YouTube
 *    thumbnail for video links, and "Replace" for documents that are already uploaded.
 *  - Warns before leaving the page with unsaved changes, and shows a floating Save bar
 *    whenever the Publish card's Save button is scrolled out of view.
 */
import Quill from 'quill';
import 'quill/dist/quill.snow.css';
import { followViewport } from './library-layout';

const Delta = Quill.import('delta');
const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
const YOUTUBE_HINT = 'Use a YouTube video link, e.g. youtu.be/… or youtube.com/watch?v=…';

/** The 11-character id from any common YouTube link (mirrors LibraryOptions::youtubeId). */
export function youtubeId(value) {
    let url = String(value ?? '').trim();
    if (!url) return null;
    if (!/^https?:\/\//i.test(url)) url = `https://${url}`;
    let parsed;
    try { parsed = new URL(url); } catch { return null; }
    const host = parsed.hostname.toLowerCase().replace(/^(www\.|m\.|music\.)/, '');
    const path = parsed.pathname;
    let id = null;
    if (host === 'youtu.be') {
        id = path.replace(/^\/+/, '').split('/')[0];
    } else if (host === 'youtube.com' || host === 'youtube-nocookie.com') {
        if (path === '/watch') id = parsed.searchParams.get('v');
        else id = path.match(/^\/(shorts|embed|live|v)\/([^/?]+)/)?.[2] ?? null;
    }
    return id && /^[A-Za-z0-9_-]{11}$/.test(id) ? id : null;
}

function toast(icon, title) {
    if (window.Swal) {
        // titleText, not title: messages can contain file names, which must stay plain text.
        window.Swal.fire({ toast: true, position: 'top-end', icon, titleText: title, showConfirmButton: false, timer: 4000, timerProgressBar: true });
    } else {
        window.alert(title);
    }
}

function setFieldError(field, message) {
    if (window.NxValidate?.setError) window.NxValidate.setError(field, message);
    else field.classList.add('is-invalid');
}

function clearFieldError(field) {
    if (window.NxValidate?.clearError) window.NxValidate.clearError(field);
    else field.classList.remove('is-invalid');
}

/* ------------------------------------------------------------------ */
/* Rich text editor                                                    */
/* ------------------------------------------------------------------ */

function initEditor(form, markDirty) {
    const wrap = form.querySelector('[data-lib-editor]');
    const mount = wrap?.querySelector('[data-lib-editor-mount]');
    const input = form.querySelector('[data-lib-body]');
    if (!wrap || !mount || !input) return null;

    // Stored guides use semantic checklists (<ul class="nx-checklist"><li data-checked>); Quill reads data-list.
    mount.querySelectorAll('ul.nx-checklist > li').forEach((item) => {
        item.setAttribute('data-list', item.getAttribute('data-checked') === 'true' ? 'checked' : 'unchecked');
    });

    const uploadUrl = wrap.dataset.uploadUrl;
    const maxBytes = Number(wrap.dataset.maxImageKb || 5120) * 1024;
    let quill;

    async function uploadImages(range, files) {
        let index = range ? range.index : quill.getLength() - 1;
        if (range?.length) quill.deleteText(range.index, range.length, 'user');
        wrap.classList.add('is-busy');
        try {
            for (const file of files) {
                if (file.size > maxBytes) {
                    toast('error', `“${file.name}” is larger than ${Math.round(maxBytes / 1048576)} MB.`);
                    continue;
                }
                const body = new FormData();
                body.append('image', file);
                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.url) {
                    throw new Error(data?.errors?.image?.[0] ?? (response.status === 419 ? 'Your session expired. Reload the page and try again.' : data?.message) ?? 'The image could not be uploaded.');
                }
                quill.insertEmbed(index, 'image', data.url, 'user');
                index += 1;
                quill.setSelection(index, 0, 'silent');
            }
        } catch (error) {
            toast('error', error.message || 'The image could not be uploaded.');
        } finally {
            wrap.classList.remove('is-busy');
        }
    }

    function pickImage() {
        const picker = document.createElement('input');
        picker.type = 'file';
        picker.accept = IMAGE_TYPES.join(',');
        picker.multiple = true;
        picker.addEventListener('change', () => {
            if (picker.files?.length) uploadImages(quill.getSelection(true), [...picker.files]);
        });
        picker.click();
    }

    async function addVideo() {
        const range = quill.getSelection(true);
        let link = null;
        if (window.Swal) {
            const result = await window.Swal.fire({
                title: 'Add a YouTube video',
                input: 'text',
                inputLabel: 'Paste the link of the YouTube video',
                inputPlaceholder: 'https://www.youtube.com/watch?v=…',
                showCancelButton: true,
                confirmButtonText: 'Add video',
                confirmButtonColor: '#1f5fbf',
                inputValidator: (value) => (youtubeId(value) ? null : YOUTUBE_HINT),
            });
            link = result.isConfirmed ? result.value : null;
        } else {
            link = window.prompt('Paste the link of the YouTube video');
        }
        const id = youtubeId(link);
        if (!id) {
            if (link) toast('error', 'That is not a YouTube video link.');
            return;
        }
        const index = range ? range.index : quill.getLength() - 1;
        quill.insertEmbed(index, 'video', `https://www.youtube-nocookie.com/embed/${id}?rel=0`, 'user');
        quill.setSelection(index + 1, 0, 'user');
    }

    function addLink(value) {
        if (!value) {
            quill.format('link', false, 'user');
            return;
        }
        const range = quill.getSelection(true);
        if (!range || range.length === 0) {
            toast('info', 'Select the words you want to turn into a link first.');
            return;
        }
        // Quill would put the selected words in the address box; only keep them when they already are an address.
        const text = quill.getText(range).trim();
        quill.theme.tooltip.edit('link', /^(https?:\/\/|www\.)\S+$/i.test(text) ? text : '');
    }

    quill = new Quill(mount, {
        theme: 'snow',
        // Keep the link / video bubbles inside the editor instead of under the sidebar.
        bounds: wrap,
        placeholder: 'Write the steps… e.g. 1. Switch off the power supply at the isolator.',
        modules: {
            toolbar: {
                container: [
                    [{ header: [2, 3, 4, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ color: [] }, { background: [] }],
                    [{ list: 'ordered' }, { list: 'bullet' }, { list: 'check' }],
                    [{ indent: '-1' }, { indent: '+1' }, { align: [] }],
                    ['blockquote', 'code-block'],
                    ['link', 'image', 'video'],
                    ['clean'],
                ],
                handlers: { image: pickImage, video: addVideo, link: addLink },
            },
            uploader: { mimetypes: IMAGE_TYPES, handler: uploadImages },
        },
    });

    // Images pasted as embedded data (e.g. from Word) would be dropped by the server, so skip them here.
    let skippedPaste = false;
    quill.clipboard.addMatcher('IMG', (node, delta) => {
        if ((node.getAttribute('src') || '').startsWith('data:')) {
            skippedPaste = true;
            return new Delta();
        }
        return delta;
    });
    quill.root.addEventListener('paste', () => {
        window.setTimeout(() => {
            if (skippedPaste) toast('info', 'Pasted pictures were skipped. Use the image button to upload them.');
            skippedPaste = false;
        }, 50);
    });

    const titles = {
        bold: 'Bold', italic: 'Italic', underline: 'Underline', strike: 'Strikethrough', blockquote: 'Quote / tip', 'code-block': 'Code block',
        link: 'Link', image: 'Insert image', video: 'Embed YouTube video', clean: 'Clear formatting',
    };
    const toolbar = quill.getModule('toolbar').container;
    toolbar.querySelectorAll('button').forEach((button) => {
        const format = [...button.classList].find((name) => name.startsWith('ql-'))?.slice(3);
        const label = format === 'list' ? { ordered: 'Numbered steps', bullet: 'Bullet list', check: 'Checklist' }[button.value]
            : format === 'indent' ? (button.value === '+1' ? 'Indent' : 'Outdent') : titles[format];
        if (label) { button.title = label; button.setAttribute('aria-label', label); }
    });
    wrap.querySelectorAll('.ql-tooltip input, .ql-toolbar select').forEach((field) => field.setAttribute('data-v-skip', ''));
    // Names for screen readers: Quill leaves the editing area and its pickers unlabelled.
    quill.root.setAttribute('role', 'textbox');
    quill.root.setAttribute('aria-multiline', 'true');
    quill.root.setAttribute('aria-label', 'Written steps');
    toolbar.setAttribute('role', 'toolbar');
    toolbar.setAttribute('aria-label', 'Formatting');
    const pickerNames = { 'ql-header': 'Text style', 'ql-color': 'Text colour', 'ql-background': 'Highlight colour', 'ql-align': 'Alignment' };
    Object.entries(pickerNames).forEach(([name, label]) => {
        toolbar.querySelector(`.ql-picker.${name} .ql-picker-label`)?.setAttribute('aria-label', label);
    });
    const linkBox = quill.theme.tooltip?.textbox;
    if (linkBox) {
        linkBox.dataset.link = 'https://…';
        linkBox.setAttribute('aria-label', 'Link address');
    }

    quill.on('text-change', (_delta, _old, source) => {
        if (source === 'user') {
            markDirty();
            wrap.classList.remove('is-invalid');
        }
    });
    quill.on('selection-change', (range) => wrap.classList.toggle('is-focused', Boolean(range)));

    return {
        isBlank: () => quill.getText().trim() === '' && !quill.root.querySelector('img, iframe'),
        sync() { input.value = this.isBlank() ? '' : quill.root.innerHTML; },
        focus: () => quill.focus(),
        wrap,
    };
}

/* ------------------------------------------------------------------ */
/* Videos, documents & links                                           */
/* ------------------------------------------------------------------ */

function initResources(form, markDirty) {
    const builder = form.querySelector('[data-res-builder]');
    const list = builder?.querySelector('[data-res-list]');
    if (!builder || !list) return null;
    let nextIndex = Number(builder.dataset.nextIndex || list.children.length || 0);

    const rows = () => [...list.querySelectorAll(':scope > [data-res-row]')];
    const updateMoves = () => {
        const all = rows();
        all.forEach((row, position) => {
            row.querySelector('[data-res-move="up"]').disabled = position === 0;
            row.querySelector('[data-res-move="down"]').disabled = position === all.length - 1;
        });
    };

    const paintThumb = (row) => {
        const field = row.querySelector('[data-res-youtube]');
        const thumb = row.querySelector('[data-res-thumb]');
        if (!field || !thumb) return;
        const id = youtubeId(field.value);
        if (thumb.dataset.videoId === (id ?? '')) return;
        thumb.dataset.videoId = id ?? '';
        thumb.innerHTML = id
            ? `<img src="https://i.ytimg.com/vi/${id}/hqdefault.jpg" alt="" referrerpolicy="no-referrer"><span class="lib-res-thumb-play"><i data-lucide="play"></i></span>`
            : '<i data-lucide="square-play"></i>';
        window.refreshIcons?.();
    };

    const checkYoutube = (field) => {
        if (field.value.trim() !== '' && !youtubeId(field.value)) {
            setFieldError(field, YOUTUBE_HINT);
            return false;
        }
        return true;
    };

    const prepareRow = (row) => {
        const field = row.querySelector('[data-res-youtube]');
        if (field) {
            const thumb = row.querySelector('[data-res-thumb]');
            if (thumb) thumb.dataset.videoId = youtubeId(field.value) ?? '';
            field.addEventListener('input', () => paintThumb(row));
            field.addEventListener('change', () => checkYoutube(field));
            field.addEventListener('blur', () => checkYoutube(field));
            // A link sent back after a failed save is checked straight away.
            if (field.value.trim() !== '') window.setTimeout(() => checkYoutube(field), 0);
        }
        const replace = row.querySelector('[data-res-replace]');
        const collapsible = row.querySelector('[data-res-collapsible]');
        if (replace && collapsible) {
            collapsible.hidden = true;
            replace.hidden = false;
            replace.addEventListener('click', () => {
                collapsible.hidden = false;
                replace.hidden = true;
                collapsible.querySelector('input[type="file"]')?.click();
            });
        }
    };

    const addRow = (kind) => {
        const template = builder.querySelector(`template[data-res-template="${kind}"]`);
        if (!template) return;
        const index = nextIndex++;
        const holder = document.createElement('div');
        holder.innerHTML = template.innerHTML.replaceAll('__INDEX__', String(index));
        const row = holder.firstElementChild;
        list.appendChild(row);
        prepareRow(row);
        window.refreshIcons?.();
        updateMoves();
        markDirty();
        row.querySelector(kind === 'document' ? 'input[type="file"]' : 'input[type="url"]')?.focus();
        if (kind === 'document') row.querySelector('input[type="file"]')?.click();
        row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };

    builder.querySelectorAll('[data-res-add]').forEach((button) => button.addEventListener('click', () => addRow(button.dataset.resAdd)));

    list.addEventListener('click', async (event) => {
        const button = event.target.closest('button');
        const row = button?.closest('[data-res-row]');
        if (!row) return;

        if (button.matches('[data-res-move]')) {
            const sibling = button.dataset.resMove === 'up' ? row.previousElementSibling : row.nextElementSibling;
            if (!sibling) return;
            if (button.dataset.resMove === 'up') sibling.before(row); else sibling.after(row);
            updateMoves();
            markDirty();
            button.disabled ? row.querySelector('[data-res-move]:not(:disabled)')?.focus() : button.focus();
            return;
        }

        if (button.matches('[data-res-remove]')) {
            const saved = row.querySelector('[data-res-name="id"]')?.value;
            const filled = [...row.querySelectorAll('input[type="url"], input[type="text"], input[type="file"]')].some((field) => field.value.trim() !== '');
            if (saved || filled) {
                const kind = row.querySelector('.lib-res-kind')?.textContent.trim().toLowerCase() || 'item';
                const text = saved ? `The ${kind} will be removed from the guide when you save.` : `Remove this ${kind}?`;
                const confirmed = window.Swal
                    ? (await window.Swal.fire({ icon: 'warning', title: `Remove ${kind}?`, text, showCancelButton: true, confirmButtonText: 'Remove', cancelButtonText: 'Keep', confirmButtonColor: '#b91c1c' })).isConfirmed
                    : window.confirm(text);
                if (!confirmed) return;
            }
            row.remove();
            updateMoves();
            markDirty();
        }
    });

    rows().forEach(prepareRow);
    updateMoves();

    return {
        count: () => rows().length,
        /** Rename fields to resources[0..n] in the order shown, so the saved order matches the screen. */
        reindex() {
            rows().forEach((row, position) => {
                row.querySelectorAll('[data-res-name]').forEach((field) => {
                    field.name = `resources[${position}][${field.dataset.resName}]`;
                });
            });
        },
        validate() {
            let first = null;
            rows().forEach((row) => {
                const field = row.querySelector('[data-res-youtube]');
                if (field && !checkYoutube(field)) first ??= field;
            });
            return first;
        },
        builder,
    };
}

/* ------------------------------------------------------------------ */
/* Form                                                                */
/* ------------------------------------------------------------------ */

function initForm() {
    const form = document.querySelector('[data-lib-form]');
    if (!form) return;

    let dirty = false;
    const savebar = form.querySelector('[data-lib-savebar]');
    const savebarState = savebar?.querySelector('[data-lib-savebar-state]');
    const markDirty = () => {
        if (!dirty && savebarState) savebarState.textContent = 'Unsaved changes';
        savebar?.classList.add('is-dirty');
        dirty = true;
    };
    const editor = initEditor(form, markDirty);
    const resources = initResources(form, markDirty);

    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);

    const submitLabels = form.querySelectorAll('[data-lib-submit-label]');
    const statusHint = form.querySelector('[data-lib-status-hint]');
    const isEdit = Boolean(form.querySelector('input[name="_method"][value="PUT"]'));
    form.querySelectorAll('[data-lib-status]').forEach((radio) => radio.addEventListener('change', () => {
        const draft = radio.value === 'draft' && radio.checked;
        if (!radio.checked) return;
        submitLabels.forEach((label) => { label.textContent = draft ? 'Save draft' : (isEdit ? 'Save changes' : 'Publish guide'); });
        if (statusHint) statusHint.textContent = draft ? 'Only people who can edit the library see drafts.' : 'Visible to everyone with library access, on the web and in the mobile app.';
    }));

    form.addEventListener('submit', (event) => {
        // validation.js checks the other fields first (document capture phase) and scrolls to them.
        const blockedByFields = event.defaultPrevented;
        editor?.sync();
        resources?.reindex();

        const badVideo = resources?.validate();
        if (badVideo) {
            event.preventDefault();
            if (!blockedByFields) {
                badVideo.focus();
                badVideo.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }
        if (editor && editor.isBlank() && (resources?.count() ?? 0) === 0) {
            event.preventDefault();
            editor.wrap.classList.add('is-invalid');
            toast('error', 'Write the steps, or add a video, document or link.');
            if (!blockedByFields) {
                editor.wrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
                editor.focus();
            }
            return;
        }
        if (!blockedByFields) dirty = false;
    });

    window.addEventListener('beforeunload', (event) => {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });

    followViewport(form.querySelector('.lib-form-side'));

    // The floating Save bar shows only while the Publish card's own Save button is out of sight.
    const mainSubmit = form.querySelector('[data-lib-submit]');
    if (savebar && mainSubmit && 'IntersectionObserver' in window) {
        new IntersectionObserver(([entry]) => {
            savebar.hidden = entry.isIntersecting;
            document.body.classList.toggle('lib-has-savebar', !entry.isIntersecting);
        }, { rootMargin: '-60px 0px 0px 0px' }).observe(mainSubmit);
    }
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initForm); else initForm();
