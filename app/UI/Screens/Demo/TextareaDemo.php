<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\UI;
use Idei\Usim\Screen;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Components\Label;

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
    // protected Textarea $md_textarea;
    protected Label    $lbl_plain_result;

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
            ->padding('30px')
            ->gap('28px');

        // ── Sección 1: texto plano ───────────────────────────────────────────
        $sectionPlain = UI::container('section_plain')
            ->plain()
            ->width('100%')
            ->gap('12px');

        $sectionPlain->add(
            UI::label('lbl_plain_title')
                ->text('Modo: Texto plano')
                ->style('subtitle')
                ->width('100%')
        );

        $sectionPlain->add(
            UI::textarea('plain_textarea')
                ->label('Notas rápidas')
                ->placeholder('Escribe algo aquí…')
                ->plainText()
                ->width('100%')
                ->height('200px')
                ->maxLength(300)
                ->borderColor('#4f46e5')
                ->borderWidth(3)
                ->borderRadius(5)
                ->helpText('Máximo 300 caracteres. Se guarda al salir del campo (onChange).')
                ->onChange('on_plain_saved')
        );

        $sectionPlain->add(
            UI::label('lbl_plain_result')
                ->text('Estado: pendiente de guardar. Haz click fuera del campo para enviar.')
                ->style('muted')
                ->width('100%')
        );

        $container->add($sectionPlain);

        // // ── Sección 2: markdown ──────────────────────────────────────────────
        $sectionMd = UI::container('section_md')
            ->plain()
            ->width('100%')
            ->gap('12px');

        $sectionMd->add(
            UI::label('lbl_md_title')
                ->text('Modo: Markdown')
                ->style('subtitle')
                ->width('100%')
        );

        $defaultMd = "## Bienvenido al editor Markdown\n\nEscribe **negrita**, *cursiva* o `código inline`.\n\n- Viñeta 1\n- Viñeta 2\n\n> Una cita de ejemplo.\n";

        $sectionMd->add(
            UI::textarea('md_textarea')
                ->label('Descripción con formato')
                ->placeholder('Escribe en Markdown…')
                ->markdown()
                ->value($defaultMd)
                ->width('100%')
                ->height('260px')
                ->borderColor('#16a34a')
                ->borderWidth(2)
                ->borderRadius(8)
                ->maxLength(2000)
                ->helpText('Vista previa en tiempo real a la derecha.')
                ->onChange('on_md_saved')
        );

        $container->add($sectionMd);
    }

    protected function postLoadUI(): void
    {
        $this->lbl_plain_result->text('Estado: pendiente de guardar. Haz click fuera del campo para enviar.')->style('muted');
    }

    // ── Handlers ──────────────────────────────────────────────────────────────

    public function onPlainSaved(array $params): void
    {
        $value = trim($params['value'] ?? '');
        $len   = mb_strlen($value);

        if ($len === 0) {
            $this->lbl_plain_result->text('El campo está vacío.')->style('warning');
            return;
        }

        $this->lbl_plain_result
            ->text("✅ Guardado ({$len} caracteres): \"" . mb_substr($value, 0, 60) . (mb_strlen($value) > 60 ? '…' : '') . '"')
            ->style('success');
    }

    public function onMdSaved(array $params): void
    {
        $value = $params['value'] ?? '';
        $len   = mb_strlen($value);

        $this->toast("Markdown guardado ({$len} car.)");
    }
}
