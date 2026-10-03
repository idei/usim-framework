<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Auth;

use Idei\Usim\Components\Container;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Str;

/**
 * Legal Terms Screen
 *
 * Displays the legal terms and conditions for the application.
 */
class LegalTerms extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $wrapper = UI::container('legal_terms_wrapper')
            ->plain()
            ->width(Size::px(640))
            ->justifyContent(JustifyContent::CENTER)
            ->alignItems(AlignItems::CENTER);

        $card = UI::container('legal_terms_card')
            ->plain()
            ->minWidth(Size::px(640))
            ->width(Size::full())
            ->padding(Spacing::px(5));

        $card->add(UI::label('lbl_title')
            ->text(t('screen.auth.legal_terms.title'))
            ->style('h3'));

        $card->add(
            UI::label('legal_terms_document')
                ->html($this->termsText())
                ->inline(false)
                ->width(Size::full())
        );

        $buttons = UI::container('legal_terms_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->plain()
            ->shadow(false)
            ->gap(Spacing::px(5))
            ->padding(Spacing::zero());

        $buttons->add(
            UI::button('btn_close_terms')
                ->label(t('screen.auth.legal_terms.close'))
                ->style('secondary')
                ->action('close_legal_terms')
        );

        $card->add($buttons);
        $wrapper->add($card);
        $container->add($wrapper)->plain()->padding(Spacing::px(5));
    }

    private function termsText(): string
    {
        // Determine the path to the legal terms document based on localization and availability
        $relativeDoc = t('screen.auth.legal_terms.document');

        if ($relativeDoc === 'screen.auth.legal_terms.document' || empty($relativeDoc)) {
            $relativeDoc = t('modal.terms.document');
        }
        if ($relativeDoc === 'modal.terms.document' || empty($relativeDoc)) {
            $locale = app()->getLocale();
            $relativeDoc = "legal/terms.{$locale}.md";
        }

        $documentPath = resource_path($relativeDoc);
        if (!is_file($documentPath) || !is_readable($documentPath)) {
            $documentPath = resource_path('legal/terms.en.md');
        }

        $markdown = '';
        if (is_file($documentPath) && is_readable($documentPath)) {
            $markdown = (string) file_get_contents($documentPath);
        }

        $markdownHtml = Str::markdown($markdown);
        $scrollableDocumentHtml = '<div class="usim-markdown-content" style="height:60vh;max-height:60vh;overflow:auto;padding:8px;border:1px solid var(--ui-border, #d7dee8);border-radius:6px;">'
            . $markdownHtml
            . '</div>';

        return $scrollableDocumentHtml;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onCloseLegalTerms(array $params = []): void
    {
        if ($this->isOpenedAsModal()) {
            $this->closeModal();
            return;
        }
    }
}

