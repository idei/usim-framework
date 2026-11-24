# USIM `CardGrid` Component (`UI::cardGrid()`) — AI Technical Reference

> **Target audience:** AI coding assistants (LLMs) and framework maintainers.  
> **Scope:** Authoritative backend, DTO, JSON protocol, incremental diff (`card_patches`), frontend runtime, `.layout.yaml`, and testing specification for `Idei\Usim\Components\CardGrid` (`type: 'card_grid'`, introduced in **v2.20.0**).

---

## 1. Component Boundaries & Non-Negotiable Rules

1. **Strict separation from `ItemList` and `Card`:**
   - Use **`UI::cardGrid()`** (`Idei\Usim\Components\CardGrid`, `type: 'card_grid'`) for responsive 2D card grids with pagination, sorting, integrated toolbar filters, media/badges/metrics, and per-card actions.
   - Keep **`UI::itemList()`** for 1D vertical form pickers, persistent selection (`SelectionMode`), and inline quick-CRUD.
   - `CardGrid` renders lightweight standardized cards directly on the client (`UsimCardGridComponent`); **do NOT** instantiate child `Card` PHP components per item.
2. **Closed typed card slots (NO raw HTML):**
   - Cards are modeled strictly through `Idei\Usim\DTOs\CardItemDTO` and `Idei\Usim\DTOs\CardActionDTO`.
   - Frontend renders all card fields via `textContent` (never `innerHTML`).
   - Per-card visual differentiation must use `Idei\Usim\Enums\Style` tokens (and optional 3px top / 4px left `accent_color`) so Light/Dark theme contrast is preserved.
3. **Read-only `GET /api/ui-data` vs mutating `POST /api/ui-event`:**
   - Search, sort, integrated filters, and pagination (`pages` and `load_more`) execute via `GET /api/ui-data` against `CardListable::query(CardQuery)` (or adapted `Listable::list()`). Never mutate component state inside read-only queries (`UsimReadOnlyLifecycleException`).
   - Card clicks (`on_card_click` / `click_action`) and per-card footer/overflow actions dispatch `POST /api/ui-event` directly to `Screen::on<Action>()`.
4. **Prefer surgical patching (`patchCard` / `removeCard` / `prependCard`) over `reload()`:**
   - When a `POST /api/ui-event` handler mutates a single item (e.g. star/favorite, pin, status badge, disabled action), call `$grid->patchCard($id, [...])` (or `removeCard($id)` / `prependCard($dto)`).
   - **Never mutate `$grid->items(...)`** for single-card updates — `ComponentDiffer` compares top-level keys strictly (`!==`) and would serialize the entire `items` array.
   - Reserve `$grid->reload()` for changes that invalidate global sort order or active filter membership.
5. **Lazy initial service hydration:**
   - `CardGrid::service()` marks `$serviceDataStale = true` and defers the initial `CardListable::query()` until `toJson()` or `getItems()` is called. This ensures `.layout.yaml` keys or fluent calls chained after `service()` (`perPage`, `defaultSort`, `filters`, `activeFilters`) take effect in a single initial query.

---

## 2. Backend Contracts & DTOs

| Class | Role |
|---|---|
| `Idei\Usim\Contracts\CardGrid\CardListable` | Single-method contract: `query(CardQuery $query): CardPageResult`. |
| `Idei\Usim\DTOs\CardQuery` | Immutable query VO: `search`, `page`, `perPage`, `sortBy`, `sortDirection` (`'asc'\|'desc'`), `filters`, `context`; helpers `fromArray()`, `filter()`, `hasFilter()`, `offset()`. |
| `Idei\Usim\DTOs\CardPageResult` | Immutable page VO: `items` (`list<CardItemDTO>`), `total` (`?int`), `page`, `perPage`, `hasMore`; helpers `make()`, `fromCollection()`, `totalPages()`. |
| `Idei\Usim\DTOs\CardItemDTO` | Immutable standardized card VO (`make()`, `fromArray()`, `fromListItemDTO()`). |
| `Idei\Usim\DTOs\CardActionDTO` | Immutable per-card action VO (`label`, `action`, `parameters`, `url`, `target`, `icon`, `style`, `variant` `'solid'\|'outline'\|'ghost'`, `disabled`, `overflow`, `tooltip`). |
| `Idei\Usim\Components\CardGrid` | Fluent builder (`type = 'card_grid'`, `SERVICE_ACTION_PREFIX = '__usim_card_grid:'`). Accepts `CardListable` or `Listable` in `->service(...)`. |

---

## 3. JSON Contract (`type: "card_grid"`)

### 3.1 Component Snapshot
```json
{
  "type": "card_grid",
  "name": "movies_grid",
  "title": "Cartelera Destacada",
  "subtitle": "Subtítulo opcional",
  "show_count": true,
  "header_action": { "label": "Nuevo", "action": "create_item", "parameters": {}, "icon": "plus", "style": "primary" },
  "items": [ /* list<CardItemDTO> */ ],
  "pagination": {
    "mode": "pages",
    "page": 1,
    "per_page": 12,
    "per_page_options": [12, 24, 36, 48],
    "show_per_page": true,
    "total": 18,
    "total_pages": 2,
    "has_more": true,
    "can_prev": false,
    "can_next": true
  },
  "searchable": true,
  "search_placeholder": "Buscar...",
  "search_term": null,
  "min_search_chars": 0,
  "debounce": 300,
  "sort_options": [{ "field": "title", "label": "Título" }],
  "sort_by": "title",
  "sort_direction": "asc",
  "filters": [
    { "name": "genre", "label": "Género", "type": "select", "placeholder": "Todos", "options": [{ "value": "sci-fi", "label": "Ciencia Ficción" }] },
    { "name": "tags", "label": "Etiquetas", "type": "multi_select", "options": [{ "value": "4K", "label": "4K" }] },
    { "name": "featured", "label": "Solo destacadas", "type": "toggle" },
    { "name": "decade", "label": "Década", "type": "chips", "options": [{ "value": "2010", "label": "2010s" }] }
  ],
  "active_filters": {},
  "action": "__usim_card_grid:movies_grid:query",
  "parameters": {},
  "auto_fetch": true,
  "reload_token": 0,
  "card_patches": null,
  "min_card_width": "280px",
  "max_columns": 3,
  "gap": "16px",
  "card_layout": "vertical",
  "variant": "elevated",
  "density": "comfortable",
  "image_aspect_ratio": "16/9",
  "max_visible_actions": 2,
  "max_visible_tags": 3,
  "skeleton_count": 6,
  "show_layout_toggle": true,
  "on_card_click": { "action": "view_movie", "parameters": {} }
}
```

### 3.2 `GET /api/ui-data` Request & Response
- **Query parameters**: `component_id`, `action` (`__usim_card_grid:<name>:query` or custom Screen `get<Action>()`), `parameters` JSON containing `{ page, per_page, search, sort_by, sort_direction, filters, ...extraParams }`.
- **JSON response** (`CardPageResult::toArray()`):
  ```json
  {
    "items": [ /* list<CardItemDTO> */ ],
    "pagination": {
      "page": 1,
      "per_page": 6,
      "total": 18,
      "total_pages": 3,
      "has_more": true,
      "can_prev": false,
      "can_next": true
    }
  }
  ```

### 3.3 Surgical Incremental Diff Protocol (`card_patches`)
Returned inside `POST /api/ui-event` delta payloads (strictly without `items` or `reload_token`):
```json
{
  "<component_id>": {
    "card_patches": {
      "seq": 1,
      "ops": [
        { "op": "patch", "id": 2, "data": { "badge": "★ Favorita", "badge_style": "warning", "footer_text": "En tu lista", "actions": [] } },
        { "op": "replace", "id": 5, "data": { "id": 5, "title": "Full CardItemDTO" } },
        { "op": "remove", "id": 9 },
        { "op": "prepend", "data": { "id": 99, "title": "New Card" } }
      ]
    }
  }
}
```
- `seq` increments strictly once per event turn (multiple calls to `patchCard`/`removeCard`/`prependCard` in one handler batch into the same `seq`).
- Client tracks `this._lastPatchSeq` and ignores already-applied sequences (idempotent across snapshots).

---

## 4. Framework Integration Points

| File | Responsibility |
|---|---|
| `packages/idei/usim/src/UI.php` | Factory method `UI::cardGrid(?string $name = null): CardGrid`. |
| `packages/idei/usim/src/Support/ScreenLifecycleOrchestrator.php` | Maps `'card_grid', 'cardgrid' => CardGrid::class`; resolves `resolveCardGridServiceBinding()` for read-only `GET /api/ui-data` queries (`queryServicePage()`). |
| `packages/idei/usim/src/Support/ContainerLayoutLoader.php` | Auto-instantiation prefix `'grd_' => CardGrid::class`, aliases `'card_grid'` & `'cardgrid'`, and `i18n: auto` hydration (`groups: ['grids', 'cards']`, `attributes: ['title', 'subtitle', 'empty_message', 'no_results_message', 'min_chars_message', 'search_placeholder', 'load_more_label', 'clear_filters_label']`). |
| `packages/idei/usim/resources/views/app.blade.php` | Loads `css/components/card-grid/index.css` and `js/components/card-grid/index.js`. |
| `packages/idei/usim/resources/assets/js/components/card-grid/index.js` | `UsimCardGridComponent` registered as `'card_grid'` in `window.USIM_COMPONENTS`. |
| `packages/idei/usim/resources/assets/css/components/card-grid/index.css` | Intrinsic responsive CSS Grid (`--usim-cg-min-width`, `--usim-cg-max-cols`), Light/Dark theme tokens, native `border-top` (vertical) / `border-left` (horizontal) semantic accents so `border-radius: 12px` renders cleanly with `overflow: visible` for `⋮` overflow menus. |

---

## 5. Frontend Runtime Mechanics

1. **Surgical Fast Path (`update()`):**
   - If `newConfig` contains only `card_patches` (with `seq > this._lastPatchSeq`), `_applyCardPatches()` runs directly without re-rendering the toolbar, unrelated cards, or calling `GET /api/ui-data`.
   - `_replaceCardDomNode()` reuses the existing `.usim-cg-media img` DOM node whenever `previousItem.image === updatedItem.image`, avoiding flicker and image re-decoding.
2. **Action Overflow Discipline:**
   - Renders up to `max_visible_actions` (default `2`) inline buttons per card; actions marked `overflow: true` or exceeding the limit collapse automatically into a `⋮` menu (`role="menu"`, closes on outside click or `Escape`).
   - All footer buttons and overflow triggers call `e.stopPropagation()` so they never trigger card click.
3. **Static Fallback:**
   - When `action` is `null` and `items` are supplied statically, search, filters, sorting, and pagination (`pages` and `load_more`) run locally in the browser with identical UX.

---

## 6. Testing Patterns

- **Unit tests (`tests/Unit/CardGridComponentTest.php`):** fluent builder serialization (`toJson()[$grid->getId()]`), DTO normalization (`CardItemDTO`, `CardActionDTO`, `CardQuery`, `CardPageResult`), `.layout.yaml` `grd_` / `card_grid` auto-instantiation, and `card_patches` batching + `null` clearing.
- **Feature tests (`tests/Feature/CardGridServiceWiringTest.php`):** lazy `CardListable` binding, `Listable` fallback adaptation, `GET /api/ui-data` with search/filters/sort/pagination, and `POST /api/ui-event` asserting surgical `card_patches` diffs strictly omit `items` and `reload_token`.
