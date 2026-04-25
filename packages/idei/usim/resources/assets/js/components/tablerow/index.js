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

        if (this.config.min_height) {
            row.style.height = `${this.config.min_height}px`;
            row.setAttribute('data-min-height', this.config.min_height);
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
