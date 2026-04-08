<?php

namespace App\UI\Screens\Auth;

use App\Services\Auth\PasswordService;
use Idei\Usim\Services\UIBuilder;
use Idei\Usim\Services\Enums\LayoutType;
use Idei\Usim\Services\AbstractUIService;
use Idei\Usim\Services\Components\UIContainer;
use Idei\Usim\Services\Components\InputBuilder;
use Idei\Usim\Services\Components\LabelBuilder;

class ResetPassword extends AbstractUIService
{
    public function __construct(
        protected PasswordService $passwordService
    ) {
    }

    protected LabelBuilder $lbl_result;
    protected InputBuilder $password;
    protected InputBuilder $password_confirmation;

    public function buildBaseUI(UIContainer $container, ...$params): void
    {
        $token = request()->query('token');
        $email = request()->query('email');

        $container
            ->layout(LayoutType::VERTICAL)
            ->justifyContent('start')
            ->alignItems('center')
            ->plain()
            ->padding(40)
            ->paddingTop('80px')
            ->minHeight('100vh');

        // Icono superior
        $container->add(
            UIBuilder::label('key_icon')
                ->text('🔑')
                ->style('h1')
                ->center()
                ->fontSize('80px')
        );

        $container->add(
            UIBuilder::label('lbl_title')
                ->text(t('app.screen.auth.reset_password.title'))
                ->style('h2')
                ->center()
                ->color('#10b981') // Green to match theme
        );

        // Subtitle moved inside card
        /*
        $container->add(
            UIBuilder::label('lbl_subtitle')
                ->text('Por favor ingresa tu nueva contraseña segura.')
                ->style('p')
                ->center()
                ->color('#6b7280')
                ->marginTop('10px')
        );
        */

        // Card Container
        $formCard = UIBuilder::container('reset_password_card')
            ->layout(LayoutType::VERTICAL)
            ->shadow(true)
            ->maxWidth('600px')
            ->width('100%')
            ->borderRadius('8px')
            ->marginTop('30px')
            ->padding(30)
            ->gap('20px')
            ->backgroundColor('white')
            ->customStyle('border-left: 5px solid #10b981; overflow: hidden;');

        $formCard->add(
            UIBuilder::label('card_title')
                ->text(t('app.screen.auth.reset_password.card_title'))
                ->style('h3')
                ->color('#1f2937')
                ->marginBottom('5px')
        );

        $formCard->add(
            UIBuilder::label('lbl_subtitle_card')
                ->text(t('app.screen.auth.reset_password.instruction'))
                ->style('p')
                ->color('#6b7280')
                ->marginBottom('15px')
        );

        // Hidden fields for token and email
        $formCard->add(
             UIBuilder::input('reset_token')->type('hidden')->value($token ?? '')
        );
        $formCard->add(
             UIBuilder::input('reset_email')->type('hidden')->value($email ?? '')
        );

        $formCard->add(
            UIBuilder::input('password')
                ->label(t('app.screen.auth.reset_password.password.label'))
                ->type('password')
                ->placeholder(t('app.screen.auth.reset_password.password.placeholder'))
                ->required(true)
                ->width('100%')
        );

        $formCard->add(
            UIBuilder::input('password_confirmation')
                ->label(t('app.screen.auth.reset_password.confirm.label'))
                ->type('password')
                ->placeholder(t('app.screen.auth.reset_password.confirm.placeholder'))
                ->required(true)
                ->width('100%')
        );

        $formCard->add(
            UIBuilder::label('lbl_result')
                ->text('')
                ->visible(false)
                ->center()
        );

        $formCard->add(
            UIBuilder::button('btn_reset')
                ->label(t('app.screen.auth.reset_password.actions.submit'))
                ->style('success')
                ->action('reset_password')
                ->marginTop('10px')
        );

        $container->add($formCard);
    }

    public function onResetPassword(array $params): void
    {
        $token = $params['reset_token'] ?? '';
        $email = $params['reset_email'] ?? '';
        $expires = (int) ($params['expires'] ?? request()->query('expires', 0));
        $password = $params['password'] ?? '';
        $passwordConfirmation = $params['password_confirmation'] ?? '';

        if (empty($token) || empty($email)) {
             $this->showError(t('app.screen.auth.reset_password.errors.invalid_link'));
             return;
        }

        if ($expires > 0 && now()->timestamp > $expires) {
            $this->showError(t('app.screen.auth.reset_password.errors.link_expired'));
            return;
        }

        if (strlen($password) < 8) {
            $this->showError(t('app.screen.auth.reset_password.validation.min_length'));
            return;
        }

        if ($password !== $passwordConfirmation) {
            $this->showError(t('app.screen.auth.reset_password.validation.mismatch'));
            return;
        }

        try {
            $response = $this->passwordService->resetPassword(
                token: $token,
                email: $email,
                password: $password,
                passwordConfirmation: $passwordConfirmation
            );

            $status = $response['status'] ?? 'error';
            $message = $response['message'] ?? t('app.screen.auth.reset_password.errors.unknown');

            if ($status === 'success') {
                $this->lbl_result
                    ->text(t('app.screen.auth.reset_password.success.label'))
                    ->style('text-green-600 font-medium')
                    ->visible(true);

                $this->toast(t('app.screen.auth.reset_password.toast.success'), 'success');

                // Redirect to login after short delay (handled by frontend if possible, or immediate)
                $this->redirect('/auth/login');
            } else {
                // Extract validation errors if any
                if (isset($response['errors']) && is_array($response['errors'])) {
                     $firstError = reset($response['errors'])[0] ?? $message;
                     $this->showError($firstError);
                } else {
                    $this->showError($message);
                }
            }

        } catch (\Exception $e) {
            $this->showError(t('app.screen.auth.reset_password.errors.connection', ['message' => $e->getMessage()]));
        }
    }

    private function showError(string $message): void
    {
        if (isset($this->lbl_result)) {
            $this->lbl_result->text($message)->style('text-red-500 text-sm')->visible(true);
        }
        $this->toast($message, 'error');
    }
}
