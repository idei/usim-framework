<?php

namespace App\UI\Screens;

use App\Models\User;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\RegisterActionHandler;
use App\Services\Units\UsimUnitsService;
use App\UI\Navigation\Menus\MainMenu;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Profile;
use App\UI\Screens\Auth\Register;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Modals\ConfirmDialog;
use Idei\Usim\Models\UsimLanguage;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Navigation\MenuBuilder;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\Upload\UploadService;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Menu Service
 *
 * Builds the top navigation bar for USIM application screens.
 */
class Menu extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;
    public static ?string $layout = null;

    public function __construct(
        protected AuthSessionService $authSessionService,
        protected UsimUnitsService $usimUnitsService,
        protected RegisterActionHandler $registerActionHandler
    ) {
    }

    protected MenuDropdown $main_menu;
    protected MenuDropdown $user_menu;
    protected MenuDropdown $lang_menu;
    protected ?MenuDropdown $unit_menu = null;
    protected Button $theme_toggle;
    protected string $store_theme = 'light';
    protected string $store_lang = '';
    protected string $store_unit = '';

    protected function buildBaseUI(Container $container, ...$params): void
    {
        if ($this->parent === null || $this->parent === 'main') {
            $container->parent('menu');
        }

        $container
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(8))
            ->marginBottom(Spacing::px(0));

        $this->main_menu = $this->buildLeftMenu();
        $this->user_menu = $this->buildUserMenu();

        if (empty($this->store_lang)) {
            $this->store_lang = $this->normalizeLocale(config('usim.i18n.fallback_locale', 'en'));
        }
        $this->lang_menu = $this->buildLangMenu();
        $this->lang_menu->marginLeft(Spacing::px(12));
        $this->user_menu->marginLeft(Spacing::px(12));

        $container->add($this->main_menu);

        $this->theme_toggle = UI::button('theme_toggle')
            ->action('toggleTheme')
            ->plain()
            ->marginLeft(Spacing::auto());
        $container->add($this->theme_toggle);

        $units = $this->usimUnitsService->getAvailableUnits();
        $this->unit_menu = $this->buildUnitMenu($units);
        $this->unit_menu->marginLeft(Spacing::px(12));
        $container->add($this->unit_menu);

        $container->add($this->lang_menu);
        $container->add($this->user_menu);
        $this->updateThemeButton();
    }

    private function updateThemeButton(): void
    {
        $icon = $this->store_theme === 'light' ? 'theme-icon-light' : 'theme-icon-dark';
        $this->theme_toggle->icon("/vendor/idei/usim/images/$icon.svg");
        $this->theme_toggle->iconColor('var(--usim-menu-trigger-text)');
        $this->theme_toggle->iconSize(24);
        $this->theme_toggle->tooltip(t('screen.menu.theme_switch_to', ['theme' => $this->store_theme === 'light' ? 'dark' : 'light']));
    }

    /**
     * Toggles the application theme between light and dark modes.
     *
     * @param array<string, mixed> $params
     * @return void
     */
    public function onToggleTheme(array $params): void
    {
        $this->store_theme = $this->store_theme === 'light' ? 'dark' : 'light';
        $this->updateThemeButton();
        event(new UsimEvent('theme_changed', ['theme' => $this->store_theme]));
        $this->changeTheme($this->store_theme);
    }

    /**
     * Changes the application language.
     *
     * @param array<string, mixed> $params
     * @return void
     */
    public function onChangeLang(array $params): void
    {
        $lang = $this->stringParamOrDefault($params, 'lang', '');
        if (empty($lang) || $lang === $this->store_lang) {
            return;
        }

        $this->store_lang = $lang;
        $this->changeLanguage($lang);
        $this->updateLangMenu();

        event(new UsimEvent('reset_screen'));

        $referer = request()->headers->get('referer');
        if (empty($referer) || str_contains($referer, '/api/ui-event')) {
            $referer = url('/');
        }

        $parts = parse_url($referer);
        $path = $parts['path'] ?? '/';
        $query = [];
        $queryString = $parts['query'] ?? '';
        if (!empty($queryString)) {
            parse_str((string) $queryString, $query);
        }

        $target = $path;
        if (!empty($query)) {
            $target .= '?' . http_build_query($query);
        }

        $this->redirect($target);
    }

    private function buildLangMenu(): MenuDropdown
    {
        $lang_menu = UI::menuDropdown('lang_menu')
            ->trigger(strtoupper($this->store_lang))
            ->position('bottom-right')
            ->width(Size::px(160));

        $this->populateLangMenu($lang_menu);
        return $lang_menu;
    }

    private function populateLangMenu(MenuDropdown $menu): void
    {
        $menu->clearItems();
        $languages = UsimLanguage::where('is_active', true)->orderBy('name')->get();
        foreach ($languages as $lang) {
            $label = $lang->native_name ?: $lang->name;
            if ($lang->code === $this->store_lang) {
                $label = "✓ $label";
            }
            $menu->item($label, 'changeLang', ['lang' => $lang->code]);
        }
    }

    private function updateLangMenu(): void
    {
        if (empty($this->store_lang)) {
            $this->store_lang = $this->normalizeLocale(config('usim.i18n.fallback_locale', 'en'));
        }

        $this->lang_menu->trigger(strtoupper($this->store_lang));
        $this->populateLangMenu($this->lang_menu);
    }

    /**
     * Changes the application unit.
     *
     * @param array<string, mixed> $params
     * @return void
     */
    public function onChangeUnit(array $params): void
    {
        $unitSlug = $this->stringParamOrDefault($params, 'unit', '');
        $unitId = is_numeric($params['unit_id'] ?? null) ? (int) $params['unit_id'] : 0;

        if (empty($unitSlug) && $unitId <= 0) {
            return;
        }

        /** @var User $user */
        $user = Auth::user();
        /** @var Collection<int, UsimUnit> $units */
        $units = $this->usimUnitsService->getAvailableUnits();
        /** @var UsimUnit|null $unit */
        $unit = $unitId > 0
            ? $units->firstWhere('id', $unitId)
            : $units->firstWhere('slug', $unitSlug);

        if ($unit === null || $unit->slug === $this->store_unit) {
            return;
        }

        $this->store_unit = $unit->slug;
        session()->put('current_unit_id', $unit->id);
        session()->put('current_unit_slug', $unit->slug);

        setPermissionsTeamId($unit->id);

        event(new UsimEvent('unit_changed', [
            'unit_id' => $unit->id,
            'unit_slug' => $unit->slug,
            'user_id' => $user->id,
        ]));

        $this->updateUnitMenu();

        $this->toast(t('screen.menu.unit_changed', ['unit' => $this->getUnitDisplayName($unit)]), 'success');

        $redirectTo = $this->authSessionService->resolvePostLoginRedirect($user, $unit->slug);
        $this->redirect($redirectTo);
    }

    /**
     * @param Collection<int, UsimUnit> $units
     */
    private function buildUnitMenu(Collection $units): MenuDropdown
    {
        $unit_menu = UI::menuDropdown('unit_menu')
            ->position('bottom-right')
            ->width(Size::px(200));

        $this->populateUnitMenu($unit_menu, $units);
        return $unit_menu;
    }

    /**
     * @param Collection<int, UsimUnit> $units
     */
    private function populateUnitMenu(MenuDropdown $menu, Collection $units): void
    {
        $menu->clearItems();

        if ($units->isEmpty()) {
            $menu->visible(false);
            return;
        }

        $this->initStoreUnit($units);
        $menu->visible(true);

        $activeUnit = $units->firstWhere('slug', $this->store_unit) ?? $units->first();
        if ($activeUnit instanceof UsimUnit) {
            $menu->trigger('🏢 ' . $this->getUnitDisplayName($activeUnit));
        }

        foreach ($units as $unit) {
            $label = $this->getUnitDisplayName($unit);
            if ($unit->slug === $this->store_unit) {
                $label = "✓ $label";
            }
            $menu->item($label, 'changeUnit', ['unit' => $unit->slug, 'unit_id' => $unit->id]);
        }
    }

    /**
     * @param Collection<int, UsimUnit> $units
     */
    private function initStoreUnit(Collection $units): void
    {
        if ($units->isEmpty()) {
            $this->store_unit = '';
            return;
        }

        if (!empty($this->store_unit) && $units->contains('slug', $this->store_unit)) {
            return;
        }

        $sessionUnitSlug = session()->get('current_unit_slug');
        if (is_string($sessionUnitSlug) && $sessionUnitSlug !== '' && $units->contains('slug', $sessionUnitSlug)) {
            $this->store_unit = $sessionUnitSlug;
            return;
        }

        $sessionUnitId = session()->get('current_unit_id');
        if (is_numeric($sessionUnitId) && $units->contains('id', (int) $sessionUnitId)) {
            $unit = $units->firstWhere('id', (int) $sessionUnitId);
            if ($unit instanceof UsimUnit) {
                $this->store_unit = $unit->slug;
                return;
            }
        }

        if (function_exists('getPermissionsTeamId')) {
            $teamId = getPermissionsTeamId();
            if ($teamId && $units->contains('id', (int) $teamId)) {
                $unit = $units->firstWhere('id', (int) $teamId);
                if ($unit instanceof UsimUnit) {
                    $this->store_unit = $unit->slug;
                    return;
                }
            }
        }

        $first = $units->first();
        $this->store_unit = $first->slug;
    }

    private function updateUnitMenu(): void
    {
        if ($this->unit_menu === null || !Auth::check()) {
            return;
        }

        $this->populateUnitMenu($this->unit_menu, $this->usimUnitsService->getAvailableUnits());
    }

    private function getUnitDisplayName(UsimUnit $unit): string
    {
        $displayName = $unit->display_name;
        if (empty($displayName) || $displayName === $unit->translation_key) {
            return ucfirst($unit->slug);
        }

        return $displayName;
    }

    protected function postLoadUI(): void
    {
        $this->updateThemeButton();
        $this->updateLangMenu();

        if (Auth::check()) {
            $user = Auth::user();
            if ($user instanceof User) {
                $this->updateUserMenuTrigger($user);
                $this->updateUnitMenu();
            } else {
                $this->user_menu->trigger("⚙️");
            }
            $this->populateMainMenu($this->main_menu);
            $this->populateUserMenu($this->user_menu);
        } else {
            $this->user_menu->trigger("⚙️");
        }
    }

    private function updateUserMenuTrigger(User $user): void
    {
        if ($user->profile_image) {
            $imageUrl = UploadService::fileUrl("uploads/images/$user->profile_image");
            $this->user_menu->triggerImage(
                imageUrl: $imageUrl,
                alt: $user->name,
                label: $user->name
            );
        } else {
            $this->user_menu->trigger("👤 $user->name");
        }
    }

    private function buildLeftMenu(): MenuDropdown
    {
        $builder = MenuBuilder::make('main_menu')
            ->trigger()
            ->position('bottom-left')
            ->width(Size::px(200))
            ->provider(MainMenu::class);

        return $builder->render('main_menu');
    }

    private function populateMainMenu(MenuDropdown $menu): void
    {
        $builder = MenuBuilder::make('main_menu')
            ->trigger()
            ->position('bottom-left')
            ->width(Size::px(200))
            ->provider(MainMenu::class);

        $builder->populate($menu);
    }

    private function buildUserMenu(): MenuDropdown
    {
        $builder = $this->getUserMenuBuilder();
        $menu = $builder->render('user_menu');
        $menu->trigger("⚙️");
        return $menu;
    }

    private function populateUserMenu(MenuDropdown $menu): void
    {
        $builder = $this->getUserMenuBuilder();
        $builder->populate($menu);
    }

    private function getUserMenuBuilder(): MenuBuilder
    {
        $builder = MenuBuilder::make('user_menu')
            ->position('bottom-right')
            ->width(Size::px(180));

        $builder->screenShow(Login::class, modal: true, when: !Auth::check());

        // $builder->action(
        //     t('screen.menu.items.register'),
        //     'show_register_form',
        //     [],
        //     '📝',
        //     when: !Auth::check()
        // );
        $builder->screenShow(Register::class, modal: true, when: !Auth::check());
        $builder->screenShow(Profile::class, modal: true, when: Auth::check());

        $builder->action(
            t('screen.menu.items.logout'),
            'confirm_logout',
            [],
            '🚪',
            when: Auth::check()
        );

        return $builder;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onShowScreen(array $params): void
    {
        /** @var class-string<Screen>|string|null $screen */
        $screen = $params['screen'] ?? null;
        $modal = $params['modal'] ?? false;

        if ($screen !== null && is_subclass_of($screen, Screen::class)) {
            /** @var string|null $slot */
            $slot = isset($params['slot']) && \is_string($params['slot']) ? $params['slot'] : null;

            if ($modal) {
                $this->openModal($screen);
                return;
            }

            $this->showInto($screen, slot: $slot, updateBrowserUrl: true);
        }
    }

    /**
     * Handles the event when a user logs in, updating the menu accordingly.
     *
     * @param array<string, mixed> $params
     * @return void
     */
    public function onLoggedUser(array $params): void
    {
        /** @var User|null $user */
        $user = $params['user'] ?? null;
        $unitValue = $params['unit'] ?? '';
        $homeScreen = $params['home_screen'] ?? null;

        $this->store_unit = \is_string($unitValue) ? $unitValue : '';

        if ($user instanceof User) {
            $this->updateUserMenuTrigger($user);
        }

        $this->populateUserMenu($this->user_menu);
        $this->populateMainMenu($this->main_menu);
        $this->updateUnitMenu();

        if (\is_string($homeScreen) && $homeScreen !== '') {
            $this->showInto($homeScreen);
        }
    }

    /**
     * Handles the event when a user updates their profile, refreshing the menu to reflect any changes.
     *
     * @param array<string, mixed> $params
     * @return void
     */
    public function onUpdatedProfile(array $params): void
    {
        $user = $params['user'] ?? null;
        if ($user instanceof User) {
            $this->updateUserMenuTrigger($user);
        }
    }

    /**
     * Handles the event when a user logs out, resetting the menu to its default state.
     *
     * @param array<string, mixed> $params
     * @return void
     */
    public function onConfirmLogout(array $params): void
    {
        Auth::logout();
        session()->forget(['current_unit_id', 'current_unit_slug']);
        if (function_exists('setPermissionsTeamId') && config('permission.teams')) {
            setPermissionsTeamId(null);
        }

        $this->store_unit = '';
        if ($this->unit_menu !== null) {
            $this->unit_menu->trigger('🏢');
            $this->unit_menu->clearItems();
        }

        $this->user_menu->trigger("⚙️");
        $this->populateUserMenu($this->user_menu);
        $this->populateMainMenu($this->main_menu);

        $this->toast(t('screen.menu.logout_success'));
        $this->redirect();
    }

    /**
     * Handles the event when a user cancels the logout process, closing any open modals.
     *
     * @param array<string, mixed> $params
     * @return void
     */
    public function onShowAboutInfo(array $params): void
    {
        $version = "0.7.0";
        $aboutMessage = t('screen.menu.about.message', [
            'version' => $version,
        ]);

        ConfirmDialog::open(
            caller: $this,
            type: DialogType::INFO,
            title: t('screen.menu.about.title'),
            message: $aboutMessage,
        );
    }

    /**
     * Handles the event when an error occurs, aborting the process and displaying an error message.
     *
     * @param array<string, mixed> $params
     * @return void
     */
    public function onShowErrorInfo(array $params): void
    {
        $this->abort(500, t('screen.menu.abort_demo_error'));
    }

    /**
     * Handles the event when a user closes the profile dialog, closing any open modals.
     *
     * @param array<string, mixed> $params
     * @return void
     */
    public function onCloseProfileDialog(array $params): void
    {
        $this->closeModal();
    }

    /**
     * Handles the event when a user requests to log out, showing a confirmation dialog.
     *
     * @param array<string, mixed> $params
     * @return void
     */
    public function onLogoutUser(array $params): void
    {
        ConfirmDialog::open(
            caller: $this,
            type: DialogType::CONFIRM,
            title: t('screen.menu.logout_confirm.title'),
            message: t('screen.menu.logout_confirm.message'),
            confirmAction: 'confirm_logout',
            cancelAction: 'cancel_logout',
        );
    }

    /**
     * Handles the event when a user cancels the logout process, closing any open modals.
     *
     * @param array<string, mixed> $params
     * @return void
     */
    public function onCancelLogout(array $params): void
    {
        $this->closeModal();
    }

    /**
     * Retrieves a string parameter from the provided array, returning a default value if the parameter is not set or is not a string.
     *
     * @param array<string, mixed> $params
     * @param string $key
     * @param string $default
     * @return string
     */
    private function stringParamOrDefault(array $params, string $key, string $default): string
    {
        $value = $params[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    /**
     * Normalizes the locale by ensuring it is a non-empty string.
     *
     * @param mixed $locale
     * @return string
     */
    private function normalizeLocale(mixed $locale): string
    {
        if (is_string($locale) && $locale !== '') {
            return $locale;
        }

        throw new InvalidArgumentException('Fallback locale must be a non-empty string.');
    }
}
