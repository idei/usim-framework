<?php

namespace Idei\Usim\Screens;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Components\NavTree;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Navigation\MenuBuilder;
use Idei\Usim\Screen;
use Idei\Usim\Screens\Concerns\HandlesPackageShellActions;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\Support\UsimConfig;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Built-in VS Code-style Activity Bar Screen for `WorkbenchLayout`.
 *
 * Automatically used as a fallback whenever `App\UI\Screens\SidebarMenu` does not exist
 * in the consuming application. Renders SVG icons (`bolt`, `home`, `user`, `settings`, etc.)
 * and automatically populates accessible Screens via `App\UI\Navigation\Menus\MainMenu`
 * (if defined) or `Idei\Usim\Navigation\AutoDiscoveredMenuProvider`.
 */
class SidebarMenu extends Screen
{
    use HandlesPackageShellActions;

    public static Visibility $visibility = Visibility::PUBLIC;

    public static ?string $layout = null;

    public static bool $showInMenu = false;

    protected NavTree $main_menu;

    protected MenuDropdown $user_menu;

    protected MenuDropdown $settings_menu;

    protected string $store_theme = 'dark';

    protected string $store_lang = '';

    protected string $store_token = '';

    protected string $state_active_group = '';

    protected function buildBaseUI(Container $container, ...$params): void
    {
        /** @var UsimConfig $usimConfig */
        $usimConfig = app(UsimConfig::class);

        $container
            ->plain()
            ->customClass('usim-sidebar-shell usim-activity-bar')
            ->layout(LayoutType::VERTICAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::STRETCH)
            ->width(Size::full())
            ->height(Size::full())
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0));

        // 1. Brand header (SVG bolt icon)
        $brand = UI::container('sidebar_brand')
            ->plain()
            ->customClass('usim-sidebar-brand')
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(8))
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0));

        $brand->add(
            UI::newLabel('sidebar_brand_icon')
                ->icon('bolt')
                ->tooltip($usimConfig->appName)
        );
        $brand->add(
            UI::newLabel('sidebar_brand_title')
                ->text($usimConfig->appName)
                ->customClass('usim-sidebar-brand-title')
        );

        // 2. Scrollable navigation body (NavTree in Activity Bar mode)
        $body = UI::container('sidebar_body')
            ->plain()
            ->customClass('usim-sidebar-body')
            ->layout(LayoutType::VERTICAL)
            ->width(Size::full())
            ->flexGrow(1)
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0));

        $this->main_menu = $this->buildNavTree();
        $body->add($this->main_menu);

        // 3. VS Code-style footer: User SVG (`user`) + Settings SVG (`settings`)
        $footer = UI::container('sidebar_footer')
            ->plain()
            ->customClass('usim-sidebar-footer')
            ->layout(LayoutType::VERTICAL)
            ->width(Size::full())
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0));

        $this->user_menu = $this->buildSidebarUserMenu();
        $this->settings_menu = $this->buildSettingsMenu();

        $footer->add($this->user_menu);
        $footer->add($this->settings_menu);

        $container->add($brand);
        $container->add($body);
        $container->add($footer);
    }

    protected function postLoadUI(): void
    {
        $layout = $this->getLayout();
        $submenuPanelClass = $this->resolveSubmenuPanelClass();
        if ($layout !== null && $layout->getActiveScreen('left_panel') !== $submenuPanelClass) {
            $this->state_active_group = '';
        }

        $user = Auth::check() ? Auth::user() : null;
        $this->applyUserTrigger(
            $this->user_menu,
            $user,
            guestLabel: $this->translateOrFallback('screens.menu.items.login', 'Login'),
            guestIcon: 'user'
        );
        $this->populateSidebarUserMenu($this->user_menu);
        $this->populateSettingsMenu($this->settings_menu);
        $this->populateNavTree($this->main_menu);
    }

    private function buildNavTree(): NavTree
    {
        $builder = MenuBuilder::make('main_menu')
            ->provider($this->resolveNavigationMenuProvider());

        $nav = $builder->renderNavTree('main_menu');
        $nav
            ->compact(true)
            ->accordion(true)
            ->onGroupClick('open_menu_group')
            ->activeGroup($this->state_active_group !== '' ? $this->state_active_group : null);
        $this->syncActiveScreen($nav);

        return $nav;
    }

    private function populateNavTree(NavTree $nav): void
    {
        $builder = MenuBuilder::make('main_menu')
            ->provider($this->resolveNavigationMenuProvider());

        $builder->populateNavTree($nav);
        $nav
            ->compact(true)
            ->onGroupClick('open_menu_group')
            ->activeGroup($this->state_active_group !== '' ? $this->state_active_group : null);
        $this->syncActiveScreen($nav);
    }

    private function syncActiveScreen(NavTree $nav): void
    {
        $currentScreen = UIStateManager::getClientCurrentScreenClass();
        if (\is_string($currentScreen) && $currentScreen !== '') {
            $nav->activeScreen($currentScreen);
        }
    }

    private function buildSidebarUserMenu(): MenuDropdown
    {
        $menu = $this->buildUserMenuBuilder('right-top', 200)
            ->render('user_menu');

        $menu->floating(true);
        $user = Auth::check() ? Auth::user() : null;
        $this->applyUserTrigger(
            $menu,
            $user,
            guestLabel: $this->translateOrFallback('screens.menu.items.login', 'Login'),
            guestIcon: 'user'
        );

        return $menu;
    }

    private function populateSidebarUserMenu(MenuDropdown $menu): void
    {
        $this->buildUserMenuBuilder('right-top', 200)->populate($menu);
        $menu->floating(true);
    }

    private function buildSettingsMenu(): MenuDropdown
    {
        $menu = UI::menuDropdown('settings_menu')
            ->trigger($this->translateOrFallback('screens.menu.items.settings', 'Settings'), 'settings')
            ->position('right-top')
            ->floating(true)
            ->width(Size::px(220));

        $this->populateSettingsMenu($menu);

        return $menu;
    }

    private function populateSettingsMenu(MenuDropdown $menu): void
    {
        if (empty($this->store_lang)) {
            $this->store_lang = $this->normalizeLocale(app()->getLocale() ?: config('usim.i18n.fallback_locale', 'en'));
        }

        $menu->clearItems();
        $nextTheme = $this->store_theme === 'light' ? 'dark' : 'light';
        $themeIcon = $this->store_theme === 'light' ? 'moon' : 'sun';

        $menu->item(
            $this->translateOrFallback('screens.menu.theme_switch_to', "Switch to {$nextTheme} theme", ['theme' => $nextTheme]),
            'toggleTheme',
            [],
            $themeIcon
        );
        $menu->submenu(
            $this->translateOrFallback('screens.menu.items.language', 'Language'),
            fn (MenuDropdown $sub) => $this->populateLangDropdown($sub, $this->store_lang),
            'sliders'
        );
        $menu->separator();
        $menu->item(
            $this->translateOrFallback('screens.menu.items.about', 'About'),
            'show_about_info',
            [],
            'info'
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onToggleTheme(array $params): void
    {
        $this->store_theme = $this->store_theme === 'light' ? 'dark' : 'light';
        $this->populateSettingsMenu($this->settings_menu);
        event(new UsimEvent('theme_changed', ['theme' => $this->store_theme]));
        $this->changeTheme($this->store_theme);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onChangeLang(array $params): void
    {
        $lang = $this->stringParamOrDefault($params, 'lang', '');
        if (empty($this->store_lang)) {
            $this->store_lang = $this->normalizeLocale(app()->getLocale() ?: config('usim.i18n.fallback_locale', 'en'));
        }
        if (empty($lang) || $lang === $this->store_lang) {
            return;
        }

        $this->store_lang = $lang;
        $this->changeLanguage($lang);
        $this->populateSettingsMenu($this->settings_menu);

        event(new UsimEvent('reset_screen'));

        $this->redirect($this->buildResetRedirectUrl());
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onLoggedUser(array $params): void
    {
        $user = $params['user'] ?? null;
        $unitValue = $params['unit'] ?? '';
        $homeScreen = $params['home_screen'] ?? null;
        $token = $params['token'] ?? null;

        if (\is_string($token) && $token !== '') {
            $this->store_token = $token;
        }

        if (\is_string($unitValue) && $unitValue !== '') {
            UIStateManager::setActiveUnit($unitValue);
        }

        $this->applyUserTrigger(
            $this->user_menu,
            $user instanceof Authenticatable ? $user : (Auth::check() ? Auth::user() : null),
            guestLabel: $this->translateOrFallback('screens.menu.items.login', 'Login'),
            guestIcon: 'user'
        );

        $this->populateSidebarUserMenu($this->user_menu);
        $this->populateNavTree($this->main_menu);

        if (\is_string($homeScreen) && $homeScreen !== '') {
            $this->main_menu->activeScreen($homeScreen);
            $this->showInto($homeScreen, force: true, updateBrowserUrl: true);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onUpdatedProfile(array $params): void
    {
        $user = $params['user'] ?? null;
        if ($user instanceof Authenticatable) {
            $this->applyUserTrigger($this->user_menu, $user);
        }
    }

    /**
     * Open or toggle a top-level submenu group inside WorkbenchLayout's `left_panel` Split.
     *
     * @param  array<string, mixed>  $params
     */
    public function onOpenMenuGroup(array $params): void
    {
        $groupLabel = $this->stringParamOrDefault($params, 'group_label', '');
        if ($groupLabel === '') {
            return;
        }

        $submenuPanelClass = $this->resolveSubmenuPanelClass();
        $leftPanelCollapsed = (bool) ($params['left_panel_collapsed'] ?? false);
        $layout = $this->getLayout();
        $activeLeftScreen = $layout?->getActiveScreen('left_panel');
        $isSameGroupOpen = ! $leftPanelCollapsed
            && $activeLeftScreen === $submenuPanelClass
            && $this->state_active_group === $groupLabel;

        if ($isSameGroupOpen) {
            $this->state_active_group = '';
            $this->main_menu->activeGroup(null);
            $this->closeSlot('left_panel');

            return;
        }

        $this->state_active_group = $groupLabel;
        $this->main_menu->activeGroup($groupLabel);

        $this->showInto(
            $submenuPanelClass,
            slot: 'left_panel',
            params: ['group' => $groupLabel],
            updateBrowserUrl: false,
            force: true
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onLeftPanelClosed(array $params = []): void
    {
        $this->state_active_group = '';
        $this->main_menu->activeGroup(null);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onUnitChanged(array $params): void
    {
        $this->state_active_group = '';
        $this->populateNavTree($this->main_menu);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onConfirmLogout(array $params): void
    {
        if (UIStateManager::getClientActiveModal() !== null) {
            $this->closeModal();
        }

        $homeScreen = $this->performShellLogout();

        $this->toast($this->translateOrFallback('screens.menu.logout_success', 'You have been logged out successfully.'), 'success');
        $this->resetToGuestState($homeScreen);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onUserLoggedOut(array $params): void
    {
        if ($this->store_token === '' && ! Auth::check()) {
            return;
        }

        /** @var class-string<Screen>|string|null $homeScreen */
        $homeScreen = $params['home_screen'] ?? $this->resolveAppScreenClass('Home');
        $targetClass = \is_string($homeScreen) && class_exists($homeScreen) && is_subclass_of($homeScreen, Screen::class)
            ? $homeScreen
            : $this->resolveAppScreenClass('Home');

        $this->resetToGuestState($targetClass);
    }

    /**
     * @param  class-string<Screen>|null  $homeScreen
     */
    private function resetToGuestState(?string $homeScreen): void
    {
        $this->store_token = '';
        $this->applyUserTrigger(
            $this->user_menu,
            null,
            guestLabel: $this->translateOrFallback('screens.menu.items.login', 'Login'),
            guestIcon: 'user'
        );
        $this->populateSidebarUserMenu($this->user_menu);
        $this->populateNavTree($this->main_menu);

        if ($homeScreen !== null) {
            $this->showInto($homeScreen, force: true, updateBrowserUrl: true);
        }
    }

    /**
     * @return class-string<Screen>
     */
    private function resolveSubmenuPanelClass(): string
    {
        return $this->resolveAppScreenClass('SidebarSubmenuPanel') ?? SidebarSubmenuPanel::class;
    }
}
