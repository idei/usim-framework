<?php

namespace App\UI\Screens\Concerns;

use App\Models\User;
use App\Services\Auth\AuthSessionService;
use App\Services\Units\UsimUnitsService;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Profile;
use App\UI\Screens\Auth\Register;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Modals\ConfirmDialog;
use Idei\Usim\Models\UsimLanguage;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Navigation\MenuBuilder;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\Upload\UploadService;
use Idei\Usim\ValueObjects\Size;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

/**
 * Shared shell actions for Menu (topbar), SidebarMenu (workbench), and StatusBar.
 *
 * @mixin Screen
 */
trait HandlesShellActions
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
        $version = '1.0.0';
        $aboutMessage = t('screens.menu.about.message', [
            'version' => $version,
        ]);

        ConfirmDialog::open(
            caller: $this,
            type: DialogType::INFO,
            title: t('screens.menu.about.title'),
            message: $aboutMessage,
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onShowErrorInfo(array $params): void
    {
        $this->abort(500, t('screens.menu.abort_demo_error'));
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
            title: t('screens.menu.logout_confirm.title'),
            message: t('screens.menu.logout_confirm.message'),
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

    protected function buildUserMenuBuilder(string $position = 'bottom-right', int $widthPx = 180): MenuBuilder
    {
        $builder = MenuBuilder::make('user_menu')
            ->position($position)
            ->width(Size::px($widthPx));

        $builder->screenShow(Login::class, modal: true, when: ! Auth::check());
        $builder->screenShow(Register::class, modal: true, when: ! Auth::check());
        $builder->screenShow(Profile::class, modal: true, when: Auth::check());

        $builder->action(
            t('screens.menu.items.logout'),
            'confirm_logout',
            [],
            '🚪',
            when: Auth::check()
        );

        return $builder;
    }

    protected function applyUserTrigger(MenuDropdown $menu, ?User $user, string $guestLabel = '⚙️', ?string $guestIcon = null): void
    {
        if ($user instanceof User) {
            if ($user->profile_image) {
                $imageUrl = UploadService::fileUrl("uploads/images/$user->profile_image");
                $menu->triggerImage(
                    imageUrl: $imageUrl,
                    alt: $user->name,
                    label: $user->name
                );
            } else {
                $menu->trigger($user->name, '👤');
            }

            return;
        }

        $menu->trigger($guestLabel, $guestIcon);
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
            $menu->trigger('🏢 '.$this->getUnitDisplayName($activeUnit));
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
            if ($unit instanceof UsimUnit) {
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
        setPermissionsTeamId($first->id);
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
        $languages = UsimLanguage::where('is_active', true)->orderBy('name')->get();
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
    protected function executeUnitSwitch(
        array $params,
        UsimUnitsService $usimUnitsService,
        AuthSessionService $authSessionService,
        callable $onUpdated
    ): void {
        $unitSlug = $this->stringParamOrDefault($params, 'unit', '');
        $unitId = is_numeric($params['unit_id'] ?? null) ? (int) $params['unit_id'] : 0;

        if (empty($unitSlug) && $unitId <= 0) {
            return;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        /** @var Collection<int, UsimUnit> $units */
        $units = $usimUnitsService->getAvailableUnits();
        /** @var UsimUnit|null $unit */
        $unit = $unitId > 0
            ? $units->firstWhere('id', $unitId)
            : $units->firstWhere('slug', $unitSlug);

        $currentSlug = UIStateManager::getActiveUnit();
        if ($unit === null || $unit->slug === $currentSlug) {
            return;
        }

        UIStateManager::setActiveUnit($unit->slug);
        setPermissionsTeamId($unit->id);

        event(new UsimEvent('unit_changed', [
            'unit_id' => $unit->id,
            'unit_slug' => $unit->slug,
            'user_id' => $user->id,
        ]));

        $onUpdated();

        $this->toast(t(
            'screens.menu.unit_changed',
            ['unit' => $this->getUnitDisplayName($unit)]
        ), 'success');

        $homeScreen = $authSessionService->resolveHomeScreen($user, $unit->slug);
        if ($homeScreen) {
            $this->showInto($homeScreen);
        }
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

        return is_string($value) ? $value : $default;
    }

    protected function normalizeLocale(mixed $locale): string
    {
        if (is_string($locale) && $locale !== '') {
            return $locale;
        }

        throw new InvalidArgumentException('Fallback locale must be a non-empty string.');
    }
}
