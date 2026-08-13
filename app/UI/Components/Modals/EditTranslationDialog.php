<?php

namespace App\UI\Components\Modals;

use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class EditTranslationDialog
{
    /**
     * @param string $key
     * @param string $group
     * @param string $fallbackLanguageCode
     * @param string|null $selectedLanguageCode
     * @param string $fallbackText
     * @param string $selectedText
     * @param bool $fallbackNeedsReview
     * @param bool $selectedNeedsReview
     * @param string $submitAction
     * @param string|null $cancelAction
     * @param int|null $callerServiceId
     */
    public static function open(
        string $key,
        string $group,
        string $fallbackLanguageCode,
        ?string $selectedLanguageCode = null,
        string $fallbackText = '',
        string $selectedText = '',
        bool $fallbackNeedsReview = false,
        bool $selectedNeedsReview = false,
        string $submitAction = 'submit_update_translation',
        ?string $cancelAction = 'close_modal',
        ?int $callerServiceId = null
    ): void {
        $dialog = new self();
        $format = $dialog->getUI(
            $key,
            $group,
            $fallbackLanguageCode,
            $selectedLanguageCode,
            $fallbackText,
            $selectedText,
            $fallbackNeedsReview,
            $selectedNeedsReview,
            $submitAction,
            $cancelAction,
            $callerServiceId
        );
        app(UIChangesCollector::class)->add($format);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUI(
        string $key,
        string $group,
        string $fallbackLanguageCode,
        ?string $selectedLanguageCode,
        string $fallbackText = '',
        string $selectedText = '',
        bool $fallbackNeedsReview = false,
        bool $selectedNeedsReview = false,
        string $submitAction = 'submit_update_translation',
        ?string $cancelAction = 'close_modal',
        ?int $callerServiceId = null
    ): array {
        $container = UI::container('edit_translation_dialog')
            ->parent('modal')
            ->shadow(false)
            ->plain()
            ->padding(Spacing::px(20));

        $container->add(
            UI::label('translation_dialog_title')
                ->text('Edit Translation')
                ->style('info')
        );

        $container->add(
            UI::label('translation_dialog_key')
                ->text('Key: ' . $key)
                ->style('default')
        );

        $container->add(
            UI::label('translation_dialog_group')
                ->text('Group: ' . ($group !== '' ? $group : 'global'))
                ->style('default')
        );

        $container->add(UI::input('translation_key')->type('hidden')->value($key));
        $container->add(UI::input('fallback_language_code')->type('hidden')->value($fallbackLanguageCode));
        $container->add(UI::input('selected_language_code')->type('hidden')->value($selectedLanguageCode ?? ''));

        $container->add(
            UI::input('fallback_text')
                ->label('Fallback (' . strtoupper($fallbackLanguageCode) . ')')
                ->placeholder('Enter fallback translation')
                ->value($fallbackText)
                ->autocomplete('off')
                ->width(Size::full())
        );

        $container->add(
            UI::checkbox('fallback_mark_reviewed')
                ->label('Fallback translation reviewed by a human (no longer needs review)')
                ->checked(!$fallbackNeedsReview)
        );

        if ($selectedLanguageCode !== null && $selectedLanguageCode !== '') {
            $container->add(
                UI::input('selected_text')
                    ->label('Selected (' . strtoupper($selectedLanguageCode) . ')')
                    ->placeholder('Enter selected language translation')
                    ->value($selectedText)
                    ->autocomplete('off')
                    ->width(Size::full())
            );

            $container->add(
                UI::checkbox('selected_mark_reviewed')
                    ->label('Selected translation reviewed by a human (no longer needs review)')
                    ->checked(!$selectedNeedsReview)
            );
        }

        $buttons = UI::container('edit_translation_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->shadow(false)
            ->plain()
            ->gap(Spacing::px(10))
            ->padding(Spacing::each(Spacing::px(10)));

        if ($cancelAction) {
            $buttons->add(
                UI::button('btn_cancel_translation')
                    ->label('Cancel')
                    ->style('secondary')
                    ->action($cancelAction, [
                        '_caller_service_id' => $callerServiceId,
                    ])
            );
        }

        $buttons->add(
            UI::button('btn_submit_translation')
                ->label('Save Translation')
                ->style('primary')
                ->action($submitAction, [
                    '_caller_service_id' => $callerServiceId,
                ])
        );

        $container->add($buttons);

        return $container->toJson();
    }
}
