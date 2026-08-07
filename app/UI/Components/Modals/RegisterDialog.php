<?php

namespace App\UI\Components\Modals;

use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
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
     * @param mixed ...$params
     */
    public static function open(...$params): void
    {
        $dialog = new self();
        $format = $dialog->getUI(...$params);
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
        $role = 'user';
        if ($fakeData) {
            $userData = FakeDataHelper::userData(['user', 'admin']);
            $name = $userData['name'];
            $email = $userData['email'];
            $password = $userData['password'];
            $password_confirmation = $userData['password_confirmation'];
            $role = $userData['role'];
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

            // Role select
            $registerContainer->add(
                UI::select('roles')
                    ->label(t('modal.register_dialog.role.label'))
                    ->options([
                        ['value' => 'user', 'label' => t('modal.register_dialog.role.user')],
                        ['value' => 'admin', 'label' => t('modal.register_dialog.role.admin')],
                    ])
                    ->value($role)
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
