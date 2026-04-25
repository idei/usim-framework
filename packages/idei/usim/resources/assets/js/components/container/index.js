/**
 * Container component (modular override)
 */
class UsimContainerComponent extends UIComponent {
    render() {
        const container = document.createElement('div');
        const layout = this.config.layout || 'vertical';
        const appearance = typeof this.config.appearance === 'string'
            ? this.config.appearance.trim().toLowerCase()
            : 'card';
        const appearanceClass = appearance === 'plain' ? 'ui-container-plain' : 'ui-container-card';
        container.className = `ui-container ${layout} ${appearanceClass}`;

        if (this.config.title) {
            const title = document.createElement('div');
            title.className = 'title';
            title.textContent = this.config.title;
            container.appendChild(title);
        }

        return this.applyCommonAttributes(container);
    }
}

window.UsimContainerComponent = UsimContainerComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('container');
    window.USIM_COMPONENTS.register('container', (id, config) => new UsimContainerComponent(id, config), {
        source: 'modular',
    });
}
