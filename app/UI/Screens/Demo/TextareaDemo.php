<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * TextareaDemo
 *
 * Demonstrates the Textarea component for multi-line plain text input:
 *  - Length constraints (minLength / maxLength) and live character counter
 *  - onChange and debounced onInput events
 */
class TextareaDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected Textarea $plain_textarea;
    protected Textarea $notes_textarea;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $wrapper = UI::container('wrapper')
            ->card()
            ->maxWidth(Size::px(1024))
            ->centerHorizontal()
            ->gap(Spacing::px(12))
            ->padding(Spacing::px(16));

        $title = UI::newLabel('textarea_demo_title')
            ->text(t('screens.demo.textarea_demo.title'))
            ->center()
            ->xl()
            ->bold();

        $wrapper->add($title);

        // Sección 1: texto plano con límite máximo
        $sectionPlain = UI::container('section_plain')
            ->plain()
            ->width(Size::full())
            ->gap(Spacing::px(12));

        $plainTextarea = UI::textarea('plain_textarea');

        $sectionPlain->add(
            $plainTextarea
                ->label('Notas rápidas')
                ->placeholder('Escribe algo aquí…')
                ->width(Size::full())
                ->height(Size::px(180))
                ->maxLength(300)
                ->helpText('Máximo 300 caracteres. Se guarda al salir del campo (onChange).')
                ->onChange('on_plain_saved')
        );

        $wrapper->add($sectionPlain);

        // Sección 2: texto plano con mínimo y máximo
        $sectionNotes = UI::container('section_notes')
            ->plain()
            ->width(Size::full())
            ->gap(Spacing::px(12));

        $notesTextarea = UI::textarea('notes_textarea');

        $sectionNotes->add(
            $notesTextarea
                ->label('Observaciones detalladas')
                ->placeholder('Escribe al menos 20 caracteres…')
                ->width(Size::full())
                ->height(Size::px(220))
                ->minLength(20)
                ->maxLength(1000)
                ->helpText('Entre 20 y 1000 caracteres. Contador dinámico integrado.')
                ->onChange('on_notes_saved')
        );

        $wrapper->add($sectionNotes);

        $container->add($wrapper);
    }

    /** @param array<string, mixed> $params */
    public function onPlainSaved(array $params): void
    {
        $rawValue = $params['value'] ?? '';
        $value = trim(is_scalar($rawValue) || $rawValue instanceof \Stringable ? (string) $rawValue : '');
        $len = mb_strlen($value);

        $this->toast("Texto guardado ({$len} car.)");
    }

    /** @param array<string, mixed> $params */
    public function onNotesSaved(array $params): void
    {
        $rawValue = $params['value'] ?? '';
        $value = trim(is_scalar($rawValue) || $rawValue instanceof \Stringable ? (string) $rawValue : '');
        $len = mb_strlen($value);

        $this->toast("Observaciones guardadas ({$len} car.)");
    }
}
