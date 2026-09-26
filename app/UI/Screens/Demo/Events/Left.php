<?php

namespace App\UI\Screens\Demo\Events;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Str;

class Left extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected Input $input_text;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->padding(Spacing::px(10))
            ->width(Size::full())
            ->add(
                UI::input('input_text')
                    ->label('Input')
                    ->placeholder('Escribe aquí y presiona Enter...')
                    ->value('')
                    ->type('text')
                    ->width(Size::full())
                    ->autocomplete('off')
                    ->onInput('check_text', [])
                    ->onEnter('send_text', [])
                    ->debounce(500)
            );
    }

    /**
     * Recibe el evento del Input y valida si el texto no está vacío
     *
     * @param array<string, mixed> $params
     */
    public function onCheckText(array $params): void
    {
        $raw = $params['value'] ?? $params['input_text'] ?? '';
        $text = \is_string($raw) ? $raw : '';

        $this->input_text->value(Str::headline($text));
    }

    /**
     * Recibe el evento del Input y despacha un UsimEvent hacia las demás Screens abiertas
     *
     * @param array<string, mixed> $params
     */
    public function onSendText(array $params): void
    {
        $raw = $params['value'] ?? $params['input_text'] ?? '';
        $text = \is_string($raw) ? trim($raw) : '';

        if ($text === '') {
            return;
        }

        $this->input_text->value('');

        event(new UsimEvent('text_sent', [
            'text' => $text,
        ]));
    }
}

