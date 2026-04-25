/**
 * Select component (modular override)
 */
class UsimSelectComponent extends UIComponent {
    render() {
        const group = document.createElement('div');
        group.className = 'ui-select-group';

        if (this.config.label) {
            const label = document.createElement('label');
            label.textContent = this.config.label;
            if (this.config.required) {
                label.className = 'required';
            }
            if (this.config.name) {
                label.setAttribute('for', this.config.name);
            }
            group.appendChild(label);
        }

        const select = document.createElement('select');
        select.className = 'ui-select';
        select.required = this.config.required || false;
        select.disabled = this.config.disabled || false;

        if (this.config.name) {
            select.name = this.config.name;
            select.id = this.config.name;
        }

        if (this.config.placeholder && !this.config.value) {
            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = this.config.placeholder;
            placeholderOption.disabled = true;
            placeholderOption.selected = true;
            select.appendChild(placeholderOption);
        }

        if (this.config.options) {
            if (Array.isArray(this.config.options)) {
                this.config.options.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt.value;
                    option.textContent = opt.label;
                    if (this.config.value === opt.value) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });
            } else {
                for (const [value, label] of Object.entries(this.config.options)) {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    if (this.config.value === value) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                }
            }
        }

        group.appendChild(select);

        if (this.config.on_change) {
            select.addEventListener('change', () => {
                this.handleChange(this.config.on_change, select.value);
            });
        }

        return this.applyCommonAttributes(group);
    }

    async handleChange(action, value) {
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
                parameters: { value },
            });

            if (ok) {
                helpers.applyUiUpdate?.(result);
            }
        } catch (error) {
            console.error('Select action error:', error);
        }
    }
}

window.UsimSelectComponent = UsimSelectComponent;

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('select');
    window.USIM_COMPONENTS.register('select', (id, config) => new UsimSelectComponent(id, config), {
        source: 'modular',
    });
}
