const DEFAULT_USIM_STORAGE_KEY = 'usim';
const USIM_STORAGE_KEY_SESSION = '__usim_storage_key__';
const DEFAULT_USIM_THEME = 'dark';
const SUPPORTED_USIM_THEMES = new Set(['light', 'dark']);

function safeParseJsonObject(rawValue) {
    if (typeof rawValue !== 'string' || !rawValue.trim()) {
        return null;
    }

    try {
        const parsed = JSON.parse(rawValue);
        return (parsed && typeof parsed === 'object') ? parsed : null;
    } catch (error) {
        return null;
    }
}

function detectUsimStorageKeyFromLocalStorage() {
    try {
        const keys = Object.keys(localStorage);
        let fallbackCandidate = '';

        for (const key of keys) {
            const parsedObject = safeParseJsonObject(localStorage.getItem(key));
            if (!parsedObject) {
                continue;
            }

            const parsedKeys = Object.keys(parsedObject);
            const hasStoreVariables = parsedKeys.some(parsedKey => parsedKey.startsWith('store_'));
            if (!hasStoreVariables) {
                continue;
            }

            // Strong signal: store_theme exists in this payload.
            if (Object.prototype.hasOwnProperty.call(parsedObject, 'store_theme')) {
                return key;
            }

            // Keep first store_* candidate as fallback.
            if (!fallbackCandidate) {
                fallbackCandidate = key;
            }
        }

        return fallbackCandidate || '';
    } catch (error) {
        // Ignore storage access errors (private mode / blocked storage)
        return '';
    }
}

function setActiveUsimStorageKey(key) {
    const normalizedKey = typeof key === 'string' ? key.trim() : '';
    if (!normalizedKey) {
        return;
    }

    window.USIM_STORAGE_KEY = normalizedKey;

    try {
        sessionStorage.setItem(USIM_STORAGE_KEY_SESSION, normalizedKey);
    } catch (error) {
        // Ignore storage access errors (private mode / blocked storage)
    }
}

function getActiveUsimStorageKey() {
    const windowKey = typeof window.USIM_STORAGE_KEY === 'string'
        ? window.USIM_STORAGE_KEY.trim()
        : '';
    if (windowKey) {
        return windowKey;
    }

    try {
        const sessionKey = sessionStorage.getItem(USIM_STORAGE_KEY_SESSION);
        if (typeof sessionKey === 'string' && sessionKey.trim()) {
            return sessionKey.trim();
        }
    } catch (error) {
        // Ignore storage access errors (private mode / blocked storage)
    }

    const detectedStorageKey = detectUsimStorageKeyFromLocalStorage();
    if (detectedStorageKey) {
        setActiveUsimStorageKey(detectedStorageKey);
        return detectedStorageKey;
    }

    return DEFAULT_USIM_STORAGE_KEY;
}

function getUsimStorageValue() {
    const storageKey = getActiveUsimStorageKey();
    return localStorage.getItem(storageKey) || '';
}

function encodeHeaderSafeValue(rawValue) {
    if (typeof rawValue !== 'string' || rawValue.length === 0) {
        return '';
    }

    try {
        const utf8Bytes = new TextEncoder().encode(rawValue);
        let binary = '';
        for (const byte of utf8Bytes) {
            binary += String.fromCharCode(byte);
        }
        return `b64:${btoa(binary)}`;
    } catch (error) {
        // Fallback: return raw value if encoding APIs are unavailable.
        return rawValue;
    }
}

function getUsimStorageHeaderValue() {
    return encodeHeaderSafeValue(getUsimStorageValue());
}

function getUsimStorageObject() {
    const storageRaw = getUsimStorageValue();
    return safeParseJsonObject(storageRaw) || {};
}

function getPersistedStoreValue(storeKey) {
    if (typeof storeKey !== 'string' || !storeKey.trim()) {
        return null;
    }

    const storageObject = getUsimStorageObject();
    if (Object.prototype.hasOwnProperty.call(storageObject, storeKey)) {
        return storageObject[storeKey];
    }

    // Backward compatibility for legacy flat keys in localStorage.
    const legacyValue = localStorage.getItem(storeKey);
    return legacyValue !== null ? legacyValue : null;
}

function persistStoreValue(storeKey, value) {
    if (typeof storeKey !== 'string' || !storeKey.trim()) {
        return false;
    }

    try {
        const storageKey = getActiveUsimStorageKey();
        const storageObject = getUsimStorageObject();
        storageObject[storeKey] = value;
        localStorage.setItem(storageKey, JSON.stringify(storageObject));
        return true;
    } catch (error) {
        // Ignore storage write errors (private mode / blocked storage)
        return false;
    }
}

function normalizeUsimTheme(theme) {
    if (typeof theme !== 'string') {
        return null;
    }

    const normalizedTheme = theme.trim().toLowerCase();
    if (!SUPPORTED_USIM_THEMES.has(normalizedTheme)) {
        return null;
    }

    return normalizedTheme;
}

function getThemeFromDocument() {
    const htmlTheme = document.documentElement.getAttribute('data-theme');
    const bodyTheme = document.body ? document.body.getAttribute('data-theme') : null;
    return normalizeUsimTheme(htmlTheme) || normalizeUsimTheme(bodyTheme);
}

function applyThemeToDocument(theme, dispatchChangeEvent = false, source = 'framework') {
    const normalizedTheme = normalizeUsimTheme(theme);
    if (!normalizedTheme) {
        return false;
    }

    document.documentElement.setAttribute('data-theme', normalizedTheme);
    document.documentElement.style.colorScheme = normalizedTheme;

    if (document.body) {
        document.body.setAttribute('data-theme', normalizedTheme);
        document.body.style.colorScheme = normalizedTheme;
    }

    if (dispatchChangeEvent) {
        window.dispatchEvent(new CustomEvent('usim:theme-changed', {
            detail: { theme: normalizedTheme, source }
        }));
    }

    return true;
}

function setGlobalUsimTheme(theme, source = 'framework-api', dispatchChangeEvent = true, persistTheme = true) {
    const normalizedTheme = normalizeUsimTheme(theme);
    if (!normalizedTheme) {
        return false;
    }

    applyThemeToDocument(normalizedTheme, false, source);

    if (persistTheme) {
        persistStoreValue('store_theme', normalizedTheme);
    }

    if (dispatchChangeEvent) {
        window.dispatchEvent(new CustomEvent('usim:theme-changed', {
            detail: { theme: normalizedTheme, source }
        }));
    }

    return true;
}

function applyPersistedThemeFromStorage(dispatchChangeEvent = false, source = 'storage') {
    const persistedTheme = getPersistedStoreValue('store_theme');
    if (normalizeUsimTheme(persistedTheme)) {
        return setGlobalUsimTheme(persistedTheme, source, dispatchChangeEvent, false);
    }

    const documentTheme = getThemeFromDocument();
    if (documentTheme) {
        return setGlobalUsimTheme(documentTheme, source, dispatchChangeEvent, false);
    }

    return setGlobalUsimTheme(DEFAULT_USIM_THEME, source, dispatchChangeEvent, false);
}

function normalizeTextLineBreaks(value) {
    const text = value === undefined || value === null ? '' : String(value);
    return text.replace(/\r\n?/g, '\n').replace(/\\n/g, '\n');
}

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function sanitizeMarkdownUrl(rawUrl) {
    const value = String(rawUrl || '').trim();
    if (!value) {
        return '#';
    }

    if (value.startsWith('/')) {
        return value;
    }

    const lowered = value.toLowerCase();
    const allowedProtocols = ['http://', 'https://', 'mailto:', 'tel:'];
    if (allowedProtocols.some(protocol => lowered.startsWith(protocol))) {
        return value;
    }

    return '#';
}

function renderSimpleMarkdownToSafeHtml(value) {
    const normalizedText = normalizeTextLineBreaks(value);
    let html = escapeHtml(normalizedText);

    // Inline code first to prevent other markdown replacements from changing code snippets.
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');

    // Links: [label](https://example.com)
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, (_match, label, url) => {
        const safeUrl = escapeHtml(sanitizeMarkdownUrl(url));
        return `<a href="${safeUrl}" target="_blank" rel="noopener noreferrer">${label}</a>`;
    });

    // Basic emphasis support.
    html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    html = html.replace(/\*([^*]+)\*/g, '<em>$1</em>');
    html = html.replace(/_([^_]+)_/g, '<em>$1</em>');
    html = html.replace(/~~([^~]+)~~/g, '<del>$1</del>');

    // Headings (single-line).
    html = html.replace(/^######\s+(.+)$/gm, '<h6>$1</h6>');
    html = html.replace(/^#####\s+(.+)$/gm, '<h5>$1</h5>');
    html = html.replace(/^####\s+(.+)$/gm, '<h4>$1</h4>');
    html = html.replace(/^###\s+(.+)$/gm, '<h3>$1</h3>');
    html = html.replace(/^##\s+(.+)$/gm, '<h2>$1</h2>');
    html = html.replace(/^#\s+(.+)$/gm, '<h1>$1</h1>');

    // Preserve line breaks from plain text and escaped "\\n" payloads.
    html = html.replace(/\n/g, '<br>');

    return html;
}

// Apply theme as early as possible so CSS tokens are correct before initial UI render.
applyPersistedThemeFromStorage(false, 'framework-bootstrap');

window.USIM_THEME = {
    get() {
        return getThemeFromDocument() || normalizeUsimTheme(getPersistedStoreValue('store_theme')) || DEFAULT_USIM_THEME;
    },
    set(theme, source = 'external') {
        return setGlobalUsimTheme(theme, source, true, true);
    }
};

window.addEventListener('usim:theme-changed', (event) => {
    const theme = event?.detail?.theme;
    const source = event?.detail?.source;

    if (typeof source === 'string' && source.startsWith('framework-')) {
        return;
    }

    if (!normalizeUsimTheme(theme)) {
        return;
    }

    setGlobalUsimTheme(theme, 'framework-event-sync', false, true);
});

// ==================== Base Component Class ====================
class UIComponent {
    constructor(id, config) {
        this.id = id;
        this.config = config;
        this.element = null;
    }

    getComponentId() {
        const parsedId = Number.parseInt(this.id, 10);
        return Number.isNaN(parsedId) ? this.id : parsedId;
    }

    render() {
        // Override in subclasses
        throw new Error('render() must be implemented by subclass');
    }

    mount(parentElement) {
        if (!this.element) {
            this.element = this.render();
        }
        if (parentElement) {
            parentElement.appendChild(this.element);
        }
    }

    applyCommonAttributes(element) {
        const componentId = this.getComponentId();
        const isTransparentColor = (value) => {
            if (typeof value !== 'string') {
                return false;
            }

            const normalized = value.trim().toLowerCase();
            if (!normalized) {
                return false;
            }

            if (normalized === 'transparent') {
                return true;
            }

            const rgbaMatch = normalized.match(/^rgba\([^,]+,[^,]+,[^,]+,\s*([\d.]+)\s*\)$/);
            if (rgbaMatch) {
                return Number(rgbaMatch[1]) < 1;
            }

            const hslaMatch = normalized.match(/^hsla\([^,]+,[^,]+,[^,]+,\s*([\d.]+)\s*\)$/);
            if (hslaMatch) {
                return Number(hslaMatch[1]) < 1;
            }

            const hexAlphaMatch = normalized.match(/^#([0-9a-f]{8})$/);
            if (hexAlphaMatch) {
                const alphaHex = hexAlphaMatch[1].slice(6, 8);
                return parseInt(alphaHex, 16) < 255;
            }

            return false;
        };
        const normalizeSizeValue = (value) => typeof value === 'number' ? value + 'px' : value;
        const normalizeOffset = (value) => {
            if (value === undefined || value === null || value === '') return '0px';
            if (typeof value === 'number') return `${value}px`;
            const trimmed = String(value).trim();
            return /^\d+(\.\d+)?$/.test(trimmed) ? `${trimmed}px` : trimmed;
        };
        const normalizeAnchor = (value) => {
            if (!value) return null;
            const raw = String(value).trim().toUpperCase().replace(/-/g, '_');
            const aliases = {
                TOP_MIDDLE: 'TOP_CENTER',
                BOTTOM_MIDDLE: 'BOTTOM_CENTER',
                LEFT_TOP: 'TOP_LEFT',
                LEFT_MIDDLE: 'MIDDLE_LEFT',
                LEFT_BOTTOM: 'BOTTOM_LEFT',
                RIGHT_TOP: 'TOP_RIGHT',
                RIGHT_MIDDLE: 'MIDDLE_RIGHT',
                RIGHT_BOTTOM: 'BOTTOM_RIGHT',
            };
            return aliases[raw] || raw;
        };
        const layout = typeof this.config.layout === 'string'
            ? this.config.layout.toLowerCase()
            : null;

        element.setAttribute('data-component-id', componentId);
        if (this.config.name) {
            element.id = this.config.name;
        }

        // Ensure layout-specific spacing works even when CSS only defines part of the layouts.
        if (layout === 'grid') {
            element.style.setProperty('display', 'grid', 'important');
        } else if (layout === 'flex') {
            element.style.setProperty('display', 'flex', 'important');
        }

        if (this.config.flex_direction) {
            element.style.setProperty('flex-direction', this.config.flex_direction, 'important');
        }
        if (this.config.flex_wrap) {
            element.style.setProperty('flex-wrap', this.config.flex_wrap, 'important');
        }

        if (this.config.grid_template_columns) {
            element.style.setProperty('grid-template-columns', this.config.grid_template_columns, 'important');
        }
        if (this.config.grid_template_rows) {
            element.style.setProperty('grid-template-rows', this.config.grid_template_rows, 'important');
        }
        if (this.config.grid_template_areas) {
            element.style.setProperty('grid-template-areas', this.config.grid_template_areas, 'important');
        }
        if (this.config.grid_auto_columns) {
            element.style.setProperty('grid-auto-columns', this.config.grid_auto_columns, 'important');
        }
        if (this.config.grid_auto_rows) {
            element.style.setProperty('grid-auto-rows', this.config.grid_auto_rows, 'important');
        }
        if (this.config.grid_auto_flow) {
            element.style.setProperty('grid-auto-flow', this.config.grid_auto_flow, 'important');
        }

        // Apply visual styling if specified
        if (this.config.background_color) {
            const isTopMenuBar = element.id === 'top-menu-bar' || this.config.name === 'top-menu-bar';
            if (isTopMenuBar && isTransparentColor(this.config.background_color)) {
                element.style.backgroundColor = 'var(--usim-menu-bar-bg, var(--ui-surface))';
            } else {
                element.style.backgroundColor = this.config.background_color;
            }
        }
        if (this.config.text_color) {
            element.style.color = this.config.text_color;
        }
        if (this.config.border_color) {
            element.style.borderColor = this.config.border_color;
        }
        if (this.config.background_image) {
            element.style.backgroundImage = `url(${this.config.background_image})`;
        }
        if (this.config.background_size) {
            element.style.backgroundSize = this.config.background_size;
        }
        if (this.config.background_position) {
            element.style.backgroundPosition = this.config.background_position;
        }
        if (this.config.border) {
            element.style.border = this.config.border === true
                ? '1px solid #d7dee8'
                : this.config.border;
        }
        if (this.config.box_shadow) {
            element.style.boxShadow = this.config.box_shadow;
        }
        if (this.config.border_radius) {
            element.style.borderRadius = this.config.border_radius;
        }
        if (this.config.opacity !== undefined && this.config.opacity !== null) {
            element.style.opacity = this.config.opacity;
        }

        // Apply layout properties with !important to override CSS
        if (this.config.justify_content) {
            element.style.setProperty('justify-content', this.config.justify_content, 'important');
        }
        if (this.config.align_items) {
            element.style.setProperty('align-items', this.config.align_items, 'important');
        }
        if (this.config.gap !== undefined && this.config.gap !== null && this.config.gap !== '') {
            element.style.setProperty('gap', normalizeSizeValue(this.config.gap), 'important');
        }
        if (this.config.row_gap !== undefined && this.config.row_gap !== null && this.config.row_gap !== '') {
            element.style.setProperty('row-gap', normalizeSizeValue(this.config.row_gap), 'important');
        }
        if (this.config.column_gap !== undefined && this.config.column_gap !== null && this.config.column_gap !== '') {
            element.style.setProperty('column-gap', normalizeSizeValue(this.config.column_gap), 'important');
        }

        // Apply padding
        if (this.config.padding !== undefined) {
            if (typeof this.config.padding === 'number') {
                element.style.padding = this.config.padding + 'px';
            } else {
                element.style.padding = this.config.padding;
            }
        }

        // Apply margin
        if (this.config.margin !== undefined) {
            element.style.margin = this.config.margin;
        }
        if (this.config.margin_left !== undefined) {
            element.style.setProperty('margin-left', this.config.margin_left, 'important');
        }
        if (this.config.margin_right !== undefined) {
            element.style.marginRight = this.config.margin_right;
        }
        if (this.config.margin_top !== undefined) {
            element.style.marginTop = this.config.margin_top;
        }
        if (this.config.margin_bottom !== undefined) {
            element.style.marginBottom = this.config.margin_bottom;
        }

        // Apply sizing
        if (this.config.width !== undefined) {
            element.style.width = this.config.width;
        }
        if (this.config.height !== undefined) {
            element.style.height = this.config.height;
        }
        if (this.config.max_width !== undefined) {
            element.style.maxWidth = this.config.max_width;
        }
        if (this.config.max_height !== undefined) {
            element.style.maxHeight = this.config.max_height;
        }
        if (this.config.min_width !== undefined) {
            element.style.minWidth = this.config.min_width;
        }
        if (this.config.min_height !== undefined) {
            element.style.minHeight = this.config.min_height;
        }

        // Apply font size
        if (this.config.font_size) {
            element.style.fontSize = this.config.font_size + 'px';
        }

        // Apply accessibility attributes
        if (this.config.aria_label) {
            element.setAttribute('aria-label', this.config.aria_label);
        }
        if (this.config.role) {
            element.setAttribute('role', this.config.role);
        }

        // Generic positioning for all components (except menu dropdown trigger positioning)
        const isMenuDropdown = (this.config.type || '').toLowerCase() === 'menudropdown';
        if (!isMenuDropdown) {
            const rawPosition = this.config.position;
            const rawMode = typeof this.config.position_mode === 'string'
                ? this.config.position_mode.toLowerCase()
                : null;
            const cssPositionModes = new Set(['static', 'relative', 'absolute', 'fixed', 'sticky']);

            // Backward compatibility: allow using position as CSS mode.
            const positionAsMode = typeof rawPosition === 'string' && cssPositionModes.has(rawPosition.toLowerCase())
                ? rawPosition.toLowerCase()
                : null;

            const mode = rawMode || positionAsMode;
            const anchor = normalizeAnchor(rawPosition);

            if (mode && cssPositionModes.has(mode)) {
                element.style.position = mode;
            }

            if (this.config.z_index !== undefined && this.config.z_index !== null && this.config.z_index !== '') {
                element.style.zIndex = String(this.config.z_index);
            }

            // Anchor positioning applies only when mode supports offsets.
            const supportsAnchors = (mode === 'absolute' || mode === 'fixed');
            if (supportsAnchors && anchor) {
                const x = normalizeOffset(this.config.position_offset_x);
                const y = normalizeOffset(this.config.position_offset_y);

                // Reset before applying anchor mapping
                element.style.top = '';
                element.style.right = '';
                element.style.bottom = '';
                element.style.left = '';
                element.style.translate = '';

                switch (anchor) {
                    case 'TOP_LEFT':
                        element.style.top = y;
                        element.style.left = x;
                        break;
                    case 'TOP_CENTER':
                        element.style.top = y;
                        element.style.left = '50%';
                        element.style.translate = '-50% 0';
                        break;
                    case 'TOP_RIGHT':
                        element.style.top = y;
                        element.style.right = x;
                        break;
                    case 'MIDDLE_LEFT':
                        element.style.top = '50%';
                        element.style.left = x;
                        element.style.translate = '0 -50%';
                        break;
                    case 'CENTER':
                        element.style.top = '50%';
                        element.style.left = '50%';
                        element.style.translate = '-50% -50%';
                        break;
                    case 'MIDDLE_RIGHT':
                        element.style.top = '50%';
                        element.style.right = x;
                        element.style.translate = '0 -50%';
                        break;
                    case 'BOTTOM_LEFT':
                        element.style.bottom = y;
                        element.style.left = x;
                        break;
                    case 'BOTTOM_CENTER':
                        element.style.bottom = y;
                        element.style.left = '50%';
                        element.style.translate = '-50% 0';
                        break;
                    case 'BOTTOM_RIGHT':
                        element.style.bottom = y;
                        element.style.right = x;
                        break;
                    default:
                        // Unknown anchor: leave as-is
                        break;
                }
            }
        }

        return element;
    }

    /**
     * Send UI event to backend
     *
     * @param {string} event - Event type (click, change, etc.)
     * @param {string} action - Action name (snake_case)
     * @param {object} parameters - Event parameters
     */
    async sendEventToBackend(event, action, parameters = {}) {
        try {

            // Get CSRF token from meta tag
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

            const componentId = this.getComponentId();

            // Get USIM storage from localStorage
            const usimStorage = getUsimStorageHeaderValue();

            // console.log('Sending event:', { component_id: componentId, action, csrfToken });

            const response = await fetch('/api/ui-event', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-USIM-Storage': usimStorage,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    component_id: componentId,
                    event: event,
                    action: action,
                    parameters: parameters,
                }),
            });

            const result = await response.json();

            // ÉXITO: response.ok = true (status 200-299)
            if (response.ok) {
                // Handle UI updates using global renderer
                if (result && Object.keys(result).length > 0) {
                    if (globalRenderer) {
                        globalRenderer.handleUIUpdate(result);
                    } else {
                        console.error('❌ Global renderer not initialized');
                    }
                }

                // Show success message if provided
                if (result.message) {
                    this.showNotification(result.message, 'success');
                }

                // Handle redirects if provided
                if (result.redirect) {
                    window.location.href = result.redirect;
                }
            } else {
                // ERROR: response.ok = false (status 400+)
                console.error('❌ Action failed:', action, result);
                this.showNotification(result.error || 'Action failed', 'error');
            }
        } catch (error) {
            console.error('❌ Network error:', error);
            this.showNotification('Network error: ' + error.message, 'error');
        }
    }

    /**
     * Show notification to user
     *
     * @param {string} message - Message to display
     * @param {string} type - Type (success, error, info, warning)
     */
    showNotification(message, type = 'info') {
        // Simple console notification for now
        // TODO: Implement proper UI notification system
        const emoji = { success: '✅', error: '❌', info: 'ℹ️', warning: '⚠️' }[type] || 'ℹ️';
        console.log(`${emoji} ${message}`);
    }
}

// ==================== Component Factory ====================
class ComponentFactory {
    static create(id, config) {
        const componentType = typeof config?.type === 'string' ? config.type.toLowerCase() : '';

        // Prefer plugin registry when available to keep the renderer closed for modification.
        const registryInstance = window.USIM_COMPONENTS?.create?.(componentType, id, config);
        if (registryInstance) {
            return registryInstance;
        }

        console.warn(`Unknown component type: ${config?.type}`);
        return null;
    }
}

const SPECIAL_UI_KEYS = new Set([
    'action',
    'redirect',
    'toast',
    'storage',
    'modal',
    'abort',
    'update_modal',
    'clear_uploaders',
    'set_uploader_existing_file',
    'change_theme',
    'change_language'
]);

function isSpecialUIKey(key) {
    return SPECIAL_UI_KEYS.has(key);
}

// ==================== UI Renderer ====================
class UIRenderer {

    constructor(data) {
        this.data = data;
        this.components = new Map();
    }

    render() {

        // Handle abort if present (checks for truthy value OR explicit action)
        if (this.data.abort || this.data.action === 'abort') {
            this.handleAbort(this.data.abort);
            return;
        }

        if (this.data.change_theme) {
            this.handleThemeChange(this.data.change_theme);
        }

        // Check for redirect instruction immediately
        if (this.data.redirect) {
            window.location.href = this.data.redirect;
            return;
        }

        const componentIds = Object.keys(this.data);

        // console.log('📋 Component IDs from JSON keys:', componentIds);

        // Step 1: Create all component instances
        for (const id of componentIds) {
            // Skip special keys that are not UI components
            if (isSpecialUIKey(id)) {
                continue;
            }

            const config = this.data[id];
            // console.log(`  🏗️ Creating component type="${config.type}" id="${id}"`, config);
            const component = ComponentFactory.create(id, config);
            if (component) {
                this.components.set(id, component);
                // console.log(`    ✅ Created successfully`);
            } else {
                console.log(`    ❌ Failed to create`);
            }
        }

        // console.log(`✅ Created ${this.components.size} components`);

        // Step 2: Group components by parent and sort by _order
        const childrenByParent = new Map();
        for (const id of componentIds) {
            // Skip special keys that are not UI components
            if (isSpecialUIKey(id)) {
                continue;
            }

            const component = this.components.get(id);
            if (!component) continue;

            const parentId = component.config.parent;
            let parentKey;

            if (typeof parentId === 'string') {
                // Parent is a DOM element
                parentKey = parentId;
            } else if (typeof parentId === 'number') {
                // Parent is a component - use numeric ID as component map key
                parentKey = String(parentId);
                if (!this.components.has(parentKey)) {
                    console.error(`Parent component with internal ID ${parentId} not found in JSON`);
                    continue;
                }
            }

            if (!childrenByParent.has(parentKey)) {
                childrenByParent.set(parentKey, []);
            }
            childrenByParent.get(parentKey).push({
                id: id,
                order: component.config._order ?? 999999
            });
        }

        // Sort children within each parent by their _order (or column for table cells, or row for table rows)
        for (const [parent, children] of childrenByParent.entries()) {
            children.sort((a, b) => {
                const compA = this.components.get(a.id);
                const compB = this.components.get(b.id);

                // If both are table rows, sort by row index
                if (compA && compB &&
                    compA.config.type === 'tablerow' &&
                    compB.config.type === 'tablerow') {
                    const rowA = compA.config.row ?? 999999;
                    const rowB = compB.config.row ?? 999999;
                    return rowA - rowB;
                }

                // If both are table cells or header cells, sort by column
                if (compA && compB &&
                    (compA.config.type === 'tablecell' || compA.config.type === 'tableheadercell') &&
                    (compB.config.type === 'tablecell' || compB.config.type === 'tableheadercell')) {
                    const colA = compA.config.column ?? 999999;
                    const colB = compB.config.column ?? 999999;
                    return colA - colB;
                }

                // Otherwise sort by _order
                return a.order - b.order;
            });
        }

        // Step 3: Mount components in hierarchical order
        const mounted = new Set();
        const maxIterations = this.components.size * 2;
        let iterations = 0;

        console.log('🚀 Starting component mounting...');

        while (mounted.size < this.components.size && iterations < maxIterations) {
            iterations++;

            // For each parent, mount its children in order
            for (const [parentKey, children] of childrenByParent.entries()) {
                for (const childInfo of children) {
                    const id = childInfo.id;
                    const component = this.components.get(id);

                    if (!component || mounted.has(id)) continue;

                    const parentId = component.config.parent;

                    // console.log(`  📍 Attempting to mount "${id}" (type: ${component.config.type}), parent: ${parentId}`);

                    if (typeof parentId === 'string') {
                        // Parent is a DOM element (always available)
                        const parentElement = document.getElementById(parentId);
                        if (parentElement) {
                            component.mount(parentElement);
                            mounted.add(id);
                            console.log(`    ✅ Mounted to DOM element "${parentId}"`);
                        } else {
                            console.error(`    ❌ Parent element not found: ${parentId}`);
                            mounted.add(id);
                        }
                    } else if (typeof parentId === 'number') {
                        // Parent is a component - component map key is its numeric ID as string
                        const parentComponentKey = String(parentId);
                        const parentComponent = this.components.get(parentComponentKey);

                        if (!parentComponent) {
                            console.error(`    ❌ Parent component not found for ID: ${parentId}`);
                            mounted.add(id);
                            continue;
                        }

                        // Wait for parent to be mounted first
                        if (mounted.has(parentComponentKey)) {
                            // Determine mount target
                            let mountTarget = parentComponent.element;

                            // Special case: if parent is a table, mount rows inside <table> element
                            if (parentComponent.tableElement) {
                                mountTarget = parentComponent.tableElement;

                                // Special case: if child is a container inside a table, it's probably the rows container
                                // Don't create a DOM element for it, just mark it as mounted and let its children mount to the table
                                if (component.config.type === 'container') {
                                    // Make this container "transparent" - its children will mount directly to the table
                                    component.element = mountTarget; // Point to the table
                                    mounted.add(id);
                                    console.log(`    ✅ Transparent container mounted (children will use parent table)`);
                                    continue;
                                }
                            }

                            component.mount(mountTarget);
                            mounted.add(id);
                            // console.log(`    ✅ Mounted to component "${parentComponentKey}"`);
                        } else {
                            // console.log(`    ⏳ Waiting for parent "${parentComponentKey}" to be mounted first`);
                        }
                    }
                }
            }
        }

        if (mounted.size < this.components.size) {
            console.warn(`⚠️ Could not mount ${this.components.size - mounted.size} components (circular dependency or missing parents)`);
        }

        console.log(`✅ UI rendering complete (${mounted.size}/${this.components.size} mounted)`);
    }

    /**
     * Clear uploaders
     *
     * @param {Array} uploaderIds - Array of uploader component IDs to clear
     */
    clearUploaders(uploaderIds) {
        if (!Array.isArray(uploaderIds)) return;

        console.log('🗑️ Attempting to clear uploaders with IDs:', uploaderIds);

        uploaderIds.forEach(uploaderId => {
            // Buscar el componente por su ID numérico
            const component = this.components.get(String(uploaderId));

            if (component && typeof component.clearFiles === 'function') {
                console.log(`✅ Clearing uploader ID: ${uploaderId}`);
                component.clearFiles();
            } else {
                console.warn(`⚠️ Uploader component not found or doesn't have clearFiles method:`, uploaderId);
            }
        });
    }

    /**
     * Update uploader component with new existing file
     *
     * @param {object} updateData - Update data with id and existing_file URL
     */
    updateUploader(updateData) {
        if (!updateData || !updateData.id) return;

        console.log('🔄 Updating uploader:', updateData);

        const component = this.components.get(String(updateData.id));

        if (component && typeof component.showExistingFile === 'function') {
            console.log(`✅ Updating uploader ID ${updateData.id} with new image`);

            // Buscar el elemento del uploader en el DOM
            const uploaderElement = document.querySelector(`[data-component-id="${updateData.id}"]`);
            if (!uploaderElement) {
                console.warn(`⚠️ Uploader DOM element not found for ID:`, updateData.id);
                return;
            }

            // Buscar o crear la lista de archivos
            let fileList = uploaderElement.querySelector('.ui-uploader-file-list');
            if (!fileList) {
                fileList = document.createElement('div');
                fileList.className = 'ui-uploader-file-list';
                uploaderElement.appendChild(fileList);
            }

            // Limpiar archivos actuales
            if (typeof component.clearFiles === 'function') {
                component.clearFiles();
            }

            // Mostrar nueva imagen
            component.showExistingFile(updateData.existing_file, fileList);
        } else {
            console.warn(`⚠️ Uploader component not found or doesn't support updates:`, updateData.id);
        }
    }

    /**
     * Set existing file on uploader component
     *
     * @param {object} data - Data with uploader_id and url
     */
    setUploaderExistingFile(data) {
        if (!data || !data.uploader_id || !data.url) {
            console.warn('⚠️ Invalid data for set_uploader_existing_file:', data);
            return;
        }

        console.log('🔄 Setting existing file on uploader:', data);

        const component = this.components.get(String(data.uploader_id));

        if (component && typeof component.setExistingFile === 'function') {
            console.log(`✅ Setting existing file on uploader ID ${data.uploader_id}`);
            component.setExistingFile(data.url);
        } else {
            console.warn(`⚠️ Uploader component not found or doesn't support setExistingFile:`, data.uploader_id);
        }
    }

    /**
     * Handle storage updates - store variables in localStorage
     *
     * @param {object} storageData - Storage variables object
     */
    handleStorageUpdate(storageData) {
        const storageKeys = Object.keys(storageData);
        if (storageKeys.length > 0) {
            const currentKey = getActiveUsimStorageKey();
            const preferredKey = storageKeys.includes(currentKey) ? currentKey : storageKeys[0];
            setActiveUsimStorageKey(preferredKey);
        }

        storageKeys.forEach(key => {

            const value = storageData[key];

            // Store the value in localStorage
            // If it's an object/array, stringify it
            if (typeof value === 'object' && value !== null) {
                localStorage.setItem(key, JSON.stringify(value));
            } else {
                localStorage.setItem(key, String(value));
            }
        });

        // Ensure screens (e.g. Home) reflect the persisted theme even when backend does not send change_theme.
        applyPersistedThemeFromStorage(true, 'framework-storage-update');
    }

    /**
     * Apply theme change sent by backend and notify embedded fragments.
     *
     * @param {string} theme
     */
    handleThemeChange(theme) {
        if (typeof theme !== 'string') {
            return;
        }

        const normalizedTheme = theme.trim().toLowerCase();
        if (!normalizedTheme) {
            return;
        }

        setGlobalUsimTheme(normalizedTheme, 'framework-backend-change_theme', true, true);
        console.log('🎨 Theme changed:', normalizedTheme);
    }

    /**
     * Persist language change sent by backend.
     * The actual locale is applied server-side on the next request via PrepareUIContext middleware.
     *
     * @param {string} lang
     */
    handleLanguageChange(lang) {
        if (typeof lang !== 'string' || !lang.trim()) {
            return;
        }

        persistStoreValue('store_lang', lang.trim().toLowerCase());
    }

    /**
     * Show toast notification
     *
     * @param {object} toastConfig - Toast configuration
     */
    showToast(toastConfig) {
        const {
            message,
            type = 'info',
            duration = 3000,
            open_effect = 'fade',
            show_effect = 'bounce',
            close_effect = 'fade',
            position = 'top-right'
        } = toastConfig;

        // Create toast container if it doesn't exist or update position
        let toastContainer = document.getElementById('toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'toast-container';
            document.body.appendChild(toastContainer);
        }
        raiseToastContainerAboveModals(toastContainer);
        document.body.appendChild(toastContainer);
        toastContainer.className = `toast-container toast-position-${position}`;

        // Create toast element with position-aware classes
        const toast = document.createElement('div');
        toast.className = `toast toast-${type} toast-open-${open_effect} toast-show-${show_effect} toast-position-${position}`;

        // Toast icon based on type
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        const icon = icons[type] || icons.info;

        toast.innerHTML = `
            <span class="toast-icon">${icon}</span>
            <span class="toast-message">${message}</span>
            <button class="toast-close" aria-label="Close">&times;</button>
        `;

        // Add to container
        toastContainer.appendChild(toast);
        raiseToastContainerAboveModals(toastContainer);

        // Trigger opening animation
        requestAnimationFrame(() => {
            toast.classList.add('toast-open');
        });

        // Close button handler
        const closeBtn = toast.querySelector('.toast-close');
        let isClosing = false;
        const closeToast = () => {
            if (isClosing) {
                return;
            }

            isClosing = true;
            toast.classList.add(`toast-close-${close_effect}`);

            setTimeout(() => {
                toast.remove();

                // Remove container if empty
                if (toastContainer.children.length === 0) {
                    toastContainer.remove();
                }
            }, 300);
        };

        closeBtn.addEventListener('click', closeToast);

        // Auto close after duration
        if (duration > 0) {
            setTimeout(closeToast, duration);
        }
    }

    /**
     * Handle abort instruction
     * @param {object|boolean} abortData
     */
    handleAbort(abortData) {
        // Normalize abortData
        const data = (typeof abortData === 'object') ? abortData : {};
        const code = data.status_code || data.code || 'ERROR';
        const message = data.message || 'La operación ha sido abortada.';

        // Try to find main container
        const mainContainer = document.getElementById('main');
        mainContainer.innerHTML = `
                <div class="ui-error-screen">
                    <div class="ui-error-icon">⛔</div>
                    <p class="ui-error-message">
                        <span class="ui-error-code">${code}</span> ${message}
                    </p>
                    <button onclick="window.location = '/'" class="ui-error-button">
                        Recargar Página
                    </button>
                </div>
            `;
    }

    /**
     * Handle UI updates from backend
     *
     * @param {object} uiUpdate - UI update object (same structure as initial render)
     */
    handleUIUpdate(uiUpdate) {
        // Handle storage updates if present
        if (uiUpdate.storage) {
            this.handleStorageUpdate(uiUpdate.storage);
        }

        if (uiUpdate.change_theme) {
            this.handleThemeChange(uiUpdate.change_theme);
        }

        if (uiUpdate.change_language) {
            this.handleLanguageChange(uiUpdate.change_language);
        }

        // Handle abort if present (checks for truthy value OR explicit action)
        if (uiUpdate.abort || uiUpdate.action === 'abort') {
            this.handleAbort(uiUpdate.abort);
            return;
        }

        // Handle toast notifications if present (but only if no redirect)
        if (uiUpdate.toast && !uiUpdate.redirect) {
            this.showToast(uiUpdate.toast);
        }

        // Handle clear uploaders if present
        if (uiUpdate.clear_uploaders) {
            this.clearUploaders(uiUpdate.clear_uploaders);
        }

        // Handle set existing file on uploaders
        if (uiUpdate.set_uploader_existing_file) {
            this.setUploaderExistingFile(uiUpdate.set_uploader_existing_file);
        }

        // Handle redirects if present
        if (uiUpdate.redirect) {
            // If there's a toast, save it to show after redirect
            if (uiUpdate.toast) {
                sessionStorage.setItem('pendingToast', JSON.stringify(uiUpdate.toast));
            }
            window.location.href = uiUpdate.redirect;
            return; // Stop processing after redirect
        }

        // Check if there are components with parent='modal' - if so, open modal
        let hasModalComponents = false;
        for (const [key, component] of Object.entries(uiUpdate)) {
            if (isSpecialUIKey(key) || !component || typeof component !== 'object') {
                continue;
            }

            if (component.parent === 'modal') {
                hasModalComponents = true;
                break;
            }
        }

        if (hasModalComponents) {
            // Open modal with these components
            openModal(uiUpdate);
            return; // Don't process as regular updates
        }

        // Check for update_modal (modal component updates)
        if (uiUpdate.update_modal) {
            updateModalComponents(uiUpdate.update_modal);
            // Don't return - continue processing other updates like toast
        }

        // Check for special actions
        if (uiUpdate.action) {
            console.log('🎬 Action detected:', uiUpdate.action);
            switch (uiUpdate.action) {
                case 'show_modal':
                    if (uiUpdate.modal) {
                        openModal(uiUpdate.modal);
                    }
                    return; // Don't process as regular updates

                case 'close_modal':
                    closeModal();
                    // Process UI updates directly from root object
                    for (const [jsonKey, changes] of Object.entries(uiUpdate)) {
                        // Skip special keys
                        if (isSpecialUIKey(jsonKey) || !changes || typeof changes !== 'object') {
                            continue;
                        }

                        const parsedId = Number.parseInt(jsonKey, 10);
                        const componentId = Number.isNaN(parsedId) ? jsonKey : parsedId;

                        // If parent is null, remove the component and all descendants from DOM/registry
                        if (changes.parent === null) {
                            this.removeComponentAndChildren(componentId);
                            continue;
                        }

                        const element = document.querySelector(`[data-component-id="${componentId}"]`);

                        if (element) {
                            // console.log(`✏️ Updating component ${componentId}`, changes);
                            this.updateComponent(element, changes);
                        } else {
                            // console.log(`➕ Creating new component ${componentId}`, changes);
                            this.addComponent(jsonKey, changes);
                        }
                    }
                    return; // Don't continue processing
            }
        }

        // Handle UI updates (for non-modal actions)
        for (const [jsonKey, changes] of Object.entries(uiUpdate)) {
            // Skip special keys
            if (isSpecialUIKey(jsonKey) || !changes || typeof changes !== 'object') {
                continue;
            }

            const parsedId = Number.parseInt(jsonKey, 10);
            const componentId = Number.isNaN(parsedId) ? jsonKey : parsedId;

            // If parent is null, remove the component and all descendants from DOM/registry
            if (changes.parent === null) {
                this.removeComponentAndChildren(componentId);
                continue;
            }

            const element = document.querySelector(`[data-component-id="${componentId}"]`);

            if (element) {
                // console.log(`✏️ Updating component ${componentId}`, changes);
                this.updateComponent(element, changes);
            } else {
                // console.log(`➕ Creating new component ${componentId}`, changes);
                this.addComponent(jsonKey, changes);
            }
        }
    }

    /**
     * Remove a component and all descendants from DOM and component registry
     *
        * @param {number|string} componentId - Component id
     */
    removeComponentAndChildren(componentId) {
        const targetId = String(componentId);
        const removedIds = new Set([targetId]);

        // Remove subtree from DOM (removing parent removes visual children automatically)
        const rootElement = document.querySelector(`[data-component-id="${componentId}"]`);
        if (rootElement) {
            const descendants = rootElement.querySelectorAll('[data-component-id]');
            descendants.forEach((el) => {
                const id = el.getAttribute('data-component-id');
                if (id !== null) {
                    removedIds.add(String(id));
                }
            });

            rootElement.remove();
        }

        // Also resolve descendants from registry (covers orphaned/stale entries)
        let foundNewDescendant = true;
        while (foundNewDescendant) {
            foundNewDescendant = false;

            for (const [mapKey, component] of this.components.entries()) {
                if (!component || !component.config) {
                    continue;
                }

                const parentId = component.config.parent;
                const internalId = String(mapKey);

                if (parentId !== undefined && parentId !== null && removedIds.has(String(parentId)) && !removedIds.has(internalId)) {
                    removedIds.add(internalId);
                    foundNewDescendant = true;
                }
            }
        }

        // Cleanup component registry
        for (const [mapKey, component] of this.components.entries()) {
            const internalId = String(mapKey);

            if (removedIds.has(internalId) || removedIds.has(String(mapKey))) {
                this.components.delete(mapKey);
            }
        }

        console.log(`🗑️ Removed component ${componentId} and ${Math.max(removedIds.size - 1, 0)} descendants`);
    }

    /**
     * Update existing component in DOM
     *
     * @param {HTMLElement} element - DOM element to update
     * @param {object} changes - Properties to update
     */
    updateComponent(element, changes) {
        const componentId = element.getAttribute('data-component-id');

        try {
            // Generic component update delegation
            if (componentId && this.components) {
                const component = this.components.get(String(componentId));
                if (component && typeof component.update === 'function') {
                    component.update(changes);
                }
            }

            // Button in table cell - needs special handling to update the button inside the cell
            if (changes.button !== undefined && element.tagName === 'TD') {
                // Clear the cell and re-render with new button
                element.innerHTML = '';

                // si el botón es null, no hacemos nada más
                if (changes.button === null) {
                    return;
                }

                const btn = document.createElement('button');
                btn.className = `ui-button ${changes.button.style || 'default'}`;
                if (changes.button.no_background) btn.classList.add('ui-button-no-background');
                if (changes.button.no_hover) btn.classList.add('ui-button-no-hover');

                btn.innerHTML = '';
                if (changes.button.icon) {
                    const iconElement = document.createElement('img');
                    iconElement.className = 'ui-button-icon';
                    iconElement.src = String(changes.button.icon);
                    iconElement.alt = changes.button.label || '';

                    if (changes.button.icon_size !== undefined && changes.button.icon_size !== null && changes.button.icon_size !== '') {
                        const rawSize = String(changes.button.icon_size).trim();
                        const normalizedSize = (typeof changes.button.icon_size === 'number' || /^\d+(\.\d+)?$/.test(rawSize))
                            ? `${rawSize}px`
                            : rawSize;
                        iconElement.style.width = normalizedSize;
                        iconElement.style.height = normalizedSize;
                    }

                    if (changes.button.icon_only) {
                        btn.appendChild(iconElement);
                    } else if ((changes.button.icon_position || 'left') === 'right') {
                        btn.appendChild(document.createTextNode(changes.button.label || ''));
                        btn.appendChild(iconElement);
                    } else {
                        btn.appendChild(iconElement);
                        btn.appendChild(document.createTextNode(changes.button.label || ''));
                    }
                } else {
                    btn.textContent = changes.button.label || 'Action';
                }

                if (changes.button.tooltip) {
                    btn.title = changes.button.tooltip;
                }

                // Handle button click
                if (changes.button.action) {
                    btn.addEventListener('click', async () => {
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                        const componentId = element.getAttribute('data-component-id');
                        const usimStorage = getUsimStorageHeaderValue();

                        try {
                            const response = await fetch('/api/ui-event', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-USIM-Storage': usimStorage,
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({
                                    component_id: parseInt(componentId),
                                    event: 'action',
                                    action: changes.button.action,
                                    parameters: changes.button.parameters || {},
                                }),
                            });

                            const result = await response.json();

                            if (response.ok && result && globalRenderer) {
                                globalRenderer.handleUIUpdate(result);
                            }
                        } catch (error) {
                            console.error('Button click error:', error);
                        }
                    });
                }

                element.appendChild(btn);
                return;
            }

            // Text (labels)
            if (changes.text !== undefined) {
                const text = normalizeTextLineBreaks(changes.text);
                if (element.getAttribute('data-markdown') === '1') {
                    element.style.whiteSpace = 'normal';
                    element.innerHTML = renderSimpleMarkdownToSafeHtml(text);
                } else if (text.includes('\n')) {
                    // Support line breaks
                    element.style.whiteSpace = 'pre-line';
                    element.textContent = text;
                } else {
                    element.style.whiteSpace = '';
                    element.textContent = text;
                }
            }

            // Label / icon (buttons)
            if (changes.label !== undefined || changes.icon !== undefined || changes.icon_color !== undefined || changes.icon_size !== undefined) {
                const btnComponent = componentId ? this.components?.get(String(componentId)) : null;
                if (btnComponent && typeof btnComponent._applyContent === 'function') {
                    if (changes.label !== undefined) btnComponent.config.label = changes.label;
                    if (changes.icon !== undefined) btnComponent.config.icon = changes.icon;
                    if (changes.icon_color !== undefined) btnComponent.config.icon_color = changes.icon_color;
                    if (changes.icon_size !== undefined) btnComponent.config.icon_size = changes.icon_size;
                    btnComponent._applyContent(element);
                } else {
                    element.textContent = changes.label ?? element.textContent;
                }
            }

            // Trigger (menudropdown)
            if (changes.trigger !== undefined) {
                const triggerButton = element.querySelector('.menu-dropdown-trigger');
                if (triggerButton) {
                    const triggerConfig = changes.trigger;
                    const triggerLabel = triggerConfig.label || '☰ Menu';
                    const triggerIcon = triggerConfig.icon;
                    const triggerImage = triggerConfig.image;
                    const triggerAlt = triggerConfig.alt || 'Menu';
                    const triggerStyle = triggerConfig.style || 'default';

                    // Update trigger style classes
                    triggerButton.className = 'menu-dropdown-trigger';
                    triggerButton.className += ` menu-trigger-${triggerStyle}`;

                    // Build trigger content (same logic as initial render)
                    let triggerContent = '';

                    // Image trigger (priority over icon)
                    if (triggerImage) {
                        triggerContent += `<img src="${triggerImage}" alt="${triggerAlt}" class="trigger-image">`;
                        if (triggerLabel) {
                            triggerContent += `<span class="trigger-label">${triggerLabel}</span>`;
                        }
                    } else {
                        // Standard icon/label trigger
                        if (triggerIcon) {
                            triggerContent += `<span class="trigger-icon">${triggerIcon}</span>`;
                        }
                        triggerContent += `<span class="trigger-label">${triggerLabel}</span>`;
                    }

                    triggerButton.innerHTML = triggerContent;
                }
            }

            // Items (menudropdown) - DEPRECATED: items are immutable in frontend
            // Only permissions array should be updated from backend
            if (changes.items !== undefined) {
                console.warn('⚠️ Updating items[] is deprecated. Use permissions[] instead.');
            }

            // Permissions (menudropdown) - Re-render menu items with new permissions
            if (changes.permissions !== undefined) {
                const menuContainer = element;
                const component = componentId ? globalRenderer?.components?.get(String(componentId)) : null;

                if (component && component.config) {
                    // Update component config
                    component.config.permissions = changes.permissions;

                    // Re-render menu content
                    const menuContent = element.querySelector('.menu-dropdown-content');

                    if (menuContent) {
                        // Clear existing content
                        menuContent.innerHTML = '';

                        // Re-render all items with new permissions
                        const items = component.config.items || [];

                        items.forEach(item => {
                            const itemElement = component.renderMenuItem(item);
                            menuContent.appendChild(itemElement);
                        });

                        // Check if all items are hidden
                        const permissions = changes.permissions;
                        const hasVisibleItems = items.some(item => {
                            if (item.type === 'separator') {
                                return false;
                            }
                            const isVisible = component.isItemVisible(item, permissions);
                            if (item.submenu && item.submenu.length > 0) {
                                return isVisible && component.hasVisibleChildren(item.submenu, permissions);
                            }
                            return isVisible;
                        });                        // Hide/show entire menu
                        menuContainer.style.display = hasVisibleItems ? '' : 'none';

                        // Close menu if it was open
                        menuContent.classList.remove('show');
                        const trigger = element.querySelector('.menu-dropdown-trigger');
                        if (trigger) {
                            trigger.classList.remove('active');
                        }
                    }
                }
            }

            // Style/classes
            if (changes.style !== undefined) {
                element.classList.remove('default', 'primary', 'secondary', 'success', 'warning', 'danger', 'info');
                element.classList.add(changes.style);
            }

            // Inline color/border updates (support explicit null to clear stale styles)
            if (changes.background_color !== undefined) {
                element.style.backgroundColor = changes.background_color ?? '';
            }

            if (changes.text_color !== undefined) {
                element.style.color = changes.text_color ?? '';
            }

            if (changes.border_color !== undefined) {
                element.style.borderColor = changes.border_color ?? '';
            }

            if (changes.align !== undefined) {
                element.style.textAlign = changes.align ?? '';
            }

            // Width constraints (tables) - keep columns stable across incremental updates
            if (changes.min_width !== undefined || changes.max_width !== undefined) {
                const minWidth = changes.min_width ?? changes.max_width;
                const maxWidth = changes.max_width ?? changes.min_width;

                if (changes.min_width === 0 && changes.max_width === 0) {
                    element.style.width = '0';
                    element.style.maxWidth = '0';
                    element.style.minWidth = '0';
                    element.style.padding = '0';
                    element.style.overflow = 'hidden';
                    element.style.border = 'none';
                } else {
                    const targetWidth = minWidth ?? maxWidth;

                    if (targetWidth !== undefined && targetWidth !== null) {
                        element.style.width = `${targetWidth}px`;
                    }

                    if (minWidth !== undefined && minWidth !== null) {
                        element.style.minWidth = `${minWidth}px`;
                    }

                    if (maxWidth !== undefined && maxWidth !== null) {
                        element.style.maxWidth = `${maxWidth}px`;
                    }
                }
            }

            // Button background/hover behavior
            if (changes.no_background !== undefined) {
                if (changes.no_background) {
                    element.classList.add('ui-button-no-background');
                } else {
                    element.classList.remove('ui-button-no-background');
                }
            }

            if (changes.no_hover !== undefined) {
                if (changes.no_hover) {
                    element.classList.add('ui-button-no-hover');
                } else {
                    element.classList.remove('ui-button-no-hover');
                }
            }

            // Generic component positioning updates
            const hasPositioningUpdate = (
                changes.position !== undefined ||
                changes.position_mode !== undefined ||
                changes.position_offset_x !== undefined ||
                changes.position_offset_y !== undefined ||
                changes.z_index !== undefined
            );
            if (hasPositioningUpdate) {
                const runtimeComponent = componentId ? this.components?.get(String(componentId)) : null;
                if (runtimeComponent && runtimeComponent.config) {
                    if (changes.position !== undefined) runtimeComponent.config.position = changes.position;
                    if (changes.position_mode !== undefined) runtimeComponent.config.position_mode = changes.position_mode;
                    if (changes.position_offset_x !== undefined) runtimeComponent.config.position_offset_x = changes.position_offset_x;
                    if (changes.position_offset_y !== undefined) runtimeComponent.config.position_offset_y = changes.position_offset_y;
                    if (changes.z_index !== undefined) runtimeComponent.config.z_index = changes.z_index;
                    runtimeComponent.applyCommonAttributes(element);
                }
            }

            // Visibility
            if (changes.visible !== undefined) {
                element.style.display = changes.visible ? '' : 'none';
            }

            // Enabled/disabled state
            if (changes.enabled !== undefined) {
                if (element.tagName === 'BUTTON' || element.tagName === 'INPUT') {
                    element.disabled = !changes.enabled;
                }
            }

            // Disabled state (for selects and other elements)
            if (changes.disabled !== undefined) {
                const targetElement = element.querySelector('select, input, textarea, button') || element;
                targetElement.disabled = changes.disabled;
            }

            // Value (inputs)
            if (changes.value !== undefined) {
                if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                    element.value = changes.value;
                } else {
                    const input = element.querySelector('input, textarea');
                    if (input) input.value = changes.value;
                }
            }

            // Error state (inputs)
            if (changes.error !== undefined) {
                const inputWrapper = element.querySelector('.ui-input-wrapper');
                const input = element.querySelector('input');

                if (inputWrapper && input) {
                    // Remove existing error icon if any
                    const existingIcon = inputWrapper.querySelector('.ui-input-error-icon');
                    if (existingIcon) {
                        existingIcon.remove();
                    }

                    if (changes.error) {
                        // Add error state
                        input.classList.add('ui-input-error');

                        // Create and add error icon
                        const errorIcon = document.createElement('span');
                        errorIcon.className = 'ui-input-error-icon';
                        errorIcon.innerHTML = '⚠️';
                        errorIcon.setAttribute('data-tooltip', changes.error);
                        inputWrapper.appendChild(errorIcon);
                    } else {
                        // Remove error state
                        input.classList.remove('ui-input-error');
                    }
                }
            }

            // Existing file (uploader)
            if (changes.existing_file !== undefined) {
                // The element might be the uploader itself or a parent
                // Try to find the uploader instance on element or within it
                let uploaderInstance = element.uploaderInstance;

                if (!uploaderInstance) {
                    // Search for uploader instance in child elements
                    const uploaderElement = element.querySelector('.ui-uploader-group');
                    if (uploaderElement) {
                        uploaderInstance = uploaderElement.uploaderInstance;
                    }
                }

                if (uploaderInstance && typeof uploaderInstance.setExistingFile === 'function') {
                    uploaderInstance.setExistingFile(changes.existing_file);
                }
            }

            // Options (selects)
            if (changes.options !== undefined) {
                const select = element.querySelector('select') || (element.tagName === 'SELECT' ? element : null);
                if (select) {
                    // Clear existing options (except placeholder if exists)
                    const placeholder = select.querySelector('option[disabled][value=""]');
                    select.innerHTML = '';

                    // Re-add placeholder if it existed
                    if (placeholder) {
                        select.appendChild(placeholder);
                    }

                    // Add new options (support both array and object formats)
                    if (Array.isArray(changes.options)) {
                        // Array format: [{value, label}]
                        changes.options.forEach(opt => {
                            const option = document.createElement('option');
                            option.value = opt.value;
                            option.textContent = opt.label;
                            select.appendChild(option);
                        });
                    } else {
                        // Object format: {value: label}
                        for (const [value, label] of Object.entries(changes.options)) {
                            const option = document.createElement('option');
                            option.value = value;
                            option.textContent = label;
                            select.appendChild(option);
                        }
                    }
                }
            }

            // Placeholder (selects)
            if (changes.placeholder !== undefined) {
                const select = element.querySelector('select') || (element.tagName === 'SELECT' ? element : null);
                if (select) {
                    let placeholder = select.querySelector('option[disabled][value=""]');
                    if (placeholder) {
                        placeholder.textContent = changes.placeholder;
                    } else {
                        // Create placeholder if it doesn't exist
                        placeholder = document.createElement('option');
                        placeholder.value = '';
                        placeholder.textContent = changes.placeholder;
                        placeholder.disabled = true;
                        placeholder.selected = true;
                        select.insertBefore(placeholder, select.firstChild);
                    }
                }
            }

            // Checked (checkboxes)
            if (changes.checked !== undefined) {
                const component = componentId ? this.components?.get(String(componentId)) : null;
                if (component && component.config) {
                    component.config.checked = changes.checked;
                }

                if (element.type === 'checkbox') {
                    element.checked = changes.checked;
                } else {
                    const checkbox = element.querySelector('input[type="checkbox"]');
                    if (checkbox) checkbox.checked = changes.checked;
                }
            }

            // Pagination (tables) - Update pagination info and controls
            if (changes.pagination !== undefined || changes.total_items !== undefined) {
                const paginationDiv = element.querySelector('.ui-pagination');
                if (paginationDiv) {
                    const pagination = changes.pagination || {};
                    const currentPage = pagination.current_page || 1;
                    const perPage = pagination.per_page || 10;
                    const totalItems = changes.total_items || pagination.total_items || 0;
                    const totalPages = pagination.total_pages || Math.ceil(totalItems / perPage);
                    const canNext = pagination.can_next !== undefined ? pagination.can_next : (currentPage < totalPages);
                    const canPrev = pagination.can_prev !== undefined ? pagination.can_prev : (currentPage > 1);

                    // Get component reference once for all operations
                    const component = componentId ? globalRenderer?.components?.get(String(componentId)) : null;

                    // Labels from backend (fall back to component config labels, then English)
                    const deltaLabels = pagination.labels
                        || component?.config?.pagination?.labels
                        || {};
                    const dLabelPrevious = deltaLabels.previous || '\u00ab Previous';
                    const dLabelNext     = deltaLabels.next     || 'Next \u00bb';
                    const dLabelShowing  = deltaLabels.showing  || 'Showing :start-:end of :total items';

                    // Update info text
                    const infoDiv = paginationDiv.querySelector('.ui-pagination-info');
                    if (infoDiv) {
                        const start = (currentPage - 1) * perPage + 1;
                        const end = Math.min(currentPage * perPage, totalItems);
                        infoDiv.textContent = dLabelShowing
                            .replace(':start', start)
                            .replace(':end', end)
                            .replace(':total', totalItems);
                    }

                    // Update controls
                    const controlsDiv = paginationDiv.querySelector('.ui-pagination-controls');
                    if (controlsDiv) {
                        // If component not found, skip interactive controls
                        if (!component) {
                            console.warn(`Component ${componentId} not found in registry for pagination update`);
                            return;
                        }

                        // Clear all controls and rebuild
                        controlsDiv.innerHTML = '';

                        // Re-create loading indicator
                        const loadingDiv = document.createElement('div');
                        loadingDiv.className = 'ui-pagination-loading';
                        loadingDiv.style.display = 'none';
                        loadingDiv.style.marginLeft = '16px';
                        loadingDiv.style.alignItems = 'center';
                        loadingDiv.style.gap = '8px';
                        loadingDiv.innerHTML = `
                            <span class="spinner" style="
                                display: inline-block;
                                width: 16px;
                                height: 16px;
                                border: 2px solid #f3f3f3;
                                border-top: 2px solid #3498db;
                                border-radius: 50%;
                                animation: spin 1s linear infinite;
                            "></span>
                        `;
                        controlsDiv.appendChild(loadingDiv);
                        controlsDiv.paginationLoading = loadingDiv;

                        // Previous button
                        const prevBtn = document.createElement('button');
                        prevBtn.className = 'ui-pagination-button';
                        prevBtn.textContent = dLabelPrevious;
                        prevBtn.disabled = !canPrev;
                        if (component) {
                            prevBtn.addEventListener('click', () => component.changePage(currentPage - 1, paginationDiv));
                        }
                        controlsDiv.appendChild(prevBtn);

                        // Page number buttons
                        if (component && component.getPageNumbers) {
                            const pages = component.getPageNumbers(currentPage, totalPages);
                            pages.forEach(page => {
                                if (page === '...') {
                                    const ellipsis = document.createElement('span');
                                    ellipsis.textContent = '...';
                                    ellipsis.style.padding = '0 8px';
                                    controlsDiv.appendChild(ellipsis);
                                } else {
                                    const pageBtn = document.createElement('button');
                                    pageBtn.className = 'ui-pagination-button';
                                    if (page === currentPage) {
                                        pageBtn.classList.add('active');
                                    }
                                    pageBtn.textContent = page;
                                    pageBtn.addEventListener('click', () => component.changePage(page, paginationDiv));
                                    controlsDiv.appendChild(pageBtn);
                                }
                            });
                        }

                        // Next button
                        const nextBtn = document.createElement('button');
                        nextBtn.className = 'ui-pagination-button';
                        nextBtn.textContent = dLabelNext;
                        nextBtn.disabled = !canNext;
                        if (component) {
                            nextBtn.addEventListener('click', () => component.changePage(currentPage + 1, paginationDiv));
                        }
                        controlsDiv.appendChild(nextBtn);
                    }

                    // Update component config
                    if (component) {
                        if (changes.pagination) {
                            component.config.pagination = changes.pagination;
                        }
                        if (changes.total_items !== undefined) {
                            component.config.total_items = changes.total_items;
                            if (component.config.pagination) {
                                component.config.pagination.total_items = changes.total_items;
                            }
                        }
                    }
                }
            }

            // console.log(`✅ Component ${componentId} updated successfully`);
        } catch (error) {
            console.error(`❌ Error updating component ${componentId}:`, error);
        }
    }

    /**
     * Add new component to DOM
     *
     * @param {string} jsonKey - JSON key of the component
     * @param {object} config - Component configuration
     */
    addComponent(jsonKey, config) {
        try {
            const component = ComponentFactory.create(jsonKey, config);

            if (!component) {
                console.error(`❌ ComponentFactory returned null for type: ${config.type}`);
                return;
            }

            const element = component.render();

            // Find parent and append
            const parentElement = document.querySelector(`[data-component-id="${config.parent}"]`)
                || document.getElementById(config.parent);

            if (parentElement) {
                parentElement.appendChild(element);
                console.log(`➕ Component ${jsonKey} added to parent ${config.parent}`);
            } else {
                console.error(`❌ Parent ${config.parent} not found for component ${jsonKey}`);
            }
        } catch (error) {
            console.error(`❌ Error adding component:`, error);
        }
    }
}

// Global renderer instance
let globalRenderer = null;

// ==================== Main Application ====================
async function loadScreenUI(screenName = null, forceReset = null) {
    try {
        // Use screen name from window global (set by Laravel) or parameter
        const screen = screenName || window.SCREEN_NAME || 'home';

        // Apply persisted theme before rendering the screen.
        applyPersistedThemeFromStorage(false, 'framework-initial-load');

        // Build query parameters string
        const urlParams = new URLSearchParams();

        // Add reset parameter if needed
        const shouldReset = forceReset === null ? Boolean(window.RESET_STATE) : Boolean(forceReset);
        if (shouldReset) {
            urlParams.append('reset', 'true');
        }

        // Add any existing query parameters from window.QUERY_PARAMS
        if (window.QUERY_PARAMS && window.QUERY_PARAMS.toString()) {
            // Merge existing params into urlParams
            for (const [key, value] of window.QUERY_PARAMS.entries()) {
                urlParams.append(key, value);
            }
        }

        // Get the parameters from the URL
        const params = window.PARAMS || {};
        // si hay params, los añadimos a urlParams
        for (const [key, value] of Object.entries(params)) {
            urlParams.append(key, value);
        }

        // Build final query string (with ? prefix if there are params)
        const queryString = urlParams.toString() ? `?${urlParams.toString()}` : '';

        const usimStorage = getUsimStorageHeaderValue();
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        // Use /api/ui/ prefix to separate UI definitions from Data API
        const response = await fetch(`/api/ui/${screen}${queryString}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'X-USIM-Storage': usimStorage,
            }
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const uiData = await response.json();

        // Create and store global renderer
        globalRenderer = new UIRenderer(uiData);
        globalRenderer.render();

        // Re-apply and broadcast persisted theme after render so embedded fragments
        // (like Home landing blocks) that subscribe to usim:theme-changed can sync.
        applyPersistedThemeFromStorage(true, 'framework-post-render-sync');

        console.log('✅ Screen UI loaded successfully');

    } catch (error) {
        console.error('Error loading screen UI:', error);
        document.getElementById('main').innerHTML = `
            <div style="padding: 20px; color: red; background: #fee; border: 1px solid #fcc; border-radius: 6px;">
                <h2>❌ Error loading UI components</h2>
                <p><strong>Message:</strong> ${error.message}</p>
                <p><strong>Check the console</strong> for more details.</p>
            </div>
        `;
    }
}

// Listen for UI actions
window.addEventListener('ui-action', (event) => {
    console.log('UI Action triggered:', event.detail);
    // Here you can handle actions globally
    // e.g., send to backend, update state, etc.
});

// ==================== Modal Functions ====================

const MODAL_ROOT_ID = 'modal-root';
const MODAL_BASE_Z_INDEX = 1400;
const modalOverlayPool = [];
const modalOverlayStack = [];
const modalTimeouts = new Map();
let modalOverlayCounter = 0;

function raiseToastContainerAboveModals(toastContainer = document.getElementById('toast-container')) {
    if (!toastContainer) {
        return;
    }

    const topModalZIndex = MODAL_BASE_Z_INDEX + Math.max(modalOverlayStack.length - 1, 0);
    const targetZIndex = Math.min(2147483647, topModalZIndex + 1000);

    toastContainer.style.setProperty('z-index', String(targetZIndex), 'important');
}

function ensureModalRoot() {
    let root = document.getElementById(MODAL_ROOT_ID);
    if (!root) {
        root = document.createElement('div');
        root.id = MODAL_ROOT_ID;
        document.body.appendChild(root);
    }
    return root;
}

function createModalLayer() {
    const root = ensureModalRoot();
    const overlayIndex = ++modalOverlayCounter;

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay hidden';
    overlay.dataset.modalOverlay = '1';
    overlay.dataset.overlayIndex = String(overlayIndex);

    const container = document.createElement('div');
    container.className = 'modal-container';
    container.dataset.modalContainer = '1';

    overlay.appendChild(container);
    root.appendChild(overlay);

    return { overlayIndex, overlay, container };
}

function getTopModalLayer() {
    return modalOverlayStack[modalOverlayStack.length - 1] || null;
}

function getTopModalContainer() {
    const topLayer = getTopModalLayer();
    return topLayer?.container || null;
}

function setBodyModalState() {
    if (modalOverlayStack.length > 0) {
        document.body.classList.add('modal-open');
    } else {
        document.body.classList.remove('modal-open');
    }
}

function clearLayerTimer(layerOrIndex) {
    const overlayIndex = typeof layerOrIndex === 'number'
        ? layerOrIndex
        : layerOrIndex?.overlayIndex;

    if (!overlayIndex || !modalTimeouts.has(overlayIndex)) {
        return;
    }

    const timer = modalTimeouts.get(overlayIndex);
    if (timer.kind === 'interval') {
        clearInterval(timer.handle);
    } else {
        clearTimeout(timer.handle);
    }
    modalTimeouts.delete(overlayIndex);
}

function setLayerTimer(layer, handle, kind) {
    clearLayerTimer(layer);
    modalTimeouts.set(layer.overlayIndex, { handle, kind });
}

function acquireModalLayer() {
    if (modalOverlayPool.length === 0) {
        return createModalLayer();
    }

    let selectedIndex = 0;
    for (let i = 1; i < modalOverlayPool.length; i++) {
        if (modalOverlayPool[i].overlayIndex < modalOverlayPool[selectedIndex].overlayIndex) {
            selectedIndex = i;
        }
    }

    return modalOverlayPool.splice(selectedIndex, 1)[0];
}

function releaseModalLayer(layer) {
    clearLayerTimer(layer);
    layer.container.removeAttribute('id');
    layer.container.innerHTML = '';
    layer.overlay.classList.add('hidden');
    layer.overlay.style.pointerEvents = 'none';

    if (!modalOverlayPool.some((entry) => entry.overlayIndex === layer.overlayIndex)) {
        modalOverlayPool.push(layer);
    }
}

/**
 * Open a modal with UI content
 * @param {Object} uiData - UI configuration for modal content (should have parent='modal')
 */
function openModal(uiData) {
    if (!uiData || typeof uiData !== 'object') {
        console.error('Invalid modal payload');
        return;
    }

    const previousTop = getTopModalLayer();
    if (previousTop) {
        previousTop.container.removeAttribute('id');
        previousTop.overlay.style.pointerEvents = 'none';
    }

    const layer = acquireModalLayer();
    layer.container.innerHTML = '';
    layer.container.id = 'modal';
    layer.overlay.style.zIndex = String(MODAL_BASE_Z_INDEX + modalOverlayStack.length);
    layer.overlay.style.pointerEvents = 'auto';

    // Add ui-container class to modal container so collectContextValues() can find inputs.
    if (!layer.container.classList.contains('ui-container')) {
        layer.container.classList.add('ui-container');
    }

    const modalRenderer = new UIRenderer(uiData);
    modalRenderer.render();

    layer.overlay.classList.remove('hidden');
    modalOverlayStack.push(layer);
    raiseToastContainerAboveModals();
    setBodyModalState();

    let timeoutConfig = null;

    for (const [, component] of Object.entries(uiData)) {
        if (component.parent === 'modal' && component._timeout && component._timeout_ms) {
            timeoutConfig = component;
            break;
        }
    }

    if (timeoutConfig) {
        const timeoutMs = timeoutConfig._timeout_ms;
        const showCountdown = timeoutConfig._show_countdown ?? true;
        const timeoutAction = timeoutConfig._timeout_action || 'close_modal';
        const callerServiceId = timeoutConfig._caller_service_id;
        const timeUnitLabel = timeoutConfig._time_unit_label || 'segundos';

        if (showCountdown) {
            startModalCountdown(layer, timeoutMs, timeoutConfig._timeout, timeoutConfig._time_unit, timeUnitLabel, timeoutAction, callerServiceId);
        } else {
            const timeoutHandle = setTimeout(() => {
                executeTimeoutAction(timeoutAction, callerServiceId);
            }, timeoutMs);
            setLayerTimer(layer, timeoutHandle, 'timeout');
        }
    }

    console.log('✅ Modal opened');
}

/**
 * Close the modal
 */
function closeModal() {
    const closingLayer = modalOverlayStack.pop();
    if (!closingLayer) {
        return;
    }

    releaseModalLayer(closingLayer);

    const topLayer = getTopModalLayer();
    if (topLayer) {
        topLayer.container.id = 'modal';
        topLayer.overlay.style.pointerEvents = 'auto';
        topLayer.overlay.style.zIndex = String(MODAL_BASE_Z_INDEX + modalOverlayStack.length - 1);
    }

    setBodyModalState();
    raiseToastContainerAboveModals();

    console.log('✅ Modal closed');
}

/**
 * Update components inside modal
 * @param {Object} updates - Object with component names as keys and their updates as values
 */
function updateModalComponents(updates) {
    const modalContainer = getTopModalContainer();

    if (!modalContainer) {
        console.error('❌ Modal container not found');
        return;
    }

    // First, clear all existing errors in modal inputs
    const allInputGroups = modalContainer.querySelectorAll('.ui-input-group');
    allInputGroups.forEach(inputGroup => {
        if (globalRenderer) {
            globalRenderer.updateComponent(inputGroup, { error: null });
        }
    });

    // Iterate through each field update
    for (const [fieldName, changes] of Object.entries(updates)) {
        // Find the input group by looking for an input with id matching the field name
        // The input id is set to the component name in InputComponent.render()
        const input = modalContainer.querySelector(`input[id="${fieldName}"]`);

        if (!input) {
            console.warn(`⚠️ Input field "${fieldName}" not found in modal`);
            continue;
        }

        // Get the input's wrapper (ui-input-group) which is the component container
        const inputGroup = input.closest('.ui-input-group');

        if (!inputGroup) {
            console.warn(`⚠️ Input group not found for field "${fieldName}"`);
            continue;
        }

        // Apply the changes using the existing updateComponent logic
        if (globalRenderer) {
            globalRenderer.updateComponent(inputGroup, changes);
        } else {
            console.error('❌ Global renderer not available');
        }
    }
}

/**
 * Start countdown timer for modal
 */
function startModalCountdown(layer, totalMs, initialValue, timeUnit, timeUnitLabel, timeoutAction, callerServiceId) {

    const delayedStartHandle = setTimeout(() => {
        // Try to find countdown label by ID (name property creates id attribute)
        let countdownLabel = layer.container.querySelector('#countdown');

        if (!countdownLabel) {
            // Fallback: Try by querySelector
            countdownLabel = layer.container.querySelector('.ui-label.h2');
            console.log('⚠️ Countdown not found by ID, using fallback selector');
        }

        if (!countdownLabel) {
            console.error('❌ Countdown label not found!');
            console.log('📋 Modal HTML:', layer.container?.innerHTML || 'Modal not found');
            return;
        }

        const startTime = Date.now();
        const endTime = startTime + totalMs;

        let updateCount = 0;

        // Update countdown every 100ms for smooth updates
        const intervalHandle = setInterval(() => {
            const remaining = endTime - Date.now();
            updateCount++;

            if (remaining <= 0) {
                clearLayerTimer(layer);
                // console.log(`⏱️ Timeout completed after ${updateCount} updates`);
                // console.log('🎬 Executing action:', timeoutAction);
                executeTimeoutAction(timeoutAction, callerServiceId);
            } else {
                // Calculate remaining time in the original unit
                const remainingValue = Math.ceil(getRemainingValue(remaining, timeUnit));
                const label = remainingValue === 1 ? getSingularLabel(timeUnit) : timeUnitLabel;
                const newText = `${remainingValue} ${label}`;

                // Update the label
                countdownLabel.textContent = newText;
            }
        }, 100);

        setLayerTimer(layer, intervalHandle, 'interval');

        console.log('✅ Countdown timer started successfully!');
    }, 150); // Wait 150ms for DOM rendering

    setLayerTimer(layer, delayedStartHandle, 'timeout');
}

/**
 * Get remaining value in the specified time unit
 */
function getRemainingValue(remainingMs, timeUnit) {
    switch (timeUnit) {
        case 'seconds': return remainingMs / 1000;
        case 'minutes': return remainingMs / (60 * 1000);
        case 'hours': return remainingMs / (60 * 60 * 1000);
        case 'days': return remainingMs / (24 * 60 * 60 * 1000);
        default: return remainingMs / 1000;
    }
}

/**
 * Get singular label for time unit
 */
function getSingularLabel(timeUnit) {
    switch (timeUnit) {
        case 'seconds': return 'segundo';
        case 'minutes': return 'minuto';
        case 'hours': return 'hora';
        case 'days': return 'día';
        default: return 'segundo';
    }
}

/**
 * Execute action when timeout completes
 */
async function executeTimeoutAction(action, callerServiceId) {
    if (action === 'close_modal') {
        closeModal();
    } else {
        // Execute custom action via backend
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            const usimStorage = getUsimStorageHeaderValue();

            const response = await fetch('/api/ui-event', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-USIM-Storage': usimStorage,
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    component_id: callerServiceId,
                    event: 'timeout',
                    action: action,
                    parameters: {},
                }),
            });

            const result = await response.json();

            if (response.ok && globalRenderer) {
                globalRenderer.handleUIUpdate(result);
            }
        } catch (error) {
            console.error('❌ Error executing timeout action:', error);
            closeModal();
        }
    }
}

// Close modal when clicking on overlay background
document.addEventListener('DOMContentLoaded', () => {
    // Ensure modal root is available even if template did not include it.
    ensureModalRoot();

    document.addEventListener('click', (e) => {
        const overlay = e.target;
        if (!(overlay instanceof HTMLElement)) {
            return;
        }

        if (!overlay.classList.contains('modal-overlay')) {
            return;
        }

        const topLayer = getTopModalLayer();
        if (!topLayer) {
            return;
        }

        // Only close if clicking directly on the top overlay background.
        if (overlay === topLayer.overlay) {
            closeModal();
        }
    });
});

// Make modal functions globally available
window.openModal = openModal;
window.closeModal = closeModal;

/**
 * Load menu UI
 */
async function loadMenuUI(forceReset = null) {
    if (!window.MENU_SERVICE) {
        console.log('ℹ️ No MENU_SERVICE defined, skipping menu load');
        return;
    }

    try {
        const shouldReset = forceReset === null ? Boolean(window.RESET_STATE) : Boolean(forceReset);
        const resetQuery = shouldReset ? 'reset=true' : '';
        const usimStorage = getUsimStorageHeaderValue();
        const parentElement = 'parent=menu';

        // Use /api/ui/ prefix for menu as well
        const response = await fetch(`/api/ui/${window.MENU_SERVICE}?${parentElement}&${resetQuery}`,
            {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-USIM-Storage': usimStorage,
                }
            }
        );
        const uiData = await response.json();

        const menuContainer = document.getElementById('menu');
        if (!menuContainer) {
            console.error('❌ Menu container #menu not found');
            return;
        }

        // If globalRenderer exists, merge menu data and components
        if (globalRenderer) {
            // Merge menu data
            Object.assign(globalRenderer.data, uiData);

            // Sort components by _order field (preserves backend order)
            const entries = Object.entries(uiData)
                .filter(([id, config]) => !isSpecialUIKey(id) && config && typeof config === 'object')
                .sort((a, b) => {
                    const orderA = a[1]._order || 999;
                    const orderB = b[1]._order || 999;
                    return orderA - orderB;
                });

            // Separate top-level (parent='menu') from children
            const topLevel = entries.filter(([, config]) => config.parent === 'menu');
            const children = entries.filter(([, config]) => config.parent !== 'menu');

            // Combine: top-level first (sorted by _order), then children
            const components = [...topLevel, ...children];

            // Create and add menu components to globalRenderer AND render them
            for (const [id, config] of components) {
                const component = ComponentFactory.create(id, config);
                if (component) {
                    // Add to global registry
                    globalRenderer.components.set(id, component);

                    // Render and mount component
                    const element = component.render();
                    if (element) {
                        let parentEl;

                        // Find parent element by data-component-id or by DOM id
                        if (config.parent === 'menu') {
                            parentEl = menuContainer;
                        } else {
                            // Try to find by data-component-id first
                            parentEl = document.querySelector(`[data-component-id="${config.parent}"]`);
                            // Fallback to regular id
                            if (!parentEl) {
                                parentEl = document.getElementById(config.parent);
                            }
                        }

                        if (parentEl) {
                            parentEl.appendChild(element);
                        }
                    }
                }
            }
        } else {
            // First load - create globalRenderer with menu
            globalRenderer = new UIRenderer(uiData);
            globalRenderer.render();
        }
    } catch (error) {
        console.error('❌ Error loading menu:', error);
    }
}

// Load UI on page load
document.addEventListener('DOMContentLoaded', async () => {
    const initialResetState = Boolean(window.RESET_STATE);

    await loadScreenUI(null, initialResetState);  // Load main UI first to create globalRenderer
    await loadMenuUI(initialResetState);  // Then load menu and merge into globalRenderer

    // Clear reset flag after both screen and menu consumed it.
    if (initialResetState) {
        window.history.replaceState({}, document.title, window.location.pathname);
        window.RESET_STATE = false;
    }

    // Check for pending toast after page load
    const pendingToast = sessionStorage.getItem('pendingToast');
    if (pendingToast) {
        sessionStorage.removeItem('pendingToast');
        try {
            const toastConfig = JSON.parse(pendingToast);
            if (globalRenderer) {
                globalRenderer.showToast(toastConfig);
            }
        } catch (error) {
            console.error('Error showing pending toast:', error);
        }
    }
});
