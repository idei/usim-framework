<?php

namespace App\UI\Components\Modals;

use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UIBuilder;
use Idei\Usim\UIChangesCollector;

class EditTranslationDialog
{
    public static function open(...$params): void
    {
        $dialog = new self();
        $format = $dialog->getUI(...$params);
        app(UIChangesCollector::class)->add($format);
    }

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
        $container = UIBuilder::container('edit_translation_dialog')
            ->parent('modal')
            ->shadow(false)
            ->plain()
            ->padding('20px');

        $container->add(
            UIBuilder::label('translation_dialog_title')
                ->text('Edit Translation')
                ->style('info')
        );

        $container->add(
            UIBuilder::label('translation_dialog_key')
                ->text('Key: ' . $key)
                ->style('default')
        );

        $container->add(
            UIBuilder::label('translation_dialog_group')
                ->text('Group: ' . ($group !== '' ? $group : 'global'))
                ->style('default')
        );

        $container->add(UIBuilder::input('translation_key')->type('hidden')->value($key));
        $container->add(UIBuilder::input('fallback_language_code')->type('hidden')->value($fallbackLanguageCode));
        $container->add(UIBuilder::input('selected_language_code')->type('hidden')->value($selectedLanguageCode ?? ''));

        $container->add(
            UIBuilder::input('fallback_text')
                ->label('Fallback (' . strtoupper($fallbackLanguageCode) . ')')
                ->placeholder('Enter fallback translation')
                ->value($fallbackText)
                ->autocomplete('off')
                ->width('100%')
        );

        $container->add(
            UIBuilder::checkbox('fallback_mark_reviewed')
                ->label('Fallback translation reviewed by a human (no longer needs review)')
                ->checked(!$fallbackNeedsReview)
        );

        if ($selectedLanguageCode !== null && $selectedLanguageCode !== '') {
            $container->add(
                UIBuilder::input('selected_text')
                    ->label('Selected (' . strtoupper($selectedLanguageCode) . ')')
                    ->placeholder('Enter selected language translation')
                    ->value($selectedText)
                    ->autocomplete('off')
                    ->width('100%')
            );

            $container->add(
                UIBuilder::checkbox('selected_mark_reviewed')
                    ->label('Selected translation reviewed by a human (no longer needs review)')
                    ->checked(!$selectedNeedsReview)
            );
        }

        $buttons = UIBuilder::container('edit_translation_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->shadow(false)
            ->plain()
            ->gap('10px')
            ->padding('10px 0 0 0');

        if ($cancelAction) {
            $buttons->add(
                UIBuilder::button('btn_cancel_translation')
                    ->label('Cancel')
                    ->style('secondary')
                    ->action($cancelAction, [
                        '_caller_service_id' => $callerServiceId,
                    ])
            );
        }

        $buttons->add(
            UIBuilder::button('btn_submit_translation')
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
