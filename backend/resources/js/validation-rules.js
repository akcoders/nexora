/**
 * Pure validation rules for the Nexora client-side validation engine.
 *
 * Nothing in this file touches the DOM. Every rule has the signature
 *   rule(value, args, ctx) => null | "error message"
 * where `ctx` is supplied by the engine (see validation.js) and provides:
 *   label            human readable field label
 *   type             input type ("text", "date", "number", ...)
 *   ref(name)        sibling lookup  -> { value, label, type } | null
 *   others(group)    values of the other fields sharing a `unique_in` group
 *
 * Keep these rules in step with the Laravel rules in the controllers; the
 * server remains the source of truth, the client only gives faster feedback.
 */

export const config = {
    defaultCountry: 'IN',
    /** Reject GSTINs whose last (check) character does not match. */
    gstinChecksum: true,
    /** Reject PANs whose 4th character is not a valid holder type. */
    panEntityCheck: true,
    minYear: 1900,
    maxYear: 2100,
};

const len = (value) => [...String(value)].length;
const num = (value) => (value === '' || value === null || value === undefined ? NaN : Number(value));
const plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;

/* ------------------------------------------------------------------ */
/* Identifier helpers                                                  */
/* ------------------------------------------------------------------ */

const BASE36 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const GST_STATE_CODES = new Set([...Array.from({ length: 38 }, (_, i) => String(i + 1).padStart(2, '0')), '97', '99']);
const GSTIN_RE = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/;
const PAN_RE = /^[A-Z]{3}[ABCFGHLJPTK][A-Z][0-9]{4}[A-Z]$/;
const PAN_LOOSE_RE = /^[A-Z]{5}[0-9]{4}[A-Z]$/;

export function gstinCheckChar(first14) {
    let sum = 0;
    for (let i = 0; i < 14; i++) {
        const code = BASE36.indexOf(first14[i]);
        const product = code * (i % 2 === 0 ? 1 : 2);
        sum += Math.floor(product / 36) + (product % 36);
    }
    return BASE36[(36 - (sum % 36)) % 36];
}

export function isGstin(value) {
    const v = String(value).toUpperCase();
    if (!GSTIN_RE.test(v) || !GST_STATE_CODES.has(v.slice(0, 2))) return false;
    if (config.panEntityCheck && !PAN_RE.test(v.slice(2, 12))) return false;
    return !config.gstinChecksum || gstinCheckChar(v) === v[14];
}

export function isPan(value) {
    return (config.panEntityCheck ? PAN_RE : PAN_LOOSE_RE).test(String(value).toUpperCase());
}

const VERHOEFF_D = [
    [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [1, 2, 3, 4, 0, 6, 7, 8, 9, 5], [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
    [3, 4, 0, 1, 2, 8, 9, 5, 6, 7], [4, 0, 1, 2, 3, 9, 5, 6, 7, 8], [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
    [6, 5, 9, 8, 7, 1, 0, 4, 3, 2], [7, 6, 5, 9, 8, 2, 1, 0, 4, 3], [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
    [9, 8, 7, 6, 5, 4, 3, 2, 1, 0],
];
const VERHOEFF_P = [
    [0, 1, 2, 3, 4, 5, 6, 7, 8, 9], [1, 5, 7, 6, 2, 8, 3, 0, 9, 4], [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
    [8, 9, 1, 6, 0, 4, 3, 5, 2, 7], [9, 4, 5, 3, 1, 2, 6, 8, 7, 0], [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
    [2, 7, 9, 3, 8, 0, 6, 4, 1, 5], [7, 0, 4, 6, 9, 1, 3, 2, 5, 8],
];

export function verhoeff(digits) {
    let c = 0;
    [...String(digits)].reverse().forEach((ch, i) => {
        c = VERHOEFF_D[c][VERHOEFF_P[i % 8][Number(ch)]];
    });
    return c === 0;
}

export function isAadhaar(value) {
    return /^[2-9][0-9]{11}$/.test(value) && verhoeff(value);
}

/* ------------------------------------------------------------------ */
/* Email / phone                                                       */
/* ------------------------------------------------------------------ */

const EMAIL_LOCAL_RE = /^[A-Za-z0-9.!#$%&'*+/=?^_`{|}~-]+$/;
const EMAIL_LABEL_RE = /^[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/;

export function isEmail(value) {
    const v = String(value);
    if (v.length > 254 || /\s/.test(v)) return false;
    const at = v.lastIndexOf('@');
    if (at < 1) return false;
    const local = v.slice(0, at);
    const domain = v.slice(at + 1);
    if (local.length > 64 || !EMAIL_LOCAL_RE.test(local) || local.startsWith('.') || local.endsWith('.') || local.includes('..')) return false;
    const labels = domain.split('.');
    return labels.length >= 2 && labels.every((l) => EMAIL_LABEL_RE.test(l)) && /^[A-Za-z]{2,24}$/.test(labels[labels.length - 1]);
}

const repeated = (digits) => /^(\d)\1+$/.test(digits);
const indianMobile = (d) => /^[6-9]\d{9}$/.test(d) && !repeated(d);

/**
 * Accepts Indian mobiles (with optional +91 / 91 / 0 prefix), Indian landlines
 * (STD-prefixed or 7-8 digit local numbers) and international E.164 numbers.
 */
export function isPhone(value, { mobileOnly = false } = {}) {
    const raw = String(value).trim();
    if (!/^\+?[0-9\s().-]+$/.test(raw)) return false;
    const digits = raw.replace(/[\s().-]/g, '').replace(/^\+/, '');
    const international = raw.startsWith('+');
    if (!/^\d+$/.test(digits) || repeated(digits)) return false;

    if (international) {
        if (digits.startsWith('91')) {
            const rest = digits.slice(2);
            return rest.length === 10 && (mobileOnly ? indianMobile(rest) : /^[1-9]\d{9}$/.test(rest));
        }
        return digits.length >= 8 && digits.length <= 15 && !digits.startsWith('0');
    }
    if (digits.length === 12 && digits.startsWith('91')) return indianMobile(digits.slice(2));
    if (digits.startsWith('0')) {
        if (/^0[6-9]\d{9}$/.test(digits)) return indianMobile(digits.slice(1));
        return !mobileOnly && /^0[1-9]\d{8,10}$/.test(digits);
    }
    if (indianMobile(digits)) return true;
    return !mobileOnly && /^[2-9]\d{6,7}$/.test(digits);
}

/* ------------------------------------------------------------------ */
/* Dates                                                               */
/* ------------------------------------------------------------------ */

function realDate(y, mo, d, h = 0, mi = 0) {
    const dt = new Date(y, mo - 1, d, h, mi);
    const ok = dt.getFullYear() === y && dt.getMonth() === mo - 1 && dt.getDate() === d && dt.getHours() === h && dt.getMinutes() === mi;
    return ok ? dt.getTime() : NaN;
}

/** Converts an input value to a comparable number (epoch ms, or minutes for time). */
export function toTime(value, type) {
    const v = String(value ?? '').trim();
    let m;
    switch (type) {
        case 'date':
            return (m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v)) ? realDate(+m[1], +m[2], +m[3]) : NaN;
        case 'datetime-local':
            return (m = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::\d{2})?$/.exec(v)) ? realDate(+m[1], +m[2], +m[3], +m[4], +m[5]) : NaN;
        case 'month':
            return (m = /^(\d{4})-(\d{2})$/.exec(v)) ? realDate(+m[1], +m[2], 1) : NaN;
        case 'time':
            return (m = /^(\d{2}):(\d{2})(?::\d{2})?$/.exec(v)) && +m[1] < 24 && +m[2] < 60 ? +m[1] * 60 + +m[2] : NaN;
        default:
            return NaN;
    }
}

const startOfToday = () => {
    const n = new Date();
    return new Date(n.getFullYear(), n.getMonth(), n.getDate()).getTime();
};

/** Resolves a bound such as "today", "now", an ISO literal or a sibling field. */
function bound(arg, ctx) {
    if (arg === 'today') return { time: startOfToday(), label: 'today' };
    if (arg === 'now') return { time: Date.now(), label: 'now' };
    const literal = toTime(arg, ctx.type);
    if (!Number.isNaN(literal)) return { time: literal, label: arg };
    const ref = ctx.ref(arg);
    if (!ref || ref.value === '') return null;
    return { time: toTime(ref.value, ref.type || ctx.type), label: ref.label };
}

const compareDates = (cmp, wording) => (value, [arg], ctx) => {
    const b = bound(arg, ctx);
    if (!b || Number.isNaN(b.time)) return null; // nothing to compare against yet
    const t = toTime(value, ctx.type);
    if (Number.isNaN(t)) return null;
    return cmp(t, b.time) ? null : `${ctx.label} must be ${wording} ${b.label}.`;
};

export function ageOn(dobTime, now = Date.now()) {
    const dob = new Date(dobTime);
    const today = new Date(now);
    let age = today.getFullYear() - dob.getFullYear();
    if (today.getMonth() < dob.getMonth() || (today.getMonth() === dob.getMonth() && today.getDate() < dob.getDate())) age--;
    return age;
}

/* ------------------------------------------------------------------ */
/* Files                                                               */
/* ------------------------------------------------------------------ */

export const EXT_GROUPS = {
    'image/*': ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'svg', 'heic'],
};

/** Detects a file kind from its first bytes. Returns null when unknown. */
export function sniffKind(bytes) {
    const b = bytes;
    const at = (i, ...sig) => sig.every((v, k) => b[i + k] === v);
    if (at(0, 0xff, 0xd8, 0xff)) return 'jpg';
    if (at(0, 0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a)) return 'png';
    if (at(0, 0x52, 0x49, 0x46, 0x46) && at(8, 0x57, 0x45, 0x42, 0x50)) return 'webp';
    if (at(0, 0x47, 0x49, 0x46, 0x38)) return 'gif';
    if (at(0, 0x25, 0x50, 0x44, 0x46)) return 'pdf';
    if (at(0, 0x50, 0x4b, 0x03, 0x04)) return 'zip'; // docx/xlsx/pptx
    if (at(0, 0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1)) return 'ole'; // legacy doc/xls
    return null;
}

const KIND_EXTENSIONS = {
    jpg: ['jpg', 'jpeg'], png: ['png'], webp: ['webp'], gif: ['gif'], pdf: ['pdf'], zip: ['docx', 'xlsx', 'pptx'], ole: ['doc', 'xls', 'ppt'],
};

export const extensionOf = (name) => (String(name).includes('.') ? String(name).split('.').pop().toLowerCase() : '');

export const formatBytes = (bytes) => (bytes >= 1048576 ? `${(bytes / 1048576).toFixed(bytes % 1048576 ? 1 : 0)} MB` : `${Math.ceil(bytes / 1024)} KB`);

/**
 * Validates one File against `accept` tokens and a size limit (KB).
 * `kind` is three-state: undefined = not inspected yet, null = inspected but
 * unrecognised, otherwise the sniffed signature from sniffKind().
 */
export function checkFile(file, { accept = [], maxKb = 0, kind, label = 'File' } = {}) {
    const ext = extensionOf(file.name);
    if (file.size === 0) return `${label}: “${file.name}” is empty.`;
    if (maxKb && file.size > maxKb * 1024) return `${label}: “${file.name}” is ${formatBytes(file.size)}; the limit is ${formatBytes(maxKb * 1024)}.`;

    if (accept.length) {
        const allowedExt = accept.flatMap((t) => (t === 'image/*' ? EXT_GROUPS['image/*'] : t.startsWith('.') ? [t.slice(1)] : []));
        const allowedMime = accept.filter((t) => t.includes('/'));
        const mimeOk = file.type && allowedMime.some((t) => (t.endsWith('/*') ? file.type.startsWith(t.slice(0, -1)) : file.type === t));
        const extOk = ext && allowedExt.includes(ext);
        const mimeExt = allowedMime.flatMap((t) => (t === 'image/jpeg' ? ['jpg', 'jpeg'] : t === 'image/png' ? ['png'] : t === 'image/webp' ? ['webp'] : t === 'application/pdf' ? ['pdf'] : []));
        const extViaMime = ext && mimeExt.includes(ext);
        if (!mimeOk && !extOk && !extViaMime) {
            const nice = [...new Set([...allowedExt, ...mimeExt])].filter(Boolean).map((e) => e.toUpperCase()).join(', ');
            return `${label}: “${file.name}” is not an allowed type${nice ? ` (allowed: ${nice})` : ''}.`;
        }
    }

    if (kind === 'unreadable') return `${label}: “${file.name}” could not be read. Choose the file again.`;
    if (kind && ext && KIND_EXTENSIONS[kind] && !KIND_EXTENSIONS[kind].includes(ext)) {
        return `${label}: “${file.name}” looks like a ${kind.toUpperCase()} file but is named .${ext}. Re-save it in the right format.`;
    }
    if (kind === null && /^(jpe?g|png|webp|gif|pdf)$/.test(ext) && file.size > 0) {
        return `${label}: “${file.name}” is not a valid ${ext.toUpperCase()} file.`;
    }
    return null;
}

/* ------------------------------------------------------------------ */
/* Passwords                                                           */
/* ------------------------------------------------------------------ */

const COMMON_PASSWORDS = new Set([
    'password', 'password1', 'password123', 'passw0rd', '12345678', '123456789', '1234567890', '11111111', '00000000', 'qwerty123',
    'qwertyuiop', 'abcd1234', 'abc12345', 'iloveyou', 'admin123', 'administrator', 'welcome1', 'welcome123', 'letmein123', 'changeme',
    'nexora123', 'classic123',
]);

export function passwordScore(value) {
    const v = String(value);
    let score = 0;
    if (v.length >= 8) score++;
    if (v.length >= 12) score++;
    if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
    if (/\d/.test(v) && /[^A-Za-z0-9]/.test(v)) score++;
    if (COMMON_PASSWORDS.has(v.toLowerCase()) || repeated(v.replace(/\D/g, '')) && /^\d+$/.test(v)) score = Math.min(score, 1);
    return Math.min(score, 4);
}

/* ------------------------------------------------------------------ */
/* Rule table                                                          */
/* ------------------------------------------------------------------ */

const pattern = (re, message) => (value, _args, ctx) => (re.test(String(value)) ? null : message(ctx.label));
const upper = (value) => String(value).toUpperCase();

export const rules = {
    /* --- text -------------------------------------------------------- */
    minlength: (v, [n], c) => (len(v) >= +n ? null : `${c.label} must be at least ${plural(+n, 'character')} (you entered ${len(v)}).`),
    maxlength: (v, [n], c) => (len(v) <= +n ? null : `${c.label} must be at most ${plural(+n, 'character')} (you entered ${len(v)}).`),
    safe_text: (v, _a, c) => (/<\/?[a-z!][^>]*>|[\u0000-\u0008\u000b\u000c\u000e-\u001f]/i.test(v) ? `${c.label} cannot contain HTML tags or control characters.` : null),
    person_name: (v, _a, c) => (/^[\p{L}\p{M}][\p{L}\p{M}\s.'’-]*$/u.test(v) ? null : `${c.label} can only contain letters, spaces, dots, hyphens and apostrophes.`),
    alpha_dash: (v, _a, c) => (/^[A-Za-z0-9_-]+$/.test(v) ? null : `${c.label} can only contain letters, numbers, dashes and underscores (no spaces).`),
    uuid: (v, _a, c) => (/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(v) ? null : `${c.label} must be a UUID like 00000000-0000-0000-0000-000000000000.`),
    alpha_num: (v, _a, c) => (/^[A-Za-z0-9]+$/.test(v) ? null : `${c.label} can only contain letters and numbers.`),
    pattern: (v, [re], c) => {
        try {
            return new RegExp(`^(?:${re})$`, 'u').test(v) ? null : `${c.label} is not in the expected format.`;
        } catch {
            return null;
        }
    },
    in: (v, args, c) => (args.includes(String(v)) ? null : `${c.label} must be one of: ${args.join(', ')}.`),
    not_in: (v, args, c) => (args.includes(String(v)) ? `${c.label} cannot be “${v}”.` : null),
    lines: (v, [maxLines = '200', maxLine = '255'], c) => {
        const lines = String(v).split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
        if (!lines.length) return `${c.label} needs at least one line.`;
        if (lines.length > +maxLines) return `${c.label} can have at most ${plural(+maxLines, 'line')} (you entered ${lines.length}).`;
        const tooLong = lines.findIndex((l) => len(l) > +maxLine);
        if (tooLong !== -1) return `Line ${tooLong + 1} of ${c.label} is longer than ${plural(+maxLine, 'character')}.`;
        const seen = new Set();
        const dupe = lines.find((l) => (seen.has(l.toLowerCase()) ? true : (seen.add(l.toLowerCase()), false)));
        return dupe ? `${c.label} has the same line twice: “${dupe}”.` : null;
    },
    unique_in: (v, [group], c) => {
        const others = c.others(group).map((x) => String(x).trim().toLowerCase());
        return others.includes(String(v).trim().toLowerCase()) ? `${c.label} “${v}” is used more than once here. Each entry must be different.` : null;
    },

    /* --- contact ------------------------------------------------------ */
    email: (v, _a, c) => (isEmail(v) ? null : `${c.label} must be a valid email address, like name@company.com.`),
    url: (v, _a, c) => {
        try {
            const u = new URL(v);
            return /^https?:$/.test(u.protocol) && u.hostname.includes('.') ? null : `${c.label} must be a full web address starting with http:// or https://.`;
        } catch {
            return `${c.label} must be a full web address starting with http:// or https://.`;
        }
    },
    phone: (v, _a, c) => (isPhone(v) ? null : `${c.label} must be a valid phone number (e.g. 98765 43210, 022 2345 6789 or +91 98765 43210).`),
    mobile: (v, _a, c) => (isPhone(v, { mobileOnly: true }) ? null : `${c.label} must be a valid 10-digit mobile number starting with 6-9 (a +91 prefix is optional).`),
    pincode: (v, _a, c) => (/^[1-9][0-9]{5}$/.test(v) ? null : `${c.label} must be a 6-digit PIN code that does not start with 0.`),

    /* --- Indian statutory / banking ids ------------------------------- */
    gstin: (v, _a, c) => {
        const g = upper(v);
        if (g.length !== 15) return `${c.label} must be exactly 15 characters (you entered ${g.length}).`;
        if (!GSTIN_RE.test(g) || !GST_STATE_CODES.has(g.slice(0, 2))) return `${c.label} is not in the GSTIN format (e.g. 27AAPFU0939F1ZV: state code, PAN, entity number, Z, check digit).`;
        if (config.panEntityCheck && !PAN_RE.test(g.slice(2, 12))) return `${c.label} contains an invalid PAN (characters 3-12).`;
        return !config.gstinChecksum || gstinCheckChar(g) === g[14] ? null : `${c.label} fails the GSTIN checksum. Please re-check the last character and the digits.`;
    },
    pan: (v, _a, c) => (isPan(v) ? null : `${c.label} must look like ABCPD1234E (5 letters, 4 digits, 1 letter; the 4th letter is the holder type).`),
    tan: pattern(/^[A-Z]{4}[0-9]{5}[A-Z]$/, (l) => `${l} must look like ABCD12345E (4 letters, 5 digits, 1 letter).`),
    cin: pattern(/^[LU][0-9]{5}[A-Z]{2}[0-9]{4}[A-Z]{3}[0-9]{6}$/, (l) => `${l} must be a 21-character CIN like U12345MH2010PTC123456.`),
    udyam: pattern(/^UDYAM-[A-Z]{2}-[0-9]{2}-[0-9]{7}$/, (l) => `${l} must look like UDYAM-MH-12-1234567.`),
    ifsc: pattern(/^[A-Z]{4}0[A-Z0-9]{6}$/, (l) => `${l} must be 11 characters: 4 letters, a zero, then 6 letters or digits (e.g. HDFC0001234).`),
    swift: pattern(/^[A-Z]{4}[A-Z]{2}[A-Z0-9]{2}([A-Z0-9]{3})?$/, (l) => `${l} must be an 8 or 11 character SWIFT/BIC code (e.g. HDFCINBB).`),
    upi: pattern(/^[A-Za-z0-9._-]{2,256}@[A-Za-z][A-Za-z0-9.-]{1,63}$/, (l) => `${l} must look like name@bank.`),
    account: pattern(/^[0-9]{9,18}$/, (l) => `${l} must be 9 to 18 digits.`),
    aadhaar: (v, _a, c) => {
        if (!/^[0-9]{12}$/.test(v)) return `${c.label} must be exactly 12 digits.`;
        return isAadhaar(v) ? null : `${c.label} is not a valid Aadhaar number (it must not start with 0 or 1 and must pass the check-digit test).`;
    },
    uan: pattern(/^[0-9]{12}$/, (l) => `${l} must be exactly 12 digits.`),
    esic: pattern(/^([0-9]{10}|[0-9]{17})$/, (l) => `${l} must be 10 or 17 digits.`),
    voter: pattern(/^[A-Z]{3}[0-9]{7}$/, (l) => `${l} must be 3 letters followed by 7 digits (e.g. ABC1234567).`),
    passport: pattern(/^[A-PR-WY][1-9][0-9]{5}[1-9]$/, (l) => `${l} must be 1 letter followed by 7 digits (e.g. K1234567).`),
    dl: pattern(/^[A-Z]{2}[0-9]{2}[0-9]{4}[0-9]{7}$/, (l) => `${l} must look like MH12 20110012345 (state, RTO, year, serial).`),
    pan_matches_gstin: (v, [ref], c) => {
        const g = c.ref(ref);
        if (!g || g.value.length !== 15) return null;
        return upper(g.value).slice(2, 12) === upper(v) ? null : `${c.label} does not match the PAN inside ${g.label} (${upper(g.value).slice(2, 12)}).`;
    },

    /* --- numbers ------------------------------------------------------ */
    number: (v, _a, c) => (/^-?(\d+\.?\d*|\.\d+)$/.test(String(v).trim()) ? null : `${c.label} must be a number.`),
    integer: (v, _a, c) => (/^-?\d+$/.test(String(v).trim()) ? null : `${c.label} must be a whole number.`),
    digits: (v, [n], c) => (new RegExp(`^\\d{${+n}}$`).test(v) ? null : `${c.label} must be exactly ${plural(+n, 'digit')}.`),
    min: (v, [n], c) => (num(v) >= +n ? null : `${c.label} must be at least ${n}.`),
    max: (v, [n], c) => (num(v) <= +n ? null : `${c.label} must be at most ${n}.`),
    between: (v, [a, b], c) => (num(v) >= +a && num(v) <= +b ? null : `${c.label} must be between ${a} and ${b}.`),
    positive: (v, _a, c) => (num(v) > 0 ? null : `${c.label} must be greater than 0.`),
    decimals: (v, [n], c) => (new RegExp(`^-?\\d+(\\.\\d{1,${+n}})?$`).test(String(v).trim()) ? null : `${c.label} can have at most ${plural(+n, 'decimal place')}.`),
    step: (v, [step, base = '0'], c) => {
        if (step === 'any' || !(+step > 0)) return null;
        const scale = 10 ** Math.max(String(step).split('.')[1]?.length ?? 0, String(base).split('.')[1]?.length ?? 0, String(v).split('.')[1]?.length ?? 0);
        const steps = Math.round((num(v) - +base) * scale) / Math.round(+step * scale);
        return Math.abs(steps - Math.round(steps)) < 1e-9 ? null : `${c.label} must be a multiple of ${step}${+base ? ` starting from ${base}` : ''}.`;
    },
    gte: (v, [ref], c) => cmpNumber(v, ref, c, (a, b) => a >= b, 'at least'),
    lte: (v, [ref], c) => cmpNumber(v, ref, c, (a, b) => a <= b, 'at most'),
    gt: (v, [ref], c) => cmpNumber(v, ref, c, (a, b) => a > b, 'greater than'),
    lt: (v, [ref], c) => cmpNumber(v, ref, c, (a, b) => a < b, 'less than'),

    /* --- dates -------------------------------------------------------- */
    datetime: (v, _a, c) => {
        const t = toTime(v, c.type);
        if (Number.isNaN(t)) return `${c.label} is not a valid ${c.type === 'time' ? 'time' : 'date'}.`;
        if (c.type === 'time') return null;
        const year = new Date(t).getFullYear();
        return year >= config.minYear && year <= config.maxYear ? null : `${c.label} must be between the years ${config.minYear} and ${config.maxYear}.`;
    },
    after: compareDates((a, b) => a > b, 'after'),
    after_or_equal: compareDates((a, b) => a >= b, 'on or after'),
    before: compareDates((a, b) => a < b, 'before'),
    before_or_equal: compareDates((a, b) => a <= b, 'on or before'),
    not_past: (v, [grace = '5'], c) => {
        const t = toTime(v, c.type);
        const floor = c.type === 'date' ? startOfToday() : Date.now() - +grace * 60000;
        return Number.isNaN(t) || t >= floor ? null : `${c.label} cannot be in the past.`;
    },
    not_future: (v, _a, c) => {
        const t = toTime(v, c.type);
        const ceil = c.type === 'date' ? startOfToday() : Date.now() + 60000;
        return Number.isNaN(t) || t <= ceil ? null : `${c.label} cannot be in the future.`;
    },
    age_min: (v, [years], c) => {
        const t = toTime(v, 'date');
        return Number.isNaN(t) || ageOn(t) >= +years ? null : `${c.label} makes the person younger than ${plural(+years, 'year')}.`;
    },
    age_max: (v, [years], c) => {
        const t = toTime(v, 'date');
        return Number.isNaN(t) || ageOn(t) <= +years ? null : `${c.label} makes the person older than ${plural(+years, 'year')}; please check the year.`;
    },

    /* --- cross-field --------------------------------------------------- */
    same: (v, [ref], c) => {
        const r = c.ref(ref);
        return !r || r.value === v ? null : `${c.label} does not match ${r.label}.`;
    },
    different: (v, [ref], c) => {
        const r = c.ref(ref);
        return !r || r.value === '' || r.value !== v ? null : `${c.label} must be different from ${r.label}.`;
    },

    /* --- passwords ----------------------------------------------------- */
    new_password: (v, _a, c) => {
        if (len(v) < 8) return `${c.label} must be at least 8 characters (you entered ${len(v)}).`;
        if (len(v) > 128) return `${c.label} must be at most 128 characters.`;
        if (/^\s|\s$/.test(v)) return `${c.label} cannot start or end with a space.`;
        if (COMMON_PASSWORDS.has(v.toLowerCase()) || /^(.)\1+$/.test(v) || '0123456789'.includes(v) || 'abcdefghijklmnopqrstuvwxyz'.includes(v.toLowerCase())) {
            return `${c.label} is too easy to guess. Mix letters, numbers and symbols.`;
        }
        return null;
    },

    /* --- groups / arrays (value is an array of selected values) -------- */
    min_checked: (v, [n], c) => (v.length >= +n ? null : `Select at least ${plural(+n, 'option')} for ${c.label}.`),
    max_checked: (v, [n], c) => (v.length <= +n ? null : `Select at most ${plural(+n, 'option')} for ${c.label}.`),
};

function cmpNumber(v, ref, c, ok, wording) {
    const r = c.ref(ref);
    if (!r || r.value === '' || Number.isNaN(num(r.value)) || Number.isNaN(num(v))) return null;
    return ok(num(v), num(r.value)) ? null : `${c.label} must be ${wording} ${r.label} (${r.value}).`;
}

/** Rules that look at other fields; the engine re-validates dependants when a field changes. */
export const CROSS_FIELD_RULES = new Set([
    'same', 'different', 'gte', 'lte', 'gt', 'lt', 'after', 'after_or_equal', 'before', 'before_or_equal', 'pan_matches_gstin', 'unique_in',
    'required_if', 'required_unless', 'required_with', 'required_without', 'required_if_checked',
]);

/** Rules whose input should be normalised to upper case / stripped of separators while typing. */
export const UPPERCASE_RULES = new Set(['gstin', 'pan', 'tan', 'cin', 'udyam', 'ifsc', 'swift', 'voter', 'passport', 'dl']);
export const DIGITS_ONLY_RULES = new Set(['pincode', 'aadhaar', 'uan', 'esic', 'account']);
export const STRIP_SEPARATOR_RULES = new Set([...UPPERCASE_RULES, ...DIGITS_ONLY_RULES]);
