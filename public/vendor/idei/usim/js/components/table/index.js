/**
 * Table component (modular implementation)
 */
class UsimTableComponent extends UIComponent {
    render() {
        const tableWrapper = document.createElement('div');
        tableWrapper.className = 'ui-table-wrapper';

        if (this.config.align) {
            tableWrapper.classList.add(`align-${this.config.align}`);
        }

        if (this.config.title) {
            const title = document.createElement('h3');
            title.className = 'ui-table-title';
            title.textContent = this.config.title;
            tableWrapper.appendChild(title);
        }

        const table = document.createElement('table');
        table.className = 'ui-table';
        tableWrapper.appendChild(table);

        if (this.config.pagination) {
            const paginationDiv = this.createPaginationControls();
            tableWrapper.appendChild(paginationDiv);
        }

        this.tableElement = table;

        return this.applyCommonAttributes(tableWrapper);
    }

    createPaginationControls() {
        const paginationDiv = document.createElement('div');
        paginationDiv.className = 'ui-pagination';
        paginationDiv.setAttribute('data-component-id', this.id);

        const pagination = this.config.pagination || {};
        const currentPage = pagination.current_page || 1;
        const perPage = pagination.per_page || 10;
        const totalItems = pagination.total_items || 0;
        const totalPages = pagination.total_pages || 1;
        const canNext = pagination.can_next !== undefined ? pagination.can_next : (currentPage < totalPages);
        const canPrev = pagination.can_prev !== undefined ? pagination.can_prev : (currentPage > 1);

        const labels = pagination.labels || {};
        const labelPrevious = labels.previous || '\u00ab Previous';
        const labelNext = labels.next || 'Next \u00bb';
        const labelShowing = labels.showing || 'Showing :start-:end of :total items';

        const start = (currentPage - 1) * perPage + 1;
        const end = Math.min(currentPage * perPage, totalItems);
        const infoDiv = document.createElement('div');
        infoDiv.className = 'ui-pagination-info';
        infoDiv.textContent = labelShowing
            .replace(':start', start)
            .replace(':end', end)
            .replace(':total', totalItems);
        paginationDiv.appendChild(infoDiv);

        const controlsDiv = document.createElement('div');
        controlsDiv.className = 'ui-pagination-controls';

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
                border: 2px solid var(--usim-pagination-spinner-track, rgba(15, 23, 36, 0.12));
                border-top: 2px solid var(--usim-pagination-spinner-indicator, var(--usim-color-primary, #3498db));
                border-radius: 50%;
                animation: spin 1s linear infinite;
            "></span>
        `;

        if (!document.querySelector('#pagination-spinner-style')) {
            const style = document.createElement('style');
            style.id = 'pagination-spinner-style';
            style.textContent = `
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            `;
            document.head.appendChild(style);
        }

        controlsDiv.appendChild(loadingDiv);
        loadingDiv.style.display = 'none';
        controlsDiv.paginationLoading = loadingDiv;

        const prevBtn = document.createElement('button');
        prevBtn.className = 'ui-pagination-button';
        prevBtn.textContent = labelPrevious;
        prevBtn.disabled = !canPrev;
        prevBtn.addEventListener('click', () => this.changePage(currentPage - 1, paginationDiv));
        controlsDiv.appendChild(prevBtn);

        const pages = this.getPageNumbers(currentPage, totalPages);
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
                pageBtn.addEventListener('click', () => this.changePage(page, paginationDiv));
                controlsDiv.appendChild(pageBtn);
            }
        });

        const nextBtn = document.createElement('button');
        nextBtn.className = 'ui-pagination-button';
        nextBtn.textContent = labelNext;
        nextBtn.disabled = !canNext;
        nextBtn.addEventListener('click', () => this.changePage(currentPage + 1, paginationDiv));
        controlsDiv.appendChild(nextBtn);

        paginationDiv.appendChild(controlsDiv);

        return paginationDiv;
    }

    getPageNumbers(current, total) {
        const pages = [];
        const maxVisible = 5;

        if (total <= maxVisible + 2) {
            for (let i = 1; i <= total; i++) {
                pages.push(i);
            }
        } else {
            pages.push(1);

            if (current > 3) {
                pages.push('...');
            }

            const start = Math.max(2, current - 1);
            const end = Math.min(total - 1, current + 1);

            for (let i = start; i <= end; i++) {
                pages.push(i);
            }

            if (current < total - 2) {
                pages.push('...');
            }

            pages.push(total);
        }

        return pages;
    }

    async changePage(page, paginationDiv = null) {
        if (!paginationDiv) {
            paginationDiv = this.element?.querySelector('.ui-pagination');
        }

        if (paginationDiv) {
            this.setLoadingState(paginationDiv, true);
        }

        try {
            const componentId = this.getComponentId();
            const helpers = window.USIM_COMPONENT_HELPERS;
            if (!helpers?.sendUiEvent) {
                return;
            }

            const { ok, response, result } = await helpers.sendUiEvent({
                componentId,
                event: 'action',
                action: 'change_page',
                parameters: { page },
                credentials: 'omit',
            });

            if (!ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            if (result) {
                const tableData = result[this.id];

                if (tableData && tableData.pagination) {
                    this.config.pagination = tableData.pagination;

                    const oldPagination = this.element.querySelector('.ui-pagination');
                    if (oldPagination) {
                        const newPagination = this.createPaginationControls();
                        oldPagination.replaceWith(newPagination);
                    }
                } else {
                    if (paginationDiv) {
                        this.setLoadingState(paginationDiv, false);
                    }
                }

                helpers.applyUiUpdate?.(result);
            }
        } catch (error) {
            console.error('Error changing page:', error);

            if (paginationDiv) {
                this.setLoadingState(paginationDiv, false);
            }
        }
    }

    setLoadingState(paginationDiv, isLoading) {
        const controlsDiv = paginationDiv.querySelector('.ui-pagination-controls');
        if (!controlsDiv) return;

        const buttons = controlsDiv.querySelectorAll('button');
        const loadingDiv = controlsDiv.querySelector('.ui-pagination-loading');

        if (isLoading) {
            buttons.forEach(btn => btn.disabled = true);
            if (loadingDiv) {
                loadingDiv.style.display = 'flex';
            }
        } else {
            const pagination = this.config.pagination || {};
            const currentPage = pagination.current_page || 1;
            const totalPages = pagination.total_pages || 1;
            const canNext = pagination.can_next !== undefined ? pagination.can_next : (currentPage < totalPages);
            const canPrev = pagination.can_prev !== undefined ? pagination.can_prev : (currentPage > 1);

            buttons.forEach((btn) => {
                const btnText = btn.textContent.trim();

                if (btnText === '« Previous') {
                    btn.disabled = !canPrev;
                } else if (btnText === 'Next »') {
                    btn.disabled = !canNext;
                } else if (!isNaN(btnText)) {
                    btn.disabled = false;
                }
            });

            if (loadingDiv) {
                loadingDiv.style.display = 'none';
            }
        }
    }

    mount(parentElement) {
        super.mount(parentElement);
    }
}

window.UsimTableComponent = UsimTableComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('table');
    window.USIM_COMPONENTS.register('table', (id, config) => new UsimTableComponent(id, config), {
        source: 'modular',
    });
}
