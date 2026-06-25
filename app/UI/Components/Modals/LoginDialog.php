<?php

namespace App\UI\Components\Modals;

use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Login Dialog Service
 *
 * Provides a modal dialog with login form
 */
class LoginDialog
{
    /**
     * Build login dialog UI
     *
     * @param string $submitAction Action to call when form is submitted
     * @param string|null $cancelAction Action to call when cancel is clicked
     * @param int|null $callerServiceId Service ID that will receive callbacks
     * @return array UI components for the modal
     */
    public function getUI(
        ?int $callerServiceId = null,
        string $submitAction = 'submit_login',
        ?string $cancelAction = 'close_login_dialog',
        string $defaultEmail = '',
        string $defaultPassword = ''
    ): array {
        // Main container for the modal
        $loginContainer = UI::container('login_dialog')
            ->parent('modal')
            ->shadow(false)
            ->padding(Spacing::px(30));

        // Email input
        $loginContainer->add(
            UI::input('login_email')
                ->label('Email')
                ->placeholder('Enter your email')
                ->value($defaultEmail)
                ->required(true)
        );

        // Password input
        $loginContainer->add(
            UI::input('login_password')
                ->label('Password')
                ->type('password')
                ->placeholder('Enter your password')
                ->value($defaultPassword)
                ->required(true)
        );

        // Buttons container
        $buttonsContainer = UI::container('login_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->gap(Spacing::px(10))
            ->shadow(false)
            ->padding(Spacing::each(Spacing::px(20)));

        // Cancel button
        if ($cancelAction) {
            $buttonsContainer->add(
                UI::button('btn_cancel_login')
                    ->label('Cancel')
                    ->style('secondary')
                    ->action($cancelAction, [
                        '_caller_service_id' => $callerServiceId
                    ])
            );
        }

        // Submit button
        $buttonsContainer->add(
            UI::button('btn_submit_login')
                ->label('Login')
                ->style('primary')
                ->action($submitAction, [
                    '_caller_service_id' => $callerServiceId
                ])
        );

        $loginContainer->add($buttonsContainer);

        return $loginContainer->toJson();
    }
}
