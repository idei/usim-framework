<?php

namespace Idei\Usim\Widgets;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Declarative Modal/Dialog widget.
 *
 * Renders into the 'modal' anchor of the USIM protocol seamlessly,
 * eliminating the need for detached static dialog services.
 */
class Modal extends Widget
{
    /**
     * @param list<Button> $actions
     */
    public function __construct(
        protected string $title,
        protected ?Widget $child = null,
        protected array $actions = [],
        protected ?string $onClose = 'close_modal',
        protected ?string $icon = null,
        protected int|Spacing $padding = 24,
        ?string $key = 'modal_dialog'
    ) {
        parent::__construct($key);
    }

    public function mount(Container $parent, string $contextClass): UIElement
    {
        $container = UI::container($this->key ?? 'modal_dialog', $contextClass)
            ->parent('modal')
            ->layout(LayoutType::VERTICAL)
            ->plain()
            ->padding(is_int($this->padding) ? Spacing::px($this->padding) : $this->padding)
            ->gap(Spacing::px(14))
            ->centerContent();

        if ($this->icon !== null) {
            $container->add(
                UI::label('modal_icon')
                    ->text($this->icon)
                    ->fontSize('48')
            );
        }

        $container->add(
            UI::label('modal_title')
                ->text($this->title)
                ->style('h3')
        );

        if ($this->child !== null) {
            $this->child->mount($container, $contextClass);
        }

        if (!empty($this->actions)) {
            $actionsRow = UI::container('modal_actions', $contextClass)
                ->layout(LayoutType::HORIZONTAL)
                ->plain()
                ->gap(Spacing::px(12))
                ->centerContent();

            foreach ($this->actions as $actionBtn) {
                if ($actionBtn instanceof Button) {
                    $actionBtn->mount($actionsRow, $contextClass);
                }
            }

            $container->add($actionsRow);
        }

        $parent->add($container);
        return $container;
    }
}
