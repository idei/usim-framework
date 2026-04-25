/**
 * TableHeaderRow component (modular implementation)
 */
class UsimTableHeaderRowComponent extends UIComponent {
    render() {
        const headerRow = document.createElement('tr');
        headerRow.className = 'ui-table-header-row';

        return this.applyCommonAttributes(headerRow);
    }
}

window.UsimTableHeaderRowComponent = UsimTableHeaderRowComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('tableheaderrow');
    window.USIM_COMPONENTS.register('tableheaderrow', (id, config) => new UsimTableHeaderRowComponent(id, config), {
        source: 'modular',
    });
}
