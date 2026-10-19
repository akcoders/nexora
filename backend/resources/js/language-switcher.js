/**
 * Interface language switcher backed by the free Google Translate website widget.
 *
 * The widget reads the `googtrans` cookie (`/en/<code>`) when it boots, so the flow is:
 *   1. Decide the language for this user (server preference, then cookie, then English).
 *   2. Write the cookie *before* loading translate.google.com/translate_a/element.js.
 *   3. On a switch: persist the choice to the server, rewrite the cookie and reload.
 *
 * Configuration comes from `window.nxLanguage`:
 *   { userId, preferred: 'Hindi', preferredCode: 'hi', options: { English: 'en', Hindi: 'hi' }, saveUrl, csrf }
 */

const COOKIE = 'googtrans';
const OWNER_COOKIE = 'nx_lang_uid';
const STATE_KEY = 'nx:lang:applied';

function readCookie(name) {
    const match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
}

function cookieDomains() {
    const host = window.location.hostname;
    const domains = [null];
    if (host && !/^\d+\.\d+\.\d+\.\d+$/.test(host) && host.includes('.')) {
        domains.push('.' + host.split('.').slice(-2).join('.'));
        domains.push('.' + host);
    }
    return domains;
}

function writeCookie(name, value, { maxAge = 60 * 60 * 24 * 365 } = {}) {
    cookieDomains().forEach((domain) => {
        let cookie = `${name}=${encodeURIComponent(value)}; path=/; max-age=${maxAge}; SameSite=Lax`;
        if (domain) cookie += `; domain=${domain}`;
        if (window.location.protocol === 'https:') cookie += '; Secure';
        document.cookie = cookie;
    });
}

function clearCookie(name) {
    cookieDomains().forEach((domain) => {
        let cookie = `${name}=; path=/; max-age=0; SameSite=Lax`;
        if (domain) cookie += `; domain=${domain}`;
        document.cookie = cookie;
    });
}

function currentCode() {
    const raw = readCookie(COOKIE);
    if (!raw) return 'en';
    const parts = raw.split('/').filter(Boolean);
    return parts[1] || 'en';
}

function nameForCode(options, code) {
    return Object.keys(options).find((name) => options[name] === code) || null;
}

function applyCookieFor(code) {
    if (!code || code === 'en') {
        clearCookie(COOKIE);
        return;
    }
    writeCookie(COOKIE, `/en/${code}`);
}

function loadWidget(config, code) {
    if (code === 'en' || document.getElementById('nx-google-translate-script')) return;
    const host = document.getElementById('google_translate_element') || Object.assign(document.createElement('div'), { id: 'google_translate_element' });
    host.hidden = true;
    if (!host.isConnected) document.body.appendChild(host);

    const included = Object.values(config.options || {}).filter((value) => value && value !== 'en');
    window.googleTranslateElementInit = () => {
        try {
            // eslint-disable-next-line no-new
            new window.google.translate.TranslateElement({
                pageLanguage: 'en',
                includedLanguages: included.join(','),
                autoDisplay: false,
            }, 'google_translate_element');
        } catch (error) {
            console.warn('Google Translate widget failed to initialise', error);
        }
    };

    const script = document.createElement('script');
    script.id = 'nx-google-translate-script';
    script.src = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
    script.async = true;
    script.onerror = () => document.documentElement.classList.add('nx-translate-offline');
    document.head.appendChild(script);
    document.documentElement.classList.add('nx-translating');
}

async function persist(config, name) {
    if (!config.saveUrl) return;
    try {
        await fetch(config.saveUrl, {
            method: 'PATCH',
            keepalive: true,
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': config.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ language: name }),
        });
    } catch (error) {
        console.warn('Could not save language preference', error);
    }
}

function renderSwitcher(config, activeCode) {
    const root = document.querySelector('[data-language-switcher]');
    if (!root) return;
    const options = config.options || {};
    const activeName = nameForCode(options, activeCode) || 'English';
    const label = root.querySelector('[data-language-label]');
    if (label) label.textContent = activeName;

    const menu = root.querySelector('[data-language-menu]');
    if (!menu) return;
    menu.innerHTML = '';
    Object.entries(options).forEach(([name, code]) => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'dropdown-item nx-language-option' + (code === activeCode ? ' active' : '');
        item.dataset.language = name;
        item.setAttribute('translate', 'no');
        item.classList.add('notranslate');
        item.innerHTML = `<span class="nx-language-code">${code}</span><span class="nx-language-name"></span>${code === activeCode ? '<i data-lucide="check"></i>' : ''}`;
        item.querySelector('.nx-language-name').textContent = name;
        item.addEventListener('click', () => switchTo(config, name));
        menu.appendChild(item);
    });
    if (window.refreshIcons) window.refreshIcons();
}

async function switchTo(config, name) {
    const code = (config.options || {})[name];
    if (!code) return;
    const root = document.querySelector('[data-language-switcher]');
    root?.classList.add('is-busy');
    await persist(config, name);
    writeCookie(OWNER_COOKIE, String(config.userId ?? ''));
    applyCookieFor(code);
    try { sessionStorage.setItem(STATE_KEY, code); } catch (_) { /* private mode */ }
    window.location.reload();
}

function boot() {
    const config = window.nxLanguage;
    if (!config) return;
    const options = config.options || {};
    let code = currentCode();

    // The preference saved on the server (employee master / last switch on any device) always wins,
    // so whatever language HR selected for the employee is what the app opens in.
    const serverCode = config.preferredCode || 'en';
    const wanted = Object.values(options).includes(serverCode) ? serverCode : 'en';
    if (code !== wanted || readCookie(OWNER_COOKIE) !== String(config.userId ?? '')) {
        code = wanted;
        applyCookieFor(code);
        writeCookie(OWNER_COOKIE, String(config.userId ?? ''));
    }

    document.documentElement.setAttribute('data-nx-language', code);
    renderSwitcher(config, code);
    loadWidget(config, code);

    window.NxLanguage = {
        current: () => code,
        switchTo: (name) => switchTo(config, name),
        options: () => ({ ...options }),
    };
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
