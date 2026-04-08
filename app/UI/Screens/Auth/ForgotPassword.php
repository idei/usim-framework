<?php

namespace App\UI\Screens\Auth;

use App\Services\Auth\PasswordService;
use Idei\Usim\Services\UIBuilder;
use Idei\Usim\Services\Enums\LayoutType;
use Idei\Usim\Services\AbstractUIService;
use Idei\Usim\Services\Components\UIContainer;
use Idei\Usim\Services\Components\LabelBuilder;

class ForgotPassword extends AbstractUIService
{
    public function __construct(
        protected PasswordService $passwordService
    ) {
    }

    protected LabelBuilder $lbl_result;
    protected \Idei\Usim\Services\Components\InputBuilder $email;

    public function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container
            ->layout(LayoutType::VERTICAL)
            ->justifyContent('start')
            ->plain()
            ->alignItems('center')
            ->padding(40)
            ->paddingTop('80px')
            ->minHeight('100vh');

        // Icono superior
        $container->add(
            UIBuilder::label('lock_icon')
                ->text('🔒') // O un ícono similar
                ->style('h1')
                ->center()
                ->fontSize('80px')
        );

        $container->add(
            UIBuilder::label('lbl_title')
                ->text(t('app.screen.auth.forgot_password.title'))
                ->style('h2')
                ->center()
                ->color('#3b82f6') // Blue to match theme
        );

        /*
        // Subtitle removed from outside to be inside the card as description
        $container->add(
            UIBuilder::label('lbl_instruction')
                ->text('Ingresa tu email y te enviaremos un enlace para restablecer tu contraseña.')
                ->style('p')
                ->center()
                ->color('#6b7280')
                ->marginTop('10px')
        );
        */

        // Card Container
        $formCard = UIBuilder::container('forgot_password_card')
            ->layout(LayoutType::VERTICAL)
            ->shadow(true)
            ->maxWidth('600px')
            ->width('100%')
            ->borderRadius('8px')
            ->marginTop('30px')
            ->padding(30)
            ->gap('20px')
            ->backgroundColor('white')
            ->customStyle('border-left: 5px solid #3b82f6; overflow: hidden;');

        $formCard->add(
            UIBuilder::label('card_title')
                ->text(t('app.screen.auth.forgot_password.card_title'))
                ->style('h3')
                ->color('#1f2937')
                ->marginBottom('5px')
        );

        $formCard->add(
            UIBuilder::label('lbl_instruction')
                ->text(t('app.screen.auth.forgot_password.instruction'))
                ->style('p')
                ->color('#6b7280')
                ->marginBottom('15px')
        );

        $formCard->add(
            UIBuilder::input('email')
                ->label(t('app.screen.auth.forgot_password.email.label'))
                ->type('email')
                ->placeholder(t('app.screen.auth.forgot_password.email.placeholder'))
                ->width('100%')
        );

        $formCard->add(
            UIBuilder::label('lbl_result')
                ->text('')
                ->visible(false)
                ->center()
        );

        $buttons = UIBuilder::container('buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent('start')
            ->gap('15px')
            ->marginTop('10px');

        $buttons->add(
            UIBuilder::button('btn_send')
                ->label(t('app.screen.auth.forgot_password.actions.send'))
                ->style('primary')
                ->action('send_link')
        );

        $buttons->add(
            UIBuilder::button('btn_back')
                ->label(t('app.screen.auth.forgot_password.actions.back'))
                ->style('outline')
                ->action('navigate_to_login')
        );

        $formCard->add($buttons);

        $container->add($formCard);
    }

    public function onNavigateToLogin(array $params): void
    {
        $this->redirect('/auth/login');
    }

    public function onSendLink(array $params): void
    {
        $email = $params['email'] ?? '';

        if (empty($email)) {
            if (isset($this->lbl_result)) {
                $this->lbl_result->text(t('app.screen.auth.forgot_password.validation.email_required'))->style('error')->visible(true);
            }
            return;
        }

        try {
            $result = $this->passwordService->sendResetLink($email);
            $status = $result['status'] ?? 'error';
            $message = $result['message'] ?? 'No se pudo enviar el enlace de recuperación.';

            if ($status === 'success') {
                $this->lbl_result->text(t('app.screen.auth.forgot_password.success'))->style('success')->visible(true);
                $this->toast(t('app.screen.auth.forgot_password.toast.sent'), 'success');
                $this->email->value('');
            } else {
                $this->lbl_result->text($message)->style('error')->visible(true);
                $this->toast($message, 'error');
            }

        } catch (\Exception $e) {
            $this->lbl_result->text(t('app.screen.auth.forgot_password.errors.connection', ['message' => $e->getMessage()]))->style('error')->visible(true);
        }
    }
}
