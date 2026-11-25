# Changelog

All notable changes to this package will be documented in this file.

The format is based on Keep a Changelog, and this project follows Semantic Versioning.

## [Unreleased]

### Fixed
- **Invisible `0px × 0px` SVG icons inside `CardGrid` and `ItemCard` (`js/components/shared/content-render.js`, `css/ui-components.css`, `css/components/card-grid/index.css`, `css/components/item-card/index.css`):** updated `createIconElement()` to honor `options.size` (`width`/`height` on `.ui-icon-svg`/`.ui-icon-img`, `font-size` on `.ui-icon-text`), added base dimensions to `.ui-icon-svg`/`.ui-icon-img` in `ui-components.css` (without overriding `.ui-button-icon`/`.ui-label-icon`), and added explicit `.usim-cg-icon`/`.usim-ic-icon` rules so published SVG icons render visibly across toolbars, header actions, card attributes, and action buttons.
- **`CardGrid` static-mode `static_items` desynchronization (`Idei\Usim\Components\CardGrid`):** synchronized `$this->config['static_items']` in `prependCard()`, `removeCard()`, and `patchCard()` (and omitted internal server-only `static_items` from client wire diffs in `ComponentDiffer`) so subsequent search, sort, filter, or pagination queries preserve inserted, patched, and deleted cards.
- **Icon-only action accessibility and `⋮` overflow menu fallback (`js/components/item-card/index.js`):** set `aria-label` from `tooltip` on icon-only inline action buttons (`!label && tooltip`), and fell back to `act.label || act.tooltip || ''` inside the `⋮` overflow menu so icon-only actions always display readable text.
- **Level 1 declarative i18n parity for `CardGrid` and `ItemCard` (`Idei\Usim\Support\ContainerLayoutLoader`):** added `load_more_label`, `clear_filters_label`, `subtitle`, `eyebrow`, `badge`, and `footer_text` to `TRANSLATABLE_TEXT_PROPERTIES` (matching Level 2 `i18n: auto` schemas) and resolved `.key`/`t:key` strings inside `header_actions`.

### Added
- **Image storage & placeholder pipeline parity for `CardGrid`, `ItemCard`, and `CardItemDTO` (`Idei\Usim\Support\ImageSourceResolver`, `ItemCard`, `CardGrid`, `CardItemDTO`, `js/components/item-card/index.js`):**
  - Added `imageStorage(?string $dir)`, `imagePlaceholder(...)`, `imagePlaceholderAspect(...)`, `imagePlaceholderColors(...)` on both `ItemCard` and `CardGrid` (plus `imagePlaceholderId()` on `ItemCard` and optional per-item fields on `CardItemDTO`), delegating relative filename resolution to `UploadService::fileUrl()` and empty-image placeholder generation to `ImagePlaceholderGeneratorInterface` identically to `UI::image()`.
  - Added `'scale-down'` support to `imageFit` alongside `'cover'` and `'contain'` across `CardItemDTO`, `ItemCard`, and `UsimItemCardComponent`.
- **Multiple header actions & semantic title styling on `CardGrid` (`CardGrid`, `js/components/card-grid/index.js`, `css/components/card-grid/index.css`):**
  - Added `headerActions(array $actions)` and `addHeaderAction(...)` (accepting `CardActionDTO` or arrays, supporting `variant`, `style`, `icon`, `url`, `disabled`, `tooltip`) while keeping `headerAction()` fully backward compatible.
  - Added `titleStyle(Style|string $style)` for semantic header title coloring (`primary`, `secondary`, `muted`, `success`, `warning`, `danger`, `info`).
- **`CardGrid` / `ItemCard` testing helpers & `Container` tree traversal (`ScreenTestHarness`, `Container`, `CardGrid`):**
  - Extended `Container::findByName()` and `Container::findById()` to search already-mounted child `ItemCard`s inside `CardGrid` without triggering premature lazy service hydration.
  - Added `getCardGrid()`, `findCard()`, `getCard()`, `assertHasCard()`, `assertMissingCard()`, `assertCardCount()`, `assertCardBadge()`, `assertCardHasAction()`, `assertCardMissingAction()`, `clickCardAction()`, and `clickCard()` to `ScreenTestHarness`.

### Fixed (v2.20.1)
- **Dynamically added components missing `component.element` reference (`js/ui-renderer.js`):** updated `UIRenderer::addComponent()` to assign `component.element = element` after `component.render()` (matching `UIComponent::mount()`). Previously, `ItemCard` children mounted incrementally during pagination, search, filtering, or `load_more` had `this.element === null`, causing subsequent `density` or `layout` updates to skip those dynamically added cards while only updating initially mounted cards.
- **Resilient `ItemCard` DOM binding & local layout toggle sync (`js/components/item-card/index.js`, `js/components/card-grid/index.js`, `tests/Feature/CardGridServiceWiringTest.php`):** assigned `this.element` directly in `UsimItemCardComponent::render()`, added a `[data-component-id]` DOM fallback in `update()`, synchronized `childComp.config.layout` when toggling layout from `CardGrid`'s inline toolbar button so subsequent single-card updates preserve the active orientation, and added regression tests verifying `density_changed` and `card_layout_changed` diffs across all mounted cards after `load_more`.

### Added (v2.20.0)
- **New `UI::itemCard()` First-Class Standardized Card Component (`Idei\Usim\Components\ItemCard`, `type: 'item_card'`)**:
  - Added modern, theme-safe card component usable both **standalone** (in any `Screen`, `Container`, `Modal`, or `.layout.yaml`) and **managed as child components inside `CardGrid`**.
  - Closed, typed 6-zone visual anatomy rendered safely via `textContent` (no raw `innerHTML`): media (`image`, `aspectRatio`, `imageFit`, lazy loading + automatic fallback) or `icon`/`avatar`; header (`eyebrow`, `title`, `subtitle`, `badge`/`badgeStyle`); highlight `metric`/`metricLabel`/`metricStyle`; body (`description`, `attributes`, `tags`); bottom-aligned footer (`footerText` + `actions` with automatic `⋮` overflow kebab menu); and semantic styling (`Style` enum with native `border-top` / `border-left` rounded accents, `featured`, `disabled`, `accentColor`, `vertical`/`horizontal`, `elevated`/`outlined`/`flat`, `compact`/`comfortable`/`spacious`).
  - Client-side `UsimItemCardComponent` (`js/components/item-card/index.js`, `css/components/item-card/index.css`) reuses existing `<img>` DOM nodes on `update()` when the image URL is unchanged (zero visual flicker) and supports `.layout.yaml` auto-instantiation (`icrd_` prefix, `item_card`/`itemcard` aliases, `i18n: auto`).
- **New `UI::cardGrid()` Pure Server-Driven Responsive Card Grid (`Idei\Usim\Components\CardGrid`, `type: 'card_grid'`)**:
  - Added responsive 2D CSS Grid container (`--usim-cg-min-width`, optional `maxColumns()`, `gap()`, `cardLayout()`, `variant()`, `density()`, `showLayoutToggle()`) that mounts and manages child `ItemCard` components with **deterministic per-item IDs** (`<grid>__card_<itemId>`) and explicit `card_order` tracking.
  - Added typed domain contract `Idei\Usim\Contracts\CardGrid\CardListable` (`query(CardQuery $query): CardPageResult`), automatic fallback adapter for existing `ItemList\Listable` services, server-side static collection support via `items()`, and immutable DTOs: `CardQuery`, `CardPageResult`, `CardItemDTO`, and `CardActionDTO`.
  - Added integrated responsive toolbar inside `CardGrid`: debounced live search, sort selector + direction toggle, declarative filters (`select`, `multi_select`, `toggle`, `chips`), removable active-filter chips with "Limpiar filtros", collapsible mobile filter bar, and dual pagination (`pages` with `perPageOptions` and incremental `load_more`).
  - Wired all `CardGrid` queries and mutations natively over **`POST /api/ui-event`** (`__usim_card_grid:<target>:query` resolved automatically by `ScreenLifecycleOrchestrator::handleEvent()`) so `ComponentDiffer` produces minimal native `ADD` / `UPDATE` / `DELETE` (`parent: null`) diffs:
    - Single-card mutations (`$grid->card($id)->badge(...)` or `$grid->patchCard($id, ...)`) emit **only** the mutated properties of that `ItemCard` (zero diff on `CardGrid` or sibling cards).
    - `removeCard($id)` emits `{ "<card_id>": { "parent": null } }`, `prependCard()` emits only the new card + shifted `card_order`, and `load_more` keeps already-mounted cards untouched (zero diff) while emitting only newly appended `ItemCard`s.
  - Integrated `UI::itemCard()`, `UI::cardGrid()`, lazy initial service hydration, `ScreenLifecycleOrchestrator` (`connectChild`/`postConnect` reconstruction, dirty-state traversal, event routing), `ContainerLayoutLoader` (`grd_`/`icrd_` prefixes, `card_grid`/`item_card` aliases, `i18n: auto`), `UsimCardGridComponent::getChildMountTarget()`, and `app.blade.php` assets.
  - Added interactive `CardGridDemo` (`app/UI/Screens/Demo/CardGridDemo.php`, `card-grid-demo.layout.yaml`, `MovieCardGridService`, `en`/`es` translations, registered in `DemosMenuProvider`), comprehensive unit/feature tests (`CardGridComponentTest`, `CardGridServiceWiringTest`), user guide (`docs/CARD_GRID_GUIDE.md`), AI technical reference (`docs/CARD_GRID_AI_REFERENCE.md`), and updated JSON contract/context docs.

### Deprecated (v2.20.0)
- **Deprecated legacy `Idei\Usim\Components\Card` (`UI::card()`)**:
  - Marked `@deprecated` in favor of `UI::itemCard()` (`Idei\Usim\Components\ItemCard`) for standardized cards and `UI::container()->card()` for card-styled containers. Existing screens (`Registered`, `EmailVerified`) remain fully backward compatible until migrated.

### Added (v2.19.0)
- **Workbench Center `bottom_panel` (Vertical `Split`) & Sticky `top_bar` Slots**:
  - Extended `WorkbenchLayout` (`app/UI/Layouts/workbench-layout.layout.yaml` and package stub) so the center column (`center_column_container`, `.usim-workbench-center`) hosts:
    - **`top_bar`** (`top_bar_container`, aliases `[header, toolbar]`, `visible: false` by default, `persist_on_navigate: false`): sticky contextual header row (`auto`) directly above `main` that occupies `0px` when unused.
    - **`bottom_panel`** (`bottom_panel_container`, aliases `[bottom, panel, console, terminal]`, `persist_on_navigate: false`) inside a vertical `Split` (`split_center`, `split_size: 240px`, `split_size_target: second`, `collapse_target: second`, `collapse_size: 0px`, `collapsed_panel: second`, `hide_divider_when_collapsed: true`, `min_first_size: 200px`, `min_second_size: 120px`) ideal for consoles, logs, batch output, or AI prompt bars.
  - Extended `AbstractLayout::expandSlot()`, `collapseSlot()`, and `isSlotExpanded()` to toggle `visible` seamlessly on non-Split auxiliary local slots (such as `top_bar`), added `bottom_panel` / `top_bar` alias resolution, and added `.usim-workbench-center` / `top_bar` / `bottom_panel` CSS rules.
  - Extended `SlotProjectionDemo` (PHP, `.layout.yaml`, `en`/`es` translations), `WorkbenchLayoutAndNavigationTest`, `ScreenSlotProjectionTest`, `LAYOUTS_COMPOSITION_GUIDE.md`, `WORKBENCH_AND_UNIFIED_LAYOUTS_AI_REFERENCE.md`, and `usim_context_prompt.md`.

### Fixed (v2.19.0)
- **Split drag size persistence with `split_size_target: 'second'`** (`js/components/split/index.js`): updated `UsimSplitComponent::_startDrag()` to compute and store the second pane's size in `lastExpandedSize` when `split_size_target === 'second'`, preventing vertical/horizontal secondary panes (`bottom_panel`, `right_panel`) from snapping to the wrong size on resize after manual dragging.

### Added (v2.18.0)
- **New `UI::richText()` WYSIWYG HTML Editor Component (`Idei\Usim\Components\RichText`, `type: 'rich_text'`)**:
  - Added dedicated server-driven rich text editor powered by **SunEditor v3.4.3** (MIT, zero dependencies), vendored locally under `resources/assets/lib/suneditor/` and **lazy-loaded** on demand only when a screen renders a `rich_text` component (zero payload overhead on other screens).
  - Fluent PHP builder API in `RichText`: `label()`, `placeholder()`, `value()`, `getValue()`, `toolbar()` (`'basic' | 'standard' | 'full'` or custom SunEditor `buttonList` array), `basicToolbar()`, `standardToolbar()`, `fullToolbar()`, `allowImages()` (base64 data URIs with interactive resize, rotate, mirror, align, and caption editing by default), `imageUploadUrl()`, `maxLength()`, `charCounter()`, `wordCounter()`, `statusbar()`, `lang()`, `required()`, `disabled()`, `readonly()`, `error()`, `helpText()`, `onChange()`, `onInput()`, `debounce()`, and border/sizing helpers.
  - Full USIM integration: `UI::richText()`, client value synchronization & deserialization in `ScreenLifecycleOrchestrator`, declarative `.layout.yaml` support (`rte_` prefix, `rich_text` / `richtext` aliases, and `i18n: auto` hydration in `ContainerLayoutLoader`), automatic light/dark theme synchronization via `usim:theme-changed` (`editor.$.ui.setTheme()`), synced hidden `<textarea>` for form submissions with internal SunEditor modal controls excluded in `UsimButtonComponent::collectContextValues()`, and outer wrapper height override so SunEditor chrome never overlaps sibling components.
  - Added `window.USIM_ASSET_BASE` in `resources/views/app.blade.php`, interactive `RichTextDemo` screen (`en`/`es` translations, registered in `DemosMenuProvider` and `ButtonDemo`), unit/JS tests (`RichTextComponentTest`, `tests/js/rich-text-component.test.mjs`), user guide (`docs/RICH_TEXT_GUIDE.md`), and AI technical reference (`docs/RICH_TEXT_AI_REFERENCE.md`).

### Removed / Breaking Changes (v2.18.0)
- **Removed incomplete Markdown split-view mode from `UI::textarea()`**:
  - Removed `Textarea::mode()`, `Textarea::plainText()`, and `Textarea::markdown()`, along with the split-view editor/preview UI, toolbar, CSS, and bundled `marked.min.js` (`resources/assets/js/lib/marked.min.js`).
  - `UI::textarea()` is now focused exclusively on multi-line plain text input; rich content editing should use `UI::richText()`.

### Fixed (v2.17.1)
- **StatusBar unit selector not updating on unit switch (`UIChangesCollector::add()`):** merged incremental component property deltas (entries without a `parent` key) instead of shallowly overwriting them with `array_replace()`. Previously, when `StatusBar::onChangeUnit()` updated `unit_menu` (`trigger` + `items`) and queued `UsimEvent('unit_changed')`, the subsequent `StatusBar::onUnitChanged()` pass emitted only `{ visible: true, type: 'menudropdown' }` against the already-cached snapshot and clobbered the `trigger` and `items` diff in the response.
- **Activity Bar `NavTree` layout collision on `Home` landing page** (`resources/views/welcome-usim.blade.php`, `css/components/nav-tree/index.css`): scoped global `nav`, `*`, `a`, `code`, `pre`, `h1`, `h2`, `section`, and `footer` rules in `welcome-usim.blade.php` under `.wf` and hardened `nav.usim-nav-tree` positioning/backdrop styles so embedded HTML fragments cannot hijack the Workbench Activity Bar.
- **Stale active screen & Home vs. Role Landing consistency** (`UIController.php`, `AbstractLayout.php`, `ScreenLifecycleOrchestrator.php`, `app/UI/Navigation/Menus/MainMenu.php`, `lang/*/screens/home.php`): updated `UIController::show()` to set `UIStateManager::setClientCurrentScreen()` before `render()`, fixed `AbstractLayout::postLoadUI()` and `ScreenLifecycleOrchestrator::render()` so explicit `main` slot renders are never overwritten by stale cached `state_active_screens`, and switched `MainMenu`'s `🏠` Home item to SPA `screenShow(Home::class)` so clicking `🏠` loads the landing page without colliding with the role's default screen (e.g. `Users`) in `NavTree`.
- **Activity Bar footer icon alignment** (`css/ui-components.css`): cleared the inherited `margin-right: 8px` on `.trigger-icon` and normalized `.trigger-image` / `.menu-dropdown-trigger` box alignment inside `.usim-activity-bar .usim-sidebar-footer` (and collapsed Split sidebars) so the `⚙️` settings icon aligns centered directly beneath circular user profile avatars.

### Added (v2.17.0)
- **Declarative Screen Slot Projections (`slots:` in `.layout.yaml`)**:
  - Extended `ContainerLayoutLoader` and `HandlesScreenProperties` to support a root `slots:` section alongside `grid:` and `timers:`, allowing a single `Screen` to project containers, components, or Sub-Screens into any slot of the active `AbstractLayout` (`left_panel`, `right_panel`, `status_bar` / `status_bar_center`, `status_bar_left`, `status_bar_right`, or custom layout slots) while keeping all projected inline/`include:` components under the host `Screen`'s PHP properties, `$state_*`, `i18n: auto`, and `on*()` event handlers.
  - Added symmetric `slots.main` / `slots.center` / `slots.content` support as an alternative to root `grid:` (with fail-fast `InvalidArgumentException` if both are declared simultaneously), direct `children:` shorthand on non-grid flex/stack containers, `include:` partials, and declarative `screen:` + `params:` Sub-Screen mounting inside `slots:`.
  - Added per-slot control options: `open` (default `true`), `size` (temporary Split width/height override restored automatically on navigation), `fallback` (`ignore` default → `portal_unmounted: true`, plus `inline`, `inline_top`, `inline_bottom` for graceful degradation on `MainLayout` or `$layout = null`), and `persist_on_navigate` (default `false`).
  - Added portal metadata APIs on `Container` (`portalSlot()`, `getPortalSlot()`, `getPortalMeta()`, `portalTargetId()`, `portalUnmounted()`, `clearPortalTarget()`, `setDeclaredSlotScreens()`, `getDeclaredSlotScreens()`, `collectPortalContainers()`, `moveChildToStart()`) and persisted `_layout_slot_meta` across snapshot serialization/deserialization.
- **Contextual `StatusBar` Sub-Slots**:
  - Extended `App\UI\Screens\StatusBar` and `css/ui-components.css` with three non-persistent contextual projection slots (`status_bar_left`, `status_bar_center` with aliases `status_bar` / `status_bar_items`, and `status_bar_right`) that preserve permanent shell controls (`unit_menu` and dev-only `btn_reset`) and automatically hide `lbl_status_message` (`visible: false`) while a Screen projects into `status_bar` / `status_bar_center` (restoring it on clear or navigation).
- **Second-Pane `Split` Sizing, Slot Ownership & Runtime Helpers**:
  - Added `Split::splitSizeTarget('first'|'second')`, `getSplitSize()`, `getSplitSizeTarget()`, and matching JS support in `js/components/split/index.js` so `size:` works identically in `px` or `%` on both `left_panel` (`first`) and `right_panel` (`second`).
  - Extended `LayoutInterface` and `AbstractLayout` with `isSlotExpanded()`, `setSlotSize()`, `restoreSlotSize()`, `registerExternalSlotsFromTree()`, `resolveProjectionSlot()`, `prepareScreenPortals()`, `mountDeclaredSlotScreens()`, slot ownership tracking (`$state_slot_owners`), and seamless coexistence with `SidebarSubmenuPanel` in `left_panel`.
  - Added `Screen` (`HandlesNavigation`) helpers: `openSlot()`, `closeSlot()` (collapses own portals while preserving component state unless `clear: true`), `toggleSlot()`, `isSlotOpen()`, and `slotContainer()`.
  - Extended `UIChangesCollector` with `patchComponent()` (merges incremental component patches without clobbering initial full payloads) and multi-target `clear_containers` accumulation, and updated `ui-renderer.js` to mount `portal_slot` / `portal_target_id`, skip `portal_unmounted` subtrees, and process `clear_containers`.
- **Demo, Tests & Documentation**:
  - Added interactive `SlotProjectionDemo` (`app/UI/Screens/Demo/SlotProjectionDemo.php` + `slot-projection-demo.layout.yaml` + `en`/`es` translations), comprehensive feature test suite `tests/Feature/ScreenSlotProjectionTest.php`, and updated `LAYOUTS_COMPOSITION_GUIDE.md`, `DECLARATIVE_LAYOUTS_AND_I18N.md`, `WORKBENCH_AND_UNIFIED_LAYOUTS_AI_REFERENCE.md`, `usim_context_prompt.md`, and `docs/README.md`.

### Fixed (v2.17.0)
- **Viewport clamping & scroll for tall `MenuDropdown` submenus** (`js/components/menudropdown/index.js`, `css/ui-components.css`): prevented long submenus (such as `Demos` in guest `MainLayout`) from overflowing above the top edge of the viewport; submenus now clamp strictly within `[8px, innerHeight - 8px]` and scroll vertically when taller than the viewport.
- **Unit selector lingering in `Menu` after logout** (`app/UI/Screens/Menu.php`): updated `updateUnitMenu()`, `resetToGuestState()`, and `buildBaseUI()` so `unit_menu` is cleared and hidden (`visible: false`) whenever `!Auth::check()`, even when `Menu` is restored from its preserved shell snapshot after logging out from `WorkbenchLayout`.
- **Guest menu visibility for Workbench panel demos** (`app/UI/Navigation/Menus/DemosMenuProvider.php`): gated `RightPanelDemo` and `SlotProjectionDemo` behind `when: Auth::check()` so demos requiring Workbench side panels only appear once authenticated.
- **IDE static analysis on `Screen` traits** (`HandlesNavigation.php`, `HandlesScreenProperties.php`, `Screen.php`): declared explicit `abstract` host method contracts on traits and replaced `static::getCurrentQueryParams()` / `static::$autoTranslate` with `Screen::getCurrentQueryParams()` / `$this->isAutoTranslateEnabled()` to eliminate `intelephense(P1013)` warnings.

### Added (v2.16.0)
- **Decoupled VS Code-style Activity Bar & Independent Collapsible `left_panel` Split**:
  - Refactored `WorkbenchLayout` (`app/UI/Layouts/workbench-layout.layout.yaml` and package stub) into a 2-column declarative grid (`cols: [52px, 1fr]`, `rows: [1fr, auto]`):
    - Fixed `52px` Activity Bar (`sidebar_container`, `layout_slot: sidebar`, aliases `[activity_bar, menu]`) rendered outside any `Split`.
    - Independent collapsible `left_panel` Split (`split_shell`, `collapse_size: 0px`, `collapsed_panel: first`, `hide_divider_when_collapsed: true`, aliases `[left, explorer]`) alongside `split_workspace` (`main` + `right_panel`) and full-width `status_bar` (`span: full`).
  - Added `Screen::$persistOnNavigate` / `Screen::shouldPersistOnNavigate()` and updated `AbstractLayout::showInto()` so navigation panels mounted in `left_panel` remain open across `main` navigation while screen-owned contextual left panels automatically clear and collapse to `0px` when navigating away.
  - Added `NavTree::onGroupClick()`, `NavTree::activeGroup()`, `NavTree::getItems()`, and `NavTree::setItems()` (plus JS/CSS support in `js/components/nav-tree/index.js` and `css/components/nav-tree/index.css`) so top-level Activity Bar groups delegate clicks to the backend with VS Code-style left-border active indicators while nested `NavTree` instances render arbitrary sub-sub-menu depth.
  - Added `App\UI\Screens\SidebarSubmenuPanel` (Explorer-style header, close button, hover-fade scrollbar, `$persistOnNavigate = true`), registered it as a shell caller in `UIController`, updated `SidebarMenu` to operate as the fixed Activity Bar toggling/recovering `left_panel`, and extended `AbstractLayout::expandSlot()` to re-emit expanded state for client-desync resilience.
  - Added end-to-end feature and HTTP `/api/ui-event` tests in `WorkbenchLayoutAndNavigationTest`.

### Changed (v2.15.1)
- **Subtle `Split` dividers with auto-fading three-dot handles** (`css/components/split/index.css`, `js/components/split/index.js`, `css/ui-components.css`):
  - Replaced solid divider bars and circular arrow collapse buttons with subtle `1px` inset hairline channels and centered three-dot handles (`.ui-split-dot`, stacked vertically for horizontal splits and horizontally for vertical splits).
  - Split handles now start hidden (`opacity: 0; pointer-events: none`), fade in (`0.2s`) upon window `mousemove` activity, and fade out (`0.35s`) after 2 seconds of inactivity while remaining visible on hover, drag, or keyboard focus (`:focus-visible`).
  - Added `UsimSplitComponent::destroy()` to clean up shared pointer listeners, timers, `MutationObserver`, and `ResizeObserver` on unmount, and removed redundant sidebar/`right_panel` borders adjacent to split dividers.
- **Hover-only fade scrollbars on Workbench Sidebar & `ItemList`** (`css/ui-components.css`, `css/components/item-list/index.css`):
  - Added slim `6px` transparent-track scrollbars using `@property` color transitions (`0.2s` fade in on panel/list hover, `0.35s` fade out on leave) with light/dark theme adaptation and Firefox `@supports` fallback (preserving `scrollbar-always` and `scrollbar-hidden` overrides on `ItemList`).

### Fixed (v2.15.1)
- **Removed radio button clutter in `ItemList` single-selection mode** (`js/components/item-list/index.js`, `css/components/item-list/index.css`, `lang/*/screens/demo/item_list_demo.php`):
  - Removed the visual `.usim-item-list-row-radio` indicator in `SelectionMode::SINGLE` so rows toggle selection cleanly via direct click (or `Enter`/`Space`), while keeping hidden form inputs synchronized for form submission.
  - Refined `.usim-item-list-row.is-selected` (accent border, tinted background, subtle inset borders, dedicated selected-hover state, and `user-select: none`) and updated `ItemListDemo` option labels in English and Spanish.

### Added
- **Unified Declarative Layout Engine (`AbstractLayout` + `.layout.yaml`)**:
  - `AbstractLayout` now natively supports sibling `.layout.yaml` definitions via `ContainerLayoutLoader`, automatically discovering and registering layout slots (`layout_slot`, `slot_aliases`, `screen`, `persist_on_navigate`) recursively across grids and nested `Split` panes, and restoring all slots generically across cached requests (`$state_slot_map`).
  - Added declarative `component: split` support with `first:` / `second:` panes (including nested splits), `hide_divider_when_collapsed` (`Split::hideDividerWhenCollapsed()`), deterministic `data-split-pane` routing, and `custom_class` box property support (`UIComponent::customClass()` and `ContainerLayoutLoader`).
  - Added `LayoutInterface` / `AbstractLayout` slot orchestration API (`expandSlot()`, `collapseSlot()`, `clearSlot()`, `getSlotDefaultScreen()`) and `Screen::closeSlot()`.
  - Added client active layout tracking (`UIStateManager::setClientActiveLayout()`, `getClientActiveLayout()`, `hasClientActiveLayout()`, `clearClientActiveLayout()`) with automatic SPA layout transitions (`MainLayout <-> WorkbenchLayout <-> null`) in `HandlesNavigation::showInto()`, strict `$layout = null` standalone/kiosk guardrails in `Screen::getLayout()`, and automatic collapse of non-persistent auxiliary slots (`persist_on_navigate: false`) on `main` navigation.
- **VS Code-style `WorkbenchLayout`, `SidebarMenu`, `NavTree` & `StatusBar`**:
  - Added `Idei\Usim\Components\NavTree` (`UI::navTree()`, `js/components/nav-tree/index.js`, `css/components/nav-tree/index.css`) with inline collapsible accordions (`accordion(true)`), active screen highlighting, and automatic `52px` Activity Bar icon-only adaptation when inside a collapsed `Split` pane (auto-expanding the sidebar when clicking a group icon).
  - Added `Idei\Usim\Navigation\Contracts\MenuTargetInterface`, `MenuBuilder::renderNavTree()`, and `MenuBuilder::populateNavTree()` so existing `MenuProviderInterface` providers render transparently into either `MenuDropdown` or `NavTree`.
  - Added `MenuDropdown::floating(true)` with viewport-portaled (`document.body`, `position: fixed`) popovers (`right-top`, `right-bottom`, `top-left`, `top-right`) and viewport-clamped nested submenus so menus inside `Split` panes and `StatusBar` never clip.
  - Added `config/usim.php` / `UsimConfig` keys: `guest_layout`, `default_sidebar_menu_screen`, and `default_status_bar_screen`.
  - Added `WorkbenchLayout` + `workbench-layout.layout.yaml` (and package stubs), `SidebarMenu` (brand header, `NavTree`, user account + settings floating popovers with theme toggle & `🌐` language submenu), `StatusBar` (organizational unit switcher left, `UsimEvent('status_bar_message')` center, dev-only `R` reset button right omitted in `production`), shared `HandlesShellActions` trait, and `RightPanelDemo` + `RightPanelInspector` showcase.
  - Added comprehensive AI/LLM technical documentation in `packages/idei/usim/docs/WORKBENCH_AND_UNIFIED_LAYOUTS_AI_REFERENCE.md` and updated `usim_context_prompt.md`, `LAYOUTS_COMPOSITION_GUIDE.md`, `docs/README.md`, and `README.md`.
  - Added end-to-end feature test suite `WorkbenchLayoutAndNavigationTest`.

### Changed
- Removed hardcoded `#reset-btn` from `packages/idei/usim/resources/views/app.blade.php` (dev reset now lives exclusively in `StatusBar` and is omitted when `app()->isProduction()`, keeping standalone/kiosk screens and production builds clean).
- Removed legacy hardcoded container ID `104921240` and unused `loadMenuUI()` from `ui-renderer.js`.
- Scoped `Split` pane container styles in `css/components/split/index.css` to direct children (`.ui-split-pane > .ui-container`) so embedded screen cards preserve borders, padding, and sizing.

### Fixed
- Fixed `ItemList` row icon rendering (`resources/assets/js/components/item-list/index.js` and `css/components/item-list/index.css`):
  - Row icons (`item.icon`) now resolve dynamically via `helpers.createIconElement` into standard SVG (`span.ui-icon-svg`) or Unicode fallback elements rather than outputting raw icon name strings as plain text.
  - Sized `.usim-item-list-row-icon` proportionally (`1.2em`) with muted color and primary accent when selected, and added infinite spin animation (`usimComponentSpin`) for `loader` and `refresh` row icons.
  - Updated `createIconElement()` in `content-render.js` to preserve `data-usim-icon-name` attribute on SVG icons for icon-name-targeted CSS rules.
  - Added `loader.svg`, `alert-circle.svg`, and `check-circle.svg` standard vector icons in `resources/icons/` along with Unicode fallback definitions (`loader: '⏳'`, `'alert-circle': '⚠️'`, `'check-circle': '✅'`).

### Added
- Capability-based Service Wiring for `ItemList`:
  - Added `Idei\Usim\DTOs\ListItemDTO` (`make()`, `fromArray()`, `toArray()`, `jsonSerialize()`) to type visual row presentation (`id`, `label`, `subtitle`, `icon`, `badge`, `badgeStyle`, `style` via `Idei\Usim\Enums\Style`, `disabled`, `editable`, `deletable`, `meta`) while keeping selection state solely on `ItemList`, plus semantic `.style-*` row styling in `UsimItemListComponent` and `css/components/item-list/index.css`.
  - Added segregated contracts under `Idei\Usim\Contracts\ItemList`: `Listable` (returning `Collection<int, ListItemDTO>`), `Creatable` (returning `ListItemDTO`), `Updatable` (returning `?ListItemDTO`), `Deletable`, and `BulkDeletable`.
  - Added `ItemList::service(object|string $service)`, `getService()`, `serviceFeedback()`, `onServiceMutated()`, `useServiceDeleteHandlers()`, `serviceActionFor()`, `supportsServiceOperation()`, `queryServiceItems()`, `refreshFromService()`, `handleServiceAction()`, `performServiceDelete()`, and `performServiceBulkDelete()`.
  - Automatically infers CRUD flags and default internal service actions (`__usim_item_list_service__:<target>:<op>`) from the contracts implemented by the bound service, without clobbering explicit PHP or `.layout.yaml` overrides.
  - Added priority ordering for the `service` key in `ContainerLayoutLoader::orderCustomRuleProperties()`.
  - Added `ScreenLifecycleOrchestratorInterface::supportsComponentHandler()` and unified component-bound service resolution inside `ScreenLifecycleOrchestrator::handleAction()`, `ScreenLifecycleOrchestrator::handleQuery()`, `UIDataController`, `UIEventController`, and `Screen::callAction()`.
  - Added `SessionMovieCatalogService` (`App\Services\Movie\SessionMovieCatalogService`) implementing all five `ItemList` contracts with session-isolated demo data, updated `ItemListDemo` / `item-list-demo.layout.yaml` / demo translations, and documented the feature in `packages/idei/usim/docs/ITEM_LIST_SERVICE_WIRING.md` and `ItemListServiceWiringTest`.
- Agnostic Icon System (`Idei\Usim\Concerns\HasIcon`, `Idei\Usim\Support\UsimIconRegistry`) supporting semantic icon names, `BackedEnum`, emojis, or explicit SVG/PNG URLs across `Button`, `NewLabel`, `Label`, and `Table` cell buttons.
- Standard publishable library of **48 SVG icons** (`packages/idei/usim/resources/icons/`) via `php artisan vendor:publish --tag=usim-icons` (`public/vendor/idei/usim/icons/`), plus user-defined overrides/custom icons in `public/images/icons/`, optional `-light`/`-dark` theme variants, and `usim.icons.paths` / `usim.icons.aliases` configuration in `config/usim.php`.
- Automatic client theme, `style`, `variant`, and `ContainerAppearance` (`plain`, `subtle`, `card`, `elevated`, `highlight`, `contrast`) adaptation via CSS `mask-image` + `currentColor` (`1.2em` proportional sizing), automatic *icon-only* detection on `Button` when `label` is empty, and Unicode fallback when the icon pack is not published.
- Framework icon documentation in `packages/idei/usim/docs/ICONS_GUIDE.md` and `IconSystemTest`.

### Removed (Breaking)
- Removed `iconColor()`, `iconSize()`, and `iconOnly()` builder methods (and `icon_color`, `icon_size`, `icon_only` serialized keys) from `Button`, `NewLabel`, and `Label` to preserve client agnosticism and centralize styling in themes and container appearances.

### Added (Earlier Unreleased)
- Declarative YAML Container Layouts (`.layout.yaml`) via `Idei\Usim\Support\ContainerLayoutLoader`, `Container::layoutFile(?string $path = null)`, `Container::layoutDefinition(array $definition)`, and `Container::layout('file.layout.yaml')`:
  - Supports CSS Grid auto-flow definitions (`grid.cols`, `grid.rows`, `grid.gap`, `grid.row_gap`, `grid.column_gap`, `grid.padding`, `grid.max_width`, `grid.appearance`, `grid.default_span`, `grid.align`, `grid.justify`) and ordered `grid.children` slot rules (`slot`, `span`, `at`, `row` / `row_height`, `align`, `justify`), plus Flex (`flex`) and Stack (`vertical` / `horizontal`) layouts.
  - Automatically computes `grid_template_rows` when any child slot defines a row track (`row: 1fr`, `row_height: 120px`, etc.) and stretches `Textarea` bodies inside flexible grid rows.
  - Automatically mounts unparented `Screen` component properties referenced in `grid.children` slots during `HandlesScreenProperties::reconcileComponentProperties()`.
- Explicit Layout Slot API on `UIComponent` and `Container`:
  - Added `->slot(int|string|null $slot)` and `->getSlot(): int|string|null` on `UIComponent` and `Container`.
  - Updated `Container::add()` with an optional `$slot` parameter (`$container->add($element, slot: 'slot_name')`) and added `Container::addToSlot($slot, $element)` (supporting both argument orders).
  - Updated `Container::reorderChildrenByNames()` and `ContainerLayoutLoader::applyGridChildrenConfig()` to resolve slot placement and rules by priority: explicit `getSlot()` (by slot name or 1-based position) -> component `getName()` -> insertion order `1..N`.
- Slot-aware Screen Embedding (`Container::embed()`, `Container::embedToSlot()`, `Screen::embedInto()`):
  - `Container::embed(string $screenClass, array $params = [], int|string|null $slot = null)` and `Container::embedToSlot($slot, $screenClass, $params)` allow embedding multiple nested `Screen` instances into specific layout slots of a single `Container`.
  - Added `Container::clearSlot(int|string $slot)` and `Container::getChildOrder(int|string $elementId)`: when embedding into a specific `$slot`, only the previous occupant of that slot is removed (emitting `parent => null` for clean DOM unmounting on hot swaps), preserving sibling components and other embedded screens, re-applying the parent's layout definition, and persisting the embedded screen's snapshot with its resolved slot/grid configuration.
- Grid item placement helpers on `UIComponent` and `Container`: `gridColumn()`, `gridRow()`, `gridArea()`, `columnSpan()`, `rowSpan()`, `justifySelf()`, and `alignSelf()`, along with `justify_self` and `align_self` rendering in `ui-renderer.js`.
- Updated `FormDemo` (`App\UI\Screens\Demo\FormDemo` and `app/UI/Screens/Demo/form-demo.layout.yaml`) to use declarative YAML grid layout and added a `btn_reset` (`onResetForm`) action next to `btn_submit`.
- New `ItemList` component (`Idei\Usim\Components\ItemList`, `UI::itemList()` / `UI::list()`, `UsimItemListComponent`, and `css/components/item-list/index.css`) for vertical interactive item lists with:
  - Selection modes via `SelectionMode` (`NONE`, `SINGLE`, `MULTIPLE`), "Select All" support, and automatic hidden input synchronization (`name="field[]"` / `name="field"`) for form submission via `UsimButtonComponent`.
  - Live search (`searchable()`, `minSearchChars()`, `debounce()`) and asynchronous data querying over `GET /api/ui-data` (`action()`, `parameters()`, `autoFetch()`, `reload()`) with non-intrusive body loading spinner (`.ui-component-spinner`) that preserves search input focus while typing and pins previously selected items (`keepSelectedVisible()`).
  - Built-in CRUD support (`creatable()`, `editable()`, `deletable()`, `crud()`) with inline quick-create/inline-edit modes as well as delegated modal events (`onCreate()`, `onUpdate()`, `onEdit()`, `onDelete()`, `onBulkDelete()`), plus server-side item mutation helpers (`addItem()`, `updateItem()`, `removeItem()`, `removeItems()`).
- New `ItemListDemo` screen (`App\UI\Screens\Demo\ItemListDemo`) showcasing the Movies catalog with live `/api/ui-data` search, dynamic `SelectionMode` switching, inline CRUD, optional `ConfirmDialog` deletion flow, and form value collection.
- Strongly-typed parameter injection and automatic rehydration for Screen lifecycle methods: `buildBaseUI(Container $container, ...)` and `postLoadUI(...)` can now declare strongly-typed parameters (including Eloquent models, PHP 8.1+ `BackedEnum`s, native scalars `int`, `float`, `string`, `bool`, and `array`), container-provided dependencies (`Container $container`), or nullable/default values. Subclasses are no longer constrained by PHP LSP contravariance rules on abstract declarations. Parameters are automatically resolved from in-memory navigation parameters, URL query strings, or incoming state via `MethodParameterResolver`.
- Eloquent Model and Enum parameter normalization for browser navigation: `Screen::normalizeParamsForUrl()`, `navigate()`, `show()`, `showInto()`, and modal helpers now automatically serialize Eloquent models (`$model->getKey()`) and `BackedEnum` instances into scalar query parameters for browser URLs while preserving rich object instances in-memory during lifecycle dispatch.
- URL query string propagation in UI interactions: `POST /api/ui-event` (actions) and `GET /api/ui-data` (queries) now automatically attach the browser's active URL query parameters (`window.location.search` / `window.QUERY_PARAMS`). Handlers can inspect query parameters through `$request->query('param')` in `UIRequest`, `$this->query('param')` on `Screen`, or `$this->getQueryParams()`. Reserved internal routing parameters (`component_id`, `action`, `parameters`, `storage`) are isolated and stripped from `$queryParams`.
- Automatic Method Parameter Resolution and Type-Hydration for Screen Action (`on*()`) and Query (`get*()`) methods via `Idei\Usim\Support\MethodParameterResolver`. Handlers can declare strongly-typed parameters (`int`, `float`, `string`, `bool`, `array`), PHP 8.1+ BackedEnums (`tryFrom()`), Eloquent models (resolving model instances by `{param}_id` or `id`), container dependencies, or `UIRequest` objects with automatic coercion, default values, and nullable support. Missing required parameters return structured HTTP 422 JSON validation errors. 100% backward compatible with legacy `array $params` signatures and parameterless methods.
- Backend-generated `Image` placeholders with optional `AspectRatio`, caption, colors, stable IDs, a local SVG generator, a Picsum seeded generator, and an extensible `ImagePlaceholderGeneratorInterface`.
- `UIDataController` and route `GET /api/ui-data` for read-only component data queries, decoupling component data retrieval from the full UI diff engine (CQRS read channel).
- Screen query methods with `get*` prefix (`getUserData()`, `getImage()`, etc.) returning polymorphic payloads: JSON (`array`, `Collection`, `stdClass`), plain text, HTML, SVG, and binary/streaming media (images, audio, video, `BinaryFileResponse`, `StreamedResponse`).
- Read-only lifecycle orchestration via `ScreenLifecycleOrchestrator::handleQuery()` and `Screen::handleQuery()`, enforcing UI state integrity with `UsimReadOnlyLifecycleException` (HTTP 422) if UI component mutation is attempted during GET.
- Frontend data fetching methods in `UIComponent`: `fetchData()`, `getDataUrl()`, `setLoading()`, and automatic fetch cancellation via `AbortController` on unmount.
- Visual loading states with CSS class `.ui-is-loading`, `.ui-component-spinner` and animation keyframe `@keyframes usimComponentSpin`.
- New `Image` component (`Idei\Usim\Components\Image`, `UI::image()`, `UsimImageComponent`) with automatic fetch, loading spinner overlay, fallback support, and responsive styling.
- New `ImageDemo` screen (`App\UI\Screens\Demo\ImageDemo`) displaying public image API integration via `getImage()`.
- `usim:install` now recursively publishes all files under `stubs/resources` into the consuming application's `resources` directory while preserving their relative paths and existing files.
- Promoted `triggerAction()` to base class `UIComponent` (`ui-renderer.js`), enabling all components to dispatch actions via `sendUiEvent` and `applyUiUpdate` with fallback to `sendEventToBackend`, preventing method omission errors across custom components.
- Support for `X-UI-Client-Id` request header in `UIStateManager::getOrCreateClientId()`, prioritizing per-tab UUIDs over the shared `ui_client_id` cookie to isolate UI state trees in the backend cache.
- Per-tab authentication isolation in `PrepareUIContext`: when requests provide `X-UI-Client-Id` without a valid `store_token`, the web guard user is cleared (`clearWebGuardUser()`) and resolved as guest, preventing cross-tab session leakage from shared browser session cookies (`laravel_session`).
- Added `Device::isPaired(): bool` contract to `App\Models\Device` and package stubs, establishing pairing symmetry where pairing $\equiv$ login and unpairing $\equiv$ logout.
- Declared `protected string $store_token = '';` on `KioskScreen` and stub to preserve `sessionStorage` device authentication token across screen reloads and polling ticks.

### Changed
- `Image::storage()` now resolves relative source filenames through USIM's upload URL helper while leaving absolute image sources unchanged.
- `Uploader::aspect()` now requires an `AspectRatio` enum case and serializes its backing string to `aspect_ratio`.
- Unified published package configuration into `config/usim.php` (includes `users.roles`), replacing the previous split publish target (`config/ui-services.php` + `config/users.php`) for new installs.
- Runtime config resolution now uses `usim.*` keys only; legacy `ui-services.*` fallbacks were removed.
- Migrated web client storage from `localStorage` to `sessionStorage` (`ui-renderer.js`, `components/storage`, `components/shared/ui-event`, `components/uploader`), isolating `X-USIM-Storage` and `store_*` variables per browser tab.
- `Screen::getStorageVariables()` now safely collects nullable typed primitive properties (`?string`, `?int`, etc.) without throwing uninitialized property errors.
- `Screen::checkAccess()` and `PrepareUIContext` now enforce active device pairing: when an authenticated device is unpaired or tokens are deleted, the session guard (`device`) is terminated (`forgetUser()` and `loggedOut = true`), immediately returning a redirect response to `/device/device-pairing-screen`.
- `Screen::showInto()` and `AbstractLayout::showInto()`: passing `force: true` now automatically purges the target screen's cached snapshot (via `clearCachedScreenSnapshot()`) and state variables in `UIStateRepository`, ensuring fresh UI reconstruction without manual `Screen::make()->clearCachedScreenSnapshot()` boilerplate.

### Fixed
- Fixed initial rendering of hidden components: `applyCommonAttributes()` in `ui-renderer.js` now evaluates `this.config.visible === false` and applies `display: none !important`, ensuring containers and components configured with `->visible(false)` remain hidden on initial DOM mount without being overridden by structural layout CSS classes (`.ui-container.vertical`, etc.). Also synchronized dynamic visibility updates in `updateComponent()` and `UsimContainerComponent.update()` to properly restore layout styles (`grid`, `flex`, or default) when visibility is toggled back to `true`.
- Fixed stale screen data during navigation with cached snapshots: `ScreenLifecycleOrchestrator::embedInto()` and `render()` now execute `$screen->callPostLoadUI($params)` forwarding navigation parameters and effective query strings even when the screen tree is restored from cache, ensuring dynamic component data is refreshed for the target entity.
- Fixed loss of target URL and query parameters during unauthenticated screen redirection: `HandlesAuthorization::checkAccess()` now captures the intended URI and query string (stripping internal framework parameters `reset` and `parent`) into `session(['url.intended' => $intendedUrl])`. Upon successful authentication, `Login::onLoggedUser()` retrieves the intended target via `$this->query('redirect') ?? session()->pull('url.intended')`, validates against open redirects, and safely navigates to the originally requested destination.
- Fixed stale layout and menu snapshots on page reload (F5): dynamic slot rehydration in `ScreenLifecycleOrchestrator`, decoupled slot mounting in `AbstractLayout`, and cache invalidation via `UIStateManager::clearClientSnapshots()` on authentication boundary changes (login/logout).
- Fixed parameter loss in Screen navigation and embedding: `$params` passed via `$this->showInto($targetScreen, params: $params)` or `Screen::embedInto($screen, $container, $params)` and `Container::embed($screen, $params)` are now forwarded through `ScreenLifecycleOrchestrator::embedInto()`, properly providing `buildParams` to `initializeEventContext()` and `buildBaseUI($container, ...$params)`. Non-empty parameters also purge any stale cached screen snapshot and avoid skipping reload when the screen class is currently active.
- Action handler resolution (`Screen::actionToMethodName()`, `UIEventController::resolveActionHandler()`, `UsimEventDispatcher`, and `HandlesModals`): normalized action names prefixed with `on_` (e.g. `on_plain_saved` -> `onPlainSaved`), preventing redundant double `on` prefixing (`onOnPlainSaved`) and false HTTP 404 "Action not implemented" errors when action handlers are registered with an `on_` prefix.
- `UsimTextareaComponent` (`js/components/textarea/index.js`): implemented `triggerAction()` and parameter builder (`buildActionParameters()`), fixing `Uncaught TypeError: this.triggerAction is not a function` when emitting `onChange` (on blur) and debounced `onInput` events to the backend.
- `Container::reindexChild()` (`src/Components/Container.php`): preserved array key insertion order when reindexing a child component during property reconciliation (`reconcileComponentProperties()`), preventing auto-named components (such as an unnamed `UI::textarea()`) from being shifted to the end of the container instead of maintaining their declared position.
- `UI::textarea()` (`src/UI.php`): updated declared return type from `UIComponent` to `Textarea` and imported `Idei\Usim\Components\Textarea`, resolving static analysis warnings and restoring full IDE autocomplete and chaining for textarea-specific builder methods (`label()`, `placeholder()`, `onChange()`, etc.).
- `Uploader::aspect()` (`src/Components/Uploader.php`): accepted `AspectRatio|string` to maintain backwards compatibility with string aspect ratio values (e.g. `'16:9'`).
- Fixed image URL diff resolution in `ScreenLifecycleOrchestrator::buildDiffResponse()`: dirty component properties now prioritize transformed values present in `$newUI` rather than injecting raw configuration values, preventing relative storage paths (`Image::storage()`) from overwriting canonical URLs during reactive events.
- Preserved `Image::storage()` directory configuration across UI tree cache serialization and deserialization via internal `_storage_path`.
- Toast position styles now apply to the toast container rather than each toast, keeping `top-middle` centered; centered positions also remain centered on mobile.
- `stubs/services/Auth/RegisterService.php.stub`: users registering with only the default pending role (`usim.default_registering_role`, e.g. `registered`) are now placed in the `lobby` unit instead of `main` when no explicit `$unit` is given. Previously they landed in `main`, so the lobby-cleanup logic (removing the `registered` role and detaching `lobby`) never triggered once an admin later assigned them a role in an operational unit, leaving `registered` stuck on the user.
- Fixed static analysis warning in `PrepareUIContext::clearWebGuardUser()` by validating `Illuminate\Auth\SessionGuard` before calling `forgetUser()`.
- Fixed kiosk polling session persistence after device unpairing: background ticks (`carousel_tick`) now detect revoked credentials and redirect immediately instead of looping indefinitely on invalid session state.

## [v0.12.0] - 2026-04-30

### Added
- New landing translation stub files for scaffolded apps: `stubs/lang/en/landing.php` and `stubs/lang/es/landing.php`.
- Initial localized copy keys for the package landing experience, including hero badge/lead, CTA labels, feature highlights, and quick-start checklist entries.

### Changed
- `UIEventController` now defers processing of `UsimEvent` listeners while a screen action handler is executing, then flushes queued events after `finalizeEventContext()` to preserve deterministic event ordering in the same request.
- `UsimEventDispatcher` now separates enqueue and drain responsibilities and adds explicit deferred-processing controls (`beginDeferredProcessing()`, `endDeferredProcessing()`, `flushQueuedEvents()`).
- The generic landing stub (`stubs/views/landing.blade.php`) was redesigned into a more professional, responsive first-run page with a two-column layout, polished visual styling, and better onboarding content hierarchy.
- The landing stub now uses i18n keys (`t('landing.*')`) instead of hardcoded strings so generated projects render localized content out of the box.
- `stubs/screens/Menu.php.stub` now uses a more robust generic language-switch flow: it ignores no-op language changes, updates language/menu state coherently, emits `UsimEvent('reset_screen')`, and redirects while preserving the current route/query context.

### Fixed
- Prevented nested/dispatched-in-handler events (for example `reset_screen` emitted from `onChangeLang`) from executing before the parent screen handler completes its event context finalization.
- Added request-state reset safeguards in the deferred event flow to avoid stale static dispatcher state in persistent worker runtimes.

## [v0.11.0] - 2026-04-27

### Added
- New `Split` component (`UI::split()`) with `horizontal()`/`vertical()` orientation, draggable divider, collapsible panel support, configurable first-panel size (`splitSize`) and divider thickness (`splitterSize`).
- Registered `split` type end-to-end: PHP component class, `UI::split()` factory, `Screen::mapTypeToClass()` mapping, modular frontend renderer (`js/components/split/index.js`) and component styles (`css/components/split/index.css`).
- New **Tabs component** for `Container` (`tabs()`, `tabItem()`, `activeTab()`, `onTabChange()`, `onTabClose()`, `tabColors()`), including disabled tabs, closable tabs, and per-tab color overrides.
- `Container` now supports tabbed layouts through `tabs()`, `tabItem()`, `activeTab()`, `onTabChange()`, `onTabClose()` and `tabColors()`.
- `Container::add()` now accepts `tab:` as a named argument, so child components can be assigned to tabs by id or visible name while preserving the existing incremental JSON tree.

### Changed
- The modular `container` frontend renderer now mounts child components into tab panels, respects theme tokens by default, and supports per-tab colors, disabled tabs, and closable tabs.
- Tabbed container styles were modularized to `css/components/container/index.css`, and tab visual polish was improved (active tab continuity with panel + consistent tab height for closable/non-closable tabs).
- **Tab configuration simplified**: `id` field is now the sole unique identifier for tabs; redundant `name` field removed. Backend events (`tabs_on_change`, `tabs_on_close`) now send only `tab_id` parameter.
- `Container::tabs()` now also supports associative tab definitions keyed by id (for example: `['overview' => ['label' => 'Resumen']]`) while preserving compatibility with the existing list format.

## [v0.10.0] - 2026-04-26

### Added
- **Textarea component**: new `Textarea` builder (`UI::textarea()`) with plain-text and markdown modes.
- Textarea supports configurable `width`, `height`, `maxLength` (or unlimited), `placeholder`, `label`, `required`, `disabled`, `readonly`, `error`, `helpText`, `onChange`, `onInput`, and `debounce`.
- Markdown mode includes split-view editor with live preview and formatting toolbar (bold, italic, strikethrough, inline code, headings H1-H3, bullet list, numbered list, blockquote, code block, links, and horizontal rule).
- Added Textarea visual customization API: `borderColor()`, `borderWidth()`, `borderRadius()`, and `backgroundColor()`.
- Added contextual help indicator on textarea labels: when `helpText()` is defined, a help icon appears next to the label and shows a tooltip on hover.
- Bundled `marked.min.js` v9 as static package asset for markdown rendering.
- Registered `textarea` type end-to-end: PHP component class, `UI::textarea()` factory, `Screen::mapTypeToClass()` mapping, modular frontend renderer (`js/components/textarea/index.js`) and component styles (`css/components/textarea/index.css`).

### Changed
- **Frontend renderer refactor**: completed migration to modular component architecture (`js/components/<component>/index.js` and `css/components/<component>/index.css`) with registry-first resolution via `window.USIM_COMPONENTS`.
- Simplified `ComponentFactory.create()` to registry-first lookup with fallback warning, removing legacy switch-case paths.
- Removed legacy monolithic component bodies and duplicate top-level legacy assets after modular migration.
- Expanded shared frontend helpers (`shared/ui-event.js`, `shared/content-render.js`) and refactored modular components to reuse them, reducing duplicated render/event boilerplate.
- `ui-renderer.js` reduced significantly in size after extracting component ownership to modular files.

### Fixed
- **Protocol/response optimization**: removed redundant `_id` from USIM JSON payloads. Backend and frontend no longer emit/consume `_id`; component identity is resolved using structural diff keys and canonical `id` where applicable.
- Textarea default visual behavior is now theme-compatible when border/background overrides are not provided, aligning with existing form-control tokens.


## [0.7.0] - 2026-03-28

### Added
- `Container` appearance API: `appearance(string $appearance)`, `card()`, and `plain()` fluent methods to control container visual style; default appearance is `card`.
- Carousel and calendar components now consume CSS theme tokens for consistent light/dark styling.

### Changed
- `ConfirmDialog` and `RegisterDialog` scaffolding updated to use `plain()` container appearance.
- Admin UsersManager stub significantly refined: improved user feedback handling, clearer table layout, and role management flow.
- `UserService` stub enhanced with stronger field validation and role management logic.
- All auth screen stubs (`Login`, `EmailVerified`, `ForgotPassword`, `ResetPassword`) updated to use `plain()` container appearance for a consistent look.
- `EditUserDialog` and `Admin\UsersManager` stubs updated to use `plain()` appearance.

### Fixed
- `Screen` now always persists the container state after `postLoadUI()`, including on `?reset=true` reloads, preventing stale cache snapshots.
- Checkbox `checked` state now syncs correctly in the UI renderer when the server sends incremental changes.

## [0.6.0] - 2026-03-26

### Added
- Theme-switching support in the backend/frontend contract via `Screen::changeTheme()` and UI renderer handling for light/dark mode updates.
- Persistent theme tokens and assets for light/dark mode (`resources/assets/css/ui-theme-tokens.css`, `resources/assets/images/theme-icon-*.svg`).
- New package landing view stub with dynamic theme-aware styling (`stubs/views/landing.blade.php`).
- Rich label HTML rendering through `Label::html()`, including safe rendering of existing backend Blade views into label content.
- New positioning helpers for components and containers, including anchor-based positioning and offset APIs such as `position()`, `positionMode()`, `offsetX()`, `offsetY()`, and directional helpers like `top()` / `right()` / `bottom()` / `left()`.
- Card theme variants and theme assets for light/dark UI toggles in the default package UI.
- New `front_store_key` package configuration (from `FRONT_STORE_KEY`) to scope client storage keys per application.

### Changed
- Default USIM styles now rely more heavily on CSS variables for more consistent theming across renderer components and package-provided screens.
- Package screen scaffolding was refreshed with simplified layouts, updated button styling, theme toggle support, and richer label rendering for auth and home flows.
- Storage variables declared with the `store_` prefix are now serialized in plain JSON by default instead of being encrypted as a full opaque payload.
- Sensitive persisted values must now opt in to protection by using the `_crypt` suffix, for example `store_token_crypt` or `store_password_crypt`.
- The storage contract now supports finer-grained client synchronization, allowing clients to inspect plain values such as `store_theme` and update local storage using incremental changes instead of replacing the entire encrypted blob on every response.
- `PrepareUIContext` and `UIChangesCollector` were aligned with the new storage contract to simplify state handling and incremental updates.

### Fixed
- UI styling now accepts numeric shadow values in refreshed screen scaffolding without requiring string-only shadow configuration.

## [0.5.0] - 2026-03-20

### Added
- Email verification and password reset Blade view stubs (`stubs/views/emails/verify-email.blade.php`, `stubs/views/emails/reset-password.blade.php`).
- Terms and conditions Blade view stub (`stubs/views/terms.blade.php`) with a migration stub `add_terms_accepted_at_to_users_table.php.stub` that adds a nullable `terms_accepted_at` datetime column to the users table.
- `EventServiceProvider` stub (`stubs/providers/EventServiceProvider.php.stub`) for app-level event/listener registration.
- `ClassModifier` utility now exposes four new static methods: `addTraitToClass()`, `addInterface()`, `addPropertyArrayValue()`, and `addCast()` — enabling programmatic, AST-based modification of any PHP class during installation.
- `Screen` component lookup helpers: `findComponentAs()` and `findRootComponentAs()` for typed retrieval of child components.
- Test scaffolding stubs: `stubs/tests/Support/usim_bootstrap.php.stub` and `stubs/tests/Traits/UsimTestHelpers.php.stub`.
- Default access provisioning now runs from `usim:install`, which upserts roles, permissions, users, and languages from configuration.
- New required composer dependencies: `nikic/php-parser: ^5.7`, `symfony/var-dumper: ^6.0|^7.0`, `illuminate/contracts: ^10.0|^11.0|^12.0`.
- `UserService` stub emits email verification and profile update events for UI synchronization.
- UI renderer (`ui-renderer.js`) now supports comprehensive CSS grid properties (`grid-template-columns/rows/areas`, `grid-auto-columns/rows/flow`) and flex layout properties (`flex-direction`, `flex-wrap`), as well as background image/size/position styling.

### Changed
- **Breaking:** Seeder stubs renamed from `RoleSeeder` → `UsimRoleSeeder` and `UserSeeder` → `UsimUserSeeder` to avoid class-name collisions in consumer projects.
- The installer now provisions the default root/admin/user access data directly from configuration and no longer depends on package seeder stubs.
- User model stub now uses the `UsimUser` trait for password reset and email verification notifications instead of inline method overrides.
- User model stub adds `terms_accepted_at` to `$fillable` and `$casts` (as `datetime`).
- `usim:install` now programmatically modifies the consumer's `User` model (adds traits, interfaces, properties, casts) via `ClassModifier` instead of overwriting the file.
- `usim:install` post-install guidance now reflects config-driven provisioning and migration-first installation.
- API auth routes installation commented out in `InstallCommand` pending a dedicated auth-routes refactor.

### Removed
- Seeder scaffolding and installer references for `UsimSeeder`, `UsimRoleSeeder`, and `UsimUserSeeder`.

## [0.4.0] - 2026-03-15

### Added
- Admin UsersManager screen stub (`App\UI\Screens\Admin\UsersManager`) with full user management: search, paginated user table, create, edit, and delete users with role assignment.
- `EditUserDialog` modal component stub for inline user editing from the Admin UsersManager.
- `UserTableModel` data table component stub (`App\UI\Components\DataTable`) for paginated, searchable user lists backed by `UserService`.
- `upload_disk` configuration key (`config/ui-services.php`) to set the filesystem disk used for uploads. Defaults to `local`; override via `UPLOAD_DISK` env variable.
- `UPLOAD_DISK=local` entry added to the published `.env` template.

### Changed
- `usim:install` now exposes a single unified installation flow; the `minimal` preset and `--preset` option were removed.
- `usim:install` now scaffolds the Admin UsersManager screen in `App\UI\Screens\Admin` alongside the core Home, Menu, and auth screens.
- Menu scaffold updated to show a link to the Admin UsersManager for authenticated users.
- `UserService` stub significantly expanded with `findUser`, `getUser`, `updateUser`, and `createUser` methods including field validation, role management, and email notification handling.
- `UploadController` and `UploadService` now use the configurable `upload_disk` key instead of the hardcoded `uploads` disk name.

### Removed
- `MenuMinimal.php.stub` — removed alongside the minimal preset.

## [0.3.2] - 2026-03-13

### Fixed
- USIM routes now always include `PrepareUIContext` middleware from the package to avoid host-app bootstrap coupling.
- Initial UI loads now normalize missing or invalid storage payloads to an empty array, preventing `UIChangesCollector::setStorage()` type errors.
- `usim:install` now disables the default Laravel `/` welcome route when adding the USIM catch-all route to avoid route conflicts.

## [0.3.1] - 2026-03-13

### Fixed
- Package CI stub lint now validates only `*.php.stub` files and replaces template placeholders before running `php -l`.

## [0.3.0] - 2026-03-13

### Added
- Service-layer scaffolding stubs for auth and user flows: `App\Services\Auth\AuthSessionService`, `LoginService`, `RegisterService`, `PasswordService`, and `App\Services\User\UserService`.
- Test scaffolding stubs under `stubs/tests` including `Pest.php`, `TestCase.php`, UI test support helpers, and feature test templates for home/menu, login, password recovery, and auth event contracts.
- Full preset installer publishing for test scaffolding files into the consumer `tests/` directory.

### Changed
- Refreshed core scaffolding stubs to match the current working app architecture: `Home`, `Menu`, auth screens, `AuthController`, `User` model, and web catch-all route stubs.
- Installer full preset now publishes service stubs before auth screens to keep generated code dependencies coherent.
- Seeder scaffolding was removed; default access provisioning is handled by `usim:install`.
- User model scaffolding no longer injects the `UsimUser` trait automatically; default model stub now uses explicit notification methods for reset/verification flows.

## [0.2.0] - 2026-03-12

### Added
- `usim:install` command with `minimal` and `full` presets.
- Scaffolding stubs for screens, auth screens, modals, routes, config, and migrations.
- `Idei\Usim\Traits\UsimUser` trait for user notification integration.
- Carousel, uploader, calendar, image crop editor, and expanded table/form UI components.
- Upload handling, temporary upload cleanup job, and package routes/controllers for UI and upload flows.
- Package CI workflow, release checklist, release automation script, and consumer upgrade guide.
- Comprehensive package README, testing guide, contract docs, and mobile client templates.

### Changed
- `UIEventController` now extends `Illuminate\Routing\Controller` to avoid app-level namespace coupling.
- Installer `.env` handling now appends only missing keys to avoid duplicated environment variables.
- Demo mode handling now relies on environment configuration for local testing.
- Table pagination and loading-state behavior were improved in `Table` and the UI renderer.
- Package metadata, dependencies, and public release structure were updated for Packagist distribution.

### Fixed
- Request-scoped `UIChangesCollector` reset to stabilize UI scenario event flow.
- Dynamic internal URL/port handling for notifications and verification flows.
- Installer test coverage and generated screen validation for `Menu.php` and related scaffolding outputs.
