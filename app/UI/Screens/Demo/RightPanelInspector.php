<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\FontWeight;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\TextSize;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Inspector Screen mounted dynamically inside WorkbenchLayout's `right_panel` slot.
 */
class RightPanelInspector extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;
    public static ?string $layout = null;

    protected string $state_title = 'Inspector';
    protected Input $inp_inspector_note;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $title = is_string($params['title'] ?? null) ? $params['title'] : 'Resource Inspector';
        $category = is_string($params['category'] ?? null) ? $params['category'] : 'General';
        $status = is_string($params['status'] ?? null) ? $params['status'] : 'Active';
        $region = is_string($params['region'] ?? null) ? $params['region'] : 'local';
        $latency = is_string($params['latency'] ?? null) ? $params['latency'] : '—';
        $description = is_string($params['description'] ?? null) ? $params['description'] : '';

        $this->state_title = $title;

        $container
            ->plain()
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(16))
            ->gap(Spacing::px(12))
            ->width(Size::full());

        // Header with title + Close button
        $header = UI::container('inspector_header')
            ->plain()
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0))
            ->width(Size::full());

        $header->add(
            UI::newLabel('lbl_inspector_title')
                ->text($title)
                ->size(TextSize::LARGE)
                ->fontWeight(FontWeight::BOLD)
        );

        $header->add(
            UI::button('btn_inspector_close')
                ->label('✕')
                ->style('secondary')
                ->size('xs')
                ->tooltip(t('screens.demo.right_panel_demo.actions.close_panel'))
                ->action('close_inspector')
        );

        $container->add($header);

        // Metadata card
        $metaCard = UI::container('inspector_meta_card')
            ->card()
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(12))
            ->gap(Spacing::px(6))
            ->width(Size::full());

        $metaCard->add(UI::newLabel('lbl_inspector_category')->text("Category: {$category}")->size(TextSize::SMALL));
        $metaCard->add(UI::newLabel('lbl_inspector_status')->text("Status: {$status}")->style('success')->size(TextSize::SMALL));
        $metaCard->add(UI::newLabel('lbl_inspector_region')->text("Location: {$region}")->size(TextSize::SMALL));
        $metaCard->add(UI::newLabel('lbl_inspector_latency')->text("Latency: {$latency}")->size(TextSize::SMALL));

        $container->add($metaCard);

        if ($description !== '') {
            $container->add(
                UI::newLabel('lbl_inspector_description')
                    ->text($description)
                    ->style('info')
                    ->width(Size::full())
            );
        }

        // Interactive action inside right panel
        $this->inp_inspector_note = UI::input('inp_inspector_note')
            ->label(t('screens.demo.right_panel_demo.inspector.note_label'))
            ->placeholder(t('screens.demo.right_panel_demo.inspector.note_placeholder'))
            ->width(Size::full());
        $container->add($this->inp_inspector_note);

        $actions = UI::container('inspector_actions')
            ->plain()
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(8))
            ->padding(Spacing::px(0))
            ->width(Size::full());

        $actions->add(
            UI::button('btn_inspector_notify')
                ->label(t('screens.demo.right_panel_demo.inspector.publish_status'))
                ->style('primary')
                ->size('small')
                ->action('publish_note')
        );

        $actions->add(
            UI::button('btn_inspector_dismiss')
                ->label(t('screens.demo.right_panel_demo.actions.close_panel'))
                ->style('secondary')
                ->size('small')
                ->action('close_inspector')
        );

        $container->add($actions);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onPublishNote(array $params): void
    {
        $rawNote = $params['inp_inspector_note'] ?? '';
        $note = is_string($rawNote) && trim($rawNote) !== '' ? trim($rawNote) : 'Checked OK';

        event(new UsimEvent('status_bar_message', [
            'text' => "{$this->state_title}: {$note}",
        ]));

        $this->toast("{$this->state_title} — {$note}", 'success');
    }

    public function onCloseInspector(): void
    {
        $this->closeSlot('right_panel');
        event(new UsimEvent('status_bar_message', ['text' => '']));
    }
}
