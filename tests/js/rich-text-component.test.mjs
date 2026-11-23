import test from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import fs from 'node:fs';
import path from 'node:path';

function createDomEnvironment({ theme = 'light', lang = 'es' } = {}) {
    const dom = new JSDOM(`<!DOCTYPE html><html lang="${lang}" data-theme="${theme}"><head></head><body><div id="main"></div></body></html>`, {
        runScripts: 'dangerously',
        url: 'http://localhost/',
    });

    dom.window.fetch = async () => ({
        ok: true,
        json: async () => ({}),
    });

    const createdEditors = [];

    // Mock SunEditor v3 global API so unit tests run deterministically in JSDOM
    dom.window.SUNEDITOR_LANG = {
        es: { code: 'es', bold: 'Negrita' },
    };

    dom.window.SUNEDITOR = {
        plugins: { image: { key: 'image' }, link: { key: 'link' }, table: { key: 'table' } },
        create(target, options) {
            let html = options.value ?? '';
            let currentTheme = options.theme ?? '';
            let isDisabled = false;
            let isReadonly = false;
            let destroyed = false;

            // Simulate SunEditor DOM wrapper including an internal radio input
            const seRoot = dom.window.document.createElement('div');
            seRoot.className = 'sun-editor';
            seRoot.innerHTML = '<div class="se-modal"><input type="radio" name="suneditor_image_radio" value="none" checked></div>';
            target.appendChild(seRoot);

            const instance = {
                target,
                options,
                seRoot,
                get state() {
                    return { html, currentTheme, isDisabled, isReadonly, destroyed };
                },
                simulateUserChange(nextHtml) {
                    html = nextHtml;
                    options.events?.onChange?.({ data: nextHtml });
                },
                simulateBlur() {
                    options.events?.onBlur?.({});
                },
                $: {
                    html: {
                        get: () => html,
                        set: (nextHtml) => {
                            html = nextHtml;
                            options.events?.onChange?.({ data: nextHtml });
                        },
                    },
                    ui: {
                        setTheme: (nextTheme) => {
                            currentTheme = nextTheme;
                        },
                        disable: () => {
                            isDisabled = true;
                        },
                        enable: () => {
                            isDisabled = false;
                        },
                        readOnly: (val) => {
                            isReadonly = Boolean(val);
                        },
                    },
                },
                destroy() {
                    destroyed = true;
                    seRoot.remove();
                },
            };

            createdEditors.push(instance);
            return instance;
        },
    };

    const scriptFiles = [
        'packages/idei/usim/resources/assets/js/component-registry.js',
        'packages/idei/usim/resources/assets/js/ui-renderer.js',
        'packages/idei/usim/resources/assets/js/components/shared/ui-event.js',
        'packages/idei/usim/resources/assets/js/components/button/index.js',
        'packages/idei/usim/resources/assets/js/components/rich-text/index.js',
    ];

    for (const file of scriptFiles) {
        const fullPath = path.resolve(file);
        const code = fs.readFileSync(fullPath, 'utf8');
        const scriptEl = dom.window.document.createElement('script');
        scriptEl.textContent = code;
        dom.window.document.head.appendChild(scriptEl);
    }

    return { dom, createdEditors };
}

async function flushAsync() {
    await new Promise((resolve) => setTimeout(resolve, 15));
}

test('RichText: mounts SunEditor v3 with standard preset, base64 images, Spanish lang and dark theme sync', async () => {
    const { dom, createdEditors } = createDomEnvironment({ theme: 'dark', lang: 'es' });

    const comp = dom.window.USIM_COMPONENTS.create('rich_text', 501, {
        name: 'article_html',
        label: 'Artículo',
        placeholder: 'Escribe algo…',
        value: '<p>Inicial</p>',
        height: '320px',
        min_height: '160px',
        max_height: '500px',
        toolbar: 'standard',
        allow_images: true,
        char_counter: true,
        word_counter: true,
        max_length: 1000,
    });

    const el = comp.render();
    dom.window.document.body.appendChild(el);
    await flushAsync();

    // Outer wrapper must NOT inherit fixed height so SunEditor chrome (label + toolbar + statusbar) never overlaps siblings
    assert.equal(el.style.height, '');
    assert.equal(el.style.minHeight, '');
    assert.equal(el.style.maxHeight, '');

    assert.equal(createdEditors.length, 1, 'SunEditor.create should be called once');
    const editor = createdEditors[0];

    assert.equal(editor.options.value, '<p>Inicial</p>');
    assert.equal(editor.options.height, '320px');
    assert.equal(editor.options.minHeight, '160px');
    assert.equal(editor.options.maxHeight, '500px');
    assert.equal(editor.options.theme, 'dark');
    assert.equal(editor.options.lang, dom.window.SUNEDITOR_LANG.es);
    assert.equal(editor.options.charCounter, true);
    assert.equal(editor.options.charCounter_max, 1000);
    assert.equal(editor.options.wordCounter, true);
    assert.equal(editor.options.image.canResize, true);
    assert.equal(editor.options.image.uploadUrl, undefined, 'Base64 mode should omit uploadUrl');
    assert.ok(editor.options.buttonList.some((group) => Array.isArray(group) && group.includes('image')));

    // Switch USIM theme to light dynamically
    dom.window.dispatchEvent(new dom.window.CustomEvent('usim:theme-changed', { detail: { theme: 'light' } }));
    assert.equal(editor.state.currentTheme, '');

    // Switch back to dark
    dom.window.dispatchEvent(new dom.window.CustomEvent('usim:theme-changed', { detail: { theme: 'dark' } }));
    assert.equal(editor.state.currentTheme, 'dark');

    // Destroy cleans up SunEditor and stops listening to theme changes
    comp.destroy();
    assert.equal(editor.state.destroyed, true);
});

test('RichText: allow_images=false strips image button and excludes image plugin', async () => {
    const { dom, createdEditors } = createDomEnvironment();

    const comp = dom.window.USIM_COMPONENTS.create('rich_text', 502, {
        name: 'no_img_editor',
        toolbar: 'full',
        allow_images: false,
    });

    const el = comp.render();
    dom.window.document.body.appendChild(el);
    await flushAsync();

    const editor = createdEditors[0];
    assert.deepEqual(Array.from(editor.options.excludedPlugins), ['image']);
    assert.equal(
        editor.options.buttonList.some((group) => Array.isArray(group) && group.includes('image')),
        false,
        'image button must be stripped when allow_images is false'
    );
});

test('RichText: syncs hidden textarea for button collectContextValues and ignores SunEditor internal controls', async () => {
    const { dom, createdEditors } = createDomEnvironment();

    const container = dom.window.document.createElement('div');
    container.className = 'ui-container';
    dom.window.document.body.appendChild(container);

    const richComp = dom.window.USIM_COMPONENTS.create('rich_text', 503, {
        name: 'post_body',
        value: '<p>Borrador</p>',
    });
    container.appendChild(richComp.render());

    const buttonComp = dom.window.USIM_COMPONENTS.create('button', 504, {
        label: 'Guardar',
        action: 'save_post',
    });
    container.appendChild(buttonComp.render());

    await flushAsync();

    const editor = createdEditors[0];
    editor.simulateUserChange('<p>Contenido <strong>editado</strong></p>');

    const collected = buttonComp.collectContextValues();
    assert.equal(collected.post_body, '<p>Contenido <strong>editado</strong></p>');
    assert.equal(
        Object.prototype.hasOwnProperty.call(collected, 'suneditor_image_radio'),
        false,
        'Internal SunEditor modal inputs must not leak into USIM form submissions'
    );
});

test('RichText: dispatches debounced onInput and blur onChange, and applies external update() without echoing', async () => {
    const { dom, createdEditors } = createDomEnvironment();
    const events = [];

    dom.window.USIM_COMPONENT_HELPERS.sendUiEvent = async (payload) => {
        events.push(payload);
        return { ok: true, result: {} };
    };

    const comp = dom.window.USIM_COMPONENTS.create('rich_text', 505, {
        name: 'doc_html',
        debounce: 30,
        on_input: { action: 'on_typing', parameters: {} },
        on_change: { action: 'on_saved', parameters: { source: 'rte' } },
    });

    const el = comp.render();
    dom.window.document.body.appendChild(el);
    await flushAsync();

    const editor = createdEditors[0];

    // User types -> debounced onInput
    editor.simulateUserChange('<p>Hola</p>');
    assert.equal(events.length, 0);
    await new Promise((resolve) => setTimeout(resolve, 50));
    assert.equal(events.length, 1);
    assert.equal(events[0].event, 'input');
    assert.equal(events[0].action, 'on_typing');
    assert.equal(events[0].parameters.value, '<p>Hola</p>');

    // Blur -> onChange
    editor.simulateBlur();
    await flushAsync();
    assert.equal(events.length, 2);
    assert.equal(events[1].event, 'change');
    assert.equal(events[1].action, 'on_saved');
    assert.equal(events[1].parameters.value, '<p>Hola</p>');
    assert.equal(events[1].parameters.doc_html, '<p>Hola</p>');
    assert.equal(events[1].parameters.source, 'rte');

    // External backend update should update editor & textarea without triggering onInput
    comp.update({ value: '<p>Desde backend</p>', readonly: true, error: 'Campo requerido' });
    await new Promise((resolve) => setTimeout(resolve, 50));
    assert.equal(events.length, 2, 'External update must not echo onInput back to server');
    assert.equal(editor.state.html, '<p>Desde backend</p>');
    assert.equal(editor.state.isReadonly, true);
    assert.ok(el.classList.contains('has-error'));
    assert.equal(el.querySelector('.usim-richtext-error-icon')?.getAttribute('data-tooltip'), 'Campo requerido');

    comp.update({ disabled: true, error: null });
    assert.equal(editor.state.isDisabled, true);
    assert.equal(el.classList.contains('has-error'), false);
});
