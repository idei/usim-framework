/**
 * TableRow component (modular implementation)
 */
class UsimTableRowComponent extends UIComponent {
    render() {
        const row = document.createElement('tr');
        row.className = 'ui-table-row';

        this.applyInteractiveState(row);
        this.applyModelIdentifierState(row);

        if (this.config.selected) {
            row.classList.add('selected');
        }

        if (this.config.style) {
            row.classList.add(this.config.style);
        }

        if (this.config.min_height !== undefined && this.config.min_height !== null && this.config.min_height !== '') {
            const minHeight = typeof this.config.min_height === 'number'
                ? `${this.config.min_height}px`
                : this.config.min_height;

            row.style.minHeight = minHeight;
            row.setAttribute('data-min-height', minHeight);
        }

        return this.applyCommonAttributes(row);
    }

    update(newConfig) {
        this.config = {
            ...this.config,
            ...newConfig,
        };

        if (!this.element) {
            return;
        }

        this.element.className = 'ui-table-row';
        this.applyInteractiveState(this.element);
        this.applyModelIdentifierState(this.element);

        if (this.config.selected) {
            this.element.classList.add('selected');
        }

        if (this.config.style) {
            this.element.classList.add(this.config.style);
        }

        if (this.config.min_height !== undefined && this.config.min_height !== null && this.config.min_height !== '') {
            const minHeight = typeof this.config.min_height === 'number'
                ? `${this.config.min_height}px`
                : this.config.min_height;

            this.element.style.minHeight = minHeight;
            this.element.setAttribute('data-min-height', minHeight);
        } else {
            this.element.style.minHeight = '';
            this.element.removeAttribute('data-min-height');
        }

        this.applyCommonAttributes(this.element);
    }

    applyModelIdentifierState(row) {
        const modelId = this.config?.parameters?.model_id;

        if (modelId === null || modelId === undefined || modelId === '') {
            row.removeAttribute('data-row-model-id');
            return;
        }

        row.setAttribute('data-row-model-id', String(modelId));
    }

    applyInteractiveState(row) {
        row.classList.remove('clickable', 'pending');
        row.style.cursor = '';
        row.removeAttribute('role');
        row.removeAttribute('tabindex');
        row.removeAttribute('aria-busy');
        row.onclick = null;
        row.onkeydown = null;

        if (!this.config.action) {
            return;
        }

        row.classList.add('clickable');
        row.style.cursor = 'pointer';
        row.setAttribute('role', 'button');
        row.setAttribute('tabindex', '0');

        row.onclick = () => {
            this.handleRowClick(row);
        };

        row.onkeydown = (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            event.preventDefault();
            this.handleRowClick(row);
        };
    }

    async handleRowClick(row) {
        const helpers = window.USIM_COMPONENT_HELPERS;
        if (!helpers?.sendUiEvent) {
            return;
        }

        row.classList.add('pending');
        row.setAttribute('aria-busy', 'true');

        try {
            const { ok, result } = await helpers.sendUiEvent({
                componentId: this.getComponentId(),
                event: 'click',
                action: this.config.action,
                parameters: this.config.parameters || {},
            });

            if (ok) {
                row.classList.add('selected');
                helpers.applyUiUpdate?.(result);
            }
        } catch (error) {
            console.error('Error handling table row click:', error);
        } finally {
            row.classList.remove('pending');
            row.removeAttribute('aria-busy');
        }
    }
}

window.UsimTableRowComponent = UsimTableRowComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('tablerow');
    window.USIM_COMPONENTS.register('tablerow', (id, config) => new UsimTableRowComponent(id, config), {
        source: 'modular',
    });
}
