<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Idei\Usim\Widgets\Box;
use Idei\Usim\Widgets\Button;
use Idei\Usim\Widgets\Card;
use Idei\Usim\Widgets\Column;
use Idei\Usim\Widgets\Modal;
use Idei\Usim\Widgets\Row;
use Idei\Usim\Widgets\Text;
use Idei\Usim\Widgets\Widget;

/**
 * Demonstrates the Declarative Reactive SDUI architecture (Flutter-style).
 *
 * Characteristics:
 * - Pure declarative build(): UI = f(State)
 * - Zero component reflection properties
 * - Zero manual mutations ($this->lbl->text(...))
 * - Native declarative modals as part of the widget tree
 * - Automatic reactive diffing
 */
class DeclarativeDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    // 1. Reactive State
    public int $counter = 0;
    public bool $showDialog = false;
    public ?string $lastAction = null;

    // 2. Action Handlers (Mutate state only)
    public function onIncrement(array $params = []): void
    {
        $this->counter++;
        $this->lastAction = "Incrementado a {$this->counter}";
    }

    public function onDecrement(array $params = []): void
    {
        $this->counter--;
        $this->lastAction = "Decrementado a {$this->counter}";
    }

    public function onReset(array $params = []): void
    {
        $this->counter = 0;
        $this->lastAction = 'Contador reiniciado';
    }

    public function onOpenDialog(array $params = []): void
    {
        $this->showDialog = true;
    }

    public function onCloseDialog(array $params = []): void
    {
        $this->showDialog = false;
    }

    public function onConfirmDialog(array $params = []): void
    {
        $this->showDialog = false;
        $this->lastAction = 'Acción confirmada desde el diálogo modal declarativo!';
    }

    // 3. Declarative Build Tree (Flutter-style composition)
    public function build(): ?Widget
    {
        $children = [
            new Card(
                title: 'Reactividad Automática basada en Estado',
                subtitle: 'El estado cambia y el árbol se recalcula automáticamente con UIDiffer',
                child: new Column(
                    gap: 15,
                    children: [
                        new Text(
                            text: "Valor actual del contador: {$this->counter}",
                            style: $this->counter > 0 ? 'h2' : ($this->counter < 0 ? 'warning' : 'h3'),
                            key: 'counter_value'
                        ),
                        new Row(
                            gap: 10,
                            children: [
                                new Button(label: '+ Incrementar', style: 'primary', onPressed: 'increment', key: 'btn_inc'),
                                new Button(label: '- Decrementar', style: 'secondary', onPressed: 'decrement', key: 'btn_dec'),
                                new Button(label: 'Reiniciar', style: 'danger', onPressed: 'reset', disabled: $this->counter === 0, key: 'btn_rst'),
                                new Button(label: 'Abrir Modal', style: 'warning', icon: 'window', onPressed: 'open_dialog', key: 'btn_modal'),
                            ]
                        ),
                    ]
                )
            ),
        ];

        if ($this->lastAction !== null) {
            $children[] = new Card(
                title: 'Último evento procesado',
                child: new Text($this->lastAction, style: 'success', key: 'status_txt')
            );
        }

        if ($this->showDialog) {
            $children[] = new Modal(
                title: 'Confirmación Declarativa',
                icon: '❓',
                child: new Text(
                    'Este modal es un Widget hijo condicional del árbol. No usa servicios estáticos ni bypasses.',
                    markdown: false
                ),
                actions: [
                    new Button(label: 'Cancelar', style: 'secondary', onPressed: 'close_dialog', key: 'modal_cancel'),
                    new Button(label: 'Confirmar', style: 'primary', onPressed: 'confirm_dialog', key: 'modal_confirm'),
                ],
                key: 'confirm_modal'
            );
        }

        return new Box(
            maxWidth: Size::px(700),
            padding: Spacing::px(24),
            plain: false,
            centerHorizontal: true,
            title: 'USIM Declarativo (Arquitectura Reactiva Flutter-style)',
            child: new Column(
                gap: 20,
                children: $children
            )
        );
    }
}
