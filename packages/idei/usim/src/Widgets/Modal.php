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
        protected ?int $timeout = null,
        protected bool $showCountdown = false,
        protected bool $showCloseCross = true,
        ?string $key = 'modal_dialog'
    ) {
        parent::__construct($key);
    }

    public function mount(Container $parent, string $contextClass): UIElement
    {
        $modalKey = $this->key ?? 'modal_dialog';
        $container = UI::container($modalKey, $contextClass)
            ->parent('modal')
            ->layout(LayoutType::VERTICAL)
            ->plain()
            ->padding(is_int($this->padding) ? Spacing::px($this->padding) : $this->padding)
            ->gap(Spacing::px(16))
            ->centerContent();

        // Configuración de auto-cierre con timer si se especifica timeout (en segundos)
        if ($this->timeout !== null && $this->timeout > 0) {
            $container->config('_timeout', $this->timeout);
            $container->config('_timeout_ms', $this->timeout * 1000);
            $container->config('_timeout_action', $this->onClose ?? 'close_modal');
            $container->config('_show_countdown', $this->showCountdown);
        }

        // Header con título, icono y botón de cruz para cerrar
        if ($this->showCloseCross) {
            $headerRow = UI::container($modalKey . '_header', $contextClass)
                ->layout(LayoutType::HORIZONTAL)
                ->justifyContent(\Idei\Usim\Enums\JustifyContent::SPACE_BETWEEN)
                ->alignItems(\Idei\Usim\Enums\AlignItems::CENTER)
                ->width(\Idei\Usim\ValueObjects\Size::full())
                ->plain();

            $titleBox = UI::container($modalKey . '_title_box', $contextClass)
                ->layout(LayoutType::HORIZONTAL)
                ->alignItems(\Idei\Usim\Enums\AlignItems::CENTER)
                ->gap(Spacing::px(8))
                ->plain();

            if ($this->icon !== null) {
                $titleBox->add(
                    UI::label($modalKey . '_header_icon', $contextClass)
                        ->text($this->icon)
                        ->fontSize('24')
                );
            }

            $titleBox->add(
                UI::label($modalKey . '_header_title', $contextClass)
                    ->text($this->title)
                    ->style('h3')
            );

            $headerRow->add($titleBox);

            $headerRight = UI::container($modalKey . '_header_right', $contextClass)
                ->layout(LayoutType::HORIZONTAL)
                ->alignItems(\Idei\Usim\Enums\AlignItems::CENTER)
                ->gap(Spacing::px(8))
                ->plain();

            if ($this->timeout !== null && $this->showCountdown) {
                $headerRight->add(
                    UI::label('countdown', $contextClass)
                        ->text("{$this->timeout} s")
                        ->style('secondary')
                );
            }

            $closeCross = UI::button($modalKey . '_cross', $contextClass)
                ->label('✕')
                ->action($this->onClose ?? 'close_modal')
                ->plain()
                ->style('secondary')
                ->tooltip('Cerrar');

            $headerRight->add($closeCross);
            $headerRow->add($headerRight);
            $container->add($headerRow);
        } else {
            if ($this->icon !== null) {
                $container->add(
                    UI::label('modal_icon', $contextClass)
                        ->text($this->icon)
                        ->fontSize('48')
                );
            }

            $container->add(
                UI::label('modal_title', $contextClass)
                    ->text($this->title)
                    ->style('h3')
            );
        }

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
        $container->parent('modal');
        return $container;
    }
}
