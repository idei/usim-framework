<?php

namespace Idei\Usim\Screens\Concerns;

use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Contracts\UnitsServiceInterface;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Modals\ConfirmDialog;
use Idei\Usim\Models\UsimLanguage;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Navigation\AutoDiscoveredMenuProvider;
use Idei\Usim\Navigation\Contracts\MenuProviderInterface;
use Idei\Usim\Navigation\MenuBuilder;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\Support\UsimConfig;
use Idei\Usim\Upload\UploadService;
use Idei\Usim\ValueObjects\Size;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * App-agnostic shell actions shared by USIM's built-in Workbench screens
 * (`Idei\Usim\Screens\SidebarMenu`, `SidebarSubmenuPanel`, and `StatusBar`).
 *
 * Uses semantic SVG icon names (`home`, `user`, `settings`, `logout`, `info`, etc.)
 * and dynamically resolves app-owned auth screens, navigation providers, and services.
 *
 * @mixin Screen
 */
trait HandlesPackageShellActions
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function onShowScreen(array $params): void
    {
        /** @var class-string<Screen>|string|null $screen */
        $screen = $params['screen'] ?? null;
        $modal = (bool) ($params['modal'] ?? false);

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
     * @param  array<string, mixed>  $params
     */
    public function onShowAboutInfo(array $params): void
    {
        /** @var UsimConfig $usimConfig */
        $usimConfig = app(UsimConfig::class);
        $title = $this->translateOrFallback('screens.menu.about.title', $usimConfig->appName);
        $message = $this->translateOrFallback(
            'screens.menu.about.message',
            "{$usimConfig->appName} powered by USIM Framework.",
            ['version' => '1.0.0']
        );

        ConfirmDialog::open(
            caller: $this,
            type: DialogType::INFO,
            title: $title,
            message: $message,
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onCloseProfileDialog(array $params): void
    {
        $this->closeModal();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onLogoutUser(array $params): void
    {
        ConfirmDialog::open(
            caller: $this,
            type: DialogType::CONFIRM,
            title: $this->translateOrFallback('screens.menu.logout_confirm.title', 'Log Out'),
            message: $this->translateOrFallback('screens.menu.logout_confirm.message', 'Are you sure you want to log out?'),
            confirmAction: 'confirm_logout',
            cancelAction: 'cancel_logout',
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onCancelLogout(array $params): void
    {
        $this->closeModal();
    }

    /**
     * Resolve the menu provider for the built-in Workbench sidebar:
     * uses the app's `MainMenu` provider if present, otherwise falls back to
     * automatic permission-aware Screen discovery (`AutoDiscoveredMenuProvider`).
     *
     * @return class-string<MenuProviderInterface>
     */
    protected function resolveNavigationMenuProvider(): string
    {
        /** @var UsimConfig $usimConfig */
        $usimConfig = app(UsimConfig::class);
        $screensNamespace = trim($usimConfig->screensNamespace, '\\');
        $uiBase = str_ends_with($screensNamespace, '\\Screens')
            ? Str::beforeLast($screensNamespace, '\\Screens')
            : 'App\\UI';

        $candidates = [
            $uiBase.'\\Navigation\\Menus\\MainMenu',
            'App\\UI\\Navigation\\Menus\\MainMenu',
        ];

        foreach ($candidates as $candidate) {
            if (class_exists($candidate) && is_subclass_of($candidate, MenuProviderInterface::class)) {
                /** @var class-string<MenuProviderInterface> $candidate */
                return $candidate;
            }
        }

        return AutoDiscoveredMenuProvider::class;
    }

    /**
     * Resolve an app screen class under the configured `screens_namespace` (e.g. `Auth\Login`, `Home`).
     *
     * @return class-string<Screen>|null
     */
    protected function resolveAppScreenClass(string $relativeName): ?string
    {
        /** @var UsimConfig $usimConfig */
        $usimConfig = app(UsimConfig::class);
        $candidate = rtrim($usimConfig->screensNamespace, '\\').'\\'.ltrim($relativeName, '\\');

        if (class_exists($candidate) && is_subclass_of($candidate, Screen::class)) {
            /** @var class-string<Screen> $candidate */
            return $candidate;
        }

        return null;
    }

    protected function buildUserMenuBuilder(string $position = 'right-top', int $widthPx = 200): MenuBuilder
    {
        $builder = MenuBuilder::make('user_menu')
            ->position($position)
            ->width(Size::px($widthPx));

        $loginClass = $this->resolveAppScreenClass('Auth\\Login');
        if ($loginClass !== null) {
            $builder->screenShow($loginClass, modal: true, when: ! Auth::check())->icon('login');
        }

        $registerClass = $this->resolveAppScreenClass('Auth\\Register');
        if ($registerClass !== null) {
            $builder->screenShow($registerClass, modal: true, when: ! Auth::check())->icon('user');
        }

        $profileClass = $this->resolveAppScreenClass('Auth\\Profile');
        if ($profileClass !== null) {
            $builder->screenShow($profileClass, modal: true, when: Auth::check())->icon('user');
        }

        $builder->action(
            $this->translateOrFallback('screens.menu.items.logout', 'Logout'),
            'confirm_logout',
            [],
            'logout',
            when: Auth::check()
        );

        return $builder;
    }

    protected function applyUserTrigger(
        MenuDropdown $menu,
        ?Authenticatable $user,
        string $guestLabel = 'Login',
        string $guestIcon = 'user'
    ): void {
        if ($user !== null) {
            $name = $this->extractUserDisplayName($user);
            $profileImage = $this->extractUserProfileImage($user);

            if ($profileImage !== null && $profileImage !== '') {
                $imageUrl = UploadService::fileUrl("uploads/images/{$profileImage}");
                $menu->triggerImage(
                    imageUrl: $imageUrl,
                    alt: $name,
                    label: $name
                );
            } else {
                $menu->trigger($name, 'user');
            }

            return;
        }

        $menu->trigger($guestLabel, $guestIcon);
    }

    protected function extractUserDisplayName(Authenticatable $user): string
    {
        if (isset($user->name) && \is_string($user->name) && $user->name !== '') {
            return $user->name;
        }

        if (isset($user->email) && \is_string($user->email) && $user->email !== '') {
            return $user->email;
        }

        return 'User';
    }

    protected function extractUserProfileImage(Authenticatable $user): ?string
    {
        if (isset($user->profile_image) && \is_string($user->profile_image) && $user->profile_image !== '') {
            return $user->profile_image;
        }

        return null;
    }

    /**
     * @return Collection<int, UsimUnit>
     */
    protected function getAvailableOperationalUnits(): Collection
    {
        if (! Auth::check()) {
            return collect();
        }

        if (app()->bound(UnitsServiceInterface::class)) {
            /** @var UnitsServiceInterface $service */
            $service = app(UnitsServiceInterface::class);
            if (method_exists($service, 'getAvailableUnits')) {
                /** @var Collection<int, UsimUnit> $units */
                $units = $service->getAvailableUnits();

                return $units;
            }
        }

        if (class_exists('App\\Services\\Units\\UsimUnitsService')) {
            /** @var object $service */
            $service = app('App\\Services\\Units\\UsimUnitsService');
            if (method_exists($service, 'getAvailableUnits')) {
                /** @var Collection<int, UsimUnit> $units */
                $units = $service->getAvailableUnits();

                return $units;
            }
        }

        return collect();
    }

    /**
     * @param  Collection<int, UsimUnit>  $units
     */
    protected function populateUnitDropdown(MenuDropdown $menu, Collection $units): void
    {
        $menu->clearItems();

        if ($units->isEmpty()) {
            $menu->visible(false);

            return;
        }

        $this->initActiveUnit($units);
        $menu->visible(true);

        $activeSlug = UIStateManager::getActiveUnit();
        $activeUnit = ($activeSlug !== null ? $units->firstWhere('slug', $activeSlug) : null) ?? $units->first();
        if ($activeUnit instanceof UsimUnit) {
            $menu->trigger($this->getUnitDisplayName($activeUnit), 'layout-grid');
        }

        foreach ($units as $unit) {
            $label = $this->getUnitDisplayName($unit);
            if ($activeSlug !== null && $unit->slug === $activeSlug) {
                $label = "✓ {$label}";
            }
            $menu->item($label, 'changeUnit', ['unit' => $unit->slug, 'unit_id' => $unit->id]);
        }
    }

    /**
     * @param  Collection<int, UsimUnit>  $units
     */
    protected function initActiveUnit(Collection $units): void
    {
        if ($units->isEmpty()) {
            UIStateManager::setActiveUnit(null);

            return;
        }

        $cachedUnit = UIStateManager::getActiveUnit();
        if ($cachedUnit !== null && $units->contains('slug', $cachedUnit)) {
            $unit = $units->firstWhere('slug', $cachedUnit);
            if ($unit instanceof UsimUnit && function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId($unit->id);
            }

            return;
        }

        if (function_exists('getPermissionsTeamId')) {
            $teamId = getPermissionsTeamId();
            if ($teamId && $units->contains('id', (int) $teamId)) {
                $unit = $units->firstWhere('id', (int) $teamId);
                if ($unit instanceof UsimUnit) {
                    UIStateManager::setActiveUnit($unit->slug);

                    return;
                }
            }
        }

        $first = $units->first();
        UIStateManager::setActiveUnit($first->slug);
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($first->id);
        }
    }

    protected function getUnitDisplayName(UsimUnit $unit): string
    {
        $displayName = $unit->display_name;
        if (empty($displayName) || $displayName === $unit->translation_key) {
            return ucfirst($unit->slug);
        }

        return $displayName;
    }

    protected function populateLangDropdown(MenuDropdown $menu, string $activeLang): void
    {
        $menu->clearItems();
        try {
            $languages = UsimLanguage::where('is_active', true)->orderBy('name')->get();
        } catch (\Throwable) {
            return;
        }

        foreach ($languages as $lang) {
            $label = $lang->native_name ?: $lang->name;
            if ($lang->code === $activeLang) {
                $label = "✓ {$label}";
            }
            $menu->item($label, 'changeLang', ['lang' => $lang->code]);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function executeUnitSwitch(array $params, callable $onUpdated): void
    {
        $unitSlug = $this->stringParamOrDefault($params, 'unit', '');
        $unitId = is_numeric($params['unit_id'] ?? null) ? (int) $params['unit_id'] : 0;

        if (empty($unitSlug) && $unitId <= 0) {
            return;
        }

        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $units = $this->getAvailableOperationalUnits();
        /** @var UsimUnit|null $unit */
        $unit = $unitId > 0
            ? $units->firstWhere('id', $unitId)
            : $units->firstWhere('slug', $unitSlug);

        $currentSlug = UIStateManager::getActiveUnit();
        if ($unit === null || $unit->slug === $currentSlug) {
            return;
        }

        UIStateManager::setActiveUnit($unit->slug);
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($unit->id);
        }

        event(new UsimEvent('unit_changed', [
            'unit_id' => $unit->id,
            'unit_slug' => $unit->slug,
            'user_id' => $user->getAuthIdentifier(),
        ]));

        $onUpdated();

        $this->toast($this->translateOrFallback(
            'screens.menu.unit_changed',
            'Now working in :unit',
            ['unit' => $this->getUnitDisplayName($unit)]
        ), 'success');

        $homeScreen = $this->resolveHomeScreenForUnit($user, $unit->slug);
        if ($homeScreen !== null) {
            $this->showInto($homeScreen);
        }
    }

    /**
     * @return class-string<Screen>|null
     */
    protected function resolveHomeScreenForUnit(Authenticatable $user, string $unitSlug): ?string
    {
        if (class_exists('App\\Services\\Auth\\AuthSessionService')) {
            /** @var object $authSession */
            $authSession = app('App\\Services\\Auth\\AuthSessionService');
            if (method_exists($authSession, 'resolveHomeScreen')) {
                $resolved = $authSession->resolveHomeScreen($user, $unitSlug);
                if (\is_string($resolved) && class_exists($resolved) && is_subclass_of($resolved, Screen::class)) {
                    /** @var class-string<Screen> $resolved */
                    return $resolved;
                }
            }
        }

        return null;
    }

    /**
     * Perform logout via AuthSessionService if available, falling back to Auth::logout().
     *
     * @return class-string<Screen>|null
     */
    protected function performShellLogout(): ?string
    {
        if (class_exists('App\\Services\\Auth\\AuthSessionService')) {
            /** @var object $authSession */
            $authSession = app('App\\Services\\Auth\\AuthSessionService');
            if (method_exists($authSession, 'logout')) {
                $home = $authSession->logout();
                if (\is_string($home) && class_exists($home) && is_subclass_of($home, Screen::class)) {
                    /** @var class-string<Screen> $home */
                    return $home;
                }
            }
        }

        Auth::logout();
        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        return $this->resolveAppScreenClass('Home');
    }

    protected function buildResetRedirectUrl(): string
    {
        $referer = request()->headers->get('referer');
        if (empty($referer) || str_contains($referer, '/api/ui-event')) {
            $currentRoute = UIStateManager::getClientCurrentScreenRoute();
            $referer = ! empty($currentRoute) ? url($currentRoute) : url('/');
        }

        $parts = parse_url($referer);
        $path = $parts['path'] ?? '/';
        $query = [];
        $queryString = $parts['query'] ?? '';
        if (! empty($queryString)) {
            parse_str((string) $queryString, $query);
        }
        $query['reset'] = 'true';

        return $path.'?'.http_build_query($query);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected function stringParamOrDefault(array $params, string $key, string $default): string
    {
        $value = $params[$key] ?? null;

        return \is_string($value) ? $value : $default;
    }

    protected function normalizeLocale(mixed $locale): string
    {
        if (\is_string($locale) && $locale !== '') {
            return $locale;
        }

        throw new InvalidArgumentException('Fallback locale must be a non-empty string.');
    }

    /**
     * @param  array<string, scalar>  $replace
     */
    protected function translateOrFallback(string $key, string $fallback, array $replace = []): string
    {
        $translated = t($key, $replace);
        if ($translated === '' || $translated === $key) {
            $result = $fallback;
            foreach ($replace as $name => $value) {
                $result = str_replace(':'.$name, (string) $value, $result);
            }

            return $result;
        }

        return $translated;
    }
}
