<?php

namespace App\UI\Screens;

use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\UI;

class HolaContexto extends Screen
{
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->title('Hola Contexto')
            ->padding('24px')
            ->add(
                UI::label('hola_contexto_label')
                    ->text('Hola Contexto')
            );
    }

    public function getAgentContext(): array
    {
        return [
            'purpose' => 'Mostrar un saludo simple de prueba para contexto IA.',
            'inputs' => [],
            'outputs' => ['ui'],
            'message' => 'Hola Contexto',
        ];
    }
}
