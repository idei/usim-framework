<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Container;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class TabsDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;
    private const int TAB_CONTAINER_WIDTH = 600;
    private const int TAB_CONTAINER_HEIGHT = 300;

    protected Container $tabs_container;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $wrapper = UI::container('tabs_demo_wrapper')
            ->card()
            ->maxWidth(Size::px(1024))
            ->centerHorizontal()
            ->padding(Spacing::px(0))
            ->gap(Spacing::px(5))
            ->add(
                UI::label('tabs_demo_title')
                    ->text(t('screen.demo.tabs_demo.title'))
                    ->style('h3')
                    ->center()
                    ->width(Size::full())
            );

        $this->tabs_container = UI::container('tabs_container')
            ->width(Size::full())
            ->minWidth(Size::px(self::TAB_CONTAINER_WIDTH))
            ->maxWidth(Size::px(self::TAB_CONTAINER_WIDTH))
            ->padding(Spacing::px(5))
            ->minHeight(Size::px(self::TAB_CONTAINER_HEIGHT))
            ->maxHeight(Size::px(self::TAB_CONTAINER_HEIGHT))
            ->gap(Spacing::px(5));

        $this->tabs_container
            ->tabs($this->tabsDefaultConfig(), 'overview')
            ->onTabChange('tabs_switch')
            ->onTabClose('tabs_close');

        $this->tabs_container->add(
            UI::label('tabs_overview_title')
                ->text(t('screen.demo.tabs_demo.content.overview_title'))
                ->style('primary'),
            tab: 'overview'
        );

        $this->tabs_container->add(
            UI::label('tabs_overview_body')
                ->text(t('screen.demo.tabs_demo.content.overview_body')),
            tab: t('screen.demo.tabs_demo.tabs.overview.label')
        );

        $this->tabs_container->add(
            UI::label('tabs_activity_log')
                ->text(t('screen.demo.tabs_demo.content.activity_log'))
                ->style('secondary'),
            tab: t('screen.demo.tabs_demo.tabs.activity.label')
        );

        $this->tabs_container->add(
            UI::label('tabs_settings_copy')
                ->text(t('screen.demo.tabs_demo.content.settings_copy')),
            tab: 'settings'
        );

        $this->tabs_container->add(
            UI::label('tabs_advanced_info')
                ->text(t('screen.demo.tabs_demo.content.advanced_info'))
                ->style('info'),
            tab: 'advanced'
        );

        $wrapper->add($this->tabs_container);

        $container->add($wrapper);
    }

    /** @param array<string, mixed> $params */
    public function onTabsSwitch(array $params): void
    {
        $rawRequested = $params['tab_id'] ?? 'overview';
        $requested = is_scalar($rawRequested) || $rawRequested instanceof \Stringable ? (string) $rawRequested : '';
        $activeTab = $requested !== '' ? $requested : 'overview';
        $this->tabs_container->activeTab($activeTab);
    }

    /** @param array<string, mixed> $params */
    public function onTabsClose(array $params): void
    {
        $rawTabId = $params['tab_id'] ?? '';
        $tabId = is_scalar($rawTabId) || $rawTabId instanceof \Stringable ? (string) $rawTabId : '';
        if ($tabId === '') {
            return;
        }

        $this->toast(t('screen.demo.tabs_demo.toasts.tab_closed', ['tab' => $tabId]), 'success');
    }

    /** @return array<string, array<string, mixed>> */
    private function tabsDefaultConfig(): array
    {
        return [
            'overview' => [
                'label' => t('screen.demo.tabs_demo.tabs.overview.label'),
            ],
            'activity' => [
                'label' => t('screen.demo.tabs_demo.tabs.activity.label'),
                'closable' => true,
            ],
            'settings' => [
                'label' => t('screen.demo.tabs_demo.tabs.settings.label'),
                'closable' => true,
            ],
            'advanced' => [
                'label' => t('screen.demo.tabs_demo.tabs.advanced.label'),
            ],
        ];
    }
}
