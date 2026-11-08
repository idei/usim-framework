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
        json: async () => ({})
    });

    const scriptFiles = [
        'packages/idei/usim/resources/assets/js/component-registry.js',
        'packages/idei/usim/resources/assets/js/ui-renderer.js',
        'packages/idei/usim/resources/assets/js/components/shared/ui-event.js',
        'packages/idei/usim/resources/assets/js/components/container/index.js',
        'packages/idei/usim/resources/assets/js/components/button/index.js',
        'packages/idei/usim/resources/assets/js/components/label/index.js',
        'packages/idei/usim/resources/assets/js/components/input/index.js',
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

test('Container: renders with display: none !important on initial render when visible: false', () => {
    const dom = createDomEnvironment();

    const containerComp = dom.window.USIM_COMPONENTS.create('container', 101, {
        name: 'edit_panel',
        layout: 'vertical',
        width: '320px',
        visible: false,
    });

    const el = containerComp.render();
    dom.window.document.body.appendChild(el);

    assert.equal(el.style.getPropertyValue('display'), 'none');
    assert.equal(el.style.getPropertyPriority('display'), 'important');
});

test('Container: layout grid with visible: false renders with display: none !important and not display: grid', () => {
    const dom = createDomEnvironment();

    const containerComp = dom.window.USIM_COMPONENTS.create('container', 102, {
        name: 'grid_panel',
        layout: 'grid',
        visible: false,
    });

    const el = containerComp.render();
    dom.window.document.body.appendChild(el);

    assert.equal(el.style.getPropertyValue('display'), 'none');
    assert.equal(el.style.getPropertyPriority('display'), 'important');
});

test('Container: visible: true renders normally (or grid when layout is grid)', () => {
    const dom = createDomEnvironment();

    const normalComp = dom.window.USIM_COMPONENTS.create('container', 103, {
        name: 'normal_panel',
        layout: 'vertical',
        visible: true,
    });
    const normalEl = normalComp.render();
    assert.notEqual(normalEl.style.getPropertyValue('display'), 'none');

    const gridComp = dom.window.USIM_COMPONENTS.create('container', 104, {
        name: 'grid_panel',
        layout: 'grid',
    });
    const gridEl = gridComp.render();
    assert.equal(gridEl.style.getPropertyValue('display'), 'grid');
    assert.equal(gridEl.style.getPropertyPriority('display'), 'important');
});

test('Components (Button, Label, Input): render with display: none !important when visible: false', () => {
    const dom = createDomEnvironment();

    const button = dom.window.USIM_COMPONENTS.create('button', 201, {
        name: 'test_btn',
        label: 'Click me',
        visible: false,
    });
    const btnEl = button.render();
    assert.equal(btnEl.style.getPropertyValue('display'), 'none');
    assert.equal(btnEl.style.getPropertyPriority('display'), 'important');

    const label = dom.window.USIM_COMPONENTS.create('label', 202, {
        name: 'test_lbl',
        text: 'Title',
        visible: false,
    });
    const lblEl = label.render();
    assert.equal(lblEl.style.getPropertyValue('display'), 'none');
    assert.equal(lblEl.style.getPropertyPriority('display'), 'important');

    const input = dom.window.USIM_COMPONENTS.create('input', 203, {
        name: 'test_input',
        visible: false,
    });
    const inputEl = input.render();
    assert.equal(inputEl.style.getPropertyValue('display'), 'none');
    assert.equal(inputEl.style.getPropertyPriority('display'), 'important');
});

test('UIRenderer: renders initial screen and respects visible: false across hierarchy', () => {
    const dom = createDomEnvironment();
    const renderer = new dom.window.UIRenderer({
        1: {
            type: 'container',
            name: 'main_container',
            parent: 'main',
            layout: 'vertical',
        },
        2: {
            type: 'container',
            name: 'edit_panel',
            parent: 1,
            layout: 'vertical',
            width: '320px',
            visible: false,
        },
        3: {
            type: 'label',
            name: 'title',
            parent: 2,
            text: 'Panel de Edición',
        },
    });

    renderer.render();

    const editPanelEl = dom.window.document.getElementById('edit_panel');
    assert.ok(editPanelEl, 'edit_panel should exist in the DOM');
    assert.equal(editPanelEl.style.getPropertyValue('display'), 'none');
    assert.equal(editPanelEl.style.getPropertyPriority('display'), 'important');
});

test('UIRenderer: dynamic update toggles visibility between false and true correctly', () => {
    const dom = createDomEnvironment();
    const renderer = new dom.window.UIRenderer({
        1: {
            type: 'container',
            name: 'main_container',
            parent: 'main',
            layout: 'vertical',
        },
        2: {
            type: 'container',
            name: 'edit_panel',
            parent: 1,
            layout: 'vertical',
            width: '320px',
            visible: false,
        },
        3: {
            type: 'button',
            name: 'action_btn',
            parent: 1,
            label: 'Action',
            visible: true,
        },
    });

    renderer.render();

    const editPanelEl = dom.window.document.getElementById('edit_panel');
    const actionBtnEl = dom.window.document.getElementById('action_btn');

    assert.equal(editPanelEl.style.getPropertyValue('display'), 'none');
    assert.equal(editPanelEl.style.getPropertyPriority('display'), 'important');
    assert.notEqual(actionBtnEl.style.getPropertyValue('display'), 'none');

    // Update edit_panel to visible: true
    renderer.updateComponent(editPanelEl, { visible: true });
    assert.notEqual(editPanelEl.style.getPropertyValue('display'), 'none');

    // Update action_btn to visible: false
    renderer.updateComponent(actionBtnEl, { visible: false });
    assert.equal(actionBtnEl.style.getPropertyValue('display'), 'none');
    assert.equal(actionBtnEl.style.getPropertyPriority('display'), 'important');

    // Toggle edit_panel back to visible: false
    renderer.updateComponent(editPanelEl, { visible: false });
    assert.equal(editPanelEl.style.getPropertyValue('display'), 'none');
    assert.equal(editPanelEl.style.getPropertyPriority('display'), 'important');

    // Toggle action_btn back to visible: true
    renderer.updateComponent(actionBtnEl, { visible: true });
    assert.notEqual(actionBtnEl.style.getPropertyValue('display'), 'none');
});

test('UIRenderer: dynamic update restores display: grid !important for grid layout container', () => {
    const dom = createDomEnvironment();
    const renderer = new dom.window.UIRenderer({
        1: {
            type: 'container',
            name: 'grid_container',
            parent: 'main',
            layout: 'grid',
            visible: false,
        },
    });

    renderer.render();

    const gridEl = dom.window.document.getElementById('grid_container');
    assert.equal(gridEl.style.getPropertyValue('display'), 'none');
    assert.equal(gridEl.style.getPropertyPriority('display'), 'important');

    // Make visible
    renderer.updateComponent(gridEl, { visible: true });
    assert.equal(gridEl.style.getPropertyValue('display'), 'grid');
    assert.equal(gridEl.style.getPropertyPriority('display'), 'important');
});

test('UIRenderer: applies grid layout properties and child positioning (grid_column, grid_row, grid_area)', () => {
    const dom = createDomEnvironment();
    const renderer = new dom.window.UIRenderer({
        1: {
            type: 'container',
            name: 'grid_container',
            parent: 'main',
            layout: 'grid',
            grid_template_columns: 'repeat(3, 1fr)',
            grid_template_rows: '100px auto',
        },
        2: {
            type: 'container',
            name: 'header_area',
            parent: 1,
            grid_column: '1 / -1',
            grid_row: '1',
            grid_area: 'header',
        },
    });

    renderer.render();

    const gridEl = dom.window.document.getElementById('grid_container');
    assert.equal(gridEl.style.getPropertyValue('display'), 'grid');
    assert.equal(gridEl.style.getPropertyValue('grid-template-columns'), 'repeat(3, 1fr)');
    assert.equal(gridEl.style.getPropertyValue('grid-template-rows'), '100px auto');

    const headerEl = dom.window.document.getElementById('header_area');
    assert.equal(headerEl.style.getPropertyValue('grid-column'), '1 / -1');
    assert.equal(headerEl.style.getPropertyValue('grid-row'), '1');
    assert.equal(headerEl.style.getPropertyValue('grid-area'), 'header');
});
