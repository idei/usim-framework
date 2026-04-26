<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\UI;
use Idei\Usim\Screen;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Textarea;

/**
 * TextareaDemo
 *
 * Demonstrates the Textarea component in both modes:
 *  - Plain text  : width/height/maxLength/onChange
 *  - Markdown    : editor con toolbar, split-view preview y contador
 */
class TextareaDemo extends Screen
{
    protected Textarea $plain_textarea;
    protected Textarea $md_textarea;

    public static function getMenuLabel(): string
    {
        return 'Textarea Demo';
    }

    public static function getMenuIcon(): ?string
    {
        return '📝';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->title('Textarea — Demo')
            ->maxWidth('1024px')
            ->centerHorizontal()
            ->shadow(2)
            ->gap('6px')
            ->padding('30px');

        // ── Sección 1: texto plano ───────────────────────────────────────────
        $sectionPlain = UI::container('section_plain')
            ->plain()
            ->width('100%')
            ->gap('12px');

        /** @var Textarea $plainTextarea */
        $plainTextarea = UI::textarea('plain_textarea');

        $sectionPlain->add(
            $plainTextarea
                ->label('Notas rápidas')
                ->placeholder('Escribe algo aquí…')
                ->plainText()
                ->width('100%')
                ->height('200px')
                ->maxLength(300)
                ->helpText('Máximo 300 caracteres. Se guarda al salir del campo (onChange).')
                ->onChange('on_plain_saved')
        );

        $container->add($sectionPlain);

        // // ── Sección 2: markdown ──────────────────────────────────────────────
        $sectionMd = UI::container('section_md')
            ->plain()
            ->width('100%')
            ->gap('12px');

        $defaultMd = "## Bienvenido al editor Markdown\n\nEscribe **negrita**, *cursiva* o `código inline`.\n\n- Viñeta 1\n- Viñeta 2\n\n> Una cita de ejemplo.\n";

        /** @var Textarea $markdownTextarea */
        $markdownTextarea = UI::textarea('md_textarea');

        $sectionMd->add(
            $markdownTextarea
                ->label('Descripción con formato')
                ->placeholder('Escribe en Markdown…')
                ->markdown()
                ->value($defaultMd)
                ->width('100%')
                ->height('260px')
                ->maxLength(2000)
                ->helpText('Vista previa en tiempo real a la derecha.')
                ->onChange('on_md_saved')
        );

        $container->add($sectionMd);
    }

    public function onPlainSaved(array $params): void
    {
        $value = trim($params['value'] ?? '');
    }

    public function onMdSaved(array $params): void
    {
        $value = $params['value'] ?? '';
        $len   = mb_strlen($value);

        $this->toast("Markdown guardado ({$len} car.)");
    }
}
