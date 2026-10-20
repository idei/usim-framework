import test from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import fs from 'node:fs';
import path from 'node:path';

// Setup browser DOM environment using JSDOM
const dom = new JSDOM('<!DOCTYPE html><html><head></head><body><div id="main"></div><div id="menu"></div></body></html>', {
    runScripts: 'dangerously',
    url: 'http://localhost/',
});

dom.window.fetch = async () => ({
    ok: true,
    json: async () => ({})
});

const uiRendererPath = path.resolve('packages/idei/usim/resources/assets/js/ui-renderer.js');
const uiRendererCode = fs.readFileSync(uiRendererPath, 'utf8');

const scriptEl = dom.window.document.createElement('script');
scriptEl.textContent = uiRendererCode;
dom.window.document.head.appendChild(scriptEl);

const renderer = new dom.window.UIRenderer({});
const sessionStorage = dom.window.sessionStorage;

test('Frontend Storage: preserves existing store_token when incoming delta does not contain store_token', () => {
    sessionStorage.clear();
    const storageKey = 'usim';

    // 1. Initial login sets token and theme
    sessionStorage.setItem(storageKey, JSON.stringify({
        store_token: 'valid_bearer_token_123',
        store_theme: 'dark'
    }));

    // 2. Incoming delta from a screen that only defines store_theme and store_page (like Home or PublishPost)
    renderer.handleStorageUpdate({
        [storageKey]: JSON.stringify({
            store_theme: 'light',
            store_page: 2
        })
    });

    const stored = JSON.parse(sessionStorage.getItem(storageKey));
    assert.equal(stored.store_token, 'valid_bearer_token_123', 'store_token must not be lost when missing from incoming delta');
    assert.equal(stored.store_theme, 'light', 'store_theme must be updated');
    assert.equal(stored.store_page, 2, 'store_page must be merged');
});

test('Frontend Storage: protects store_token from accidental empty string overwrite during normal screen flow', () => {
    sessionStorage.clear();
    const storageKey = 'usim';

    sessionStorage.setItem(storageKey, JSON.stringify({
        store_token: 'auth_token_persisted',
        store_theme: 'dark'
    }));

    // Incoming delta has empty store_token without logout action (e.g. MenuStub or uninitialized screen)
    renderer.handleStorageUpdate({
        [storageKey]: JSON.stringify({
            store_token: '',
            store_theme: 'dark',
            store_counter: 5
        })
    }, { action: 'save_post' });

    const stored = JSON.parse(sessionStorage.getItem(storageKey));
    assert.equal(stored.store_token, 'auth_token_persisted', 'store_token must be protected from empty string overwrite');
    assert.equal(stored.store_counter, 5, 'other properties must be merged properly');
});

test('Frontend Storage: clears store_token when explicit logout action is triggered', () => {
    sessionStorage.clear();
    const storageKey = 'usim';

    sessionStorage.setItem(storageKey, JSON.stringify({
        store_token: 'auth_token_to_clear',
        store_theme: 'dark'
    }));

    // Explicit logout action confirm_logout
    renderer.handleStorageUpdate({
        [storageKey]: JSON.stringify({
            store_token: '',
            store_theme: 'dark'
        })
    }, { action: 'confirm_logout' });

    const stored = JSON.parse(sessionStorage.getItem(storageKey));
    assert.equal(stored.store_token, undefined, 'store_token must be deleted/cleared on explicit confirm_logout');
    assert.equal(stored.store_theme, 'dark', 'store_theme should still exist');
});

test('Frontend Storage: protects store_token_crypt from accidental clobbering', () => {
    sessionStorage.clear();
    const storageKey = 'usim';

    sessionStorage.setItem(storageKey, JSON.stringify({
        store_token_crypt: 'encrypted_secret_token_xyz',
        store_lang: 'es'
    }));

    renderer.handleStorageUpdate({
        [storageKey]: JSON.stringify({
            store_token_crypt: '',
            store_lang: 'en'
        })
    }, { action: 'toggle_language' });

    const stored = JSON.parse(sessionStorage.getItem(storageKey));
    assert.equal(stored.store_token_crypt, 'encrypted_secret_token_xyz', 'store_token_crypt must be preserved');
    assert.equal(stored.store_lang, 'en', 'store_lang must be updated');
});

