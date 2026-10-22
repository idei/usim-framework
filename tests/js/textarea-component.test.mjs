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
        'packages/idei/usim/resources/assets/js/components/textarea/index.js',
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

test('Textarea: triggers onChange action without TypeError and sends event payload', async () => {
    const dom = createDomEnvironment();
    let sentPayload = null;
    let appliedUpdate = null;

    // Mock sendUiEvent and applyUiUpdate helpers
    dom.window.USIM_COMPONENT_HELPERS.sendUiEvent = async (payload) => {
        sentPayload = payload;
        return {
            ok: true,
            result: {
                toast: { message: 'Guardado correctamente', type: 'success' }
            }
        };
    };

    dom.window.USIM_COMPONENT_HELPERS.applyUiUpdate = (result) => {
        appliedUpdate = result;
    };

    const textareaComp = dom.window.USIM_COMPONENTS.create('textarea', 102, {
        name: 'post_content',
        label: 'Contenido',
        on_change: {
            action: 'on_plain_saved',
            parameters: { custom: 'foo' }
        }
    });

    const el = textareaComp.render();
    dom.window.document.body.appendChild(el);

    const ta = el.querySelector('textarea');
    assert.ok(ta, 'Textarea element should exist');

    ta.value = 'Texto de prueba';

    // Dispatch change event (blur / loss of focus)
    const event = new dom.window.Event('change');
    ta.dispatchEvent(event);

    // Wait a tick for async triggerAction
    await new Promise((resolve) => setTimeout(resolve, 10));

    assert.ok(sentPayload, 'sendUiEvent should have been called');
    assert.equal(sentPayload.componentId, 102);
    assert.equal(sentPayload.action, 'on_plain_saved');
    assert.equal(sentPayload.parameters.value, 'Texto de prueba');
    assert.equal(sentPayload.parameters.post_content, 'Texto de prueba');
    assert.equal(sentPayload.parameters.name, 'post_content');
    assert.equal(sentPayload.parameters.custom, 'foo');

    assert.ok(appliedUpdate, 'applyUiUpdate should have been called with result');
    assert.equal(appliedUpdate.toast.message, 'Guardado correctamente');
});

test('Textarea: triggers debounced onInput action with value', async () => {
    const dom = createDomEnvironment();
    let sentPayload = null;

    dom.window.USIM_COMPONENT_HELPERS.sendUiEvent = async (payload) => {
        sentPayload = payload;
        return { ok: true, result: {} };
    };

    const textareaComp = dom.window.USIM_COMPONENTS.create('textarea', 103, {
        name: 'post_content',
        debounce: 50,
        on_input: {
            action: 'on_typing',
            parameters: {}
        }
    });

    const el = textareaComp.render();
    dom.window.document.body.appendChild(el);

    const ta = el.querySelector('textarea');
    ta.value = 'Escribiendo';

    const inputEvent = new dom.window.Event('input');
    ta.dispatchEvent(inputEvent);

    assert.equal(sentPayload, null, 'Should not send event before debounce delay');

    // Wait past debounce time
    await new Promise((resolve) => setTimeout(resolve, 80));

    assert.ok(sentPayload, 'sendUiEvent should be called after debounce delay');
    assert.equal(sentPayload.componentId, 103);
    assert.equal(sentPayload.action, 'on_typing');
    assert.equal(sentPayload.parameters.value, 'Escribiendo');
});

