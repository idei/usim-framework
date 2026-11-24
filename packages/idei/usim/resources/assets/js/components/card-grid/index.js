/**
 * CardGrid component (modular implementation)
 *
 * Responsive 2D card grid with:
 * - Client-side lightweight standardized card rendering (no raw innerHTML)
 * - Asynchronous server querying via GET /api/ui-data (CardListable / Listable / Screen query handler)
 * - Client-side fallback for static item arrays
 * - Integrated toolbar: debounced search, declarative filters (select, multi_select, toggle, chips),
 *   sort selector + direction toggle, active filter chips, and optional layout toggle
 * - Pagination modes: classic numbered 'pages' and incremental 'load_more'
 * - Skeleton loading cards, empty / no-results / error states
 * - Card click and per-card actions (up to max_visible_actions + automatic ⋮ overflow menu)
 */
class UsimCardGridComponent extends UIComponent {
    constructor(id, config) {
        super(id, config);

        this._items = [];
        this._pagination = this._normalizePagination(config?.pagination);
        this._searchTerm = typeof config?.search_term === 'string' ? config.search_term : '';
        this._sortBy = typeof config?.sort_by === 'string' ? config.sort_by : '';
        this._sortDirection = config?.sort_direction === 'desc' ? 'desc' : 'asc';
        this._activeFilters = this._cloneFilters(config?.active_filters);
        this._cardLayout = config?.card_layout === 'horizontal' ? 'horizontal' : 'vertical';

        this._isFetching = false;
        this._isAppending = false;
        this._hasError = false;
        this._debounceTimer = null;
        this._mobileFiltersOpen = false;
        this._openPopoverKey = null;
        this._openOverflowCardKey = null;
        this._lastReloadToken = Number(config?.reload_token ?? 0);
        this._lastPatchSeq = Number(config?.card_patches?.seq ?? 0);
        this._hasFetchedOnce = false;
        this._staticAllItems = [];

        this.wrapperElement = null;
        this.headerElement = null;
        this.toolbarElement = null;
        this.gridHostElement = null;
        this.paginationElement = null;
        this.searchInputElement = null;
        this.liveRegionElement = null;

        this._boundDocumentClick = this._handleDocumentClick.bind(this);
        this._boundDocumentKeydown = this._handleDocumentKeydown.bind(this);

        this._syncStateFromConfig(this.config, { initial: true });
    }

    // ─── Normalization ────────────────────────────────────────────────────────

    _normalizeStyleToken(value) {
        const normalized = String(value || 'default').toLowerCase().trim();
        const allowed = ['default', 'primary', 'secondary', 'success', 'danger', 'warning', 'info', 'muted'];
        return allowed.includes(normalized) ? normalized : 'default';
    }

    _normalizePagination(raw) {
        const p = raw && typeof raw === 'object' ? raw : {};
        const perPage = Math.max(1, Number(p.per_page) || 12);
        const page = Math.max(1, Number(p.page) || 1);
        const total = p.total === null ? null : Math.max(0, Number(p.total) || 0);
        const totalPages = Number.isFinite(Number(p.total_pages))
            ? Math.max(0, Number(p.total_pages))
            : (total !== null && total > 0 ? Math.ceil(total / perPage) : 0);
        const hasMore = p.has_more !== undefined
            ? Boolean(p.has_more)
            : (total !== null ? (page * perPage) < total : false);

        const options = Array.isArray(p.per_page_options) && p.per_page_options.length > 0
            ? p.per_page_options.map((v) => Math.max(1, Number(v) || 12))
            : [12, 24, 36, 48];

        return {
            mode: p.mode === 'load_more' ? 'load_more' : 'pages',
            page,
            per_page: perPage,
            per_page_options: Array.from(new Set(options)),
            show_per_page: p.show_per_page !== false,
            total,
            total_pages: totalPages,
            has_more: hasMore,
            can_prev: p.can_prev !== undefined ? Boolean(p.can_prev) : page > 1,
            can_next: p.can_next !== undefined ? Boolean(p.can_next) : hasMore,
        };
    }

    _cloneFilters(raw) {
        if (!raw || typeof raw !== 'object') return {};
        const out = {};
        Object.entries(raw).forEach(([key, value]) => {
            if (value === null || value === undefined || value === '' || value === false) return;
            if (Array.isArray(value)) {
                const clean = value.filter((v) => v !== null && v !== undefined && v !== '').map(String);
                if (clean.length > 0) out[key] = clean;
            } else {
                out[key] = value;
            }
        });
        return out;
    }

    _normalizeItem(raw, fallbackIdx = 0) {
        if (!raw || typeof raw !== 'object') {
            const title = raw !== null && raw !== undefined ? String(raw) : String(fallbackIdx);
            return { id: fallbackIdx, title, style: 'default', badge_style: 'default', metric_style: 'default', actions: [], attributes: [], tags: [] };
        }

        const rawId = raw.id ?? raw.value ?? raw._model_id ?? fallbackIdx;
        const id = (typeof rawId === 'number' || typeof rawId === 'string') ? rawId : String(fallbackIdx);
        const title = String(raw.title ?? raw.label ?? raw.name ?? id);

        const attributes = Array.isArray(raw.attributes)
            ? raw.attributes.filter((a) => a && typeof a === 'object').map((a) => ({
                label: String(a.label ?? ''),
                value: String(a.value ?? ''),
                icon: a.icon ? String(a.icon) : null,
            }))
            : [];

        const tags = Array.isArray(raw.tags)
            ? raw.tags.map((t) => String(t).trim()).filter(Boolean)
            : [];

        const actions = Array.isArray(raw.actions)
            ? raw.actions.filter((a) => a && typeof a === 'object').map((a) => ({
                label: String(a.label ?? ''),
                action: a.action ? String(a.action) : null,
                parameters: a.parameters && typeof a.parameters === 'object' ? a.parameters : {},
                url: a.url ? String(a.url) : null,
                target: a.target ? String(a.target) : '_self',
                icon: a.icon ? String(a.icon) : null,
                style: this._normalizeStyleToken(a.style),
                variant: ['solid', 'outline', 'ghost'].includes(String(a.variant)) ? String(a.variant) : 'outline',
                disabled: Boolean(a.disabled),
                overflow: Boolean(a.overflow),
                tooltip: a.tooltip ? String(a.tooltip) : null,
            }))
            : [];

        return {
            ...raw,
            id,
            title,
            subtitle: raw.subtitle ? String(raw.subtitle) : null,
            eyebrow: raw.eyebrow ? String(raw.eyebrow) : null,
            description: raw.description ? String(raw.description) : null,
            image: raw.image ? String(raw.image) : null,
            image_alt: raw.image_alt ? String(raw.image_alt) : title,
            image_fit: raw.image_fit === 'contain' ? 'contain' : 'cover',
            aspect_ratio: raw.aspect_ratio ? String(raw.aspect_ratio) : null,
            icon: raw.icon ? String(raw.icon) : null,
            avatar: raw.avatar ? String(raw.avatar) : null,
            badge: raw.badge !== null && raw.badge !== undefined && raw.badge !== '' ? String(raw.badge) : null,
            badge_style: this._normalizeStyleToken(raw.badge_style),
            metric: raw.metric !== null && raw.metric !== undefined && raw.metric !== '' ? String(raw.metric) : null,
            metric_label: raw.metric_label ? String(raw.metric_label) : null,
            metric_style: this._normalizeStyleToken(raw.metric_style),
            attributes,
            tags,
            footer_text: raw.footer_text ? String(raw.footer_text) : null,
            actions,
            click_action: raw.click_action ? String(raw.click_action) : null,
            click_parameters: raw.click_parameters && typeof raw.click_parameters === 'object' ? raw.click_parameters : {},
            url: raw.url ? String(raw.url) : null,
            target: raw.target ? String(raw.target) : '_self',
            style: this._normalizeStyleToken(raw.style),
            featured: Boolean(raw.featured),
            disabled: Boolean(raw.disabled),
            accent_color: raw.accent_color ? String(raw.accent_color) : null,
        };
    }

    _normalizeItems(rawItems) {
        if (!Array.isArray(rawItems)) return [];
        return rawItems.map((item, idx) => this._normalizeItem(item, idx));
    }

    _syncStateFromConfig(cfg = this.config, options = {}) {
        const normalizedItems = this._normalizeItems(cfg?.items || []);
        this._items = normalizedItems;
        if (!cfg?.action) {
            this._staticAllItems = normalizedItems;
        }

        this._pagination = this._normalizePagination(cfg?.pagination);
        if (options.initial || cfg?.card_layout) {
            this._cardLayout = cfg?.card_layout === 'horizontal' ? 'horizontal' : 'vertical';
        }
        if (options.initial) {
            this._searchTerm = typeof cfg?.search_term === 'string' ? cfg.search_term : '';
            this._sortBy = typeof cfg?.sort_by === 'string' ? cfg.sort_by : '';
            this._sortDirection = cfg?.sort_direction === 'desc' ? 'desc' : 'asc';
            this._activeFilters = this._cloneFilters(cfg?.active_filters);
        }
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    render() {
        const wrapper = document.createElement('div');
        wrapper.className = this._getWrapperClasses();
        this.wrapperElement = wrapper;

        this.headerElement = document.createElement('div');
        this.headerElement.className = 'usim-cg-header';

        this.toolbarElement = document.createElement('div');
        this.toolbarElement.className = 'usim-cg-toolbar';

        this.liveRegionElement = document.createElement('div');
        this.liveRegionElement.setAttribute('aria-live', 'polite');
        this.liveRegionElement.style.position = 'absolute';
        this.liveRegionElement.style.width = '1px';
        this.liveRegionElement.style.height = '1px';
        this.liveRegionElement.style.overflow = 'hidden';
        this.liveRegionElement.style.clip = 'rect(0 0 0 0)';

        this.gridHostElement = document.createElement('div');
        this.gridHostElement.className = 'usim-cg-body';

        this.paginationElement = document.createElement('div');
        this.paginationElement.className = 'usim-cg-pagination-host';

        wrapper.appendChild(this.liveRegionElement);
        wrapper.appendChild(this.headerElement);
        wrapper.appendChild(this.toolbarElement);
        wrapper.appendChild(this.gridHostElement);
        wrapper.appendChild(this.paginationElement);

        this._renderHeader();
        this._renderToolbar();
        this._renderGridArea();
        this._renderPagination();

        document.addEventListener('click', this._boundDocumentClick);
        document.addEventListener('keydown', this._boundDocumentKeydown);

        // If configured with remote action and no preloaded items, auto-fetch on mount
        if (this.config.action && this.config.auto_fetch !== false && this._items.length === 0) {
            setTimeout(() => {
                if (this.wrapperElement && !this._hasFetchedOnce) {
                    this.loadPage(1, { append: false });
                }
            }, 0);
        }

        return this.applyCommonAttributes(wrapper);
    }

    update(newConfig) {
        const prevReloadToken = this._lastReloadToken;
        const prevMode = this._pagination.mode;

        const incomingPatches = newConfig?.card_patches;
        const hasNewPatches = Boolean(
            incomingPatches
            && typeof incomingPatches === 'object'
            && Number(incomingPatches.seq ?? 0) > this._lastPatchSeq
            && Array.isArray(incomingPatches.ops)
            && incomingPatches.ops.length > 0
        );

        const nonPatchKeys = Object.keys(newConfig || {}).filter(
            (k) => k !== 'card_patches' && k !== '_order' && k !== 'type'
        );

        this.config = { ...this.config, ...newConfig };
        this.applyCommonAttributes(this.wrapperElement);

        // Fast surgical path: when only card_patches arrived, update only the affected card DOM nodes
        // without touching the toolbar, other cards, scroll position, or firing GET /api/ui-data.
        if (hasNewPatches && nonPatchKeys.length === 0) {
            this._lastPatchSeq = Number(incomingPatches.seq);
            this._applyCardPatches(incomingPatches.ops);
            return;
        }

        if (newConfig.card_layout !== undefined) {
            this._cardLayout = newConfig.card_layout === 'horizontal' ? 'horizontal' : 'vertical';
        }
        if (newConfig.search_term !== undefined) {
            this._searchTerm = typeof newConfig.search_term === 'string' ? newConfig.search_term : '';
        }
        if (newConfig.sort_by !== undefined) {
            this._sortBy = typeof newConfig.sort_by === 'string' ? newConfig.sort_by : '';
        }
        if (newConfig.sort_direction !== undefined) {
            this._sortDirection = newConfig.sort_direction === 'desc' ? 'desc' : 'asc';
        }
        if (newConfig.active_filters !== undefined) {
            this._activeFilters = this._cloneFilters(newConfig.active_filters);
        }
        if (newConfig.pagination !== undefined) {
            this._pagination = this._normalizePagination({ ...this._pagination, ...newConfig.pagination });
        }
        if (Array.isArray(newConfig.items)) {
            this._items = this._normalizeItems(newConfig.items);
            if (!this.config.action) {
                this._staticAllItems = this._items;
            }
        }

        if (this.wrapperElement) {
            this.wrapperElement.className = this._getWrapperClasses();
        }

        const nextReloadToken = Number(this.config.reload_token ?? prevReloadToken);
        const modeChanged = prevMode !== this._pagination.mode;

        this._renderHeader();
        this._renderToolbar();
        this._renderGridArea();
        this._renderPagination();

        if (hasNewPatches) {
            this._lastPatchSeq = Number(incomingPatches.seq);
            this._applyCardPatches(incomingPatches.ops);
        }

        if (nextReloadToken !== prevReloadToken && this.config.action) {
            this._lastReloadToken = nextReloadToken;
            const targetPage = modeChanged ? 1 : this._pagination.page;
            this.loadPage(targetPage, { append: false, silentSkeleton: this._items.length > 0 });
        } else if (modeChanged && this.config.action) {
            this.loadPage(1, { append: false, silentSkeleton: this._items.length > 0 });
        }
    }

    /**
     * Apply surgical per-card DOM patches (patch, replace, remove, prepend) without
     * re-fetching GET /api/ui-data or rebuilding unrelated cards.
     */
    _applyCardPatches(ops) {
        if (!Array.isArray(ops) || ops.length === 0) return;

        let countChanged = false;

        ops.forEach((op) => {
            if (!op || typeof op !== 'object') return;

            if (op.op === 'patch' || op.op === 'replace') {
                const targetKey = String(op.id);
                const idx = this._items.findIndex((it) => String(it.id) === targetKey);
                if (idx === -1) return;

                const existing = this._items[idx];
                const updated = op.op === 'replace'
                    ? this._normalizeItem(op.data, idx)
                    : this._normalizeItem({ ...existing, ...(op.data || {}), id: existing.id }, idx);

                this._items[idx] = updated;

                if (!this.config.action && this._staticAllItems.length > 0) {
                    const staticIdx = this._staticAllItems.findIndex((it) => String(it.id) === targetKey);
                    if (staticIdx !== -1) {
                        this._staticAllItems[staticIdx] = updated;
                    }
                }

                this._replaceCardDomNode(targetKey, existing, updated);
            } else if (op.op === 'remove') {
                const targetKey = String(op.id);
                const prevLen = this._items.length;
                this._items = this._items.filter((it) => String(it.id) !== targetKey);

                if (!this.config.action) {
                    this._staticAllItems = this._staticAllItems.filter((it) => String(it.id) !== targetKey);
                }

                if (this._items.length !== prevLen) {
                    countChanged = true;
                    if (this._pagination.total !== null) {
                        this._pagination.total = Math.max(0, this._pagination.total - 1);
                        this._pagination.total_pages = this._pagination.total > 0
                            ? Math.ceil(this._pagination.total / this._pagination.per_page)
                            : 0;
                    }
                }

                const existingNode = this._findCardDomNode(targetKey);
                if (existingNode) {
                    existingNode.remove();
                }

                if (this._items.length === 0) {
                    this._renderGridArea();
                }
            } else if (op.op === 'prepend' && op.data) {
                const newCard = this._normalizeItem(op.data, 0);
                const newKey = String(newCard.id);
                this._items = [newCard, ...this._items.filter((it) => String(it.id) !== newKey)];

                if (!this.config.action) {
                    this._staticAllItems = [newCard, ...this._staticAllItems.filter((it) => String(it.id) !== newKey)];
                }

                countChanged = true;
                if (this._pagination.total !== null) {
                    this._pagination.total += 1;
                    this._pagination.total_pages = Math.ceil(this._pagination.total / this._pagination.per_page);
                }

                const grid = this.gridHostElement?.querySelector?.('.usim-cg-grid');
                if (grid) {
                    const existingDup = this._findCardDomNode(newKey);
                    if (existingDup) existingDup.remove();
                    grid.prepend(this._buildCardElement(newCard));
                } else {
                    this._renderGridArea();
                }
            }
        });

        if (countChanged) {
            this._renderHeader();
            this._renderPagination();
        }
    }

    _findCardDomNode(cardKey) {
        if (!this.gridHostElement) return null;
        const nodes = this.gridHostElement.querySelectorAll('.usim-cg-card');
        for (const node of nodes) {
            if (node.dataset.cardId === cardKey) {
                return node;
            }
        }
        return null;
    }

    _replaceCardDomNode(cardKey, previousItem, updatedItem) {
        const oldNode = this._findCardDomNode(cardKey);
        if (!oldNode) return;

        const newNode = this._buildCardElement(updatedItem);

        // Reuse the existing <img> element when the image URL is unchanged to avoid visual flicker/re-decoding
        if (previousItem.image && updatedItem.image && previousItem.image === updatedItem.image) {
            const oldImg = oldNode.querySelector('.usim-cg-media img');
            const newImg = newNode.querySelector('.usim-cg-media img');
            if (oldImg && newImg) {
                newImg.replaceWith(oldImg);
            }
        }

        oldNode.replaceWith(newNode);
    }

    destroy() {
        super.destroy();
        if (this._debounceTimer) {
            clearTimeout(this._debounceTimer);
            this._debounceTimer = null;
        }
        document.removeEventListener('click', this._boundDocumentClick);
        document.removeEventListener('keydown', this._boundDocumentKeydown);
    }

    _getWrapperClasses() {
        const variant = ['elevated', 'outlined', 'flat'].includes(this.config.variant) ? this.config.variant : 'elevated';
        const density = ['compact', 'comfortable', 'spacious'].includes(this.config.density) ? this.config.density : 'comfortable';
        const layout = this._cardLayout === 'horizontal' ? 'horizontal' : 'vertical';
        const classes = ['usim-cg-wrapper', `variant-${variant}`, `density-${density}`, `layout-${layout}`];
        if (this.config.disabled) classes.push('is-disabled');
        return classes.join(' ');
    }

    // ─── Document Listeners (popovers & overflow menus) ───────────────────────

    _handleDocumentClick(event) {
        if (!this.wrapperElement) return;
        const target = event.target;

        if (this._openPopoverKey !== null) {
            const insidePopover = target?.closest?.('.usim-cg-multiselect');
            if (!insidePopover || insidePopover.dataset.filterName !== this._openPopoverKey) {
                this._openPopoverKey = null;
                this._renderToolbar();
            }
        }

        if (this._openOverflowCardKey !== null) {
            const insideOverflow = target?.closest?.('.usim-cg-overflow-wrap');
            if (!insideOverflow || insideOverflow.dataset.cardKey !== this._openOverflowCardKey) {
                this._openOverflowCardKey = null;
                this._closeAllOverflowMenus();
            }
        }
    }

    _handleDocumentKeydown(event) {
        if (event.key !== 'Escape') return;
        let changed = false;
        if (this._openPopoverKey !== null) {
            this._openPopoverKey = null;
            this._renderToolbar();
            changed = true;
        }
        if (this._openOverflowCardKey !== null) {
            this._openOverflowCardKey = null;
            this._closeAllOverflowMenus();
            changed = true;
        }
        if (changed) {
            event.stopPropagation();
        }
    }

    _closeAllOverflowMenus() {
        if (!this.gridHostElement) return;
        this.gridHostElement.querySelectorAll('.usim-cg-overflow-menu').forEach((menu) => menu.remove());
    }

    // ─── Header Rendering ─────────────────────────────────────────────────────

    _renderHeader() {
        if (!this.headerElement) return;
        this.headerElement.textContent = '';

        const hasTitle = Boolean(this.config.title);
        const hasSubtitle = Boolean(this.config.subtitle);
        const showCount = this.config.show_count !== false;
        const headerAction = this.config.header_action && typeof this.config.header_action === 'object'
            ? this.config.header_action
            : null;

        if (!hasTitle && !hasSubtitle && !headerAction) {
            this.headerElement.style.display = 'none';
            return;
        }

        this.headerElement.style.display = '';

        const left = document.createElement('div');
        left.className = 'usim-cg-header-text';

        if (hasTitle || showCount) {
            const titleRow = document.createElement('div');
            titleRow.className = 'usim-cg-title-row';

            if (hasTitle) {
                const h = document.createElement('h3');
                h.className = 'usim-cg-title';
                h.textContent = String(this.config.title);
                titleRow.appendChild(h);
            }

            if (showCount) {
                const count = document.createElement('span');
                count.className = 'usim-cg-count-badge';
                const total = this._getEffectiveTotal();
                count.textContent = total === 1 ? '1 resultado' : `${total} resultados`;
                titleRow.appendChild(count);
            }

            left.appendChild(titleRow);
        }

        if (hasSubtitle) {
            const sub = document.createElement('p');
            sub.className = 'usim-cg-subtitle';
            sub.textContent = String(this.config.subtitle);
            left.appendChild(sub);
        }

        this.headerElement.appendChild(left);

        if (headerAction && headerAction.label) {
            const right = document.createElement('div');
            right.className = 'usim-cg-header-actions';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `usim-cg-action-btn variant-solid style-${this._normalizeStyleToken(headerAction.style || 'primary')}`;
            if (headerAction.icon) {
                btn.appendChild(this._createIconNode(headerAction.icon, 15));
            }
            const labelSpan = document.createElement('span');
            labelSpan.textContent = String(headerAction.label);
            btn.appendChild(labelSpan);

            btn.addEventListener('click', () => {
                if (headerAction.action) {
                    this.sendEventToBackend('click', headerAction.action, headerAction.parameters || {});
                }
            });

            right.appendChild(btn);
            this.headerElement.appendChild(right);
        }
    }

    // ─── Integrated Toolbar Rendering ─────────────────────────────────────────

    _renderToolbar() {
        if (!this.toolbarElement) return;

        const searchable = this.config.searchable !== false;
        const filters = Array.isArray(this.config.filters) ? this.config.filters : [];
        const sortOptions = Array.isArray(this.config.sort_options) ? this.config.sort_options : [];
        const showLayoutToggle = Boolean(this.config.show_layout_toggle);

        if (!searchable && filters.length === 0 && sortOptions.length === 0 && !showLayoutToggle) {
            this.toolbarElement.style.display = 'none';
            return;
        }

        // Preserve focus & cursor inside search input if currently typing
        const hadSearchFocus = this.searchInputElement && document.activeElement === this.searchInputElement;
        const selectionStart = hadSearchFocus ? this.searchInputElement.selectionStart : null;
        const selectionEnd = hadSearchFocus ? this.searchInputElement.selectionEnd : null;

        this.toolbarElement.style.display = '';
        this.toolbarElement.textContent = '';

        const mainRow = document.createElement('div');
        mainRow.className = 'usim-cg-toolbar-main';

        // 1. Search Input
        if (searchable) {
            const searchBox = document.createElement('div');
            searchBox.className = 'usim-cg-search-box';

            const iconSpan = document.createElement('span');
            iconSpan.className = 'usim-cg-search-icon';
            iconSpan.appendChild(this._createIconNode('search', 15));
            searchBox.appendChild(iconSpan);

            const input = document.createElement('input');
            input.type = 'search';
            input.className = 'usim-cg-search-input';
            input.placeholder = String(this.config.search_placeholder || 'Buscar...');
            input.value = this._searchTerm;
            input.setAttribute('aria-label', input.placeholder);

            input.addEventListener('input', (e) => {
                this._searchTerm = e.target.value;
                this._onSearchChanged(false);
            });

            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this._onSearchChanged(true);
                } else if (e.key === 'Escape' && this._searchTerm !== '') {
                    e.preventDefault();
                    this._searchTerm = '';
                    input.value = '';
                    this._onSearchChanged(true);
                }
            });

            this.searchInputElement = input;
            searchBox.appendChild(input);

            if (this._isFetching && !this._isAppending) {
                const spinner = document.createElement('span');
                spinner.className = 'usim-cg-search-spinner';
                spinner.setAttribute('aria-hidden', 'true');
                searchBox.appendChild(spinner);
            } else if (this._searchTerm !== '') {
                const clearBtn = document.createElement('button');
                clearBtn.type = 'button';
                clearBtn.className = 'usim-cg-search-clear';
                clearBtn.textContent = '×';
                clearBtn.title = 'Limpiar búsqueda';
                clearBtn.setAttribute('aria-label', 'Limpiar búsqueda');
                clearBtn.addEventListener('click', () => {
                    this._searchTerm = '';
                    this._onSearchChanged(true);
                    this.searchInputElement?.focus();
                });
                searchBox.appendChild(clearBtn);
            }

            mainRow.appendChild(searchBox);
        } else {
            this.searchInputElement = null;
        }

        // Separate inline filters vs chips filters
        const inlineFilters = filters.filter((f) => f.type !== 'chips');
        const chipsFilters = filters.filter((f) => f.type === 'chips');
        const activeFilterCount = Object.keys(this._activeFilters).length;

        // Mobile Filters Collapse Trigger
        if (filters.length > 0) {
            const mobileBtn = document.createElement('button');
            mobileBtn.type = 'button';
            mobileBtn.className = `usim-cg-mobile-filters-btn${activeFilterCount > 0 ? ' has-active' : ''}`;
            mobileBtn.appendChild(this._createIconNode('filter', 14));
            const lbl = document.createElement('span');
            lbl.textContent = activeFilterCount > 0
                ? `${this.config.filters_label || 'Filtros'} (${activeFilterCount})`
                : String(this.config.filters_label || 'Filtros');
            mobileBtn.appendChild(lbl);
            mobileBtn.addEventListener('click', () => {
                this._mobileFiltersOpen = !this._mobileFiltersOpen;
                this._renderToolbar();
            });
            mainRow.appendChild(mobileBtn);
        }

        // 2. Inline Filters (select, multi_select, toggle)
        if (inlineFilters.length > 0) {
            const filtersBar = document.createElement('div');
            filtersBar.className = `usim-cg-filters-bar usim-cg-filters-collapsible${this._mobileFiltersOpen ? ' is-open' : ''}`;

            inlineFilters.forEach((filterDef) => {
                const ctrl = this._buildInlineFilterControl(filterDef);
                if (ctrl) filtersBar.appendChild(ctrl);
            });

            mainRow.appendChild(filtersBar);
        }

        // 3. Right Side: Sort Controls + Layout Toggle
        if (sortOptions.length > 0 || showLayoutToggle) {
            const rightGroup = document.createElement('div');
            rightGroup.className = 'usim-cg-toolbar-right';

            if (sortOptions.length > 0) {
                const sortGroup = document.createElement('div');
                sortGroup.className = 'usim-cg-sort-group';

                const sortSelect = document.createElement('select');
                sortSelect.className = 'usim-cg-sort-select';
                sortSelect.setAttribute('aria-label', String(this.config.sort_label || 'Ordenar'));

                const defaultOpt = document.createElement('option');
                defaultOpt.value = '';
                defaultOpt.textContent = `${this.config.sort_label || 'Ordenar'}...`;
                sortSelect.appendChild(defaultOpt);

                sortOptions.forEach((opt) => {
                    const o = document.createElement('option');
                    o.value = String(opt.field);
                    o.textContent = String(opt.label);
                    if (this._sortBy === String(opt.field)) {
                        o.selected = true;
                    }
                    sortSelect.appendChild(o);
                });

                sortSelect.addEventListener('change', (e) => {
                    this._sortBy = e.target.value;
                    this._onQueryCriteriaChanged();
                });

                sortGroup.appendChild(sortSelect);

                const dirBtn = document.createElement('button');
                dirBtn.type = 'button';
                dirBtn.className = 'usim-cg-icon-btn';
                dirBtn.textContent = this._sortDirection === 'desc' ? '↓' : '↑';
                dirBtn.title = this._sortDirection === 'desc' ? 'Orden descendente' : 'Orden ascendente';
                dirBtn.setAttribute('aria-label', dirBtn.title);
                dirBtn.addEventListener('click', () => {
                    this._sortDirection = this._sortDirection === 'desc' ? 'asc' : 'desc';
                    this._onQueryCriteriaChanged();
                });

                sortGroup.appendChild(dirBtn);
                rightGroup.appendChild(sortGroup);
            }

            if (showLayoutToggle) {
                const toggleWrap = document.createElement('div');
                toggleWrap.className = 'usim-cg-layout-toggle';

                const vertBtn = document.createElement('button');
                vertBtn.type = 'button';
                vertBtn.className = `usim-cg-icon-btn${this._cardLayout === 'vertical' ? ' is-active' : ''}`;
                vertBtn.title = 'Vista de tarjetas';
                vertBtn.setAttribute('aria-label', 'Vista de tarjetas');
                vertBtn.appendChild(this._createIconNode('grid', 15));
                vertBtn.addEventListener('click', () => {
                    if (this._cardLayout !== 'vertical') {
                        this._cardLayout = 'vertical';
                        this.wrapperElement.className = this._getWrapperClasses();
                        this._renderToolbar();
                    }
                });

                const horizBtn = document.createElement('button');
                horizBtn.type = 'button';
                horizBtn.className = `usim-cg-icon-btn${this._cardLayout === 'horizontal' ? ' is-active' : ''}`;
                horizBtn.title = 'Vista horizontal';
                horizBtn.setAttribute('aria-label', 'Vista horizontal');
                horizBtn.appendChild(this._createIconNode('list', 15));
                horizBtn.addEventListener('click', () => {
                    if (this._cardLayout !== 'horizontal') {
                        this._cardLayout = 'horizontal';
                        this.wrapperElement.className = this._getWrapperClasses();
                        this._renderToolbar();
                    }
                });

                toggleWrap.appendChild(vertBtn);
                toggleWrap.appendChild(horizBtn);
                rightGroup.appendChild(toggleWrap);
            }

            mainRow.appendChild(rightGroup);
        }

        this.toolbarElement.appendChild(mainRow);

        // 4. Chips Filters Rows
        if (chipsFilters.length > 0) {
            const chipsContainer = document.createElement('div');
            chipsContainer.className = `usim-cg-filters-collapsible${this._mobileFiltersOpen ? ' is-open' : ''}`;
            chipsContainer.style.display = this._mobileFiltersOpen ? 'flex' : '';
            chipsContainer.style.flexDirection = 'column';
            chipsContainer.style.gap = '8px';

            chipsFilters.forEach((filterDef) => {
                const row = document.createElement('div');
                row.className = 'usim-cg-chips-filter-row';

                const lbl = document.createElement('span');
                lbl.className = 'usim-cg-chips-filter-label';
                lbl.textContent = `${filterDef.label}:`;
                row.appendChild(lbl);

                const currentVal = this._activeFilters[filterDef.name] ?? '';
                const options = Array.isArray(filterDef.options) ? filterDef.options : [];

                options.forEach((opt) => {
                    const chip = document.createElement('button');
                    chip.type = 'button';
                    const isActive = String(currentVal) === String(opt.value);
                    chip.className = `usim-cg-chip-btn${isActive ? ' is-active' : ''}`;
                    chip.textContent = String(opt.label);
                    chip.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    chip.addEventListener('click', () => {
                        if (isActive) {
                            delete this._activeFilters[filterDef.name];
                        } else {
                            this._activeFilters[filterDef.name] = String(opt.value);
                        }
                        this._onQueryCriteriaChanged();
                    });
                    row.appendChild(chip);
                });

                chipsContainer.appendChild(row);
            });

            this.toolbarElement.appendChild(chipsContainer);
        }

        // 5. Active Filter Chips Summary Row
        const activeChips = this._collectActiveFilterChips(filters);
        if (activeChips.length > 0) {
            const activeBar = document.createElement('div');
            activeBar.className = 'usim-cg-active-bar';

            activeChips.forEach((chipInfo) => {
                const pill = document.createElement('span');
                pill.className = 'usim-cg-active-chip';

                const text = document.createElement('span');
                text.textContent = `${chipInfo.label}: ${chipInfo.displayValue}`;
                pill.appendChild(text);

                const rm = document.createElement('button');
                rm.type = 'button';
                rm.className = 'usim-cg-active-chip-remove';
                rm.textContent = '×';
                rm.setAttribute('aria-label', `Quitar filtro ${chipInfo.label}`);
                rm.addEventListener('click', () => {
                    chipInfo.onRemove();
                    this._onQueryCriteriaChanged();
                });

                pill.appendChild(rm);
                activeBar.appendChild(pill);
            });

            const clearAll = document.createElement('button');
            clearAll.type = 'button';
            clearAll.className = 'usim-cg-clear-all-btn';
            clearAll.textContent = String(this.config.clear_filters_label || 'Limpiar filtros');
            clearAll.addEventListener('click', () => this._clearAllCriteria());
            activeBar.appendChild(clearAll);

            this.toolbarElement.appendChild(activeBar);
        }

        // Restore focus & selection range cleanly
        if (hadSearchFocus && this.searchInputElement) {
            this.searchInputElement.focus();
            if (selectionStart !== null && selectionEnd !== null) {
                try {
                    this.searchInputElement.setSelectionRange(selectionStart, selectionEnd);
                } catch (_e) {
                    // Ignore on unsupported input types
                }
            }
        }
    }

    _buildInlineFilterControl(filterDef) {
        const name = String(filterDef.name || '');
        const label = String(filterDef.label || name);
        const type = String(filterDef.type || 'select');

        if (type === 'select') {
            const select = document.createElement('select');
            const currentVal = this._activeFilters[name] ?? '';
            select.className = `usim-cg-filter-select${currentVal !== '' ? ' is-active' : ''}`;
            select.setAttribute('aria-label', label);

            const emptyOpt = document.createElement('option');
            emptyOpt.value = '';
            emptyOpt.textContent = String(filterDef.placeholder || `${label}: Todos`);
            select.appendChild(emptyOpt);

            (Array.isArray(filterDef.options) ? filterDef.options : []).forEach((opt) => {
                const o = document.createElement('option');
                o.value = String(opt.value);
                o.textContent = String(opt.label);
                if (String(currentVal) === String(opt.value)) {
                    o.selected = true;
                }
                select.appendChild(o);
            });

            select.addEventListener('change', (e) => {
                const val = e.target.value;
                if (val === '') {
                    delete this._activeFilters[name];
                } else {
                    this._activeFilters[name] = val;
                }
                this._onQueryCriteriaChanged();
            });

            return select;
        }

        if (type === 'multi_select') {
            const wrap = document.createElement('div');
            wrap.className = 'usim-cg-multiselect';
            wrap.dataset.filterName = name;

            const selectedValues = Array.isArray(this._activeFilters[name])
                ? this._activeFilters[name].map(String)
                : [];

            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = `usim-cg-multiselect-trigger${selectedValues.length > 0 ? ' is-active' : ''}`;
            trigger.setAttribute('aria-expanded', this._openPopoverKey === name ? 'true' : 'false');

            const lblSpan = document.createElement('span');
            lblSpan.textContent = String(filterDef.placeholder || label);
            trigger.appendChild(lblSpan);

            if (selectedValues.length > 0) {
                const badge = document.createElement('span');
                badge.className = 'usim-cg-multiselect-count';
                badge.textContent = String(selectedValues.length);
                trigger.appendChild(badge);
            }

            const caret = document.createElement('span');
            caret.textContent = '▾';
            caret.style.fontSize = '0.75rem';
            trigger.appendChild(caret);

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                this._openPopoverKey = this._openPopoverKey === name ? null : name;
                this._renderToolbar();
            });

            wrap.appendChild(trigger);

            if (this._openPopoverKey === name) {
                const popover = document.createElement('div');
                popover.className = 'usim-cg-multiselect-popover';
                popover.addEventListener('click', (e) => e.stopPropagation());

                (Array.isArray(filterDef.options) ? filterDef.options : []).forEach((opt) => {
                    const row = document.createElement('label');
                    row.className = 'usim-cg-multiselect-option';

                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    const optVal = String(opt.value);
                    cb.checked = selectedValues.includes(optVal);
                    cb.addEventListener('change', () => {
                        const next = new Set(selectedValues);
                        if (cb.checked) {
                            next.add(optVal);
                        } else {
                            next.delete(optVal);
                        }
                        if (next.size === 0) {
                            delete this._activeFilters[name];
                        } else {
                            this._activeFilters[name] = Array.from(next);
                        }
                        this._onQueryCriteriaChanged();
                    });

                    const txt = document.createElement('span');
                    txt.textContent = String(opt.label);

                    row.appendChild(cb);
                    row.appendChild(txt);
                    popover.appendChild(row);
                });

                wrap.appendChild(popover);
            }

            return wrap;
        }

        if (type === 'toggle') {
            const isActive = Boolean(this._activeFilters[name]);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `usim-cg-toggle-filter${isActive ? ' is-active' : ''}`;
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            btn.textContent = label;
            btn.addEventListener('click', () => {
                if (isActive) {
                    delete this._activeFilters[name];
                } else {
                    this._activeFilters[name] = true;
                }
                this._onQueryCriteriaChanged();
            });
            return btn;
        }

        return null;
    }

    _collectActiveFilterChips(filters) {
        const chips = [];
        const defMap = new Map();
        filters.forEach((f) => defMap.set(f.name, f));

        Object.entries(this._activeFilters).forEach(([key, value]) => {
            const def = defMap.get(key);
            const label = def ? def.label : key;

            if (Array.isArray(value)) {
                value.forEach((val) => {
                    const opt = def?.options?.find?.((o) => String(o.value) === String(val));
                    chips.push({
                        label,
                        displayValue: opt ? opt.label : String(val),
                        onRemove: () => {
                            const remaining = value.filter((v) => String(v) !== String(val));
                            if (remaining.length === 0) {
                                delete this._activeFilters[key];
                            } else {
                                this._activeFilters[key] = remaining;
                            }
                        },
                    });
                });
            } else if (typeof value === 'boolean') {
                if (value) {
                    chips.push({
                        label,
                        displayValue: 'Sí',
                        onRemove: () => { delete this._activeFilters[key]; },
                    });
                }
            } else {
                const opt = def?.options?.find?.((o) => String(o.value) === String(value));
                chips.push({
                    label,
                    displayValue: opt ? opt.label : String(value),
                    onRemove: () => { delete this._activeFilters[key]; },
                });
            }
        });

        return chips;
    }

    // ─── Grid & Cards Rendering ───────────────────────────────────────────────

    _renderGridArea() {
        if (!this.gridHostElement) return;
        this.gridHostElement.textContent = '';

        // 1. Min search chars prompt
        const minChars = Number(this.config.min_search_chars || 0);
        const trimmedSearch = this._searchTerm.trim();
        if (minChars > 0 && trimmedSearch.length > 0 && trimmedSearch.length < minChars) {
            const msg = String(this.config.min_chars_message || 'Escribe al menos :count caracteres para buscar...')
                .replace(':count', String(minChars));
            this.gridHostElement.appendChild(this._buildStateBox('🔎', msg));
            return;
        }

        // 2. Initial Loading Skeletons
        if ((this._isFetching || this.config.loading) && !this._isAppending && this._items.length === 0) {
            this.gridHostElement.appendChild(this._buildSkeletonGrid());
            return;
        }

        // 3. Error State
        if (this._hasError && this._items.length === 0) {
            this.gridHostElement.appendChild(this._buildStateBox(
                '⚠️',
                String(this.config.error_message || 'No se pudieron cargar los elementos'),
                {
                    label: String(this.config.retry_label || 'Reintentar'),
                    onClick: () => this.loadPage(this._pagination.page, { append: false }),
                }
            ));
            return;
        }

        // 4. Determine items to display (remote or client-side filtered/sorted/paginated)
        const visibleItems = this._getDisplayItems();

        // 5. Empty / No Results State
        if (visibleItems.length === 0) {
            const hasCriteria = trimmedSearch !== '' || Object.keys(this._activeFilters).length > 0;
            if (hasCriteria) {
                this.gridHostElement.appendChild(this._buildStateBox(
                    '🔍',
                    String(this.config.no_results_message || 'No se encontraron resultados con los criterios actuales'),
                    {
                        label: String(this.config.clear_filters_label || 'Limpiar filtros'),
                        onClick: () => this._clearAllCriteria(),
                    }
                ));
            } else {
                this.gridHostElement.appendChild(this._buildStateBox(
                    '🗂️',
                    String(this.config.empty_message || 'No hay elementos para mostrar')
                ));
            }
            return;
        }

        // 6. Responsive Card Grid
        const grid = document.createElement('div');
        grid.className = 'usim-cg-grid';
        grid.setAttribute('role', 'list');

        if (this.config.min_card_width) {
            grid.style.setProperty('--usim-cg-min-width', String(this.config.min_card_width));
        }
        if (this.config.gap) {
            grid.style.setProperty('--usim-cg-gap', String(this.config.gap));
        }
        if (Number(this.config.max_columns) > 0) {
            grid.classList.add('has-max-cols');
            grid.style.setProperty('--usim-cg-max-cols', String(Number(this.config.max_columns)));
        }
        if (this._isFetching && !this._isAppending) {
            grid.classList.add('is-refreshing');
        }

        visibleItems.forEach((item) => {
            grid.appendChild(this._buildCardElement(item));
        });

        this.gridHostElement.appendChild(grid);

        if (this.liveRegionElement) {
            const total = this._getEffectiveTotal();
            this.liveRegionElement.textContent = `${visibleItems.length} tarjetas visibles de ${total} resultados`;
        }
    }

    _buildSkeletonGrid() {
        const grid = document.createElement('div');
        grid.className = 'usim-cg-grid';
        grid.setAttribute('aria-busy', 'true');

        if (this.config.min_card_width) {
            grid.style.setProperty('--usim-cg-min-width', String(this.config.min_card_width));
        }
        if (this.config.gap) {
            grid.style.setProperty('--usim-cg-gap', String(this.config.gap));
        }
        if (Number(this.config.max_columns) > 0) {
            grid.classList.add('has-max-cols');
            grid.style.setProperty('--usim-cg-max-cols', String(Number(this.config.max_columns)));
        }

        const count = Math.max(1, Math.min(24, Number(this.config.skeleton_count) || 6));
        for (let i = 0; i < count; i++) {
            const sk = document.createElement('div');
            sk.className = 'usim-cg-skeleton-card';

            const media = document.createElement('div');
            media.className = 'usim-cg-skeleton-media usim-cg-shimmer';

            const body = document.createElement('div');
            body.className = 'usim-cg-skeleton-body';

            const l1 = document.createElement('div');
            l1.className = 'usim-cg-skeleton-line is-short usim-cg-shimmer';
            const l2 = document.createElement('div');
            l2.className = 'usim-cg-skeleton-line is-title usim-cg-shimmer';
            const l3 = document.createElement('div');
            l3.className = 'usim-cg-skeleton-line is-full usim-cg-shimmer';

            body.appendChild(l1);
            body.appendChild(l2);
            body.appendChild(l3);
            sk.appendChild(media);
            sk.appendChild(body);
            grid.appendChild(sk);
        }

        return grid;
    }

    _buildStateBox(iconText, message, cta = null) {
        const box = document.createElement('div');
        box.className = 'usim-cg-state-box';

        const icon = document.createElement('div');
        icon.className = 'usim-cg-state-icon';
        icon.textContent = iconText;
        box.appendChild(icon);

        const p = document.createElement('p');
        p.className = 'usim-cg-state-message';
        p.textContent = message;
        box.appendChild(p);

        if (cta && cta.label && typeof cta.onClick === 'function') {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'usim-cg-action-btn variant-outline style-primary';
            btn.textContent = cta.label;
            btn.addEventListener('click', cta.onClick);
            box.appendChild(btn);
        }

        return box;
    }

    _buildCardElement(item) {
        const card = document.createElement('article');
        const cardKey = String(item.id);
        const isClickable = !item.disabled && Boolean(item.url || item.click_action || this.config.on_card_click);

        const classes = ['usim-cg-card', `style-${item.style || 'default'}`];
        if (item.featured) classes.push('is-featured');
        if (item.disabled) classes.push('is-disabled');
        if (isClickable) classes.push('is-clickable');
        if (item.accent_color) {
            classes.push('has-accent');
            card.style.setProperty('--usim-cg-card-accent', item.accent_color);
        }

        card.className = classes.join(' ');
        card.setAttribute('role', 'listitem');
        card.dataset.cardId = cardKey;

        if (isClickable) {
            card.tabIndex = 0;
            card.addEventListener('click', () => this._handleCardClick(item));
            card.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this._handleCardClick(item);
                }
            });
        }

        const hasMedia = Boolean(item.image);

        // 1. Media Zone (image + optional overlay badge)
        if (hasMedia) {
            const media = document.createElement('div');
            media.className = 'usim-cg-media';
            media.style.aspectRatio = String(item.aspect_ratio || this.config.image_aspect_ratio || '16/9');

            const img = document.createElement('img');
            img.src = item.image;
            img.alt = item.image_alt || item.title;
            img.loading = 'lazy';
            img.style.objectFit = item.image_fit || 'cover';
            img.addEventListener('error', () => {
                img.remove();
                const fallback = document.createElement('div');
                fallback.className = 'usim-cg-media-fallback';
                if (item.icon) {
                    fallback.appendChild(this._createIconNode(item.icon, 34));
                } else {
                    fallback.textContent = item.title.slice(0, 2).toUpperCase();
                }
                media.appendChild(fallback);
            });
            media.appendChild(img);

            if (item.badge) {
                const badge = this._buildBadgeElement(item.badge, item.badge_style);
                badge.classList.add('usim-cg-media-badge');
                media.appendChild(badge);
            }

            card.appendChild(media);
        }

        // 2. Content Container
        const content = document.createElement('div');
        content.className = 'usim-cg-content';

        // Header Row (icon/avatar + eyebrow + title + subtitle + inline badge if no media)
        const header = document.createElement('div');
        header.className = 'usim-cg-card-header';

        if (!hasMedia && (item.avatar || item.icon)) {
            const visual = document.createElement('div');
            visual.className = `usim-cg-visual-badge${item.avatar ? ' is-avatar' : ''}`;
            if (item.avatar) {
                if (/^(https?:\/\/|\/|data:image\/)/i.test(item.avatar)) {
                    const avImg = document.createElement('img');
                    avImg.src = item.avatar;
                    avImg.alt = item.title;
                    avImg.loading = 'lazy';
                    visual.appendChild(avImg);
                } else {
                    visual.textContent = item.avatar;
                }
            } else if (item.icon) {
                visual.appendChild(this._createIconNode(item.icon, 20));
            }
            header.appendChild(visual);
        }

        const headerMeta = document.createElement('div');
        headerMeta.className = 'usim-cg-header-meta';

        if (item.eyebrow || (!hasMedia && item.badge)) {
            const eyebrowRow = document.createElement('div');
            eyebrowRow.className = 'usim-cg-eyebrow-row';

            const eyebrow = document.createElement('span');
            eyebrow.className = 'usim-cg-eyebrow';
            eyebrow.textContent = item.eyebrow || '';
            eyebrowRow.appendChild(eyebrow);

            if (!hasMedia && item.badge) {
                eyebrowRow.appendChild(this._buildBadgeElement(item.badge, item.badge_style));
            }

            headerMeta.appendChild(eyebrowRow);
        }

        const titleEl = document.createElement('h4');
        titleEl.className = 'usim-cg-card-title';
        titleEl.textContent = item.title;
        titleEl.title = item.title;
        headerMeta.appendChild(titleEl);

        if (item.subtitle) {
            const subEl = document.createElement('p');
            subEl.className = 'usim-cg-card-subtitle';
            subEl.textContent = item.subtitle;
            subEl.title = item.subtitle;
            headerMeta.appendChild(subEl);
        }

        header.appendChild(headerMeta);
        content.appendChild(header);

        // 3. Highlight Metric
        if (item.metric) {
            const metricRow = document.createElement('div');
            metricRow.className = 'usim-cg-metric-row';

            const val = document.createElement('span');
            val.className = `usim-cg-metric-value style-${item.metric_style || 'default'}`;
            val.textContent = item.metric;
            metricRow.appendChild(val);

            if (item.metric_label) {
                const lbl = document.createElement('span');
                lbl.className = 'usim-cg-metric-label';
                lbl.textContent = item.metric_label;
                metricRow.appendChild(lbl);
            }

            content.appendChild(metricRow);
        }

        // 4. Description
        if (item.description) {
            const desc = document.createElement('p');
            desc.className = 'usim-cg-description';
            desc.textContent = item.description;
            content.appendChild(desc);
        }

        // 5. Attributes
        if (item.attributes.length > 0) {
            const list = document.createElement('ul');
            list.className = 'usim-cg-attributes';

            item.attributes.forEach((attr) => {
                const li = document.createElement('li');
                li.className = 'usim-cg-attribute';
                if (attr.icon) {
                    li.appendChild(this._createIconNode(attr.icon, 13));
                }
                if (attr.label) {
                    const l = document.createElement('span');
                    l.className = 'usim-cg-attribute-label';
                    l.textContent = `${attr.label}:`;
                    li.appendChild(l);
                }
                const v = document.createElement('span');
                v.className = 'usim-cg-attribute-value';
                v.textContent = attr.value;
                li.appendChild(v);
                list.appendChild(li);
            });

            content.appendChild(list);
        }

        // 6. Tags (capped at max_visible_tags + "+N")
        if (item.tags.length > 0) {
            const maxTags = Math.max(0, Number(this.config.max_visible_tags ?? 3));
            const tagsWrap = document.createElement('div');
            tagsWrap.className = 'usim-cg-tags';

            const visibleTags = maxTags > 0 ? item.tags.slice(0, maxTags) : [];
            const hiddenTags = maxTags > 0 ? item.tags.slice(maxTags) : item.tags;

            visibleTags.forEach((tagText) => {
                const tag = document.createElement('span');
                tag.className = 'usim-cg-tag';
                tag.textContent = tagText;
                tagsWrap.appendChild(tag);
            });

            if (hiddenTags.length > 0) {
                const more = document.createElement('span');
                more.className = 'usim-cg-tag is-more';
                more.textContent = `+${hiddenTags.length}`;
                more.title = hiddenTags.join(', ');
                tagsWrap.appendChild(more);
            }

            content.appendChild(tagsWrap);
        }

        // 7. Footer & Per-Card Actions
        if (item.footer_text || item.actions.length > 0) {
            const footer = document.createElement('div');
            footer.className = 'usim-cg-footer';

            if (item.footer_text) {
                const ft = document.createElement('span');
                ft.className = 'usim-cg-footer-text';
                ft.textContent = item.footer_text;
                ft.title = item.footer_text;
                footer.appendChild(ft);
            }

            if (item.actions.length > 0) {
                footer.appendChild(this._buildCardActions(item, cardKey));
            }

            content.appendChild(footer);
        }

        card.appendChild(content);
        return card;
    }

    _buildBadgeElement(text, styleToken) {
        const badge = document.createElement('span');
        badge.className = `usim-cg-badge style-${this._normalizeStyleToken(styleToken)}`;
        badge.textContent = text;
        return badge;
    }

    _buildCardActions(item, cardKey) {
        const actionsWrap = document.createElement('div');
        actionsWrap.className = 'usim-cg-actions';

        const maxVisible = Math.max(0, Number(this.config.max_visible_actions ?? 2));
        const inlineActions = [];
        const overflowActions = [];

        item.actions.forEach((act) => {
            if (act.overflow || inlineActions.length >= maxVisible) {
                overflowActions.push(act);
            } else {
                inlineActions.push(act);
            }
        });

        inlineActions.forEach((act) => {
            actionsWrap.appendChild(this._buildInlineActionButton(act, item));
        });

        if (overflowActions.length > 0) {
            const overflowWrap = document.createElement('div');
            overflowWrap.className = 'usim-cg-overflow-wrap';
            overflowWrap.dataset.cardKey = cardKey;

            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'usim-cg-action-btn variant-ghost usim-cg-overflow-trigger';
            trigger.textContent = '⋮';
            trigger.title = 'Más acciones';
            trigger.setAttribute('aria-label', 'Más acciones');
            trigger.setAttribute('aria-haspopup', 'menu');

            trigger.addEventListener('click', (e) => {
                e.stopPropagation();
                const alreadyOpen = this._openOverflowCardKey === cardKey;
                this._closeAllOverflowMenus();
                if (alreadyOpen) {
                    this._openOverflowCardKey = null;
                    return;
                }

                this._openOverflowCardKey = cardKey;
                const menu = document.createElement('div');
                menu.className = 'usim-cg-overflow-menu';
                menu.setAttribute('role', 'menu');
                menu.addEventListener('click', (ev) => ev.stopPropagation());

                overflowActions.forEach((act) => {
                    const menuItem = act.url && !act.disabled
                        ? document.createElement('a')
                        : document.createElement('button');
                    menuItem.className = `usim-cg-overflow-item style-${act.style || 'default'}`;
                    menuItem.setAttribute('role', 'menuitem');

                    if (act.url && !act.disabled) {
                        menuItem.href = act.url;
                        menuItem.target = act.target || '_self';
                        if (act.target === '_blank') menuItem.rel = 'noopener noreferrer';
                    } else {
                        menuItem.type = 'button';
                        menuItem.disabled = Boolean(act.disabled);
                        menuItem.addEventListener('click', () => {
                            this._openOverflowCardKey = null;
                            this._closeAllOverflowMenus();
                            this._handleCardAction(act, item);
                        });
                    }

                    if (act.icon) {
                        menuItem.appendChild(this._createIconNode(act.icon, 14));
                    }
                    const lbl = document.createElement('span');
                    lbl.textContent = act.label;
                    menuItem.appendChild(lbl);
                    menu.appendChild(menuItem);
                });

                overflowWrap.appendChild(menu);
            });

            overflowWrap.appendChild(trigger);
            actionsWrap.appendChild(overflowWrap);
        }

        return actionsWrap;
    }

    _buildInlineActionButton(act, item) {
        const isLink = Boolean(act.url && !act.disabled);
        const el = document.createElement(isLink ? 'a' : 'button');
        el.className = `usim-cg-action-btn variant-${act.variant || 'outline'} style-${act.style || 'default'}`;
        if (act.tooltip) el.title = act.tooltip;

        if (isLink) {
            el.href = act.url;
            el.target = act.target || '_self';
            if (act.target === '_blank') el.rel = 'noopener noreferrer';
            el.addEventListener('click', (e) => e.stopPropagation());
        } else {
            el.type = 'button';
            el.disabled = Boolean(act.disabled);
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                this._handleCardAction(act, item);
            });
        }

        if (act.icon) {
            el.appendChild(this._createIconNode(act.icon, 14));
        }
        if (act.label) {
            const lbl = document.createElement('span');
            lbl.textContent = act.label;
            el.appendChild(lbl);
        }

        return el;
    }

    // ─── Pagination Rendering ─────────────────────────────────────────────────

    _renderPagination() {
        if (!this.paginationElement) return;
        this.paginationElement.textContent = '';

        const total = this._getEffectiveTotal();
        const visibleCount = this._getDisplayItems().length;

        if (visibleCount === 0 && !this._isFetching) {
            return;
        }

        const p = this._getEffectivePagination();

        if (p.mode === 'load_more') {
            const wrap = document.createElement('div');
            wrap.className = 'usim-cg-load-more-wrap';

            const info = document.createElement('div');
            info.className = 'usim-cg-pagination-info';
            info.textContent = p.total !== null
                ? `Mostrando ${visibleCount} de ${p.total}`
                : `Mostrando ${visibleCount} elementos`;
            wrap.appendChild(info);

            if (p.has_more) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'usim-cg-load-more-btn';
                btn.disabled = this._isFetching;
                const remaining = p.total !== null ? Math.max(0, p.total - visibleCount) : null;
                const baseLabel = String(this.config.load_more_label || 'Cargar más');
                btn.textContent = this._isAppending
                    ? 'Cargando...'
                    : (remaining !== null && remaining > 0 ? `${baseLabel} (${remaining} restantes)` : baseLabel);

                btn.addEventListener('click', () => {
                    this.loadPage(p.page + 1, { append: true });
                });
                wrap.appendChild(btn);
            }

            this.paginationElement.appendChild(wrap);
            return;
        }

        // Classic 'pages' mode
        if (p.total_pages <= 1 && !p.show_per_page) {
            return;
        }

        const bar = document.createElement('div');
        bar.className = 'usim-cg-pagination';

        const start = total > 0 ? ((p.page - 1) * p.per_page) + 1 : 0;
        const end = total > 0 ? Math.min(start + visibleCount - 1, total) : visibleCount;

        const info = document.createElement('div');
        info.className = 'usim-cg-pagination-info';
        info.textContent = p.total !== null
            ? `Mostrando ${start}–${end} de ${p.total}`
            : `Página ${p.page}`;
        bar.appendChild(info);

        const controls = document.createElement('div');
        controls.className = 'usim-cg-pagination-controls';

        if (p.show_per_page && p.per_page_options.length > 1) {
            const perPageSel = document.createElement('select');
            perPageSel.className = 'usim-cg-per-page-select';
            perPageSel.setAttribute('aria-label', 'Elementos por página');

            p.per_page_options.forEach((opt) => {
                const o = document.createElement('option');
                o.value = String(opt);
                o.textContent = `${opt} / pág.`;
                if (opt === p.per_page) o.selected = true;
                perPageSel.appendChild(o);
            });

            perPageSel.addEventListener('change', (e) => {
                this._pagination.per_page = Math.max(1, Number(e.target.value) || 12);
                this._onQueryCriteriaChanged();
            });
            controls.appendChild(perPageSel);
        }

        if (p.total_pages > 1 || p.can_prev || p.can_next) {
            const prevBtn = document.createElement('button');
            prevBtn.type = 'button';
            prevBtn.className = 'usim-cg-page-btn';
            prevBtn.textContent = '‹ Anterior';
            prevBtn.disabled = this._isFetching || !p.can_prev;
            prevBtn.addEventListener('click', () => this._goToPage(p.page - 1));
            controls.appendChild(prevBtn);

            if (p.total_pages > 1) {
                this._computePageNumbers(p.page, p.total_pages).forEach((entry) => {
                    if (entry === '...') {
                        const dots = document.createElement('span');
                        dots.className = 'usim-cg-page-ellipsis';
                        dots.textContent = '…';
                        controls.appendChild(dots);
                        return;
                    }

                    const pageBtn = document.createElement('button');
                    pageBtn.type = 'button';
                    pageBtn.className = `usim-cg-page-btn${entry === p.page ? ' is-active' : ''}`;
                    pageBtn.textContent = String(entry);
                    pageBtn.disabled = this._isFetching || entry === p.page;
                    if (entry === p.page) {
                        pageBtn.setAttribute('aria-current', 'page');
                    }
                    pageBtn.addEventListener('click', () => this._goToPage(entry));
                    controls.appendChild(pageBtn);
                });
            }

            const nextBtn = document.createElement('button');
            nextBtn.type = 'button';
            nextBtn.className = 'usim-cg-page-btn';
            nextBtn.textContent = 'Siguiente ›';
            nextBtn.disabled = this._isFetching || !p.can_next;
            nextBtn.addEventListener('click', () => this._goToPage(p.page + 1));
            controls.appendChild(nextBtn);
        }

        bar.appendChild(controls);
        this.paginationElement.appendChild(bar);
    }

    _computePageNumbers(current, total) {
        if (total <= 7) {
            return Array.from({ length: total }, (_, i) => i + 1);
        }

        const pages = [1];
        const left = Math.max(2, current - 1);
        const right = Math.min(total - 1, current + 1);

        if (left > 2) pages.push('...');
        for (let i = left; i <= right; i++) {
            pages.push(i);
        }
        if (right < total - 1) pages.push('...');
        pages.push(total);

        return pages;
    }

    // ─── Querying (Remote GET /api/ui-data & Static Client Fallback) ───────────

    _onSearchChanged(immediate = false) {
        if (this._debounceTimer) {
            clearTimeout(this._debounceTimer);
            this._debounceTimer = null;
        }

        const delay = (!immediate && this.config.action) ? Number(this.config.debounce ?? 300) : 0;

        const execute = () => {
            this._pagination.page = 1;
            const minChars = Number(this.config.min_search_chars || 0);
            const term = this._searchTerm.trim();

            if (minChars > 0 && term.length > 0 && term.length < minChars) {
                this._renderToolbar();
                this._renderGridArea();
                this._renderPagination();
                return;
            }

            if (this.config.action) {
                this.loadPage(1, { append: false, silentSkeleton: this._items.length > 0 });
            } else {
                this._renderHeader();
                this._renderToolbar();
                this._renderGridArea();
                this._renderPagination();
            }
        };

        if (delay > 0) {
            this._debounceTimer = setTimeout(execute, delay);
        } else {
            execute();
        }
    }

    _onQueryCriteriaChanged() {
        this._pagination.page = 1;

        if (this.config.action) {
            this.loadPage(1, { append: false, silentSkeleton: this._items.length > 0 });
        } else {
            this._renderHeader();
            this._renderToolbar();
            this._renderGridArea();
            this._renderPagination();
        }
    }

    _clearAllCriteria() {
        this._searchTerm = '';
        this._activeFilters = {};
        this._openPopoverKey = null;
        this._onQueryCriteriaChanged();
    }

    _goToPage(page) {
        const target = Math.max(1, Number(page) || 1);
        if (this.config.action) {
            this.loadPage(target, { append: false, silentSkeleton: true, scrollToTop: true });
        } else {
            this._pagination.page = target;
            this._renderGridArea();
            this._renderPagination();
            this._scrollIntoViewIfNeeded();
        }
    }

    async loadPage(page = 1, options = {}) {
        if (!this.config.action) {
            this._pagination.page = Math.max(1, page);
            this._renderHeader();
            this._renderGridArea();
            this._renderPagination();
            return;
        }

        const append = Boolean(options.append);
        this._isFetching = true;
        this._isAppending = append;
        this._hasError = false;

        if (!append && !options.silentSkeleton) {
            this._items = [];
        }

        this._renderToolbar();
        this._renderGridArea();
        this._renderPagination();

        try {
            const params = {
                ...(this.config.parameters || {}),
                page: Math.max(1, Number(page) || 1),
                per_page: this._pagination.per_page,
                search: this._searchTerm.trim(),
                sort_by: this._sortBy || null,
                sort_direction: this._sortDirection,
                filters: this._activeFilters,
            };

            const result = await this.fetchData(this.config.action, params, {
                showLoadingOverlay: false,
            });

            if (result === null) {
                // Aborted or failed; if no active request replaced us, show error if nothing loaded
                if (!this._activeAbortController) {
                    this._hasError = this._items.length === 0;
                }
                return;
            }

            this._hasFetchedOnce = true;

            const rawItems = Array.isArray(result)
                ? result
                : (Array.isArray(result.items) ? result.items : []);
            const incoming = this._normalizeItems(rawItems);

            if (append) {
                const existingIds = new Set(this._items.map((it) => String(it.id)));
                incoming.forEach((it) => {
                    if (!existingIds.has(String(it.id))) {
                        this._items.push(it);
                        existingIds.add(String(it.id));
                    }
                });
            } else {
                this._items = incoming;
            }

            if (result && typeof result === 'object' && result.pagination && typeof result.pagination === 'object') {
                this._pagination = this._normalizePagination({
                    ...this._pagination,
                    ...result.pagination,
                });
            } else {
                this._pagination = this._normalizePagination({
                    ...this._pagination,
                    page,
                    total: this._items.length,
                    has_more: false,
                });
            }

            if (options.scrollToTop) {
                this._scrollIntoViewIfNeeded();
            }
        } catch (error) {
            console.error('CardGrid loadPage error:', error);
            this._hasError = true;
        } finally {
            this._isFetching = false;
            this._isAppending = false;
            this._renderHeader();
            this._renderToolbar();
            this._renderGridArea();
            this._renderPagination();
        }
    }

    _scrollIntoViewIfNeeded() {
        if (!this.wrapperElement) return;
        const rect = this.wrapperElement.getBoundingClientRect();
        if (rect.top < 0) {
            this.wrapperElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // ─── Client-Side Static Data Pipeline ─────────────────────────────────────

    _getStaticFilteredSortedItems() {
        let list = [...this._staticAllItems];
        const term = this._searchTerm.trim().toLowerCase();

        if (term !== '') {
            list = list.filter((it) => {
                const haystack = [
                    it.title,
                    it.subtitle,
                    it.eyebrow,
                    it.description,
                    it.badge,
                    ...(it.tags || []),
                    ...(it.attributes || []).map((a) => `${a.label} ${a.value}`),
                ].filter(Boolean).join(' ').toLowerCase();
                return haystack.includes(term);
            });
        }

        Object.entries(this._activeFilters).forEach(([key, val]) => {
            list = list.filter((it) => {
                const candidate = it.meta?.[key] ?? it[key];
                if (Array.isArray(val)) {
                    if (Array.isArray(candidate)) {
                        return val.some((v) => candidate.map(String).includes(String(v)));
                    }
                    return val.map(String).includes(String(candidate));
                }
                if (typeof val === 'boolean') {
                    return Boolean(candidate) === val;
                }
                return String(candidate ?? '').toLowerCase() === String(val).toLowerCase();
            });
        });

        if (this._sortBy) {
            const field = this._sortBy;
            const dir = this._sortDirection === 'desc' ? -1 : 1;
            list.sort((a, b) => {
                const va = a[field] ?? a.meta?.[field] ?? '';
                const vb = b[field] ?? b.meta?.[field] ?? '';
                if (typeof va === 'number' && typeof vb === 'number') {
                    return (va - vb) * dir;
                }
                return String(va).localeCompare(String(vb), undefined, { numeric: true, sensitivity: 'base' }) * dir;
            });
        }

        return list;
    }

    _getEffectivePagination() {
        if (this.config.action) {
            return this._pagination;
        }

        const filtered = this._getStaticFilteredSortedItems();
        const total = filtered.length;
        const perPage = Math.max(1, this._pagination.per_page);
        const totalPages = total > 0 ? Math.ceil(total / perPage) : 0;
        const page = Math.min(this._pagination.page, Math.max(1, totalPages));

        return {
            ...this._pagination,
            page,
            total,
            total_pages: totalPages,
            has_more: (page * perPage) < total,
            can_prev: page > 1,
            can_next: (page * perPage) < total,
        };
    }

    _getEffectiveTotal() {
        const p = this._getEffectivePagination();
        return p.total !== null ? p.total : this._items.length;
    }

    _getDisplayItems() {
        if (this.config.action) {
            return this._items;
        }

        const filtered = this._getStaticFilteredSortedItems();
        const p = this._getEffectivePagination();
        if (p.mode === 'load_more') {
            return filtered.slice(0, p.page * p.per_page);
        }
        const start = (p.page - 1) * p.per_page;
        return filtered.slice(start, start + p.per_page);
    }

    // ─── Card & Action Events ─────────────────────────────────────────────────

    _handleCardClick(item) {
        if (item.disabled) return;

        if (item.url) {
            if (item.target === '_blank') {
                window.open(item.url, '_blank', 'noopener,noreferrer');
            } else {
                window.location.assign(item.url);
            }
            return;
        }

        if (item.click_action) {
            this.sendEventToBackend('click', item.click_action, {
                id: item.id,
                item,
                ...(item.click_parameters || {}),
            });
            return;
        }

        const configured = this.config.on_card_click;
        if (configured) {
            const actionName = typeof configured === 'string' ? configured : configured.action;
            const extraParams = typeof configured === 'object' && configured.parameters ? configured.parameters : {};
            if (actionName) {
                this.sendEventToBackend('click', actionName, {
                    ...extraParams,
                    id: item.id,
                    item,
                });
            }
        }
    }

    _handleCardAction(act, item) {
        if (act.disabled) return;
        if (!act.action) return;

        this.sendEventToBackend('click', act.action, {
            id: item.id,
            item,
            ...(act.parameters || {}),
        });
    }

    // ─── Icon Helper ──────────────────────────────────────────────────────────

    _createIconNode(iconValue, size = 16) {
        const helpers = window.USIM_COMPONENT_HELPERS;
        if (helpers && typeof helpers.createIconElement === 'function') {
            return helpers.createIconElement(iconValue, {
                size,
                baseClass: 'usim-cg-icon',
            });
        }

        const fallback = document.createElement('span');
        fallback.className = 'usim-cg-icon';
        fallback.textContent = String(iconValue || '');
        return fallback;
    }
}

window.UsimCardGridComponent = UsimCardGridComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('card_grid');
    window.USIM_COMPONENTS.register('card_grid', (id, config) => new UsimCardGridComponent(id, config), {
        source: 'modular',
    });
}
