<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\RichText;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * RichTextDemo
 *
 * Demonstrates the RichText WYSIWYG HTML editor component powered by SunEditor v3:
 *  - Standard toolbar with headings, formatting, colors, tables, links and editable images (base64)
 *  - Basic compact toolbar with character limit
 *  - Automatic USIM light/dark theme sync and form value submission
 */
class RichTextDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected RichText $article_editor;
    protected RichText $summary_editor;
    protected NewLabel $saved_summary;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $wrapper = UI::container('wrapper')
            ->card()
            ->maxWidth(Size::px(1024))
            ->centerHorizontal()
            ->gap(Spacing::px(16))
            ->padding(Spacing::px(16));

        $title = UI::newLabel('rich_text_demo_title')
            ->text(t('screens.demo.rich_text_demo.title'))
            ->center()
            ->xl()
            ->bold();

        $wrapper->add($title);

        $initialHtml = '<h2>Bienvenido al editor RichText</h2>'
            . '<p>Edita contenido en modo <strong>WYSIWYG</strong> con formato enriquecido, tablas, enlaces e <em>imágenes editables</em> (puedes pegar o subir imágenes, redimensionarlas, rotarlas y alinearlas).</p>'
            . '<ul><li>Formato visual inmediato sin doble panel</li><li>Imágenes en base64 listas para usar</li><li>Sincronización automática con tema claro y oscuro</li></ul>';

        $this->article_editor = UI::richText('article_editor')
            ->label('Artículo (toolbar estándar + imágenes)')
            ->placeholder('Escribe o pega contenido enriquecido aquí…')
            ->standardToolbar()
            ->allowImages(true)
            ->value($initialHtml)
            ->width(Size::full())
            ->height(Size::px(320))
            ->charCounter(true)
            ->wordCounter(true)
            ->helpText('Puedes pegar o subir imágenes y editar su tamaño, rotación, alineación y pie de foto.')
            ->onChange('on_article_saved');

        $wrapper->add($this->article_editor);

        $this->summary_editor = UI::richText('summary_editor')
            ->label('Resumen corto (toolbar básica, sin imágenes)')
            ->placeholder('Escribe un resumen breve…')
            ->basicToolbar()
            ->allowImages(false)
            ->width(Size::full())
            ->height(Size::px(160))
            ->maxLength(300)
            ->helpText('Toolbar compacta con límite de 300 caracteres visibles.')
            ->onChange('on_summary_saved');

        $wrapper->add($this->summary_editor);

        $actions = UI::container('actions')
            ->plain()
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(10));

        $actions->add(
            UI::button('btn_save_demo')
                ->label('Guardar formulario')
                ->style('primary')
                ->action('save_form')
        );

        $wrapper->add($actions);

        $this->saved_summary = UI::newLabel('saved_summary')
            ->text('Modifica el contenido o pulsa "Guardar formulario" para verificar los datos enviados.')
            ->muted()
            ->small();

        $wrapper->add($this->saved_summary);

        $container->add($wrapper);
    }

    /** @param array<string, mixed> $params */
    public function onArticleSaved(array $params): void
    {
        $html = $this->extractString($params['value'] ?? '');
        $plainLen = mb_strlen(trim(strip_tags($html)));

        $this->saved_summary->text("Último guardado automático del artículo: {$plainLen} caracteres visibles (" . strlen($html) . ' bytes HTML).');
        $this->toast("Artículo guardado ({$plainLen} car.)");
    }

    /** @param array<string, mixed> $params */
    public function onSummarySaved(array $params): void
    {
        $html = $this->extractString($params['value'] ?? '');
        $plainLen = mb_strlen(trim(strip_tags($html)));

        $this->toast("Resumen guardado ({$plainLen} car.)");
    }

    /** @param array<string, mixed> $params */
    public function onSaveForm(array $params): void
    {
        $articleHtml = $this->extractString($params['article_editor'] ?? $this->article_editor->getValue());
        $summaryHtml = $this->extractString($params['summary_editor'] ?? $this->summary_editor->getValue());

        $articleLen = mb_strlen(trim(strip_tags($articleHtml)));
        $summaryLen = mb_strlen(trim(strip_tags($summaryHtml)));

        $this->saved_summary->text("Formulario recibido — Artículo: {$articleLen} car. visibles | Resumen: {$summaryLen} car. visibles.");
        $this->toast('Formulario RichText guardado correctamente', 'success');
    }

    private function extractString(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }
}
