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

    dom.window.matchMedia = dom.window.matchMedia || (() => ({
        matches: false,
        media: '',
        onchange: null,
        addListener: () => {},
        removeListener: () => {},
        addEventListener: () => {},
        removeEventListener: () => {},
        dispatchEvent: () => false,
    }));

    const scriptFiles = [
        'packages/idei/usim/resources/assets/js/component-registry.js',
        'packages/idei/usim/resources/assets/js/ui-renderer.js',
        'packages/idei/usim/resources/assets/js/components/shared/ui-event.js',
        'packages/idei/usim/resources/assets/js/components/button/index.js',
        'packages/idei/usim/resources/assets/js/components/input/index.js',
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
    assert.equal(sentPayload.event, 'change');
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
    assert.equal(sentPayload.event, 'input');
    assert.equal(sentPayload.action, 'on_typing');
    assert.equal(sentPayload.parameters.value, 'Escribiendo');
});

test('UIComponent: defines triggerAction on base class as robust fallback', async () => {
    const dom = createDomEnvironment();
    let sentPayload = null;
    let appliedUpdate = null;

    dom.window.USIM_COMPONENT_HELPERS.sendUiEvent = async (payload) => {
        sentPayload = payload;
        return { ok: true, result: { success: true } };
    };

    dom.window.USIM_COMPONENT_HELPERS.applyUiUpdate = (result) => {
        appliedUpdate = result;
    };

    class DummyComponent extends dom.window.UIComponent {
        render() {
            return dom.window.document.createElement('div');
        }
    }

    const dummy = new DummyComponent('888', { name: 'dummy' });
    assert.equal(typeof dummy.triggerAction, 'function', 'UIComponent must provide triggerAction');

    await dummy.triggerAction('custom_action', { foo: 'bar' }, 'custom_event');

    assert.ok(sentPayload, 'Base triggerAction must dispatch via sendUiEvent');
    assert.equal(sentPayload.componentId, 888);
    assert.equal(sentPayload.event, 'custom_event');
    assert.equal(sentPayload.action, 'custom_action');
    assert.deepEqual(sentPayload.parameters, { foo: 'bar' });
    assert.deepEqual(appliedUpdate, { success: true });
});

test('Textarea: assigns name attribute to DOM element and is collected by button collectContextValues', async () => {
    const dom = createDomEnvironment();

    // Create a container with class ui-container
    const container = dom.window.document.createElement('div');
    container.className = 'ui-container';
    dom.window.document.body.appendChild(container);

    // Create textarea component
    const textareaComp = dom.window.USIM_COMPONENTS.create('textarea', 201, {
        name: 'input_message',
        label: 'Mensaje',
        value: 'Hola mundo desde el textarea',
    });
    const taEl = textareaComp.render();
    container.appendChild(taEl);

    // Create button component
    const buttonComp = dom.window.USIM_COMPONENTS.create('button', 202, {
        label: 'Enviar',
        action: 'submit_form',
    });
    const btnEl = buttonComp.render();
    container.appendChild(btnEl);

    // Verify textarea DOM element has name attribute
    const ta = taEl.querySelector('textarea');
    assert.ok(ta, 'Textarea element should exist');
    assert.equal(ta.name, 'input_message', 'Textarea element must have name attribute matching config');
    assert.equal(ta.value, 'Hola mundo desde el textarea');

    // Verify button collectContextValues collects input_message
    const collected = buttonComp.collectContextValues();
    assert.equal(collected.input_message, 'Hola mundo desde el textarea', 'collectContextValues must include textarea value under its name');
});

test('Textarea: error renders error icon with tooltip matching input style, without footer error text', () => {
    const dom = createDomEnvironment();

    const textareaComp = dom.window.USIM_COMPONENTS.create('textarea', 301, {
        name: 'message',
        label: 'Mensaje',
        error: 'El mensaje es obligatorio',
    });

    const el = textareaComp.render();
    dom.window.document.body.appendChild(el);

    assert.ok(el.classList.contains('has-error'), 'Wrapper must have has-error class');

    const errorIcon = el.querySelector('.usim-textarea-error-icon');
    assert.ok(errorIcon, 'Error icon must exist inside textarea');
    assert.ok(errorIcon.classList.contains('ui-input-error-icon'), 'Error icon must share ui-input-error-icon class');
    assert.equal(errorIcon.getAttribute('data-tooltip'), 'El mensaje es obligatorio');

    const footerErrMsg = el.querySelector('.usim-textarea-error-msg');
    assert.equal(footerErrMsg, null, 'Footer error text must not be rendered');

    // Test dynamic clearing of error
    textareaComp.update({ error: null });
    assert.equal(el.classList.contains('has-error'), false);
    assert.equal(el.querySelector('.usim-textarea-error-icon'), null);

    // Test dynamic setting of error
    textareaComp.update({ error: 'Nuevo error' });
    assert.ok(el.classList.contains('has-error'));
    const newIcon = el.querySelector('.usim-textarea-error-icon');
    assert.ok(newIcon);
    assert.equal(newIcon.getAttribute('data-tooltip'), 'Nuevo error');
});

test('Input: renders helpText matching textarea help icon style and updates dynamically', () => {
    const dom = createDomEnvironment();

    const inputComp = dom.window.USIM_COMPONENTS.create('input', 302, {
        name: 'user_name',
        label: 'Nombre',
        help_text: 'El nombre es obligatorio',
        error: 'Nombre demasiado corto',
    });

    const el = inputComp.render();
    dom.window.document.body.appendChild(el);

    const helpIcon = el.querySelector('.ui-input-help-icon');
    assert.ok(helpIcon, 'Help icon must exist in input');
    assert.equal(helpIcon.textContent, '?');
    assert.equal(helpIcon.getAttribute('data-tooltip'), 'El nombre es obligatorio');

    const errorIcon = el.querySelector('.ui-input-error-icon');
    assert.ok(errorIcon, 'Error icon must exist in input');
    assert.equal(errorIcon.getAttribute('data-tooltip'), 'Nombre demasiado corto');

    // Test dynamic update of help_text
    inputComp.update({ help_text: 'Texto de ayuda actualizado' });
    const updatedHelpIcon = el.querySelector('.ui-input-help-icon');
    assert.ok(updatedHelpIcon);
    assert.equal(updatedHelpIcon.getAttribute('data-tooltip'), 'Texto de ayuda actualizado');

    // Test dynamic clearing of help_text
    inputComp.update({ help_text: null });
    assert.equal(el.querySelector('.ui-input-help-icon'), null);
});

test('Textarea: removes footer and renders subtle top-right in-field counter for min_length and max_length', () => {
    const dom = createDomEnvironment();

    const textareaComp = dom.window.USIM_COMPONENTS.create('textarea', 401, {
        name: 'summary',
        min_length: 50,
        max_length: 100,
        value: 'a'.repeat(40),
    });

    const el = textareaComp.render();
    dom.window.document.body.appendChild(el);

    assert.equal(el.querySelector('.usim-textarea-footer'), null, 'div.usim-textarea-footer must not exist');

    const field = el.querySelector('.usim-textarea-field');
    const ta = field?.querySelector('textarea');
    const counter = field?.querySelector('.usim-textarea-counter');

    assert.ok(field, 'Field wrapper should exist');
    assert.ok(ta, 'Textarea should live inside .usim-textarea-field');
    assert.ok(counter, 'Counter should live inside .usim-textarea-field');
    assert.equal(ta.getAttribute('minlength'), '50');
    assert.equal(ta.getAttribute('maxlength'), '100');

    // 40 chars out of min 50 -> -10 in error color
    assert.equal(counter.hidden, false);
    assert.equal(counter.textContent, '-10');
    assert.ok(counter.classList.contains('is-error'));
    assert.equal(counter.classList.contains('is-warning'), false);

    // 50 chars -> remaining until 100 is 50 in normal color
    ta.value = 'a'.repeat(50);
    ta.dispatchEvent(new dom.window.Event('input'));
    assert.equal(counter.textContent, '50');
    assert.equal(counter.classList.contains('is-error'), false);
    assert.equal(counter.classList.contains('is-warning'), false);

    // 75 chars -> remaining until 100 is 25 in normal color
    ta.value = 'a'.repeat(75);
    ta.dispatchEvent(new dom.window.Event('input'));
    assert.equal(counter.textContent, '25');
    assert.equal(counter.classList.contains('is-error'), false);

    // 100 chars -> 0 in normal color
    ta.value = 'a'.repeat(100);
    ta.dispatchEvent(new dom.window.Event('input'));
    assert.equal(counter.textContent, '0');
    assert.equal(counter.classList.contains('is-error'), false);
});

test('Textarea: hides counter once min_length is met when max_length is omitted', () => {
    const dom = createDomEnvironment();

    const textareaComp = dom.window.USIM_COMPONENTS.create('textarea', 402, {
        name: 'notes',
        min_length: 50,
        value: 'a'.repeat(40),
    });

    const el = textareaComp.render();
    dom.window.document.body.appendChild(el);

    const ta = el.querySelector('textarea');
    const counter = el.querySelector('.usim-textarea-counter');
    assert.ok(counter);
    assert.equal(counter.hidden, false);
    assert.equal(counter.textContent, '-10');
    assert.ok(counter.classList.contains('is-error'));

    // Passing min_length without max_length hides the indicator
    ta.value = 'a'.repeat(50);
    ta.dispatchEvent(new dom.window.Event('input'));
    assert.equal(counter.hidden, true);
    assert.equal(counter.style.display, 'none');

    // Dropping back below min_length reveals it again
    ta.value = 'a'.repeat(49);
    ta.dispatchEvent(new dom.window.Event('input'));
    assert.equal(counter.hidden, false);
    assert.equal(counter.textContent, '-1');
    assert.ok(counter.classList.contains('is-error'));
});

test('Textarea: shows remaining count when only max_length is defined', () => {
    const dom = createDomEnvironment();

    const textareaComp = dom.window.USIM_COMPONENTS.create('textarea', 403, {
        name: 'bio',
        max_length: 100,
        value: 'a'.repeat(25),
    });

    const el = textareaComp.render();
    const counter = el.querySelector('.usim-textarea-counter');
    assert.ok(counter);
    assert.equal(counter.hidden, false);
    assert.equal(counter.textContent, '75');
    assert.equal(counter.classList.contains('is-error'), false);
    assert.equal(counter.classList.contains('is-warning'), false);
});

test('Textarea: ignores invalid bounds (min_length >= max_length) and displays warning ?', () => {
    const dom = createDomEnvironment();

    for (const [id, minLen, maxLen] of [[404, 50, 50], [405, 80, 50], [406, 0, 0]]) {
        const comp = dom.window.USIM_COMPONENTS.create('textarea', id, {
            name: `field_${id}`,
            min_length: minLen,
            max_length: maxLen,
            value: 'hola',
        });

        const el = comp.render();
        const ta = el.querySelector('textarea');
        const counter = el.querySelector('.usim-textarea-counter');

        assert.equal(ta.getAttribute('minlength'), null, `minlength must be ignored for (${minLen}, ${maxLen})`);
        assert.equal(ta.getAttribute('maxlength'), null, `maxlength must be ignored for (${minLen}, ${maxLen})`);
        assert.ok(counter, 'Warning indicator must be rendered');
        assert.equal(counter.hidden, false);
        assert.equal(counter.textContent, '?');
        assert.ok(counter.classList.contains('is-warning'));
        assert.equal(counter.classList.contains('is-error'), false);
    }
});

test('Textarea: supports in-field counter in markdown mode and dynamic update()', () => {
    const dom = createDomEnvironment();

    const comp = dom.window.USIM_COMPONENTS.create('textarea', 407, {
        name: 'md_notes',
        mode: 'markdown',
        min_length: 10,
        max_length: 30,
        value: '12345',
    });

    const el = comp.render();
    assert.equal(el.querySelector('.usim-textarea-footer'), null);

    const editorField = el.querySelector('.usim-textarea-editor-pane .usim-textarea-field');
    assert.ok(editorField, 'Markdown editor pane should wrap textarea in .usim-textarea-field');

    const counter = editorField.querySelector('.usim-textarea-counter');
    const ta = editorField.querySelector('textarea');
    assert.equal(counter.textContent, '-5');
    assert.ok(counter.classList.contains('is-error'));

    // Dynamic update into invalid bounds
    comp.update({ min_length: 30 });
    assert.equal(counter.textContent, '?');
    assert.ok(counter.classList.contains('is-warning'));
    assert.equal(ta.getAttribute('minlength'), null);
    assert.equal(ta.getAttribute('maxlength'), null);

    // Dynamic update clearing limits
    comp.update({ min_length: null, max_length: null });
    assert.equal(counter.hidden, true);
    assert.equal(counter.style.display, 'none');
});



