<?php

namespace App\UI\Components\Modals;

use App\Services\Role\RoleService;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Support\FakeDataHelper;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Register Dialog Service
 *
 * Provides a modal dialog with registration form
 */
class RegisterDialog
{

    /**
     * @param string $submitAction
     * @param string|null $cancelAction
     * @param bool $fakeData
     * @param bool $askForRole
     * @param int|null $callerServiceId
     */
    public static function open(
        string $submitAction = 'submit_register',
        ?string $cancelAction = 'close_modal',
        bool $fakeData = false,
        bool $askForRole = false,
        ?int $callerServiceId = null
    ): void {
        $dialog = new self();
        $format = $dialog->getUI($submitAction, $cancelAction, $fakeData, $askForRole, $callerServiceId);
        $uiChanges = app(UIChangesCollector::class);
        $uiChanges->add($format);
    }

    /**
     * Build register dialog UI
     *
     * @param string $submitAction Action to call when form is submitted
     * @param string|null $cancelAction Action to call when cancel is clicked
     * @param int|null $callerServiceId Service ID that will receive callbacks
     * @return array<int, array<string, mixed>> UI components for the modal
     */
    public function getUI(
        string $submitAction = 'submit_register',
        ?string $cancelAction = 'close_modal',
        bool $fakeData = false,
        bool $askForRole = false,
        ?int $callerServiceId = null
    ): array {
        $name = '';
        $email = '';
        $password = '';
        $password_confirmation = '';
        /** @var list<string> $selectedRoles */
        $selectedRoles = [config('usim.default_registering_role')];

        if ($fakeData) {
            $roleService = app(RoleService::class);
            $availableRoles = array_map(
                static fn(UsimRole $role): string => $role->name,
                $roleService->getAllowedRoles()
            );
            if (empty($availableRoles)) {
                $availableRoles = ['user', 'admin'];
            }

            $userData = FakeDataHelper::userData($availableRoles);
            $name = $userData['name'];
            $email = $userData['email'];
            $password = $userData['password'];
            $password_confirmation = $userData['password_confirmation'];
            $selectedRoles = [$userData['role']];
        }
        // Main container for the modal
        $registerContainer = UI::container('register_dialog')
            ->parent('modal')
            ->shadow(false)
            ->plain()
            ->padding(Spacing::px(20));

        // Name input
        $registerContainer->add(
            UI::input('name')
                ->label(t('modal.register_dialog.name.label'))
                ->placeholder(t('modal.register_dialog.name.placeholder'))
                ->required(true)
                ->value($name)
                ->autocomplete('off')
        );

        // Email input
        $registerContainer->add(
            UI::input('email')
                ->label(t('modal.register_dialog.email.label'))
                ->placeholder(t('modal.register_dialog.email.placeholder'))
                ->required(true)
                ->value($email)
                ->autocomplete('off')
        );

        // Password input
        $registerContainer->add(
            UI::input('password')
                ->label(t('modal.register_dialog.password.label'))
                ->type('password')
                ->placeholder(t('modal.register_dialog.password.placeholder'))
                ->required(true)
                ->value($password)
                ->autocomplete('new-password')
        );

        // Password confirmation
        $registerContainer->add(
            UI::input('password_confirmation')
                ->label(t('modal.register_dialog.confirm_password.label'))
                ->type('password')
                ->placeholder(t('modal.register_dialog.confirm_password.placeholder'))
                ->required(true)
                ->value($password_confirmation)
                ->autocomplete('new-password')
        );

        if ($askForRole) {
            $roleService = app(RoleService::class);
            $roles = $roleService->getAllowedRoles();

            /** @var list<array{value: string, label: string}> $roleOptions */
            $roleOptions = array_map(static fn(UsimRole $role): array => [
                'value' => $role->name,
                'label' => t("role.{$role->name}.name"),
            ], $roles);

            if (empty($roleOptions)) {
                $roleOptions = [
                    ['value' => 'user', 'label' => t('role.user.name')],
                    ['value' => 'admin', 'label' => t('role.admin.name')],
                ];
            }

            // Role checkbox list
            $registerContainer->add(
                UI::checkbox('roles')
                    ->label(t('modal.register_dialog.role.label'))
                    ->options($roleOptions)
                    ->vertical()
                    ->selectedValues($selectedRoles)
                    ->required(true)
            );

            // Checkbox for sending verification email
            $registerContainer->add(
                UI::checkbox('send_verification_email')
                    ->label(t('modal.register_dialog.send_verification_email'))
                    ->checked(true)
            );

        } else {

            // Add Checkbox for accepting terms and conditions
            $registerContainer->add(
                UI::checkbox('accept_terms')
                    ->label(t('modal.register_dialog.accept_terms'))
                    ->checked(false)
                    ->required(true)
            );

            // The link to the page with terms and conditions (a button with link style)
            $registerContainer->add(
                UI::button('btn_terms')
                    ->label(t('modal.register_dialog.read_terms'))
                    ->style('link')
                    ->action('open_terms_and_conditions')
            );
        }

        // Buttons container
        $buttonsContainer = UI::container('register_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->shadow(false)
            ->plain()
            ->gap(Spacing::px(10))
            ->padding(Spacing::each(Spacing::px(10)));

        // Cancel button
        if ($cancelAction) {
            $buttonsContainer->add(
                UI::button('btn_cancel_register')
                    ->label(t('modal.register_dialog.cancel'))
                    ->style('secondary')
                    ->action($cancelAction, [
                        '_caller_service_id' => $callerServiceId
                    ])
            );
        }

        // Submit button
        $buttonsContainer->add(
            UI::button('btn_submit_register')
                ->label(t('modal.register_dialog.submit'))
                ->style('primary')
                ->action($submitAction, [
                    '_caller_service_id' => $callerServiceId
                ])
        );

        $registerContainer->add($buttonsContainer);

        return $registerContainer->toJson();
    }
}
