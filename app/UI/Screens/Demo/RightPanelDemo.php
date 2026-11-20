<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\NewLabel;
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
 * Interactive Demo for the Workbench Right Panel (`right_panel` slot) and StatusBar events.
 */
class RightPanelDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected NewLabel $lbl_selected_summary;

    /**
     * Sample catalog of inspectable resources.
     *
     * @return array<string, array{id: string, title: string, category: string, status: string, region: string, latency: string, description: string}>
     */
    private function sampleResources(): array
    {
        return [
            'gateway' => [
                'id' => 'gateway',
                'title' => 'API Gateway',
                'category' => 'Edge Routing',
                'status' => 'Online (99.98%)',
                'region' => 'us-east-1',
                'latency' => '14 ms',
                'description' => 'Handles incoming UI event requests, Sanctum authentication, and per-tab USIM storage hydration.',
            ],
            'database' => [
                'id' => 'database',
                'title' => 'Primary Cluster',
                'category' => 'Database',
                'status' => 'Healthy',
                'region' => 'sa-east-1',
                'latency' => '4 ms',
                'description' => 'Stores organizational units, Spatie team-scoped roles/permissions, and i18n text keys.',
            ],
            'kiosk' => [
                'id' => 'kiosk',
                'title' => 'Lobby Smart TV',
                'category' => 'Device Kiosk',
                'status' => 'Paired (Device Guard)',
                'region' => 'Building A — Floor 1',
                'latency' => '22 ms',
                'description' => 'Runs KioskScreen with $layout = null (fullscreen standalone without sidebar, topbar, or status bar).',
            ],
            'worker' => [
                'id' => 'worker',
                'title' => 'Translation Sync Worker',
                'category' => 'Background Queue',
                'status' => 'Idle (0 pending)',
                'region' => 'eu-west-1',
                'latency' => '9 ms',
                'description' => 'Synchronizes DB translations with lang/ files and emits progress updates to the Workbench StatusBar.',
            ],
        ];
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $wrapper = UI::container('right_panel_demo_wrapper')
            ->card()
            ->maxWidth(Size::px(760))
            ->centerHorizontal()
            ->padding(Spacing::px(20))
            ->marginTop(Spacing::px(20))
            ->gap(Spacing::px(14));

        $wrapper->add(
            UI::newLabel('right_panel_demo_title')
                ->text(t('screens.demo.right_panel_demo.title'))
                ->size(TextSize::XL)
                ->fontWeight(FontWeight::BOLD)
        );

        $wrapper->add(
            UI::newLabel('right_panel_demo_intro')
                ->text(t('screens.demo.right_panel_demo.intro'))
                ->style('info')
                ->width(Size::full())
        );

        $this->lbl_selected_summary = UI::newLabel('lbl_selected_summary')
            ->text(t('screens.demo.right_panel_demo.initial_summary'))
            ->style('secondary')
            ->width(Size::full());
        $wrapper->add($this->lbl_selected_summary);

        // Resource cards grid
        $list = UI::container('right_panel_demo_items')
            ->plain()
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(10))
            ->width(Size::full());

        foreach ($this->sampleResources() as $key => $item) {
            $row = UI::container("resource_row_{$key}")
                ->subtle()
                ->layout(LayoutType::HORIZONTAL)
                ->justifyContent(JustifyContent::SPACE_BETWEEN)
                ->alignItems(AlignItems::CENTER)
                ->padding(Spacing::px(12))
                ->gap(Spacing::px(12))
                ->width(Size::full());

            $info = UI::container("resource_info_{$key}")
                ->plain()
                ->layout(LayoutType::VERTICAL)
                ->gap(Spacing::px(2))
                ->padding(Spacing::px(0))
                ->margin(Spacing::px(0));

            $info->add(
                UI::newLabel("lbl_resource_title_{$key}")
                    ->text($item['title'])
                    ->fontWeight(FontWeight::BOLD)
            );
            $info->add(
                UI::newLabel("lbl_resource_meta_{$key}")
                    ->text("{$item['category']} · {$item['status']}")
                    ->size(TextSize::SMALL)
                    ->style('secondary')
            );

            $row->add($info);
            $row->add(
                UI::button("btn_inspect_{$key}")
                    ->label(t('screens.demo.right_panel_demo.actions.inspect'))
                    ->style('primary')
                    ->size('small')
                    ->action('inspect_resource', ['resource_id' => $key])
            );

            $list->add($row);
        }

        $wrapper->add($list);

        // Controls row for closing right panel & testing StatusBar event integration
        $controls = UI::container('right_panel_demo_controls')
            ->plain()
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(10))
            ->padding(Spacing::px(0));

        $controls->add(
            UI::button('btn_close_right_panel')
                ->label(t('screens.demo.right_panel_demo.actions.close_panel'))
                ->style('secondary')
                ->action('close_right_panel')
        );

        $controls->add(
            UI::button('btn_status_ping')
                ->label(t('screens.demo.right_panel_demo.actions.send_status'))
                ->style('info')
                ->action('send_status_message')
        );

        $controls->add(
            UI::button('btn_status_clear')
                ->label(t('screens.demo.right_panel_demo.actions.clear_status'))
                ->style('warning')
                ->action('clear_status_message')
        );

        $wrapper->add($controls);
        $container->add($wrapper);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onInspectResource(array $params): void
    {
        $resourceId = is_string($params['resource_id'] ?? null) ? $params['resource_id'] : 'gateway';
        $resources = $this->sampleResources();
        $item = $resources[$resourceId] ?? $resources['gateway'];

        $opened = $this->showInto(
            RightPanelInspector::class,
            slot: 'right_panel',
            params: $item,
            updateBrowserUrl: false,
            force: true
        );

        if (! $opened) {
            $this->toast(t('screens.demo.right_panel_demo.no_right_panel_warning'), 'warning');

            return;
        }

        $this->lbl_selected_summary->text(
            t('screens.demo.right_panel_demo.inspecting_summary', ['title' => $item['title']])
        );

        event(new UsimEvent('status_bar_message', [
            'text' => t('screens.demo.right_panel_demo.status_inspecting', ['title' => $item['title']]),
        ]));
    }

    public function onCloseRightPanel(): void
    {
        $this->closeSlot('right_panel');
        $this->lbl_selected_summary->text(t('screens.demo.right_panel_demo.initial_summary'));
        event(new UsimEvent('status_bar_message', ['text' => '']));
    }

    public function onSendStatusMessage(): void
    {
        event(new UsimEvent('status_bar_message', [
            'text' => t('screens.demo.right_panel_demo.status_ping_message', ['time' => now()->format('H:i:s')]),
        ]));
        $this->toast(t('screens.demo.right_panel_demo.status_sent_toast'), 'info');
    }

    public function onClearStatusMessage(): void
    {
        event(new UsimEvent('status_bar_message', ['text' => '']));
    }
}
