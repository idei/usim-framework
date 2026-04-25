/**
 * Checkbox component (modular override)
 */
class UsimCheckboxComponent extends UIComponent {
    render() {
        const group = document.createElement('div');
        group.className = 'ui-checkbox-group';

        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.className = 'ui-checkbox';
        checkbox.checked = this.config.checked || false;
        checkbox.required = this.config.required || false;
        checkbox.disabled = this.config.disabled || false;

        if (this.config.name) {
            checkbox.name = this.config.name;
            checkbox.id = this.config.name;
        }

        if (this.config.value) {
            checkbox.value = this.config.value;
        }

        if (this.config.on_change) {
            checkbox.addEventListener('change', async (e) => {
                const newCheckedState = e.target.checked;
                e.target.checked = this.config.checked || false;
                await this.handleChange(this.config.on_change, newCheckedState);
            });
        }

        group.appendChild(checkbox);

        if (this.config.label) {
            const label = document.createElement('label');
            label.className = 'ui-checkbox-label';
            label.textContent = this.config.label;
            if (this.config.required) {
                label.classList.add('required');
            }
            if (this.config.name) {
                label.setAttribute('for', this.config.name);
            }
            group.appendChild(label);
        }

        return this.applyCommonAttributes(group);
    }

    async handleChange(action, checked) {
        try {
            const componentId = this.getComponentId();
            const helpers = window.USIM_COMPONENT_HELPERS;
            if (!helpers?.sendUiEvent) {
                return;
            }

            const { ok, result } = await helpers.sendUiEvent({
                componentId,
                event: 'change',
                action,
                parameters: {
                    checked,
                    name: this.config.name,
                },
            });

            if (ok) {
                helpers.applyUiUpdate?.(result);
            } else {
                const checkbox = document.querySelector(`[data-component-id="${componentId}"] input[type="checkbox"]`);
                if (checkbox) {
                    checkbox.checked = this.config.checked || false;
                }
            }
        } catch (error) {
            console.error('Checkbox action error:', error);
            const checkbox = document.querySelector(`[data-component-id="${this.getComponentId()}"] input[type="checkbox"]`);
            if (checkbox) {
                checkbox.checked = this.config.checked || false;
            }
        }
    }
}

window.UsimCheckboxComponent = UsimCheckboxComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('checkbox');
    window.USIM_COMPONENTS.register('checkbox', (id, config) => new UsimCheckboxComponent(id, config), {
        source: 'modular',
    });
}
