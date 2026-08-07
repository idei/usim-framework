<?php

namespace App\UI\Components\Modals;

use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Str;

class TermsDialog
{
    /**
     * @param mixed ...$params
     */
    public static function open(...$params): void
    {
        $dialog = new self();
        $format = $dialog->getUI(...$params);
        app(UIChangesCollector::class)->add($format);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUI(
        ?string $cancelAction = 'close_modal',
        ?int $callerServiceId = null
    ): array {
        $documentPath = resource_path(t('modal.terms.document'));
        $markdown = '';

        if (is_file($documentPath) && is_readable($documentPath)) {
            $markdown = (string) file_get_contents($documentPath);
        }

        $markdownHtml = Str::markdown($markdown);
        $scrollableDocumentHtml = '<div class="usim-markdown-content" style="height:60vh;max-height:60vh;overflow:auto;padding:16px;border:1px solid var(--ui-border, #d7dee8);border-radius:6px;">'
            . $markdownHtml
            . '</div>';

        $container = UI::container('terms_dialog')
            ->parent('modal')
            ->layout(LayoutType::VERTICAL)
            ->plain()
            ->shadow(false)
            ->gap(Spacing::px(16))
            ->padding(Spacing::px(5))
            ->width(Size::px(680))
            ->maxWidth(Size::vw(90));

        $container->add(
            UI::label('terms_dialog_document')
                ->html($scrollableDocumentHtml)
                ->inline(false)
                ->width(Size::full())
        );

        $buttons = UI::container('terms_dialog_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->plain()
            ->shadow(false)
            ->padding(Spacing::each(Spacing::px(8)));

        if ($cancelAction) {
            $buttons->add(
                UI::button('btn_close_terms')
                    ->label(t('modal.terms.close'))
                    ->style('secondary')
                    ->action($cancelAction, [
                        '_caller_service_id' => $callerServiceId,
                    ])
            );
        }

        $container->add($buttons);

        return $container->toJson();
    }
}
