<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

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
        return t('screen.demo.textarea_demo.menu_label');
    }

    public static function getMenuIcon(): ?string
    {
        return '📝';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->maxWidth(Size::px(1024))
            ->centerHorizontal()
            ->plain()
            ->gap(Spacing::px(5))
            ->padding(Spacing::px(0));

        $title = UI::label('textarea_demo_title')
            ->text(t('screen.demo.textarea_demo.title'))
            ->style('h2');

        $container->add($title);

        // ── Sección 1: texto plano ───────────────────────────────────────────
        $sectionPlain = UI::container('section_plain')
            ->plain()
            ->width(Size::full())
            ->gap(Spacing::px(12));

        /** @var Textarea $plainTextarea */
        $plainTextarea = UI::textarea('plain_textarea');

        $sectionPlain->add(
            $plainTextarea
                ->label('Notas rápidas')
                ->placeholder('Escribe algo aquí…')
                ->plainText()
                ->width(Size::full())
                ->height(Size::px(200))
                ->maxLength(300)
                ->helpText('Máximo 300 caracteres. Se guarda al salir del campo (onChange).')
                ->onChange('on_plain_saved')
        );

        $container->add($sectionPlain);

        // // ── Sección 2: markdown ──────────────────────────────────────────────
        $sectionMd = UI::container('section_md')
            ->plain()
            ->width(Size::full())
            ->gap(Spacing::px(12));

        $defaultMd = "## Bienvenido al editor Markdown\n\nEscribe **negrita**, *cursiva* o `código inline`.\n\n- Viñeta 1\n- Viñeta 2\n\n> Una cita de ejemplo.\n";

        /** @var Textarea $markdownTextarea */
        $markdownTextarea = UI::textarea('md_textarea');

        $sectionMd->add(
            $markdownTextarea
                ->label('Descripción con formato')
                ->placeholder('Escribe en Markdown…')
                ->markdown()
                ->value($defaultMd)
                ->width(Size::full())
                ->height(Size::px(300))
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
        $len = mb_strlen($value);

        $this->toast("Markdown guardado ({$len} car.)");
    }
}
