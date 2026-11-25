import test from 'node:test';
import assert from 'node:assert/strict';
import { JSDOM } from 'jsdom';
import fs from 'node:fs';
import path from 'node:path';

function createDomEnvironment(withSvgManifest = true) {
    const dom = new JSDOM('<!DOCTYPE html><html><head></head><body><div id="main"></div></body></html>', {
        runScripts: 'dangerously',
        url: 'http://localhost/',
    });

    if (withSvgManifest) {
        dom.window.USIM_ICONS = {
            search: '/vendor/idei/usim/icons/search.svg',
            filter: '/vendor/idei/usim/icons/filter.svg',
            grid: '/vendor/idei/usim/icons/grid.svg',
            list: '/vendor/idei/usim/icons/list.svg',
            edit: '/vendor/idei/usim/icons/edit.svg',
            trash: '/vendor/idei/usim/icons/trash.svg',
            eye: '/vendor/idei/usim/icons/eye.svg',
            plus: '/vendor/idei/usim/icons/plus.svg',
            user: '/vendor/idei/usim/icons/user.svg',
        };
    }

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
        'packages/idei/usim/resources/assets/js/components/shared/content-render.js',
        'packages/idei/usim/resources/assets/js/components/item-card/index.js',
        'packages/idei/usim/resources/assets/js/components/card-grid/index.js',
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

test('RFC 2.0: published SVG icons in CardGrid and ItemCard receive explicit non-zero dimensions from options.size and CSS rules', () => {
    const dom = createDomEnvironment(true);

    const gridComp = dom.window.USIM_COMPONENTS.create('card_grid', 501, {
        name: 'posts_grid',
        title: 'Artículos',
        searchable: true,
        show_layout_toggle: true,
        filters: [{ name: 'status', label: 'Estado', type: 'select', options: [{ value: 'pub', label: 'Publicado' }] }],
        header_actions: [
            { label: 'Publicar', action: 'create_post', icon: 'plus', style: 'primary', variant: 'solid' },
        ],
    });

    const gridEl = gridComp.render();
    dom.window.document.body.appendChild(gridEl);

    const gridSvgIcons = gridEl.querySelectorAll('.usim-cg-icon.ui-icon-svg');
    assert.ok(gridSvgIcons.length >= 5, `Expected at least 5 SVG icons in CardGrid (header, search, filter, grid, list), found ${gridSvgIcons.length}`);

    gridSvgIcons.forEach((iconEl) => {
        assert.ok(iconEl.style.width && iconEl.style.width !== '0px', `SVG icon missing width: ${iconEl.outerHTML}`);
        assert.ok(iconEl.style.height && iconEl.style.height !== '0px', `SVG icon missing height: ${iconEl.outerHTML}`);
        assert.ok(iconEl.style.maskImage.includes('.svg'), `SVG icon missing maskImage: ${iconEl.outerHTML}`);
    });

    const cardComp = dom.window.USIM_COMPONENTS.create('item_card', 502, {
        name: 'posts_grid__card_1',
        item_id: 1,
        title: 'Post de prueba',
        attributes: [{ icon: 'eye', label: 'Vistas', value: '120' }],
        actions: [
            { label: 'Editar', action: 'edit_post', icon: 'edit', style: 'primary', variant: 'outline' },
            { label: '', action: 'delete_post', icon: 'trash', style: 'danger', variant: 'ghost', tooltip: 'Eliminar post' },
        ],
    });

    const cardEl = cardComp.render();
    dom.window.document.body.appendChild(cardEl);

    const cardSvgIcons = cardEl.querySelectorAll('.usim-ic-icon.ui-icon-svg');
    assert.equal(cardSvgIcons.length, 3);
    assert.equal(cardSvgIcons[0].style.width, '13px');
    assert.equal(cardSvgIcons[0].style.height, '13px');
    assert.equal(cardSvgIcons[1].style.width, '14px');
    assert.equal(cardSvgIcons[1].style.height, '14px');
    assert.equal(cardSvgIcons[2].style.width, '14px');
    assert.equal(cardSvgIcons[2].style.height, '14px');

    // Verify CSS stylesheets also define base dimensions for .ui-icon-svg, .usim-cg-icon, and .usim-ic-icon
    const uiCss = fs.readFileSync(path.resolve('packages/idei/usim/resources/assets/css/ui-components.css'), 'utf8');
    const cgCss = fs.readFileSync(path.resolve('packages/idei/usim/resources/assets/css/components/card-grid/index.css'), 'utf8');
    const icCss = fs.readFileSync(path.resolve('packages/idei/usim/resources/assets/css/components/item-card/index.css'), 'utf8');

    assert.match(uiCss, /\.ui-icon-svg\s*\{[^}]*display:\s*inline-block[^}]*width:\s*[^;]+;[^}]*height:\s*[^;]+;/s);
    assert.match(cgCss, /\.usim-cg-icon\s*\{[^}]*display:\s*inline-block[^}]*width:\s*[^;]+;[^}]*height:\s*[^;]+;/s);
    assert.match(icCss, /\.usim-ic-icon\s*\{[^}]*display:\s*inline-block[^}]*width:\s*[^;]+;[^}]*height:\s*[^;]+;/s);
});

test('RFC 2.1 & 2.3: ItemCard supports image_fit scale-down, aria-label on icon-only buttons, and tooltip fallback in overflow menu', () => {
    const dom = createDomEnvironment(true);

    const cardComp = dom.window.USIM_COMPONENTS.create('item_card', 601, {
        name: 'posts_grid__card_10',
        item_id: 10,
        title: 'Artículo con acciones icon-only',
        image: '/files/uploads/images/cover.png',
        image_fit: 'scale-down',
        max_visible_actions: 1,
        actions: [
            {
                label: '',
                action: 'edit_post',
                icon: 'edit',
                style: 'primary',
                variant: 'outline',
                tooltip: 'Editar publicación',
            },
            {
                label: '',
                action: 'delete_post',
                icon: 'trash',
                style: 'danger',
                variant: 'ghost',
                tooltip: 'Eliminar publicación',
            },
        ],
    });

    const cardEl = cardComp.render();
    dom.window.document.body.appendChild(cardEl);

    // 2.1: image_fit: scale-down
    const img = cardEl.querySelector('.usim-ic-media img');
    assert.ok(img, 'Expected media img element');
    assert.equal(img.style.objectFit, 'scale-down');

    // 2.3 inline icon-only button gets title + aria-label
    const inlineBtn = cardEl.querySelector('.usim-ic-actions > button.usim-ic-action-btn');
    assert.ok(inlineBtn, 'Expected inline icon-only button');
    assert.equal(inlineBtn.title, 'Editar publicación');
    assert.equal(inlineBtn.getAttribute('aria-label'), 'Editar publicación');

    // 2.3 overflow menu item falls back to tooltip when label is empty
    const overflowTrigger = cardEl.querySelector('.usim-ic-overflow-trigger');
    assert.ok(overflowTrigger, 'Expected overflow trigger button');
    overflowTrigger.click();

    const menuItems = cardEl.querySelectorAll('.usim-ic-overflow-menu .usim-ic-overflow-item');
    assert.equal(menuItems.length, 1);
    assert.equal(menuItems[0].textContent.trim(), 'Eliminar publicación');
    assert.equal(menuItems[0].title, 'Eliminar publicación');
});

test('RFC 2.4: CardGrid renders semantic title_style and multiple contextual header_actions with event dispatch', async () => {
    const dom = createDomEnvironment(true);
    const dispatched = [];

    dom.window.fetch = async (_url, opts) => {
        if (opts && typeof opts.body === 'string') {
            dispatched.push(JSON.parse(opts.body));
        }
        return {
            ok: true,
            status: 200,
            headers: { get: () => 'application/json' },
            json: async () => ([]),
        };
    };

    const gridComp = dom.window.USIM_COMPONENTS.create('card_grid', 701, {
        name: 'home_posts_grid',
        title: 'Posts en revisión',
        title_style: 'warning',
        header_actions: [
            { label: 'Publicar Post', action: 'go_create', parameters: { from: 'header' }, icon: 'plus', style: 'primary', variant: 'solid' },
            { label: 'Gestionar Usuarios', action: 'go_users', icon: 'user', style: 'secondary', variant: 'outline' },
            { label: '', action: 'quick_help', icon: 'eye', style: 'info', variant: 'ghost', tooltip: 'Ver guía' },
        ],
    });

    const gridEl = gridComp.render();
    dom.window.document.body.appendChild(gridEl);

    const titleEl = gridEl.querySelector('.usim-cg-title');
    assert.ok(titleEl.classList.contains('style-warning'), 'Expected style-warning on title');

    const headerButtons = gridEl.querySelectorAll('.usim-cg-header-actions .usim-ic-action-btn');
    assert.equal(headerButtons.length, 3);
    assert.ok(headerButtons[0].classList.contains('variant-solid'));
    assert.ok(headerButtons[0].classList.contains('style-primary'));
    assert.ok(headerButtons[1].classList.contains('variant-outline'));
    assert.ok(headerButtons[1].classList.contains('style-secondary'));
    assert.equal(headerButtons[2].getAttribute('aria-label'), 'Ver guía');

    headerButtons[0].click();
    headerButtons[1].click();

    assert.equal(dispatched.length, 2);
    assert.equal(dispatched[0].action, 'go_create');
    assert.deepEqual(dispatched[0].parameters, { from: 'header' });
    assert.equal(dispatched[1].action, 'go_users');
});
