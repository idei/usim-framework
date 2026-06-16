<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\UI;

class TabsDemo extends Screen
{
    protected Container $tabs_container;

    public static function getMenuLabel(): string
    {
        return t('screen.demo.tabs_demo.menu_label');
    }

    public static function getMenuIcon(): ?string
    {
        return '🗂️';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->plain()
            ->maxWidth('1024px')
            ->centerHorizontal()
            ->padding('12px 24px 24px 24px')
            ->gap('14px');

        $container->add(
            UI::label('tabs_demo_title')
                ->text(t('screen.demo.tabs_demo.title'))
                ->style('h2')
                ->width('100%')
        );

        $this->tabs_container = UI::container('tabs_container')
            ->width(100, '%')
            ->padding('16px')
            ->minHeight('300px')
            ->gap('10px');

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

        $container->add($this->tabs_container);
    }

    public function onTabsSwitch(array $params): void
    {
        $requested = (string) ($params['tab_id'] ?? 'overview');
        $activeTab = $requested !== '' ? $requested : 'overview';
        $this->tabs_container->activeTab($activeTab);
    }

    public function onTabsClose(array $params): void
    {
        $tabId = (string) ($params['tab_id'] ?? '');
        if ($tabId === '') {
            return;
        }

        $this->toast(t('screen.demo.tabs_demo.toasts.tab_closed', ['tab' => $tabId]), 'success');
    }

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
