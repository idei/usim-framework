<?php

namespace App\UI\Screens\Demo\Events;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class Right extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected Textarea $textarea_output;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        /** @var Textarea $textarea */
        $textarea = UI::textarea('textarea_output');

        $container
            ->padding(Spacing::px(20))
            ->width(Size::full())
            ->border(Spacing::px(1))
            ->add(
                $textarea
                    ->label('Texto recibido')
                    ->placeholder('Aquí aparecerá lo enviado desde el Input...')
                    ->plainText()
                    ->value('')
                    ->readonly(true)
                    ->width(Size::full())
                    ->height(Size::px(200))
            );
    }

    /**
     * Se ejecuta automáticamente cuando otra Screen emite UsimEvent('text_sent', ['text' => ...])
     *
     * @param array<string, mixed> $params
     */
    public function onTextSent(array $params): void
    {
        $raw = $params['text'] ?? '';
        $text = is_string($raw) ? trim($raw) : '';

        if ($text === '') {
            return;
        }

        $current = (string) $this->textarea_output->get('value', '');
        $newValue = $current === '' ? $text : $current . "\n" . $text;

        $this->textarea_output->value($newValue);
    }
}

