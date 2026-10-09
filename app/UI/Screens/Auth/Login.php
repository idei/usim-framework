<?php

// @usim: feature="admin", type="screen"

namespace App\UI\Screens\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Contracts\LoginActionInterface;
use Idei\Usim\DTOs\LoginCredentials;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Validation\ValidationException;

class Login extends Screen
{
    public function __construct(
        protected LoginActionInterface $loginAction
    ) {}

    public static Visibility $visibility = Visibility::GUEST;

    protected string $state_email = '';

    protected string $store_token = '';

    protected Label $lbl_login_result;

    public static function authorize(): bool
    {
        // This screen should only be accessible to guests,
        // i.e. users who are not authenticated.
        return ! self::requireAuth();
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $email = '';
        $password = '';

        if (config('app.env') === 'local') {
            // Pre-fill credentials in local environment for easier testing
            $email = empty($this->state_email)
                ? config('usim.users.root.email')
                : $this->state_email;
            $password = config('usim.users.root.password');
        }

        $wrapper = UI::container('login_wrapper')
            ->card()
            ->width(Size::full())
            ->minWidth(Size::px(400))
            ->layout(LayoutType::VERTICAL)
            ->justifyContent(JustifyContent::CENTER)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(5));

        $card = UI::container('login_card')
            ->plain()
            ->width(Size::full());

        $card->add(
            UI::input('login_email')
                ->label(t('screen.auth.login.email.label'))
                ->placeholder(t('screen.auth.login.email.placeholder'))
                ->value($email)
                ->type('email')
                ->required(true)
                ->width(Size::full())
        );

        $card->add(
            UI::input('login_password')
                ->label(t('screen.auth.login.password.label'))
                ->type('password')
                ->placeholder(t('screen.auth.login.password.placeholder'))
                ->value($password)
                ->required(true)
                ->width(Size::full())
        );

        $card->add(
            $this->lbl_login_result = UI::label('lbl_login_result')->text('')
        );

        $buttonsContainer = UI::container('login_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->plain()
            ->gap(Spacing::px(10))
            ->padding(Spacing::each(Spacing::px(20)));

        $buttonsContainer->add(
            UI::button('btn_cancel_login')
                ->label(t('screen.auth.login.actions.cancel'))
                ->style('secondary')
                ->action('close_login_dialog')
        );

        $buttonsContainer->add(
            UI::button('btn_submit_login')
                ->label(t('screen.auth.login.actions.submit'))
                ->style('primary')
                ->action('submit_login')
        );

        $card->add($buttonsContainer);

        // Forgot Password Link left-aligned below the buttons and filled with the full width of the container
        $card->add(
            UI::button('btn_forgot_password')
                ->label(t('screen.auth.login.actions.forgot_password'))
                ->style('link')
                ->action('navigate_forgot_password')
                ->width(Size::full())
        );

        $wrapper->add($card);
        $container->add($wrapper);
    }

    protected function postLoadUI(): void
    {
        if (isset($this->lbl_login_result)) {
            $this->lbl_login_result->text('')->style('');
        }
    }

    /** @param array<string, mixed> $params */
    public function onNavigateForgotPassword(array $params): void
    {
        $this->redirect('/auth/forgot-password');
    }

    /** @param array<string, mixed> $params */
    public function onSubmitLogin(array $params): void
    {
        try {
            $credentials = LoginRequest::validateData($params);
        } catch (ValidationException) {
            $message = t('service.auth.login.validation_errors');
            $this->toast(
                message: $message,
                type: 'error'
            );
            if (isset($this->lbl_login_result)) {
                $this->lbl_login_result->text($message)->style('error');
            }

            return;
        }

        $loginCredentials = new LoginCredentials(
            email: $credentials['email'],
            password: $credentials['password'],
            remember: $credentials['remember'],
        );

        $result = $this->loginAction->execute($loginCredentials, startSession: true);

        $message = $result->message;
        $status = $result->isSuccess() ? 'success' : 'error';
        $this->toast(
            message: $message,
            type: $status
        );
        if (isset($this->lbl_login_result)) {
            $this->lbl_login_result->text($message)->style($status);
        }

        if (! $result->isSuccess()) {
            return;
        }

        if ($result->token !== null) {
            $this->store_token = $result->token;
        }
        $this->state_email = $credentials['email'];

        $this->closeModal();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onLoggedUser(array $params): void
    {
        /** @var User|null $user */
        $user = $params['user'] ?? null;
        $unitValue = $params['unit'] ?? '';
        $homeScreen = $params['home_screen'] ?? null;
    }

    public function onCloseLoginDialog(): void
    {
        if ($this->isOpenedAsModal()) {
            $this->closeModal();

            return;
        }

        $this->redirect('/');
    }
}
