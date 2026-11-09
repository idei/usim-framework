import test from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import fs from 'node:fs';
import path from 'node:path';

function createDomEnvironment() {
    const dom = new JSDOM('<!DOCTYPE html><html><head></head><body><div id="main"></div></body></html>', {
        runScripts: 'dangerously',
        url: 'http://localhost/',
    });

    dom.window.fetch = async () => ({
        ok: true,
        status: 200,
        headers: { get: () => 'application/json' },
        json: async () => ([]),
    });

    const scriptFiles = [
        'packages/idei/usim/resources/assets/js/component-registry.js',
        'packages/idei/usim/resources/assets/js/ui-renderer.js',
        'packages/idei/usim/resources/assets/js/components/shared/ui-event.js',
        'packages/idei/usim/resources/assets/js/components/button/index.js',
        'packages/idei/usim/resources/assets/js/components/item-list/index.js',
    ];

    for (const file of scriptFiles) {
        const fullPath = path.resolve(file);
        const code = fs.readFileSync(fullPath, 'utf8');
        const scriptEl = dom.window.document.createElement('script');
        scriptEl.textContent = code;
        dom.window.document.head.appendChild(scriptEl);
    }

    return dom;
}

test('ItemList: renders vertical rows, handles multiple selection, and syncs hidden form inputs for Button collectContextValues', async () => {
    const dom = createDomEnvironment();
    let lastEvent = null;

    dom.window.USIM_COMPONENT_HELPERS.sendUiEvent = async (payload) => {
        lastEvent = payload;
        return { ok: true, result: {} };
    };

    const container = dom.window.document.createElement('div');
    container.className = 'ui-container';
    dom.window.document.body.appendChild(container);

    const listComp = dom.window.USIM_COMPONENTS.create('item_list', 401, {
        name: 'movies_list',
        title: 'Películas',
        selection_mode: 'multiple',
        selected_items: [1],
        items: [
            { id: 1, label: 'Inception (2010)', subtitle: 'Sci-Fi', icon: '🚀', badge: 'Sci-Fi' },
            { id: 2, label: 'The Dark Knight (2008)', subtitle: 'Action', icon: '💥', badge: 'Action' },
            { id: 3, label: 'The Matrix (1999)', subtitle: 'Sci-Fi', icon: '🚀', badge: 'Sci-Fi' },
        ],
        on_select: { action: 'movie_selected', parameters: {} },
    });

    const el = listComp.render();
    container.appendChild(el);

    const buttonComp = dom.window.USIM_COMPONENTS.create('button', 402, {
        label: 'Confirmar',
        action: 'confirm_marathon',
    });
    container.appendChild(buttonComp.render());

    const rows = el.querySelectorAll('.usim-item-list-row');
    assert.equal(rows.length, 3);
    assert.ok(rows[0].classList.contains('is-selected'));
    assert.equal(rows[1].classList.contains('is-selected'), false);

    // Click second row to select it in multiple mode
    rows[1].click();
    await new Promise((r) => setTimeout(r, 10));

    assert.ok(lastEvent, 'on_select event should have been sent');
    assert.equal(lastEvent.action, 'movie_selected');
    assert.deepEqual(Array.from(lastEvent.parameters.selected), [1, 2]);

    // Verify UsimButtonComponent.collectContextValues() collects ['1', '2'] without internal search/edit inputs polluting
    const collected = buttonComp.collectContextValues();
    assert.deepEqual(Array.from(collected.movies_list), ['1', '2']);
});

test('ItemList: queries GET /api/ui-data on search with loading state and keeps selected items visible across searches', async () => {
    const dom = createDomEnvironment();
    let fetchedQueries = [];

    dom.window.USIM_COMPONENT_HELPERS.fetchUiData = async ({ componentId, action, parameters }) => {
        fetchedQueries.push({ componentId, action, parameters });
        const search = (parameters?.search || '').toLowerCase();

        const all = [
            { id: 10, label: 'Ana Gómez', subtitle: 'ana@example.com' },
            { id: 25, label: 'Carlos Ruiz', subtitle: 'carlos@example.com' },
        ];

        const data = search
            ? all.filter((u) => u.label.toLowerCase().includes(search))
            : all;

        return { ok: true, status: 200, data };
    };

    const listComp = dom.window.USIM_COMPONENTS.create('item_list', 501, {
        name: 'invite_users',
        action: 'search_users',
        auto_fetch: false,
        searchable: true,
        debounce: 20,
        selection_mode: 'multiple',
        keep_selected_visible: true,
        items: [],
    });

    const el = listComp.render();
    dom.window.document.body.appendChild(el);

    const searchInput = el.querySelector('.usim-item-list-search-input');
    assert.ok(searchInput, 'Search input must exist');

    // Step 1: Search "ana"
    searchInput.value = 'ana';
    searchInput.dispatchEvent(new dom.window.Event('input'));
    await new Promise((r) => setTimeout(r, 50));

    assert.equal(fetchedQueries.length, 1);
    assert.equal(fetchedQueries[0].action, 'search_users');
    assert.equal(fetchedQueries[0].parameters.search, 'ana');

    let rows = el.querySelectorAll('.usim-item-list-row');
    assert.equal(rows.length, 1);
    assert.ok(rows[0].textContent.includes('Ana Gómez'));

    // Select Ana Gómez
    rows[0].click();

    // Step 2: Search "carlos" (Ana is not in the server response for "carlos", but must remain pinned!)
    searchInput.value = 'carlos';
    searchInput.dispatchEvent(new dom.window.Event('input'));
    await new Promise((r) => setTimeout(r, 50));

    assert.equal(fetchedQueries.length, 2);
    assert.equal(fetchedQueries[1].parameters.search, 'carlos');

    rows = el.querySelectorAll('.usim-item-list-row');
    assert.equal(rows.length, 2, 'Both pinned Ana and search result Carlos must be visible');
    assert.ok(rows[0].classList.contains('is-pinned-selection'));
    assert.ok(rows[0].textContent.includes('Ana Gómez'));
    assert.ok(rows[1].textContent.includes('Carlos Ruiz'));
});

test('ItemList: supports inline Create, inline Update, and direct Delete events', async () => {
    const dom = createDomEnvironment();
    const events = [];

    dom.window.USIM_COMPONENT_HELPERS.sendUiEvent = async (payload) => {
        events.push(payload);
        return { ok: true, result: {} };
    };

    const listComp = dom.window.USIM_COMPONENTS.create('item_list', 601, {
        name: 'movies_list',
        creatable: true,
        create_inline: true,
        editable: true,
        edit_inline: true,
        deletable: true,
        items: [
            { id: 1, label: 'Interstellar' },
        ],
        on_create: { action: 'movie_created', parameters: {} },
        on_update: { action: 'movie_updated', parameters: {} },
        on_delete: { action: 'movie_deleted', parameters: {} },
    });

    const el = listComp.render();
    dom.window.document.body.appendChild(el);

    // 1. Inline Create
    const createInput = el.querySelector('.usim-item-list-create-input');
    const createBtn = el.querySelector('.usim-item-list-create-btn');
    createInput.value = 'Dune: Part Two';
    createBtn.click();
    await new Promise((r) => setTimeout(r, 10));

    assert.equal(events.length, 1);
    assert.equal(events[0].action, 'movie_created');
    assert.equal(events[0].parameters.value, 'Dune: Part Two');
    assert.equal(createInput.value, '');

    // 2. Inline Edit
    const editBtn = el.querySelector('.usim-item-list-action-btn.edit');
    editBtn.click();

    const editInput = el.querySelector('.usim-item-list-edit-input');
    assert.ok(editInput, 'Row should enter inline edit mode');
    assert.equal(editInput.value, 'Interstellar');

    editInput.value = 'Interstellar (2014)';
    const saveBtn = el.querySelector('.usim-item-list-action-btn.save');
    saveBtn.click();
    await new Promise((r) => setTimeout(r, 10));

    assert.equal(events.length, 2);
    assert.equal(events[1].action, 'movie_updated');
    assert.equal(events[1].parameters.id, 1);
    assert.equal(events[1].parameters.value, 'Interstellar (2014)');

    // 3. Direct Delete
    const deleteBtn = el.querySelector('.usim-item-list-action-btn.delete');
    deleteBtn.click();
    await new Promise((r) => setTimeout(r, 10));

    assert.equal(events.length, 3);
    assert.equal(events[2].action, 'movie_deleted');
    assert.equal(events[2].parameters.id, 1);
});
