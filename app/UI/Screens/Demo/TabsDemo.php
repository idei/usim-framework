<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;

class TabsDemo extends Screen
{
    protected Container $tabs_workspace;
    protected Label $tabs_reports_state;

    protected string $store_active_tab = 'overview';
    protected bool $store_reports_disabled = true;
    protected array $store_closed_tabs = [];

    public static function getMenuLabel(): string
    {
        return 'Tabs Demo';
    }

    public static function getMenuIcon(): ?string
    {
        return '🗂️';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->plain()
            ->maxWidth('1100px')
            ->centerHorizontal()
            ->padding('12px 24px 24px 24px')
            ->gap('14px');

        $container->add(
            UI::label('tabs_demo_title')
                ->text('Contenedor con Tabs')
                ->style('h2')
                ->width('100%')
        );

        $container->add(
            UI::label('tabs_demo_intro')
                ->text('Este demo muestra tabs con colores por pestaña, pestañas deshabilitadas, cierre y cambios sincronizados con backend.')
                ->style('info')
                ->width('100%')
        );

        $toolbar = UI::container('tabs_demo_toolbar')
            ->plain()
            ->layout(LayoutType::HORIZONTAL)
            ->gap('10px')
            ->width('100%');

        $toolbar->add(
            UI::button('btn_enable_reports')
                ->label('Habilitar Reportes')
                ->style('primary')
                ->action('enable_reports_tab')
        );

        $toolbar->add(
            UI::button('btn_reset_tabs_demo')
                ->label('Restaurar Tabs')
                ->style('secondary')
                ->action('reset_tabs_demo')
        );

        $container->add($toolbar);

        $this->tabs_workspace = UI::container('tabs_workspace')
            ->width('100%')
            ->padding('16px')
            ->gap('10px');

        $this->configureTabsWorkspace();

        $this->tabs_workspace->add(
            UI::label('tabs_overview_title')
                ->text('Resumen general')
                ->style('primary'),
            tab: 'overview'
        );

        $this->tabs_workspace->add(
            UI::label('tabs_overview_body')
                ->text('La tab de resumen usa asignacion por id y define el estado inicial del contenedor.'),
            tab: 'Resumen'
        );

        $this->tabs_workspace->add(
            UI::label('tabs_activity_log')
                ->text('La tab Actividad fue asociada usando su nombre visible, no el id interno.')
                ->style('secondary'),
            tab: 'Actividad'
        );

        $this->tabs_reports_state = UI::label('tabs_reports_state')
            ->text($this->store_reports_disabled
                ? 'Reportes esta deshabilitada hasta que se pulse el boton de habilitar.'
                : 'Reportes ya fue habilitada desde backend y ahora puede ser la tab activa.')
            ->style($this->store_reports_disabled ? 'warning' : 'success');

        $this->tabs_workspace->add($this->tabs_reports_state, tab: 'reports');

        $this->tabs_workspace->add(
            UI::label('tabs_settings_copy')
                ->text('Configuracion es closable. Al cerrarla, backend actualiza la definicion de tabs.'),
            tab: 'settings'
        );

        $this->tabs_workspace->add(
            UI::label('tabs_advanced_info')
                ->text('Esta tab no tiene colores definidos, por lo que usa los colores por defecto del tema mediante herencia CSS.')
                ->style('info'),
            tab: 'advanced'
        );

        $container->add($this->tabs_workspace);
    }

    public function onTabsSwitch(array $params): void
    {
        $requested = (string) ($params['tab_id'] ?? 'overview');
        $this->store_active_tab = $requested !== '' ? $requested : 'overview';
        $this->configureTabsWorkspace();
    }

    public function onTabsClose(array $params): void
    {
        $tabId = (string) ($params['tab_id'] ?? '');
        if ($tabId === '') {
            return;
        }

        if (!in_array($tabId, $this->store_closed_tabs, true)) {
            $this->store_closed_tabs[] = $tabId;
        }

        if ($this->store_active_tab === $tabId) {
            $this->store_active_tab = 'overview';
        }

        $this->configureTabsWorkspace();
    }

    public function onEnableReportsTab(array $params): void
    {
        $this->store_reports_disabled = false;
        $this->store_active_tab = 'reports';
        $this->configureTabsWorkspace();

        $this->tabs_reports_state
            ->text('Reportes ya fue habilitada desde backend y ahora puede ser la tab activa.')
            ->style('success');
    }

    public function onResetTabsDemo(array $params): void
    {
        $this->store_active_tab = 'overview';
        $this->store_reports_disabled = true;
        $this->store_closed_tabs = [];
        $this->configureTabsWorkspace();

        $this->tabs_reports_state
            ->text('Reportes esta deshabilitada hasta que se pulse el boton de habilitar.')
            ->style('warning');
    }

    private function configureTabsWorkspace(): void
    {
        $tabs = [];

        $tabs[] = [
            'id' => 'overview',
            'name' => 'overview',
            'label' => 'Resumen',
            'color' => '#dbeafe',
            'text_color' => '#1d4ed8',
            'active_color' => '#2563eb',
            'active_text_color' => '#ffffff',
        ];

        if (!in_array('activity', $this->store_closed_tabs, true)) {
            $tabs[] = [
                'id' => 'activity',
                'name' => 'activity',
                'label' => 'Actividad',
                'closable' => true,
                'color' => '#dcfce7',
                'text_color' => '#166534',
                'active_color' => '#16a34a',
                'active_text_color' => '#ffffff',
            ];
        }

        $tabs[] = [
            'id' => 'reports',
            'name' => 'reports',
            'label' => 'Reportes',
            'disabled' => $this->store_reports_disabled,
            'disabled_color' => '#e2e8f0',
            'disabled_text_color' => '#64748b',
            'active_color' => '#7c3aed',
            'active_text_color' => '#ffffff',
        ];

        if (!in_array('settings', $this->store_closed_tabs, true)) {
            $tabs[] = [
                'id' => 'settings',
                'name' => 'settings',
                'label' => 'Configuracion',
                'closable' => true,
                'color' => '#fef3c7',
                'text_color' => '#92400e',
                'active_color' => '#f59e0b',
                'active_text_color' => '#ffffff',
            ];
        }

        $tabs[] = [
            'id' => 'advanced',
            'name' => 'advanced',
            'label' => 'Avanzado',
            // Sin colores definidos: usa los colores por defecto del tema
        ];

        $activeTab = $this->store_active_tab;
        if (in_array($activeTab, $this->store_closed_tabs, true)) {
            $activeTab = 'overview';
            $this->store_active_tab = 'overview';
        }
        if ($activeTab === 'reports' && $this->store_reports_disabled) {
            $activeTab = 'overview';
            $this->store_active_tab = 'overview';
        }

        $this->tabs_workspace
            ->tabs($tabs, $activeTab)
            ->onTabChange('tabs_switch')
            ->onTabClose('tabs_close')
            ->tabColors([
                'list_background_color' => 'var(--ui-surface-muted)',
                'border_color' => 'var(--ui-border)',
                'tab_color' => 'transparent',
                'tab_text_color' => 'var(--ui-text-muted)',
                'active_tab_color' => 'var(--ui-surface)',
                'active_tab_text_color' => 'var(--ui-text-strong)',
                'disabled_tab_color' => 'var(--ui-surface-muted)',
                'disabled_tab_text_color' => 'var(--ui-text-muted)',
                'close_color' => 'var(--ui-text-muted)',
                'close_hover_color' => 'var(--ui-text-strong)',
            ]);
    }
}
