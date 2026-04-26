<?php

namespace Idei\Usim\Components;

/**
 * Builder for Textarea UI components
 *
 * Supports plain text editing and markdown-enabled rich editing with live preview.
 */
class Textarea extends UIComponent
{
    protected function getDefaultConfig(): array
    {
        return [
            'label'        => null,
            'placeholder'  => null,
            'value'        => null,
            'width'        => null,
            'height'       => null,
            'max_length'   => null,
            'mode'         => 'plain',   // 'plain' | 'markdown'
            'required'     => false,
            'disabled'     => false,
            'readonly'     => false,
            'error'        => null,
            'help_text'    => null,
            'on_change'    => null,
            'on_input'     => null,
            'debounce'     => null,
            'border_color' => null,     // CSS color, e.g. '#4f46e5'
            'border_width' => null,     // px width, e.g. 3
            'border_radius' => null,    // px radius, e.g. 10
            'background_color' => null, // CSS color, e.g. '#0f172a'
        ];
    }

    /** Set the label text. */
    public function label(string $label): self
    {
        return $this->setConfig('label', $label);
    }

    /** Set placeholder text shown when the textarea is empty. */
    public function placeholder(string $placeholder): self
    {
        return $this->setConfig('placeholder', $placeholder);
    }

    /** Set the current value. */
    public function value(?string $value): self
    {
        return $this->setConfig('value', $value);
    }

    /**
     * Set the maximum number of characters allowed.
     * Pass null to remove the limit.
     */
    public function maxLength(?int $maxLength): self
    {
        return $this->setConfig('max_length', $maxLength);
    }

    /** Remove the character limit. */
    public function unlimited(): self
    {
        return $this->setConfig('max_length', null);
    }

    /**
     * Set the editing mode.
     *
     * @param string $mode 'plain' | 'markdown'
     */
    public function mode(string $mode): self
    {
        return $this->setConfig('mode', $mode);
    }

    /** Shortcut: enable plain text mode. */
    public function plainText(): self
    {
        return $this->mode('plain');
    }

    /** Shortcut: enable markdown editing mode. */
    public function markdown(): self
    {
        return $this->mode('markdown');
    }

    /** Mark the field as required. */
    public function required(bool $required = true): self
    {
        return $this->setConfig('required', $required);
    }

    /** Disable the textarea. */
    public function disabled(bool $disabled = true): self
    {
        return $this->setConfig('disabled', $disabled);
    }

    /** Make the textarea read-only. */
    public function readonly(bool $readonly = true): self
    {
        return $this->setConfig('readonly', $readonly);
    }

    /**
     * Set or clear an error state.
     *
     * @param string|null $error Error message, or null to clear.
     */
    public function error(?string $error): self
    {
        return $this->setConfig('error', $error);
    }

    /** Set help text displayed below the textarea. */
    public function helpText(string $helpText): self
    {
        return $this->setConfig('help_text', $helpText);
    }

    /**
     * Trigger a backend action when the value changes (on blur).
     *
     * @param string $action   Action name (snake_case).
     * @param array  $parameters Extra parameters merged with the event payload.
     */
    public function onChange(string $action, array $parameters = []): self
    {
        return $this->setConfig('on_change', [
            'action'     => $action,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Trigger a backend action while the user types.
     *
     * @param string $action   Action name (snake_case).
     * @param array  $parameters Extra parameters merged with the event payload.
     */
    public function onInput(string $action, array $parameters = []): self
    {
        return $this->setConfig('on_input', [
            'action'     => $action,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Set debounce time in milliseconds for the onInput event.
     */
    public function debounce(int $ms): self
    {
        return $this->setConfig('debounce', $ms);
    }

    /**
     * Set the border color (CSS value, e.g. '#4f46e5', 'rgb(79, 70, 229)').
     *
     * @param string|null $color CSS color or null for default
     */
    public function borderColor(?string $color): self
    {
        return $this->setConfig('border_color', $color);
    }

    /**
     * Set the border width in pixels.
     *
     * @param int|null $width Border width or null for default (2px)
     */
    public function borderWidth(?int $width): self
    {
        return $this->setConfig('border_width', $width);
    }

    /**
     * Set the border radius in pixels.
     *
     * @param int|null $radius Border radius or null for default (10px)
     */
    public function borderRadius(?int $radius): self
    {
        return $this->setConfig('border_radius', $radius);
    }

    /**
     * Set the textarea background color.
     *
     * @param string|null $color CSS color or null to use theme default
     */
    public function backgroundColor(?string $color): self
    {
        return $this->setConfig('background_color', $color);
    }
}
