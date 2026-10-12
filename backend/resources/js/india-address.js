/**
 * Linked State → District dropdowns for Indian addresses.
 *
 *   <select name="address[state]" data-india-state>…</select>
 *   <select name="address[district]" data-india-district>…</select>
 *
 * The district select is the one whose name is the state's name with "state"
 * swapped for "district" (state → district, branches[0][state] →
 * branches[0][district]); `data-district-target="#css"` overrides that. Both
 * become searchable. Options come from the states/districts tables via the
 * URL in <meta name="nx-locations-url">, fetched once per page. Any value the
 * server rendered as selected is kept, so old() input and saved records show.
 */

import { enhance } from './searchable-select.js';

let request = null;

function loadStates() {
    const url = document.querySelector('meta[name="nx-locations-url"]')?.content;
    if (!url) return Promise.reject(new Error('Missing nx-locations-url meta tag.'));
    request ??= fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((response) => (response.ok ? response.json() : Promise.reject(new Error(`HTTP ${response.status}`))))
        .then((data) => new Map(data.states.map((state) => [state.name, state.districts])))
        .catch((error) => {
            request = null;
            throw error;
        });
    return request;
}

function districtSelectFor(stateSelect) {
    if (stateSelect.dataset.districtTarget) return document.querySelector(stateSelect.dataset.districtTarget);
    const name = stateSelect.name.replace(/state(\]?)$/, 'district$1');
    const scope = stateSelect.form ?? document;
    return [...scope.querySelectorAll('select[data-india-district]')].find((select) => select.name === name) ?? null;
}

/** Replaces a select's options, keeping `selected` (as an extra option if it is not in `names`). */
function fill(select, names, selected, placeholder) {
    const options = [new Option(placeholder, ''), ...names.map((name) => new Option(name, name))];
    if (selected && !names.includes(selected)) {
        const saved = new Option(`${selected} (not in list – please reselect)`, selected);
        options.splice(1, 0, saved);
    }
    select.dataset.placeholder = placeholder;
    select.replaceChildren(...options);
    select.value = selected || '';
}

function fillDistricts(stateSelect, states, keep) {
    const districtSelect = districtSelectFor(stateSelect);
    if (!districtSelect) return;
    const districts = states.get(stateSelect.value) ?? [];
    const selected = keep ?? (districts.includes(districtSelect.value) ? districtSelect.value : '');
    fill(districtSelect, districts, selected, stateSelect.value ? 'Select district' : 'Select state first');
    // State first, then district: the district stays locked until a state is chosen.
    if (stateSelect.value) districtSelect.removeAttribute('data-locked');
    else districtSelect.dataset.locked = 'Select a state first';
}

async function init(stateSelect) {
    if (stateSelect.dataset.indiaReady) return;
    stateSelect.dataset.indiaReady = '1';
    enhance(stateSelect);
    const districtSelect = districtSelectFor(stateSelect);
    if (districtSelect) {
        if (!stateSelect.value) districtSelect.dataset.locked = 'Select a state first';
        enhance(districtSelect);
    }

    let states;
    try {
        states = await loadStates();
    } catch {
        stateSelect.dataset.placeholder = 'Could not load states – reload the page';
        stateSelect.dispatchEvent(new Event('change'));
        return;
    }
    fill(stateSelect, [...states.keys()], stateSelect.value, 'Select state');
    fillDistricts(stateSelect, states, districtSelect?.value ?? '');
}

function scan(root) {
    if (root.matches?.('select[data-india-state]')) init(root);
    root.querySelectorAll?.('select[data-india-state]').forEach(init);
}

function start() {
    scan(document.body);
    new MutationObserver((records) => records.forEach((record) => record.addedNodes.forEach((node) => {
        if (node.nodeType === 1) scan(node);
    }))).observe(document.body, { childList: true, subtree: true });

    document.addEventListener('change', (e) => {
        if (!e.target.matches?.('select[data-india-state]')) return;
        loadStates().then((states) => fillDistricts(e.target, states)).catch(() => {});
    });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
