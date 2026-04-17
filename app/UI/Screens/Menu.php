<?php
namespace App\UI\Screens;

use App\Services\Auth\AuthSessionService;
use App\Services\Auth\RegisterService;
use App\UI\Components\Modals\RegisterDialog;
use App\UI\Components\Modals\TermsDialog;
use App\UI\Screens\Admin\Dashboard;
use App\UI\Screens\Admin\TranlateManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Profile;
use App\UI\Screens\Demo\ButtonDemo;
use App\UI\Screens\Demo\CalendarDemo;
use App\UI\Screens\Demo\CarouselDemo;
use App\UI\Screens\Demo\CheckboxDemo;
use App\UI\Screens\Demo\DemoUi;
use App\UI\Screens\Demo\FormDemo;
use App\UI\Screens\Demo\InputDemo;
use App\UI\Screens\Demo\ModalDemo;
use App\UI\Screens\Demo\SelectDemo;
use App\UI\Screens\Demo\TableDemo;
use App\UI\Screens\Demo\UploaderDemo;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Components\UIContainer;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Modals\ConfirmDialogService;
use Idei\Usim\Models\UsimLanguage;
use Idei\Usim\UIBuilder;
use Idei\Usim\Upload\UploadService;
use Illuminate\Support\Facades\Auth;

/**
 * Menu Service
 *
 * Builds the main navigation menu for screens
 */
class Menu extends Screen
{
    public function __construct(
        protected RegisterService $registerService,
        protected AuthSessionService $authSessionService
    ) {
    }

    protected MenuDropdown $main_menu;
    protected MenuDropdown $user_menu;
    protected MenuDropdown $lang_menu;
    protected Button $theme_toggle;
    protected string $store_theme = 'light';
    protected string $store_lang = '';

    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container
            ->parent('menu') // Important to set parent!
            ->plain()
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(0)
            ->marginBottom('0');

        $this->main_menu = $this->buildLeftMenu();
        $this->user_menu = $this->buildUserMenu();

        if (empty($this->store_lang)) {
            $this->store_lang = config('usim.i18n.fallback_locale', 'en');
        }
        $this->lang_menu = $this->buildLangMenu();

        $container->add($this->main_menu);
        $this->theme_toggle = UIBuilder::button('theme_toggle')
            ->action('toggleTheme')
            ->plain()
            ->marginLeft('auto');
        $container->add($this->theme_toggle);
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

    public function onToggleTheme(array $params): void
    {
        $this->store_theme = $this->store_theme === 'light' ? 'dark' : 'light';
        $this->updateThemeButton();
        event(new UsimEvent('theme_changed', ['theme' => $this->store_theme]));
        $this->changeTheme($this->store_theme);
    }

    public function onChangeLang(array $params): void
    {
        $lang = $params['lang'] ?? '';
        if (empty($lang)) {
            return;
        }

        $this->store_lang = $lang;
        $this->updateLangMenu();
        $this->changeLanguage($this->store_lang);

        $referer = request()->headers->get('referer');
        if (empty($referer) || str_contains($referer, '/api/ui-event')) {
            $referer = url('/');
        }

        $parts = parse_url($referer);
        $path = $parts['path'] ?? '/';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['reset'] = 'true';

        $target = $path;
        if (!empty($query)) {
            $target .= '?' . http_build_query($query);
        }

        $this->redirect($target);
    }

    private function buildLangMenu(): MenuDropdown
    {
        $lang_menu = UIBuilder::menuDropdown('lang_menu')
            ->trigger(strtoupper($this->store_lang))
            ->position('bottom-right')
            ->width(160);

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
            $this->store_lang = config('usim.i18n.fallback_locale', 'en');
        }

        $this->lang_menu->trigger(strtoupper($this->store_lang));
        $this->populateLangMenu($this->lang_menu);
    }

    protected function postLoadUI(): void
    {
        $this->updateThemeButton();
        $this->updateLangMenu();

        if (Auth::check()) {
            $user = Auth::user();
            $this->updateUserMenuTrigger($user);
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
    private function updateUserMenuTrigger($user): void
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
        $main_menu = UIBuilder::menuDropdown('main_menu')
            ->trigger()
            ->position('bottom-left')
            ->width(200);

        $this->populateMainMenu($main_menu);

        return $main_menu;
    }

    private function populateMainMenu(MenuDropdown $menu): void
    {
        $menu->link(t('screen.menu.items.home'), '/', '🏠');
        $menu->screen(Dashboard::class);
        $menu->screen(TranlateManager::class);
        $this->buildDemosMenu($menu);
        $menu->separator();
        $menu->item(t('screen.menu.items.about'), 'show_about_info', [], 'ℹ️');
    }

    private function buildDemosMenu(MenuDropdown $menu): void
    {
        $menu->separator();
        $menu->submenu(t('screen.menu.items.demos'), function ($submenu) {
            $submenu->screen(ButtonDemo::class, t('screen.menu.demos.button_demo'), '🖲️');
            $submenu->screen(TableDemo::class, t('screen.menu.demos.table_demo'), '📊');
            $submenu->screen(ModalDemo::class, t('screen.menu.demos.modal_demo'), '🪟');
            $submenu->item(t('screen.menu.demos.abort_error'), 'show_error_info', [], '❌');
            $submenu->screen(FormDemo::class, t('screen.menu.demos.form_demo'), '📝');
            $submenu->screen(DemoUi::class, t('screen.menu.demos.demo_ui'), '🎨');
            $submenu->screen(InputDemo::class, t('screen.menu.demos.input_demo'), '⌨️');
            $submenu->screen(SelectDemo::class, t('screen.menu.demos.select_demo'), '📋');
            $submenu->screen(CheckboxDemo::class, t('screen.menu.demos.checkbox_demo'), '☑️');
            $submenu->screen(UploaderDemo::class, t('screen.menu.demos.uploader_demo'), '📤');
            $submenu->screen(CalendarDemo::class, t('screen.menu.demos.calendar_demo'), '📅');
            $submenu->screen(CarouselDemo::class, t('screen.menu.demos.carousel_demo'), '🎞️');
        }, '🎮');
    }

    private function buildUserMenu(): MenuDropdown
    {
        $user_menu = UIBuilder::menuDropdown('user_menu')
            ->position('bottom-right')
            ->width(180);
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

    public function onLoggedUser(array $params): void
    {
        $user = Auth::user();
        if ($user) {
            $this->updateUserMenuTrigger($user);

            // Rebuild user menu to check permissions for items
            $this->user_menu->clearItems();
            $this->populateUserMenu($this->user_menu);

            // Rebuild main menu to check permissions for screen() items
            $this->main_menu->clearItems();
            $this->populateMainMenu($this->main_menu);
        }
    }

    public function onUpdatedProfile(array $params): void
    {
        $user = $params['user'] ?? null;
        if ($user) {
            $this->updateUserMenuTrigger($user);
        }
    }    /**
         * Handler to confirm logout
         */
    public function onConfirmLogout(array $params): void
    {
        // Delete Sanctum token if user is authenticated
        $user = request()->user();
        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }
        Auth::logout();

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
    public function onShowAboutInfo(array $params): void
    {
        // Get this service ID to receive the callback
        $serviceId = $this->getServiceComponentId();
        $version = "0.7.0";
        // This i18n message may include escaped "\\n" and markdown; the dialog renderer handles both.
        $aboutMessage = t('screen.menu.about.message', [
            'version' => $version,
        ]);

        ConfirmDialogService::open(
            type: DialogType::INFO,
            title: t('screen.menu.about.title'),
            message: $aboutMessage,
            callerServiceId: $serviceId
        );
    }

    public function onShowErrorInfo(array $params): void
    {
        $this->abort(500, t('screen.menu.abort_demo_error'));
    }

    /**
     * Handler for Register form
     */
    public function onShowRegisterForm(array $params): void
    {
        RegisterDialog::open(
            submitAction: 'submit_register',
            fakeData: config('app.env') === 'local',
            callerServiceId: $this->getServiceComponentId()
        );
    }

    public function onOpenTermsAndConditions(array $params): void
    {
        TermsDialog::open(
            callerServiceId: $this->getServiceComponentId()
        );
    }

    /**
     * Handler to submit register (receives form data)
     */
    public function onSubmitRegister(array $params): void
    {
        if ($params['accept_terms'] == false) {
            $this->toast(t('screen.menu.register_terms_required'), type: 'error');
            return;
        }

        $response = $this->registerService->register(
            name: $params['name'] ?? '',
            email: $params['email'] ?? '',
            password: $params['password'] ?? '',
            passwordConfirmation: $params['password_confirmation'] ?? '',
            roles: (array) ($params['roles'] ?? ['user']),
            sendVerificationEmail: (bool) ($params['send_verification_email'] ?? true)
        );

        if (($response['status'] ?? 'error') !== 'success') {
            $this->handleRegisterError($response);
            return;
        }

        $this->handleRegisterSuccess($response);
    }

    private function handleRegisterSuccess(array $response): void
    {
        $message = (string) ($response['message'] ?? t('screen.menu.register_success_default'));
        $this->toast($message, 'success');

        $user = $response['user'] ?? null;
        if (!$user) {
            $this->closeModal();
            return;
        }

        $token = data_get($response, 'data.token');
        $redirectTo = $this->authSessionService->start($user, is_string($token) ? $token : null);
        $this->redirect($redirectTo);
    }

    private function handleRegisterError(array $response): void
    {
        $message = (string) ($response['message'] ?? t('screen.menu.validation_errors_default'));
        $this->toast($message, 'error');
        $this->updateModalValidationErrors((array) ($response['errors'] ?? []));
    }

    private function updateModalValidationErrors(array $errors): void
    {
        if ($errors === []) {
            return;
        }

        $modalUpdates = [];
        foreach ($errors as $fieldName => $messages) {
            $modalUpdates[$fieldName] = [
                'error' => implode(' ', (array) $messages),
            ];
        }

        $this->updateModal($modalUpdates);
    }

    /**
     * Handler to close profile dialog
     */
    public function onCloseProfileDialog(array $params): void
    {
        $this->closeModal();
    }

    /**
     * Handler for Logout
     */
    public function onLogoutUser(array $params): void
    {
        $serviceId = $this->getServiceComponentId();

        ConfirmDialogService::open(
            type: DialogType::CONFIRM,
            title: t('screen.menu.logout_confirm.title'),
            message: t('screen.menu.logout_confirm.message'),
            confirmAction: 'confirm_logout',
            cancelAction: 'cancel_logout',
            callerServiceId: $serviceId
        );
    }

    /**
     * Handler to cancel logout
     */
    public function onCancelLogout(array $params): void
    {
        $this->closeModal();
    }
}
