<?php

namespace App\UI\Screens;

use App\Models\User;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\RegisterService;
use App\UI\Components\Modals\RegisterDialog;
use App\UI\Components\Modals\TermsDialog;
use App\UI\Screens\Admin\TranslateManager;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Profile;
use App\UI\Screens\Demo\ButtonDemo;
use App\UI\Screens\Demo\CarouselDemo;
use App\UI\Screens\Demo\CheckboxDemo;
use App\UI\Screens\Demo\FormDemo;
use App\UI\Screens\Demo\InputDemo;
use App\UI\Screens\Demo\ModalDemo;
use App\UI\Screens\Demo\SelectDemo;
use App\UI\Screens\Demo\SplitDemo;
use App\UI\Screens\Demo\TableDemo;
use App\UI\Screens\Demo\TabsDemo;
use App\UI\Screens\Demo\TextareaDemo;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Modals\ConfirmDialogService;
use Idei\Usim\Models\UsimLanguage;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Screen;
use Idei\Usim\Services\UsimUnitsService;
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
 * Builds the main navigation menu for screens
 */
class Menu extends Screen
{
    public function __construct(
        protected RegisterService $registerService,
        protected AuthSessionService $authSessionService,
        protected UsimUnitsService $usimUnitsService
    ) {
    }

    protected MenuDropdown $main_menu;
    protected MenuDropdown $user_menu;
    protected MenuDropdown $lang_menu;
    protected MenuDropdown $unit_menu;
    protected Button $theme_toggle;
    protected string $store_theme = 'light';
    protected string $store_lang = '';
    protected string $store_unit = '';

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->parent('menu') // Important to set parent!
            ->plain()
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(0))
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
     * @param array<string, mixed> $params
     */
    public function onToggleTheme(array $params): void
    {
        $this->store_theme = $this->store_theme === 'light' ? 'dark' : 'light';
        $this->updateThemeButton();
        event(new UsimEvent('theme_changed', ['theme' => $this->store_theme]));
        $this->changeTheme($this->store_theme);
    }

    /**
     * @param array<string, mixed> $params
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
     * @param array<string, mixed> $params
     */
    public function onChangeUnit(array $params): void
    {
        $unitSlug = $this->stringParamOrDefault($params, 'unit', '');

        if (empty($unitSlug)) {
            return;
        }

        /** @var User $user */
        $user = Auth::user();
        /** @var Collection<int, UsimUnit> $units */
        $units = $this->usimUnitsService->getAvailableUnits();
        /** @var UsimUnit|null $unit */
        $unit = $units->firstWhere('slug', $unitSlug);

        if ($unit === null) {
            return;
        }

        if ($unit->slug === $this->store_unit) {
            return;
        }

        $this->store_unit = $unit->slug;

        setPermissionsTeamId($unit->id);

        event(new UsimEvent('unit_changed', [
            'unit_id' => $unit->id,
            'unit_slug' => $unit->slug,
            'user_id' => $user->id,
        ]));

        $this->updateUnitMenu();

        $this->toast(t(
            'screen.menu.unit_changed',
            ['unit' => $this->getUnitDisplayName($unit)]
        ), 'success');

        // event(new UsimEvent('reset_screen'));

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

        $activeUnit = $units->firstWhere('slug', $this->store_unit) ?? $units->first();
        if ($activeUnit instanceof UsimUnit) {
            $menu->trigger('🏢 ' . $this->getUnitDisplayName($activeUnit));
        }

        foreach ($units as $unit) {
            $label = $this->getUnitDisplayName($unit);
            if ($unit->slug === $this->store_unit) {
                $label = "✓ $label";
            }
            $menu->item($label, 'changeUnit', ['unit' => $unit->slug]);
        }
    }

    private function updateUnitMenu(): void
    {
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
            // Rebuild main menu to check permissions for screen() items
            $this->main_menu->clearItems();
            $this->populateMainMenu($this->main_menu);

            // Rebuild user menu to check permissions for items
            $this->user_menu->clearItems();
            $this->populateUserMenu($this->user_menu);
        } else {
            // Caso 1: Usuario no autenticado - trigger con icono de configuración
            $this->user_menu->trigger("⚙️");
        }
    }

    /**
     * Actualizar el trigger del menú de usuario según el estado del perfil
     */
    private function updateUserMenuTrigger(User $user): void
    {
        if ($user->profile_image) {
            // Caso 3: Usuario con imagen de perfil
            $imageUrl = UploadService::fileUrl("uploads/images/$user->profile_image");
            $this->user_menu->triggerImage(
                imageUrl: $imageUrl,
                alt: $user->name,
                label: $user->name
            );
        } else {
            // Caso 2: Usuario sin imagen de perfil - icono + nombre
            $this->user_menu->trigger("👤 $user->name");
        }
    }

    private function buildLeftMenu(): MenuDropdown
    {
        $main_menu = UI::menuDropdown('main_menu')
            ->trigger()
            ->position('bottom-left')
            ->width(Size::px(200));

        $this->populateMainMenu($main_menu);

        return $main_menu;
    }

    private function populateMainMenu(MenuDropdown $menu): void
    {
        $menu->link(t('screen.menu.items.home'), '/', '🏠');
        $menu->screen(UsersManager::class);
        $menu->screen(TranslateManager::class);
        $this->buildDemosMenu($menu);
        $menu->separator();
        $menu->item(t('screen.menu.items.about'), 'show_about_info', [], 'ℹ️');
    }

    private function buildDemosMenu(MenuDropdown $menu): void
    {
        $menu->separator();
        $menu->submenu(t('screen.menu.items.demos'), function ($submenu) {
            // $submenu->screen(AddressForm::class, 'Formulario de direccion', '📍');
            $submenu->screen(ButtonDemo::class, t('screen.menu.demos.button_demo'), '🖲️');
            $submenu->screen(TableDemo::class, t('screen.menu.demos.table_demo'), '📊');
            $submenu->screen(ModalDemo::class, t('screen.menu.demos.modal_demo'), '🪟');
            $submenu->item(t('screen.menu.demos.abort_error'), 'show_error_info', [], '❌');
            $submenu->screen(FormDemo::class, t('screen.menu.demos.form_demo'), '📝');
            // $submenu->screen(DemoUi::class, t('screen.menu.demos.demo_ui'), '🎨');
            $submenu->screen(InputDemo::class, t('screen.menu.demos.input_demo'), '⌨️');
            $submenu->screen(SelectDemo::class, t('screen.menu.demos.select_demo'), '📋');
            $submenu->screen(CheckboxDemo::class, t('screen.menu.demos.checkbox_demo'), '☑️');
            // $submenu->screen(UploaderDemo::class, t('screen.menu.demos.uploader_demo'), '📤');
            // $submenu->screen(CalendarDemo::class, t('screen.menu.demos.calendar_demo'), '📅');
            $submenu->screen(CarouselDemo::class, t('screen.menu.demos.carousel_demo'), '🎞️');
            $submenu->screen(TextareaDemo::class);
            $submenu->screen(SplitDemo::class);
            $submenu->screen(TabsDemo::class);
        }, '🎮');
    }

    private function buildUserMenu(): MenuDropdown
    {
        $user_menu = UI::menuDropdown('user_menu')
            ->position('bottom-right')
            ->width(Size::px(180));
        $user_menu->trigger("⚙️");
        $this->populateUserMenu($user_menu);
        return $user_menu;
    }

    private function populateUserMenu(MenuDropdown $menu): void
    {
        $menu->screen(Login::class);
        $menu->item(t('screen.menu.items.register'), 'show_register_form', [], '📝', visible: !Auth::check());
        $menu->screen(Profile::class);
        $menu->item(t('screen.menu.items.logout'), 'confirm_logout', [], '🚪', visible: Auth::check());
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onLoggedUser(array $params): void
    {
        /** @var User $user */
        $user = $params['user'] ?? null;
        $this->store_unit = $params['unit'] ?? '';

        $this->updateUserMenuTrigger($user);

        // Rebuild user menu to check permissions for items
        $this->user_menu->clearItems();
        $this->populateUserMenu($this->user_menu);

        // Rebuild main menu to check permissions for screen() items
        $this->main_menu->clearItems();
        $this->populateMainMenu($this->main_menu);

        $this->updateUnitMenu();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onUpdatedProfile(array $params): void
    {
        $user = $params['user'] ?? null;
        if ($user instanceof User) {
            $this->updateUserMenuTrigger($user);
        }
    }

    /**
     * Handler to confirm logout
     */
    /**
     * @param array<string, mixed> $params
     */
    public function onConfirmLogout(array $params): void
    {
        Auth::logout();

        $this->store_unit = '';
        $this->unit_menu->trigger('🏢');
        $this->unit_menu->clearItems();

        $this->user_menu->trigger("⚙️");

        // Rebuild user menu to update screen() items
        $this->user_menu->clearItems();
        $this->populateUserMenu($this->user_menu);

        // Rebuild main menu to remove restricted screen() items
        $this->main_menu->clearItems();
        $this->populateMainMenu($this->main_menu);

        $this->toast(t('screen.menu.logout_success'));
        $this->redirect();
    }

    /**
     * Handler for About info dialog
     */
    /**
     * @param array<string, mixed> $params
     */
    public function onShowAboutInfo(array $params): void
    {
        // Get this screen ID to receive the callback.
        $screenId = $this->getScreenComponentId();
        $version = "0.7.0";
        // This i18n message may include escaped "\\n" and markdown; the dialog renderer handles both.
        $aboutMessage = t('screen.menu.about.message', [
            'version' => $version,
        ]);

        ConfirmDialogService::open(
            type: DialogType::INFO,
            title: t('screen.menu.about.title'),
            message: $aboutMessage,
            callerServiceId: $screenId
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onShowErrorInfo(array $params): void
    {
        $this->abort(500, t('screen.menu.abort_demo_error'));
    }

    /**
     * Handler for Register form
     */
    /**
     * @param array<string, mixed> $params
     */
    public function onShowRegisterForm(array $params): void
    {
        RegisterDialog::open(
            submitAction: 'submit_register',
            fakeData: config('app.env') === 'local',
            callerServiceId: $this->getScreenComponentId()
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onOpenTermsAndConditions(array $params): void
    {
        TermsDialog::open(
            callerServiceId: $this->getScreenComponentId()
        );
    }

    /**
     * Handler to submit register (receives form data)
     */
    /**
     * @param array<string, mixed> $params
     */
    public function onSubmitRegister(array $params): void
    {
        if ($this->boolParamOrDefault($params, 'accept_terms', false) === false) {
            $this->toast(t('screen.menu.register_terms_required'), type: 'error');
            return;
        }

        $response = $this->registerService->register(
            name: $this->stringParamOrDefault($params, 'name', ''),
            email: $this->stringParamOrDefault($params, 'email', ''),
            password: $this->stringParamOrDefault($params, 'password', ''),
            passwordConfirmation: $this->stringParamOrDefault($params, 'password_confirmation', ''),
            roles: $this->normalizeRoles($params['roles'] ?? [config('usim.default_registering_role')]),
            sendVerificationEmail: $this->boolParamOrDefault($params, 'send_verification_email', true)
        );

        if ($response['status'] !== 'success') {
            $this->handleRegisterError($response);
            return;
        }

        $this->handleRegisterSuccess($response);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function handleRegisterSuccess(array $response): void
    {
        $messageValue = $response['message'] ?? t('screen.menu.register_success_default');
        $message = is_string($messageValue) ? $messageValue : t('screen.menu.register_success_default');
        $this->toast($message, 'success');

        $user = $response['user'] ?? null;
        if (!$user instanceof User) {
            $this->closeModal();
            return;
        }

        $token = data_get($response, 'data.token');
        $redirectTo = $this->authSessionService->start($user, is_string($token) ? $token : null);
        $this->redirect($redirectTo);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function handleRegisterError(array $response): void
    {
        $messageValue = $response['message'] ?? t('screen.menu.validation_errors_default');
        $message = is_string($messageValue) ? $messageValue : t('screen.menu.validation_errors_default');
        $this->toast($message, 'error');
        $this->updateModalValidationErrors($this->normalizeErrors($response['errors'] ?? []));
    }

    /**
     * @param array<string, mixed> $errors
     */
    private function updateModalValidationErrors(array $errors): void
    {
        if ($errors === []) {
            return;
        }

        $modalUpdates = [];
        foreach ($errors as $fieldName => $messages) {
            $modalUpdates[$fieldName] = [
                'error' => implode(' ', $this->normalizeStringList($messages)),
            ];
        }

        $this->updateModal($modalUpdates);
    }

    /**
     * Handler to close profile dialog
     */
    /**
     * @param array<string, mixed> $params
     */
    public function onCloseProfileDialog(array $params): void
    {
        $this->closeModal();
    }

    /**
     * Handler for Logout
     */
    /**
     * @param array<string, mixed> $params
     */
    public function onLogoutUser(array $params): void
    {
        $screenId = $this->getScreenComponentId();

        ConfirmDialogService::open(
            type: DialogType::CONFIRM,
            title: t('screen.menu.logout_confirm.title'),
            message: t('screen.menu.logout_confirm.message'),
            confirmAction: 'confirm_logout',
            cancelAction: 'cancel_logout',
            callerServiceId: $screenId
        );
    }

    /**
     * Handler to cancel logout
     */
    /**
     * @param array<string, mixed> $params
     */
    public function onCancelLogout(array $params): void
    {
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    private function stringParamOrDefault(array $params, string $key, string $default): string
    {
        $value = $params[$key] ?? null;
        return \is_string($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function boolParamOrDefault(array $params, string $key, bool $default): bool
    {
        $value = $params[$key] ?? null;
        return \is_bool($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function intParamOrDefault(array $params, string $key, int $default): int
    {
        $value = $params[$key] ?? null;
        return \is_int($value) ? $value : $default;
    }

    /**
     * @param mixed $roles
     * @return list<string>
     */
    private function normalizeRoles(mixed $roles): array
    {
        if (\is_string($roles)) {
            return [$roles];
        }

        if (!is_array($roles)) {
            return ['user'];
        }

        return $this->normalizeStringList($roles) ?: ['user'];
    }

    /**
     * @param mixed $value
     * @return array<string, mixed>
     */
    private function normalizeErrors(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $key => $messages) {
            if (!is_string($key)) {
                continue;
            }

            $normalized[$key] = $messages;
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (is_string($item)) {
                $normalized[] = $item;
            }
        }

        return $normalized;
    }

    private function normalizeLocale(mixed $locale): string
    {
        if (is_string($locale) && $locale !== '') {
            return $locale;
        }

        throw new InvalidArgumentException('Fallback locale must be a non-empty string.');
    }
}
