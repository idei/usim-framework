<?php

namespace App\UI\Screens\Auth;

use App\Services\User\UserService;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;

class EmailVerified extends Screen
{
    public function __construct(
        protected UserService $userService
    ) {
    }

    protected string $verificationStatus = 'loading';
    protected string $errorMessage = '';

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->layout(LayoutType::VERTICAL)
            ->plain()
            ->justifyContent('start')
            ->alignItems('center')
            ->padding('40px')
            ->paddingTop('80px')
            ->minHeight(Size::vh(100));

        // Determinar qué mostrar según el estado
        switch ($this->verificationStatus) {
            case 'loading':
                $this->buildLoadingUI($container);
                break;
            case 'verified':
                $this->buildSuccessUI($container);
                break;
            case 'already_verified':
                $this->buildAlreadyVerifiedUI($container);
                break;
            case 'error':
                $this->buildErrorUI($container);
                break;
    }
        }


    protected function postLoadUI(): void
    {
        // Solo verificar si aún estamos en estado de carga
        if ($this->verificationStatus !== 'loading') {
            return;
        }

        // Obtener parámetros de la URL (id y hash)
        $id = request('id');
        $hash = request('hash');

        if (!$id || !$hash) {
            $this->errorMessage = t('screen.auth.email_verified.errors.invalid_params');
            $this->verificationStatus = 'error';
            $this->container->clear();
            $this->buildBaseUI($this->container);
            return;
        }

        // Enforce link expiration using signed URL expires timestamp.
        $expires = (int) request('expires', 0);
        if ($expires > 0 && now()->timestamp > $expires) {
            $this->errorMessage = t('screen.auth.email_verified.errors.expired');
            $this->verificationStatus = 'error';
            $this->container->clear();
            $this->buildBaseUI($this->container);
            return;
        }

        // Verificación a través del servicio
        try {
            $result = $this->userService->verifyEmail((int)$id, $hash);

            if (!$result['success']) {
                $this->errorMessage = $result['message'];
                $this->verificationStatus = $result['status'] === 'invalid' ? 'error' : $result['status'];
            } else {
                $this->verificationStatus = $result['status'];
            }

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Email verification failed', [
                'message' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
            $this->errorMessage = t('screen.auth.email_verified.errors.generic');
            $this->verificationStatus = 'error';
        }

        // Reconstruir la UI con el nuevo estado
        $this->container->clear();
        $this->buildBaseUI($this->container);
    }

    /**
     * Build loading UI
     */
    private function buildLoadingUI(Container $container): void
    {
        $container->add(
            UI::label('loading_message')
                ->text(t('screen.auth.email_verified.loading'))
                ->style('h2')
                ->center()
                ->color('#666')
        );
    }

    /**
     * Build success UI
     */
    private function buildSuccessUI(Container $container): void
    {
        $container->add(
            UI::label('success_icon')
                ->text('✅')
                ->style('h1')
                ->center()
                ->fontSize('80px')
        );

        $container->add(
            UI::label('verified_message')
                ->text(t('screen.auth.email_verified.verified.message'))
                ->style('h2')
                ->center()
                ->color('#4CAF50')
        );

        $container->add(
            UI::label('verified_subtitle')
                ->text(t('screen.auth.email_verified.verified.subtitle'))
                ->style('p')
                ->center()
                ->color('#666')
                ->marginTop('10px')
        );

        $container->add(
            UI::card('success_card')
                ->title(t('screen.auth.email_verified.verified.card_title'))
                ->description(t('screen.auth.email_verified.verified.card_description'))
                ->theme('success')
                ->maxWidth(Size::px(600))
                ->marginTop('30px')
                ->addAction(t('screen.auth.email_verified.actions.go_to_login'), 'go_to_login', [], 'success')
                ->addAction(t('screen.auth.email_verified.actions.go_to_home'), 'go_to_home', [], 'outline')
        );
    }

    /**
     * Build already verified UI
     */
    private function buildAlreadyVerifiedUI(Container $container): void
    {
        $container->add(
            UI::label('info_icon')
                ->text('ℹ️')
                ->style('h1')
                ->center()
                ->fontSize('80px')
        );

        $container->add(
            UI::label('already_verified_message')
                ->text(t('screen.auth.email_verified.already_verified.message'))
                ->style('h2')
                ->center()
                ->color('#2196F3')
        );

        $container->add(
            UI::card('info_card')
                ->title(t('screen.auth.email_verified.already_verified.card_title'))
                ->description(t('screen.auth.email_verified.already_verified.card_description'))
                ->theme('info')
                ->maxWidth(Size::px(600))
                ->marginTop('30px')
                ->addAction(t('screen.auth.email_verified.actions.go_to_login'), 'go_to_login', [], 'primary')
                ->addAction(t('screen.auth.email_verified.actions.go_to_home'), 'go_to_home', [], 'outline')
        );
    }

    /**
     * Build error UI
     */
    private function buildErrorUI(Container $container): void
    {
        $container->add(
            UI::label('error_icon')
                ->text('❌')
                ->style('h1')
                ->center()
                ->fontSize('80px')
        );

        $container->add(
            UI::label('error_message')
                ->text(t('screen.auth.email_verified.error.message'))
                ->style('h2')
                ->center()
                ->color('#f44336')
        );

        $container->add(
            UI::label('error_detail')
                ->text($this->errorMessage)
                ->style('p')
                ->center()
                ->color('#666')
                ->marginTop('10px')
        );

        $container->add(
            UI::card('error_card')
                ->title(t('screen.auth.email_verified.error.card_title'))
                ->description(t('screen.auth.email_verified.error.card_description'))
                ->theme('danger')
                ->maxWidth(Size::px(600))
                ->marginTop('30px')
                ->addAction(t('screen.auth.email_verified.actions.resend'), 'resend_verification', [], 'danger')
                ->addAction(t('screen.auth.email_verified.actions.go_to_home'), 'go_to_home', [], 'outline')
        );
    }

    /**
     * Handle login button click
     */
    public function onGoToLogin(array $params): void
    {
        $this->redirect('/auth/login');
    }

    /**
     * Handle home button click
     */
    public function onGoToHome(array $params): void
    {
        $this->redirect('/');
    }

    /**
     * Handle resend verification email
     */
    public function onResendVerification(array $params): void
    {
        $this->toast(t('screen.auth.email_verified.toast.redirect_to_login'), 'info');
        $this->redirect('/auth/login');
    }
}
