/**
 * TableRow component (modular implementation)
 */
class UsimTableRowComponent extends UIComponent {
    render() {
        const row = document.createElement('tr');
        row.className = 'ui-table-row';

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
}

window.UsimTableRowComponent = UsimTableRowComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('tablerow');
    window.USIM_COMPONENTS.register('tablerow', (id, config) => new UsimTableRowComponent(id, config), {
        source: 'modular',
    });
}
