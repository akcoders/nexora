// Unit tests for the pure client-side validation rules.
// Run with Node 18+ (no extra dependencies):  node --test "tests/js/*.test.mjs"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    rules, config, isGstin, isPan, isAadhaar, verhoeff, isEmail, isPhone, toTime, ageOn,
    checkFile, sniffKind, passwordScore, gstinCheckChar,
} from '../../resources/js/validation-rules.js';

const ctx = (over = {}) => ({ label: 'Field', type: 'text', ref: () => null, others: () => [], ...over });
const refTo = (value, label = 'Other', type = 'text') => ctx({ type, ref: () => ({ value, label, type }) });
const passes = (rule, value, args = [], context = ctx()) => rules[rule](value, args, context) === null;
const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
const daysFromToday = (n) => { const d = new Date(); d.setDate(d.getDate() + n); return iso(d); };
const yearsAgo = (n) => { const d = new Date(); d.setFullYear(d.getFullYear() - n); return iso(d); };

test('GSTIN: format, state code, PAN part and checksum', () => {
    assert.ok(isGstin('27AAPFU0939F1ZV'));
    assert.ok(passes('gstin', '27aapfu0939f1zv'));
    assert.equal(isGstin('27AAPFU0939F1ZA'), false, 'wrong check character');
    assert.equal(isGstin('40AAPFU0939F1ZV'), false, 'state code 40 does not exist');
    assert.equal(passes('gstin', '27AAPFU0939F1Z'), false, 'too short');
    assert.equal(gstinCheckChar('27AAPFU0939F1Z'), 'V');
    assert.equal(isGstin('27ABCDE1234F1Z5'), false, 'the common dummy GSTIN fails the checksum');
});

test('PAN: holder-type letter can be relaxed through config', () => {
    assert.ok(isPan('ABCPD1234E'));
    assert.ok(isPan('AAACC1234C'));
    assert.equal(isPan('ABCDE1234F'), false);
    config.panEntityCheck = false;
    assert.ok(isPan('ABCDE1234F'));
    config.panEntityCheck = true;
});

test('PAN must match the PAN embedded in the GSTIN', () => {
    const gstin = refTo('27AAPFU0939F1ZV', 'GSTIN');
    assert.ok(passes('pan_matches_gstin', 'AAPFU0939F', ['gstin'], gstin));
    assert.equal(passes('pan_matches_gstin', 'AAPFU0939G', ['gstin'], gstin), false);
    assert.ok(passes('pan_matches_gstin', 'AAPFU0939G', ['gstin'], refTo('')), 'skipped while GSTIN is empty');
});

test('Aadhaar uses the Verhoeff checksum', () => {
    assert.ok(verhoeff('2363'));
    assert.equal(verhoeff('2364'), false);
    assert.equal(isAadhaar('123456789012'), false, 'cannot start with 1');
    const valid = Array.from({ length: 10 }, (_, d) => `23456789012${d}`).filter(isAadhaar);
    assert.equal(valid.length, 1, 'exactly one check digit completes the number');
    assert.equal(passes('aadhaar', '23456789012'), false, '11 digits');
});

test('Other Indian ids', () => {
    const cases = [
        ['ifsc', 'HDFC0001234', true], ['ifsc', 'HDFC1001234', false],
        ['tan', 'MUMA12345B', true], ['cin', 'U12345MH2010PTC123456', true],
        ['udyam', 'UDYAM-MH-12-1234567', true], ['udyam', 'UDYAM-MH-12-123', false],
        ['swift', 'HDFCINBB', true], ['swift', 'HDFCINBBXXX', true], ['swift', 'HDFC', false],
        ['upi', 'name@okhdfc', true], ['upi', 'name', false],
        ['account', '123456789012', true], ['account', '12345678', false],
        ['uan', '100123456789', true], ['esic', '12345678901234567', true], ['esic', '123456789012', false],
        ['voter', 'ABC1234567', true], ['passport', 'K1234567', true], ['passport', 'Q1234567', false],
        ['pincode', '400001', true], ['pincode', '040001', false], ['pincode', '40001', false],
    ];
    for (const [rule, value, expected] of cases) assert.equal(passes(rule, value), expected, `${rule} ${value}`);
});

test('email', () => {
    const cases = [
        ['a@b.co', true], ['name.surname+tag@company-name.co.in', true], ['user@sub.domain.org', true],
        ['no-at.com', false], ['a@b', false], ['a@@b.com', false], ['.a@b.com', false], ['a..b@c.com', false],
        ['a@b.c', false], ['a b@c.com', false], ['a@-b.com', false],
    ];
    for (const [value, expected] of cases) assert.equal(isEmail(value), expected, value);
});

test('phone and mobile numbers', () => {
    const cases = [
        ['9876543210', true, true], ['98765 43210', true, true], ['+91 98765 43210', true, true], ['919876543210', true, true],
        ['09876543210', true, true], ['5876543210', true, false], ['9999999999', true, false], ['123', true, false],
        ['98765abcde', true, false], ['++919876543210', true, false],
        ['022 2345 6789', false, true], ['022-23456789', true, false], ['2345678', false, true], ['2345678', true, false],
        ['+1 202 555 0143', false, true], ['+44 20 7946 0958', false, true], ['+91 22 12345678', false, true], ['+91 22 12345678', true, false],
    ];
    for (const [value, mobileOnly, expected] of cases) assert.equal(isPhone(value, { mobileOnly }), expected, `${value} mobileOnly=${mobileOnly}`);
});

test('numbers: ranges, steps and decimals without float drift', () => {
    assert.ok(passes('min', '5', ['5']));
    assert.equal(passes('min', '4', ['5']), false);
    assert.ok(passes('between', '19.07', ['-90', '90']));
    assert.equal(passes('between', '91', ['-90', '90']), false);
    assert.ok(passes('step', '12.34', ['.01', '0']));
    assert.equal(passes('step', '12.345', ['.01', '0']), false);
    assert.ok(passes('step', '2.5', ['.5', '0']));
    assert.equal(passes('step', '2.3', ['.5', '0']), false);
    assert.ok(passes('step', '19.0759837', ['.0000001', '0']));
    assert.ok(passes('step', '0.3', ['.1', '0']), '0.1 + 0.2 style drift must not fail');
    assert.ok(passes('integer', '12'));
    assert.equal(passes('integer', '12.5'), false);
    assert.ok(passes('decimals', '1.25', ['2']));
    assert.equal(passes('decimals', '1.255', ['2']), false);
    assert.equal(passes('positive', '0'), false);
    assert.ok(passes('gte', '10', ['a'], refTo('5', 'A')));
    assert.equal(passes('gte', '4', ['a'], refTo('5', 'A')), false);
});

test('dates: real calendar days, year sanity, ordering and ages', () => {
    assert.ok(Number.isNaN(toTime('2024-02-30', 'date')));
    assert.ok(Number.isNaN(toTime('2023-02-29', 'date')));
    assert.ok(!Number.isNaN(toTime('2024-02-29', 'date')));
    assert.ok(Number.isNaN(toTime('25:00', 'time')));
    assert.ok(!Number.isNaN(toTime('2026-10', 'month')));
    const date = (value) => ctx({ type: 'date', ref: () => ({ value, label: 'Start', type: 'date' }) });
    assert.equal(passes('datetime', '20255-01-01', [], ctx({ type: 'date' })), false, 'six-digit year typo');
    assert.equal(passes('datetime', '1850-01-01', [], ctx({ type: 'date' })), false);
    assert.ok(passes('datetime', '2000-01-01', [], ctx({ type: 'date' })));
    assert.ok(passes('after', '2026-02-01', ['start'], date('2026-01-01')));
    assert.equal(passes('after', '2026-01-01', ['start'], date('2026-01-01')), false);
    assert.ok(passes('after_or_equal', '2026-01-01', ['start'], date('2026-01-01')));
    assert.equal(passes('before', '2026-03-01', ['start'], date('2026-01-01')), false);
    assert.ok(passes('after', '2026-03-01', ['start'], date('')), 'skipped while the other date is empty');
    assert.ok(passes('after', '18:00', ['start'], ctx({ type: 'time', ref: () => ({ value: '09:00', label: 'Start', type: 'time' }) })));
    assert.ok(passes('not_future', daysFromToday(0), [], ctx({ type: 'date' })));
    assert.equal(passes('not_future', daysFromToday(1), [], ctx({ type: 'date' })), false);
    assert.equal(passes('not_past', daysFromToday(-1), [], ctx({ type: 'date' })), false);
    assert.ok(passes('before', daysFromToday(-1), ['today'], ctx({ type: 'date' })));
    assert.equal(passes('age_min', yearsAgo(17), ['18']), false);
    assert.ok(passes('age_min', yearsAgo(18), ['18']));
    assert.equal(passes('age_max', yearsAgo(120), ['80']), false);
    assert.equal(ageOn(new Date(2000, 5, 15).getTime(), new Date(2020, 5, 14).getTime()), 19, 'birthday not reached yet');
    assert.equal(ageOn(new Date(2000, 5, 15).getTime(), new Date(2020, 5, 15).getTime()), 20);
});

test('text rules', () => {
    assert.ok(passes('person_name', "Anuj O'Neil-Sharma Jr."));
    assert.ok(passes('person_name', 'अनुज शर्मा'));
    assert.equal(passes('person_name', 'John3'), false);
    assert.equal(passes('person_name', '.John'), false);
    assert.ok(passes('alpha_dash', 'CL-01_a'));
    assert.equal(passes('alpha_dash', 'CL 01'), false);
    assert.equal(passes('safe_text', 'hello <img src=x onerror=alert(1)>'), false);
    assert.ok(passes('safe_text', '5 < 6 and 7 > 3'));
    assert.equal(passes('safe_text', 'a\u0007b'), false);
    assert.ok(passes('safe_text', 'a\nb'));
    assert.ok(passes('maxlength', '😀😀', ['2']), 'counts characters, not UTF-16 units');
    assert.equal(passes('minlength', 'abc', ['4']), false);
    assert.ok(passes('uuid', '12345678-1234-4234-8234-123456789abc'));
    assert.equal(passes('uuid', 'not-a-uuid'), false);
    assert.ok(passes('url', 'https://recurpost.com/x?y=1'));
    assert.equal(passes('url', 'javascript:alert(1)'), false);
    assert.equal(passes('url', 'recurpost.com'), false);
});

test('multi-line lists', () => {
    assert.ok(passes('lines', 'Check filter [photo]\nMeasure temp', ['100', '255']));
    assert.equal(passes('lines', 'Check filter\ncheck filter', ['100', '255']), false, 'duplicates ignore case');
    assert.equal(passes('lines', '\n  \n', ['100', '255']), false);
    assert.equal(passes('lines', 'x'.repeat(300), ['100', '255']), false);
});

test('cross-field rules', () => {
    assert.ok(passes('same', 'abc', ['password'], refTo('abc', 'Password')));
    assert.equal(passes('same', 'abd', ['password'], refTo('abc', 'Password')), false);
    assert.equal(passes('different', 'abc', ['current_password'], refTo('abc', 'Current password')), false);
    assert.equal(passes('unique_in', 'A1', ['sites'], ctx({ others: () => ['a1', 'B2'] })), false);
    assert.ok(passes('unique_in', 'C3', ['sites'], ctx({ others: () => ['a1', 'B2'] })));
});

test('passwords', () => {
    assert.equal(passes('new_password', 'abc12'), false);
    assert.equal(passes('new_password', 'password123'), false);
    assert.equal(passes('new_password', '12345678'), false);
    assert.equal(passes('new_password', 'aaaaaaaaa'), false);
    assert.equal(passes('new_password', ' Tr0ub4dor&3'), false, 'leading space');
    assert.ok(passes('new_password', 'Tr0ub4dor&3'));
    assert.equal(passwordScore('Correct-Horse-9-Battery'), 4);
    assert.equal(passwordScore('password'), 1);
});

test('checkbox groups', () => {
    assert.equal(passes('min_checked', [], ['1']), false);
    assert.equal(passes('max_checked', ['a', 'b'], ['1']), false);
    assert.ok(passes('min_checked', ['a'], ['1']));
});

test('files: signature sniffing, size and type', () => {
    const bytes = (...b) => new Uint8Array([...b, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0].slice(0, 12));
    assert.equal(sniffKind(bytes(0xff, 0xd8, 0xff, 0xe0)), 'jpg');
    assert.equal(sniffKind(bytes(0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a)), 'png');
    assert.equal(sniffKind(bytes(0x25, 0x50, 0x44, 0x46, 0x2d)), 'pdf');
    assert.equal(sniffKind(bytes(0x52, 0x49, 0x46, 0x46, 0, 0, 0, 0, 0x57, 0x45, 0x42, 0x50)), 'webp');
    assert.equal(sniffKind(bytes(0x68, 0x65, 0x6c, 0x6c, 0x6f)), null);

    const file = (name, size, type) => ({ name, size, type });
    const images = ['image/png', 'image/jpeg', 'image/webp'];
    assert.equal(checkFile(file('a.png', 1000, 'image/png'), { accept: images, maxKb: 10, kind: 'png' }), null);
    assert.ok(checkFile(file('a.png', 20000, 'image/png'), { accept: images, maxKb: 10, kind: 'png' }), 'too big');
    assert.ok(checkFile(file('a.pdf', 100, 'application/pdf'), { accept: images, maxKb: 10, kind: 'pdf' }), 'pdf in an image field');
    assert.ok(checkFile(file('a.png', 100, 'image/png'), { accept: images, maxKb: 10, kind: null }), 'text renamed to .png');
    assert.ok(checkFile(file('a.png', 100, 'image/png'), { accept: images, maxKb: 10, kind: 'jpg' }), 'jpeg renamed to .png');
    assert.equal(checkFile(file('a.png', 100, 'image/png'), { accept: images, maxKb: 10 }), null, 'signature not read yet');
    assert.ok(checkFile(file('a.png', 0, 'image/png'), { accept: images, maxKb: 10 }), 'empty file');
    const docs = ['.pdf', '.jpg', '.jpeg', '.png', '.webp', '.doc', '.docx'];
    assert.equal(checkFile(file('a.docx', 100, ''), { accept: docs, maxKb: 10, kind: 'zip' }), null);
    assert.ok(checkFile(file('a.exe', 100, 'application/x-msdownload'), { accept: docs, maxKb: 10, kind: null }));
});
