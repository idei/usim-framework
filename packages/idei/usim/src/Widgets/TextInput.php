<?php

namespace Idei\Usim\Widgets;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;

/**
 * Declarative TextInput widget.
 */
class TextInput extends Widget
{
    /**
     * @param array<string, mixed> $onInputParams
     */
    public function __construct(
        protected string $name,
        protected ?string $label = null,
        protected ?string $placeholder = null,
        protected ?string $value = null,
        protected string $inputType = 'text',
        protected bool $required = false,
        protected bool $disabled = false,
        protected ?string $onInput = null,
        protected array $onInputParams = [],
        protected int $debounce = 300,
        protected int|Size|null $width = null,
        ?string $key = null
    ) {
        parent::__construct($key ?? $name);
    }

    public function mount(Container $parent, string $contextClass): UIElement
    {
        $input = new \Idei\Usim\Components\Input($this->name, $contextClass);
        $input->inputType($this->inputType)
            ->required($this->required)
            ->enabled(!$this->disabled);

        if ($this->label !== null) {
            $input->label($this->label);
        }

        if ($this->placeholder !== null) {
            $input->placeholder($this->placeholder);
        }

        if ($this->value !== null) {
            $input->value($this->value);
        }

        if ($this->onInput !== null) {
            $input->onInput($this->onInput, $this->onInputParams)->debounce($this->debounce);
        }

        if ($this->width !== null) {
            $input->width(is_int($this->width) ? Size::px($this->width) : $this->width);
        }

        $parent->add($input);
        return $input;
    }
}
