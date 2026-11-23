<?php

namespace Idei\Usim\Components;

/**
 * Builder for RichText (WYSIWYG HTML editor) UI components.
 *
 * Powered by SunEditor v3 (lazy-loaded on the client) with toolbar presets,
 * rich image editing (base64 by default or custom upload URL), character/word
 * counters, light/dark USIM theme sync, and backend change/input events.
 */
class RichText extends UIComponent
{
    public function __construct(?string $name = null)
    {
        parent::__construct($name);
        $this->type = 'rich_text';
        $this->config['type'] = 'rich_text';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'label' => null,
            'placeholder' => null,
            'value' => null,
            'width' => null,
            'height' => null,
            'min_height' => null,
            'max_height' => null,
            'toolbar' => 'standard', // 'basic' | 'standard' | 'full' | array<int, string|list<string>>
            'allow_images' => true,
            'image_upload_url' => null,
            'max_length' => null,
            'char_counter' => false,
            'word_counter' => false,
            'statusbar' => true,
            'lang' => null,          // 'es' | 'en' | null (auto-detect from document lang, default 'es')
            'required' => false,
            'disabled' => false,
            'readonly' => false,
            'error' => null,
            'help_text' => null,
            'on_change' => null,
            'on_input' => null,
            'debounce' => null,
            'border_color' => null,
            'border_width' => null,
            'border_radius' => null,
        ];
    }

    /** Set the field label. */
    public function label(string $label): self
    {
        return $this->setConfig('label', $label);
    }

    /** Set placeholder text shown when the editor is empty. */
    public function placeholder(string $placeholder): self
    {
        return $this->setConfig('placeholder', $placeholder);
    }

    /** Set the current HTML content. */
    public function value(?string $value): self
    {
        return $this->setConfig('value', $value ?? '');
    }

    /**
     * Get the current HTML value.
     *
     * @return string The current HTML value, or an empty string if not set.
     */
    public function getValue(): string
    {
        $value = $this->getConfig()['value'] ?? '';

        return \is_string($value) ? $value : '';
    }

    /**
     * Configure toolbar preset ('basic' | 'standard' | 'full') or a custom SunEditor buttonList array.
     *
     * @param  string|array<int, mixed>  $toolbar
     */
    public function toolbar(string|array $toolbar): self
    {
        return $this->setConfig('toolbar', $toolbar);
    }

    /** Shortcut: compact toolbar (basic formatting, lists, link, undo/redo). */
    public function basicToolbar(): self
    {
        return $this->toolbar('basic');
    }

    /** Shortcut: balanced toolbar (headings, formatting, colors, alignment, lists, tables, links, images). */
    public function standardToolbar(): self
    {
        return $this->toolbar('standard');
    }

    /** Shortcut: full toolbar (all formatting, tables, media, code view, preview, fullscreen). */
    public function fullToolbar(): self
    {
        return $this->toolbar('full');
    }

    /** Enable or disable image insertion and editing. */
    public function allowImages(bool $allow = true): self
    {
        return $this->setConfig('allow_images', $allow);
    }

    /**
     * Optional upload endpoint for images.
     * When null (default), inserted/pasted images are embedded as base64 data URIs.
     */
    public function imageUploadUrl(?string $url): self
    {
        return $this->setConfig('image_upload_url', $url);
    }

    /**
     * Set maximum number of visible text characters allowed.
     * Automatically enables the editor's character counter when set.
     */
    public function maxLength(?int $maxLength): self
    {
        return $this->setConfig('max_length', $maxLength);
    }

    /** Show or hide the visible-character counter in the status bar. */
    public function charCounter(bool $enabled = true): self
    {
        return $this->setConfig('char_counter', $enabled);
    }

    /** Show or hide the word counter in the status bar. */
    public function wordCounter(bool $enabled = true): self
    {
        return $this->setConfig('word_counter', $enabled);
    }

    /** Show or hide the bottom status bar. */
    public function statusbar(bool $enabled = true): self
    {
        return $this->setConfig('statusbar', $enabled);
    }

    /** Set the editor UI language ('es' or 'en'). */
    public function lang(?string $lang): self
    {
        return $this->setConfig('lang', $lang);
    }

    /** Mark the field as required. */
    public function required(bool $required = true): self
    {
        return $this->setConfig('required', $required);
    }

    /** Disable the editor. */
    public function disabled(bool $disabled = true): self
    {
        return $this->setConfig('disabled', $disabled);
    }

    /** Make the editor read-only. */
    public function readonly(bool $readonly = true): self
    {
        return $this->setConfig('readonly', $readonly);
    }

    /**
     * Set or clear an error state.
     *
     * @param  string|null  $error  Error message, or null to clear.
     */
    public function error(?string $error): self
    {
        return $this->setConfig('error', $error);
    }

    /** Set tooltip help text displayed next to the label. */
    public function helpText(?string $helpText): self
    {
        return $this->setConfig('help_text', $helpText);
    }

    /**
     * Trigger a backend action when the editor loses focus after changes.
     *
     * @param  string  $action  Action name (snake_case).
     * @param  array<string, mixed>  $parameters  Extra parameters merged with the event payload.
     */
    public function onChange(string $action, array $parameters = []): self
    {
        return $this->setConfig('on_change', [
            'action' => $action,
            'parameters' => $parameters,
        ]);
    }

    /**
     * Trigger a backend action while the user edits content.
     *
     * @param  string  $action  Action name (snake_case).
     * @param  array<string, mixed>  $parameters  Extra parameters merged with the event payload.
     */
    public function onInput(string $action, array $parameters = []): self
    {
        return $this->setConfig('on_input', [
            'action' => $action,
            'parameters' => $parameters,
        ]);
    }

    /** Set debounce time in milliseconds for the onInput event. */
    public function debounce(int $ms): self
    {
        return $this->setConfig('debounce', $ms);
    }

    /** Set custom border color (CSS value). */
    public function borderColor(?string $color): self
    {
        return $this->setConfig('border_color', $color);
    }

    /** Set custom border width in pixels. */
    public function borderWidth(?int $width): self
    {
        return $this->setConfig('border_width', $width);
    }

    /** Set custom border radius in pixels. */
    public function borderRadius(?int $radius): self
    {
        return $this->setConfig('border_radius', $radius);
    }
}
