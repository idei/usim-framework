<?php
namespace App\UI\Screens;

use App\UI\Components\Modals\RegisterDialog;
use App\Services\Auth\AuthSessionService;
use App\Services\Auth\RegisterService;
use App\UI\Screens\Admin\Dashboard;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Profile;
use App\UI\Screens\Demo\ButtonDemo;
use App\UI\Screens\Demo\CalendarDemo;
use App\UI\Screens\Demo\CheckboxDemo;
use App\UI\Screens\Demo\DemoUi;
use App\UI\Screens\Demo\FormDemo;
use App\UI\Screens\Demo\InputDemo;
use App\UI\Screens\Demo\ModalDemo;
use App\UI\Screens\Demo\SelectDemo;
use App\UI\Screens\Demo\TableDemo;
use App\UI\Screens\Demo\UploaderDemo;
use Idei\Usim\Services\AbstractUIService;
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
    protected string $store_token = '';
    protected string $store_password = '';

    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container
            ->parent('menu') // Important to set parent!
            ->shadow(0)
            ->borderRadius(0)
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(0);

        $this->main_menu = $this->buildLeftMenu();
        $this->user_menu = $this->buildUserMenu();

        $container->add($this->main_menu);
        $container->add($this->user_menu);
    }

    protected function postLoadUI(): void
    {
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
        $menu->link('Home', '/', '🏠');
        $menu->screen(Dashboard::class);
        $this->buildDemosMenu($menu);
        $menu->separator();
        $menu->item('About', 'show_about_info', [], 'ℹ️');
    }

    private function buildDemosMenu(MenuDropdownBuilder $menu): void
    {
        if (env('APP_DEMO_MODE', true) === false) {
            return;
        }

        $menu->separator();
        $menu->submenu('Demos', function ($submenu) {
            $submenu->screen(ButtonDemo::class, "Button Demo", '🖲️');
            $submenu->screen(TableDemo::class, "Table Demo", '📊');
            $submenu->screen(ModalDemo::class, "Modal Demo", '🪟');
            $submenu->item('Abort Error', 'show_error_info', [], '❌');
            $submenu->screen(FormDemo::class, "Form Demo", '📝');
            $submenu->screen(DemoUi::class, "Demo UI", '🎨');
            $submenu->screen(InputDemo::class, "Input Demo", '⌨️');
            $submenu->screen(SelectDemo::class, "Select Demo", '📋');
            $submenu->screen(CheckboxDemo::class, "Checkbox Demo", '☑️');
            $submenu->screen(UploaderDemo::class, "Uploader Demo", '📤');
            $submenu->screen(CalendarDemo::class, "Calendar Demo", '📅');
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
        $menu->item('Register', 'show_register_form', [], '📝', visible: !Auth::check());
        $menu->screen(Profile::class);
        $menu->item('Logout', 'confirm_logout', [], '🚪', visible: Auth::check());
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

        // Clear storage variables
        $this->store_token = '';
        $this->store_password = '';

        $this->user_menu->trigger("⚙️");

        // Rebuild user menu to update screen() items
        $this->user_menu->clearItems();
        $this->populateUserMenu($this->user_menu);

        // Rebuild main menu to remove restricted screen() items
        $this->main_menu->clearItems();
        $this->populateMainMenu($this->main_menu);

        $this->toast('You have been logged out successfully.');
        $this->redirect();
    }

    /**
     * Handler for About info dialog
     */
    public function onShowAboutInfo(array $params): void
    {
        // Get this service ID to receive the callback
        $serviceId = $this->getServiceComponentId();

        ConfirmDialogService::open(
            type: DialogType::INFO,
            title: "Acerca de USIM Framework",
            message: "Sistema de componentes UI v1.0\n
            Desarrollado con Laravel y componentes modulares.\n
            Soporta: Tables, Modals, Forms, Menus y más.",
            callerServiceId: $serviceId
        );
    }

    public function onShowErrorInfo(array $params): void
    {
        $this->abort(500, "This is a simulated error for testing error handling.");
    }

    /**
     * Handler for Register form
     */
    public function onShowRegisterForm(array $params): void
    {
        RegisterDialog::open(
            submitAction: 'submit_register',
            fakeData: true,
            callerServiceId: $this->getServiceComponentId()
        );
    }

    /**
     * Handler to submit register (receives form data)
     */
    public function onSubmitRegister(array $params): void
    {
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
        $message = (string) ($response['message'] ?? 'Usuario registrado exitosamente');
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
        $message = (string) ($response['message'] ?? 'Validation errors');
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
            title: "Cerrar Sesión",
            message: "¿Estás seguro que deseas cerrar sesión?",
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
