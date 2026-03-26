# USIM Theming Guide

## Global Theme Contract

USIM uses a global theme contract based on `data-theme` at document level:

- `html[data-theme]`
- `body[data-theme]`
- Allowed values: `light`, `dark`

The renderer applies theme to both `html` and `body` so CSS and embedded fragments can react consistently.

## Persisted Theme Source

Persistent variables (`store_*`) are stored inside a JSON payload in localStorage.
The payload key is dynamic and may vary by backend context.

USIM resolves it in this order:

1. `window.USIM_STORAGE_KEY`
2. `sessionStorage['__usim_storage_key__']`
3. Auto-detection in localStorage:
   - picks JSON payloads containing `store_*`
   - prioritizes payloads containing `store_theme`
4. Fallback: `usim`

This avoids first-render mismatches when the active key is not yet set in window/session.

## CSS Authoring Rules (Framework)

1. Use semantic tokens from `ui-theme-tokens.css`.
2. Do not hardcode local fragment theme as a source of truth.
3. Fragments should react to global theme selectors:

```css
html[data-theme="light"] .wf,
body[data-theme="light"] .wf {
    /* light overrides */
}
```

4. Keep local selectors (for compatibility) only as optional fallback:

```css
.wf[data-theme="light"],
html[data-theme="light"] .wf,
body[data-theme="light"] .wf {
    /* light overrides */
}
```

## Runtime Theme Updates

Use one event channel for cross-fragment synchronization:

- Event: `usim:theme-changed`
- Payload: `{ theme: "light" | "dark", source?: string }`

When a screen updates theme, it must update `html/body data-theme` first, then emit the event.

### Framework Theme API

USIM exposes a global API to prevent theme logic duplication in fragments:

- `window.USIM_THEME.get()`
- `window.USIM_THEME.set(theme, source?)`

Behavior of `set`:

1. Validates and applies theme on `html/body`
2. Persists `store_theme` in the active JSON storage payload
3. Emits `usim:theme-changed`

Recommended fragment toggle:

```javascript
window.USIM_THEME.set('light', 'my-fragment-toggle');
```

## Testing Recommendations

1. Contract test: fragment HTML should not hardcode `data-theme="dark"` at root.
2. Contract test: fragment CSS should include `html[data-theme]` support.
3. E2E/UI test (optional): persisted `store_theme` is applied on first Home render.
