<?php

namespace Idei\Usim\Components;

/**
 * Checkbox component builder for creating single and grouped checkboxes
 *
 * Provides a fluent API for configuring checkbox inputs with extensive customization options.
 * Supports single checkboxes, checkbox groups, validation, styling, variants (default, switch, button, card),
 * layout options (vertical, horizontal, grid), and modern UX features like indeterminate state and toggle-all behavior.
 *
 * @example
 * // Simple checkbox
 * UI::checkbox('terms')
 *     ->label('I agree to the terms and conditions')
 *     ->required();
 *
 * // Checkbox group with grid layout
 * UI::checkbox('interests')
 *     ->label('Select your interests')
 *     ->options([
 *         ['value' => 'sports', 'label' => 'Sports'],
 *         ['value' => 'music', 'label' => 'Music'],
 *         ['value' => 'tech', 'label' => 'Technology']
 *     ])
 *     ->selectedValues(['sports', 'tech'])
 *     ->grid(3)
 *     ->minSelections(1)
 *     ->maxSelections(2);
 *
 * // Switch variant
 * UI::checkbox('notifications')
 *     ->label('Enable notifications')
 *     ->asSwitch('right');
 *
 * // Button variant with multiple options
 * UI::checkbox('theme')
 *     ->options([
 *         ['value' => 'light', 'label' => 'Light', 'icon' => 'sun'],
 *         ['value' => 'dark', 'label' => 'Dark', 'icon' => 'moon']
 *     ])
 *     ->asButton()
 *     ->size('large');
 */
class Checkbox extends UIComponent
{
    /**
     * Get the default configuration for a checkbox component
     *
     * @return array<string, mixed>
     */
    protected function getDefaultConfig(): array
    {
        return [
            // Core checkbox properties
            'checked' => false,
            'value' => null,
            'label' => null,
            'description' => null,

            // Checkbox group (multiple options)
            'options' => [],
            'selected_values' => [],

            // Layout for groups
            'layout' => 'vertical', // vertical, horizontal, grid
            'columns' => null, // for grid layout
            'gap' => 'medium', // xs, small, medium, large

            // Validation
            'required' => false,
            'min_selections' => null, // for groups
            'max_selections' => null, // for groups
            'error_message' => null,
            'help_text' => null,

            // State
            'disabled' => false,
            'readonly' => false,
            'indeterminate' => false, // for parent checkboxes in nested structures

            // Appearance
            'style' => 'default', // default, primary, success, danger, warning, info
            'size' => 'medium', // small, medium, large
            'variant' => 'default', // default, switch, button, card

            // Icons
            'checked_icon' => 'check',
            'unchecked_icon' => null,
            'indeterminate_icon' => 'minus',

            // Switch variant specific
            'switch_position' => 'left', // left, right (for switch variant)

            // Button/Card variant specific
            'icon' => null, // icon for button/card variant
            'color' => null, // custom color
            'active_color' => null, // color when checked

            // Behavior
            'toggle_all' => false, // for parent checkbox that controls all children
            'auto_check_parent' => false, // auto check parent when all children checked

            // Events
            'on_change' => null,

            // Accessibility
            'aria_label' => null,
            'tooltip' => null,
        ];
    }

    /**
     * Get the component type
     */
    public function getType(): string
    {
        return 'checkbox';
    }

    // ==================== Core Configuration ====================

    /**
     * Set the checked state
     *
     * @param  bool  $checked  Whether the checkbox is checked
     */
    public function checked(bool $checked = true): static
    {
        return $this->setConfig('checked', $checked);
    }

    /**
     * Get the checked state
     */
    public function isChecked(): bool
    {
        return (bool) ($this->config['checked'] ?? false);
    }

    /**
     * Set the checkbox value
     * For single checkbox: any value to submit when checked
     * For checkbox group: array of selected values
     *
     * @param  mixed  $value  The value
     * @return $this
     */
    public function value($value): static
    {
        if (is_array($value)) {
            $this->setConfig('selected_values', $value);
        } else {
            $this->setConfig('value', $value);
        }

        return $this;
    }

    /**
     * Set the checkbox label
     *
     * @param  string  $label  The label text
     */
    public function label(string $label): static
    {
        return $this->setConfig('label', $label);
    }

    /**
     * Set a description text (appears below the label)
     *
     * @param  string  $description  The description text
     */
    public function description(string $description): static
    {
        return $this->setConfig('description', $description);
    }

    // ==================== Checkbox Group ====================

    /**
     * Set multiple checkbox options (creates a checkbox group)
     *
     * @param  list<array<string, mixed>>  $options  Array of options with 'value' and 'label' keys
     */
    public function options(array $options): static
    {
        return $this->setConfig('options', $options);
    }

    /**
     * Add a single option to the checkbox group
     *
     * @param  string  $value  The option value
     * @param  string  $label  The option label
     * @param  array<string, mixed>  $extra  Extra properties (icon, description, disabled, etc.)
     */
    public function addOption(string $value, string $label, array $extra = []): static
    {
        $options = $this->config['options'] ?? [];
        if (! is_array($options)) {
            $options = [];
        }

        $options[] = array_merge([
            'value' => $value,
            'label' => $label,
        ], $extra);

        return $this->setConfig('options', $options);
    }

    /**
     * Set the selected values for checkbox group
     *
     * @param  list<string|int>  $values  Array of selected values
     */
    public function selectedValues(array $values): static
    {
        return $this->setConfig('selected_values', $values);
    }

    // ==================== Layout (for groups) ====================

    /**
     * Set the layout for checkbox group
     *
     * @param  string  $layout  Layout type: vertical, horizontal, grid
     * @param  int|null  $columns  Number of columns for grid layout
     */
    public function layout(string $layout, ?int $columns = null): static
    {
        $this->setConfig('layout', $layout);
        if ($columns !== null) {
            $this->setConfig('columns', $columns);
        }

        return $this;
    }

    /**
     * Set vertical layout for checkbox group
     */
    public function vertical(): static
    {
        return $this->layout('vertical');
    }

    /**
     * Set horizontal layout for checkbox group
     */
    public function horizontal(): static
    {
        return $this->layout('horizontal');
    }

    /**
     * Set grid layout for checkbox group
     *
     * @param  int  $columns  Number of columns
     */
    public function grid(int $columns = 2): static
    {
        return $this->layout('grid', $columns);
    }

    /**
     * Set the gap between checkboxes in a group
     *
     * @param  string  $gap  Gap size: xs, small, medium, large
     */
    public function gap(string $gap): static
    {
        return $this->setConfig('gap', $gap);
    }

    // ==================== Validation ====================

    /**
     * Mark the checkbox as required
     *
     * @param  bool  $required  Whether the checkbox is required
     */
    public function required(bool $required = true): static
    {
        return $this->setConfig('required', $required);
    }

    /**
     * Set minimum selections required (for checkbox groups)
     *
     * @param  int  $min  Minimum number of selections
     */
    public function minSelections(int $min): static
    {
        return $this->setConfig('min_selections', $min);
    }

    /**
     * Set maximum selections allowed (for checkbox groups)
     *
     * @param  int  $max  Maximum number of selections
     */
    public function maxSelections(int $max): static
    {
        return $this->setConfig('max_selections', $max);
    }

    /**
     * Set the error message
     *
     * @param  string  $message  The error message
     */
    public function errorMessage(string $message): static
    {
        return $this->setConfig('error_message', $message);
    }

    /**
     * Set help text
     *
     * @param  string  $text  The help text
     */
    public function helpText(string $text): static
    {
        return $this->setConfig('help_text', $text);
    }

    // ==================== State ====================

    /**
     * Set the disabled state
     *
     * @param  bool  $disabled  Whether the checkbox is disabled
     */
    public function disabled(bool $disabled = true): static
    {
        return $this->setConfig('disabled', $disabled);
    }

    /**
     * Set the readonly state
     *
     * @param  bool  $readonly  Whether the checkbox is readonly
     */
    public function readonly(bool $readonly = true): static
    {
        return $this->setConfig('readonly', $readonly);
    }

    /**
     * Set the indeterminate state (for parent checkboxes)
     *
     * @param  bool  $indeterminate  Whether the checkbox is indeterminate
     */
    public function indeterminate(bool $indeterminate = true): static
    {
        return $this->setConfig('indeterminate', $indeterminate);
    }

    // ==================== Appearance ====================

    /**
     * Set the checkbox style
     *
     * @param  string  $style  Style: default, primary, success, danger, warning, info
     */
    public function style(string $style): static
    {
        return $this->setConfig('style', $style);
    }

    /**
     * Set the checkbox size
     *
     * @param  string  $size  Size: small, medium, large
     */
    public function size(string $size): static
    {
        return $this->setConfig('size', $size);
    }

    /**
     * Set the checkbox variant
     *
     * @param  string  $variant  Variant: default, switch, button, card
     */
    public function variant(string $variant): static
    {
        return $this->setConfig('variant', $variant);
    }

    /**
     * Use switch variant
     *
     * @param  string  $position  Switch position: left, right
     */
    public function asSwitch(string $position = 'left'): static
    {
        $this->setConfig('variant', 'switch');

        return $this->setConfig('switch_position', $position);
    }

    /**
     * Use button variant (checkbox looks like a button)
     */
    public function asButton(): static
    {
        return $this->setConfig('variant', 'button');
    }

    /**
     * Use card variant (checkbox as a card/tile)
     */
    public function asCard(): static
    {
        return $this->setConfig('variant', 'card');
    }

    // ==================== Icons ====================

    /**
     * Set the checked icon
     *
     * @param  string  $icon  The icon name
     */
    public function checkedIcon(string $icon): static
    {
        return $this->setConfig('checked_icon', $icon);
    }

    /**
     * Set the unchecked icon
     *
     * @param  string  $icon  The icon name
     */
    public function uncheckedIcon(string $icon): static
    {
        return $this->setConfig('unchecked_icon', $icon);
    }

    /**
     * Set the indeterminate icon
     *
     * @param  string  $icon  The icon name
     */
    public function indeterminateIcon(string $icon): static
    {
        return $this->setConfig('indeterminate_icon', $icon);
    }

    /**
     * Set an icon for button/card variant
     *
     * @param  string  $icon  The icon name
     */
    public function icon(string $icon): static
    {
        return $this->setConfig('icon', $icon);
    }

    // ==================== Colors ====================

    /**
     * Set custom color
     *
     * @param  string  $color  The color (hex, rgb, css variable)
     */
    public function color(string $color): static
    {
        return $this->setConfig('color', $color);
    }

    /**
     * Set custom active color (when checked)
     *
     * @param  string  $color  The color (hex, rgb, css variable)
     */
    public function activeColor(string $color): static
    {
        return $this->setConfig('active_color', $color);
    }

    // ==================== Behavior ====================

    /**
     * Enable toggle all behavior (checkbox controls all children)
     *
     * @param  bool  $toggle  Whether to enable toggle all
     */
    public function toggleAll(bool $toggle = true): static
    {
        return $this->setConfig('toggle_all', $toggle);
    }

    /**
     * Enable auto check parent behavior
     *
     * @param  bool  $autoCheck  Whether to auto check parent
     */
    public function autoCheckParent(bool $autoCheck = true): static
    {
        return $this->setConfig('auto_check_parent', $autoCheck);
    }

    // ==================== Events ====================

    /**
     * Set the onChange event handler
     *
     * @param  string  $handler  The event handler name
     */
    public function onChange(string $handler): static
    {
        return $this->setConfig('on_change', $handler);
    }

    // ==================== Accessibility ====================

    /**
     * Set ARIA label for accessibility
     *
     * @param  string  $label  The ARIA label
     */
    public function ariaLabel(string $label): static
    {
        return $this->setConfig('aria_label', $label);
    }

    /**
     * Set tooltip text
     *
     * @param  string  $text  The tooltip text
     */
    public function tooltip(string $text): static
    {
        return $this->setConfig('tooltip', $text);
    }
}
