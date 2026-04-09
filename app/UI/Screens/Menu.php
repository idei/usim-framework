<?php
namespace App\UI\Screens;

use App\Services\Auth\AuthSessionService;
use App\Services\Auth\RegisterService;
use App\UI\Components\Modals\RegisterDialog;
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
use Idei\Usim\Services\AbstractUIService;
use Idei\Usim\Services\Components\ButtonBuilder;
use Idei\Usim\Services\Components\MenuDropdownBuilder;
use Idei\Usim\Services\Components\UIContainer;
use Idei\Usim\Services\Enums\AlignItems;
use Idei\Usim\Services\Enums\DialogType;
use Idei\Usim\Services\Enums\JustifyContent;
use Idei\Usim\Services\Enums\LayoutType;
use Idei\Usim\Services\Modals\ConfirmDialogService;
use Idei\Usim\Services\UIBuilder;
use Idei\Usim\Services\Upload\UploadService;
use Illuminate\Support\Facades\Auth;

/**
 * Menu Service
 *
 * Builds the main navigation menu for screens
 */
class Menu extends AbstractUIService
{
    public function __construct(
        protected RegisterService $registerService,
        protected AuthSessionService $authSessionService
    ) {
    }

    protected MenuDropdownBuilder $main_menu;
    protected MenuDropdownBuilder $user_menu;
    protected ButtonBuilder $theme_toggle;
    protected string $store_theme = 'light';

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

        $container->add($this->main_menu);
        $this->theme_toggle = UIBuilder::button('theme_toggle')
            ->action('toggleTheme')
            ->plain()
            ->marginLeft('auto');
        $container->add($this->theme_toggle);
        $container->add($this->user_menu);
        $this->updateThemeButton();
    }

    private function updateThemeButton(): void
    {
        $icon = $this->store_theme === 'light' ? 'theme-icon-light' : 'theme-icon-dark';
        $this->theme_toggle->icon("/vendor/idei/usim/images/$icon.svg");
        $this->theme_toggle->iconColor('var(--usim-menu-trigger-text)');
        $this->theme_toggle->iconSize(24);
        $this->theme_toggle->tooltip(t('app.screen.menu.theme_switch_to', ['theme' => $this->store_theme === 'light' ? 'dark' : 'light']));
    }

    public function onToggleTheme(array $params): void
    {
        $this->store_theme = $this->store_theme === 'light' ? 'dark' : 'light';
        $this->updateThemeButton();
        event(new UsimEvent('theme_changed', ['theme' => $this->store_theme]));
        $this->changeTheme($this->store_theme);
    }

    protected function postLoadUI(): void
    {
        $this->updateThemeButton();

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

    private function buildLeftMenu(): MenuDropdownBuilder
    {
        $main_menu = UIBuilder::menuDropdown('main_menu')
            ->trigger()
            ->position('bottom-left')
            ->width(200);

        $this->populateMainMenu($main_menu);

        return $main_menu;
    }

    private function populateMainMenu(MenuDropdownBuilder $menu): void
    {
        $menu->link(t('app.screen.menu.items.home'), '/', '🏠');
        $menu->screen(Dashboard::class);
        $menu->screen(TranlateManager::class);
        $this->buildDemosMenu($menu);
        $menu->separator();
        $menu->item(t('app.screen.menu.items.about'), 'show_about_info', [], 'ℹ️');
    }

    private function buildDemosMenu(MenuDropdownBuilder $menu): void
    {
        $menu->separator();
        $menu->submenu(t('app.screen.menu.items.demos'), function ($submenu) {
            $submenu->screen(ButtonDemo::class, t('app.screen.menu.demos.button_demo'), '🖲️');
            $submenu->screen(TableDemo::class, t('app.screen.menu.demos.table_demo'), '📊');
            $submenu->screen(ModalDemo::class, t('app.screen.menu.demos.modal_demo'), '🪟');
            $submenu->item(t('app.screen.menu.demos.abort_error'), 'show_error_info', [], '❌');
            $submenu->screen(FormDemo::class, t('app.screen.menu.demos.form_demo'), '📝');
            $submenu->screen(DemoUi::class, t('app.screen.menu.demos.demo_ui'), '🎨');
            $submenu->screen(InputDemo::class, t('app.screen.menu.demos.input_demo'), '⌨️');
            $submenu->screen(SelectDemo::class, t('app.screen.menu.demos.select_demo'), '📋');
            $submenu->screen(CheckboxDemo::class, t('app.screen.menu.demos.checkbox_demo'), '☑️');
            $submenu->screen(UploaderDemo::class, t('app.screen.menu.demos.uploader_demo'), '📤');
            $submenu->screen(CalendarDemo::class, t('app.screen.menu.demos.calendar_demo'), '📅');
            $submenu->screen(CarouselDemo::class, t('app.screen.menu.demos.carousel_demo'), '🎞️');
        }, '🎮');
    }

    private function buildUserMenu(): MenuDropdownBuilder
    {
        $user_menu = UIBuilder::menuDropdown('user_menu')
            ->position('bottom-right')
            ->width(180);
        $user_menu->trigger("⚙️");
        $this->populateUserMenu($user_menu);
        return $user_menu;
    }

    private function populateUserMenu(MenuDropdownBuilder $menu): void
    {
        $menu->screen(Login::class);
        $menu->item(t('app.screen.menu.items.register'), 'show_register_form', [], '📝', visible: !Auth::check());
        $menu->screen(Profile::class);
        $menu->item(t('app.screen.menu.items.logout'), 'confirm_logout', [], '🚪', visible: Auth::check());
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

        $this->toast(t('app.screen.menu.logout_success'));
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

        ConfirmDialogService::open(
            type: DialogType::INFO,
            title: t('app.screen.menu.about.title'),
            message: t('app.screen.menu.about.message', [
                'version' => $version
            ]),
            callerServiceId: $serviceId
        );
    }

    public function onShowErrorInfo(array $params): void
    {
        $this->abort(500, t('app.screen.menu.abort_demo_error'));
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
        $this->redirect('terms');
    }

    /**
     * Handler to submit register (receives form data)
     */
    public function onSubmitRegister(array $params): void
    {
        if ($params['accept_terms'] == false) {
            $this->toast(t('app.screen.menu.register_terms_required'), type: 'error');
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
        $message = (string) ($response['message'] ?? t('app.screen.menu.register_success_default'));
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
        $message = (string) ($response['message'] ?? t('app.screen.menu.validation_errors_default'));
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
            title: t('app.screen.menu.logout_confirm.title'),
            message: t('app.screen.menu.logout_confirm.message'),
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
