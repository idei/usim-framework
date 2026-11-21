<?php

namespace App\UI\Screens;

use App\UI\Navigation\Menus\MainMenu;
use App\UI\Screens\Concerns\HandlesShellActions;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\NavTree;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Navigation\MenuBuilder;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * VS Code-style Primary Side Bar panel hosted inside WorkbenchLayout's collapsible `left_panel` Split.
 *
 * Renders the nested navigation tree (supporting arbitrary sub-sub-menu depth) for the
 * Activity Bar group currently selected in `SidebarMenu`. Persists across main-slot
 * navigation until toggled closed or replaced by a screen-owned contextual left panel.
 */
class SidebarSubmenuPanel extends Screen
{
    use HandlesShellActions {
        onShowScreen as protected handleShellShowScreen;
    }

    public static Visibility $visibility = Visibility::PUBLIC;

    public static ?string $layout = null;

    public static bool $persistOnNavigate = true;

    protected string $state_group = '';

    protected NewLabel $lbl_panel_title;

    protected NavTree $submenu_tree;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $group = isset($params['group']) && \is_string($params['group']) ? trim($params['group']) : '';
        if ($group !== '') {
            $this->state_group = $group;
        }

        $container
            ->plain()
            ->customClass('usim-left-panel-shell')
            ->layout(LayoutType::VERTICAL)
            ->alignItems(AlignItems::STRETCH)
            ->width(Size::full())
            ->height(Size::full())
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0));

        $header = UI::container('left_panel_header')
            ->plain()
            ->customClass('usim-left-panel-header')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->width(Size::full())
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0));

        $this->lbl_panel_title = UI::newLabel('lbl_panel_title')
            ->text($this->state_group)
            ->customClass('usim-left-panel-title');

        $closeBtn = UI::button('btn_close_left_panel')
            ->label('✕')
            ->ariaLabel('Close panel')
            ->tooltip('Close panel')
            ->customClass('usim-left-panel-close')
            ->plain()
            ->action('close_left_panel');

        $header->add($this->lbl_panel_title);
        $header->add($closeBtn);

        $body = UI::container('left_panel_body')
            ->plain()
            ->customClass('usim-left-panel-body')
            ->layout(LayoutType::VERTICAL)
            ->width(Size::full())
            ->flexGrow(1)
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0));

        $this->submenu_tree = UI::navTree('submenu_tree')
            ->accordion(true);

        $this->populateSubmenuTree();
        $body->add($this->submenu_tree);

        $container->add($header);
        $container->add($body);
    }

    protected function postLoadUI(mixed ...$params): void
    {
        $group = isset($params['group']) && \is_string($params['group']) ? trim($params['group']) : '';
        if ($group !== '') {
            $this->state_group = $group;
        }

        $this->lbl_panel_title->text($this->state_group);
        $this->populateSubmenuTree();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onShowScreen(array $params): void
    {
        $this->handleShellShowScreen($params);

        $screen = $params['screen'] ?? null;
        if (\is_string($screen) && is_subclass_of($screen, Screen::class)) {
            $this->submenu_tree->activeScreen($screen);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onCloseLeftPanel(array $params = []): void
    {
        $this->closeSlot('left_panel');
        event(new UsimEvent('left_panel_closed'));
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onUnitChanged(array $params = []): void
    {
        $this->populateSubmenuTree();
    }

    private function populateSubmenuTree(): void
    {
        $sourceNav = MenuBuilder::make('main_menu')
            ->provider(MainMenu::class)
            ->renderNavTree('main_menu_source');

        /** @var list<array<string, mixed>> $groupItems */
        $groupItems = [];

        foreach ($sourceNav->getItems() as $item) {
            if (
                isset($item['submenu'])
                && \is_array($item['submenu'])
                && ($item['label'] ?? null) === $this->state_group
            ) {
                /** @var list<array<string, mixed>> $submenu */
                $submenu = array_values(array_filter($item['submenu'], '\is_array'));
                $groupItems = $submenu;
                break;
            }
        }

        $this->submenu_tree->setItems($groupItems);

        $currentScreen = UIStateManager::getClientCurrentScreenClass();
        if (\is_string($currentScreen) && $currentScreen !== '') {
            $this->submenu_tree->activeScreen($currentScreen);
        }
    }
}
