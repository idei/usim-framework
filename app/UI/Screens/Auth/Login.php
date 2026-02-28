<?php
namespace App\UI\Screens\Auth;

use App\Models\User;
use App\Services\Auth\LoginService;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Services\AbstractUIService;
use Idei\Usim\Services\Components\LabelBuilder;
use Idei\Usim\Services\Components\UIContainer;
use Idei\Usim\Services\Enums\JustifyContent;
use Idei\Usim\Services\Enums\LayoutType;
use Idei\Usim\Services\Support\UIStateManager;
use Idei\Usim\Services\UIBuilder;
use Illuminate\Support\Facades\Auth;

class Login extends AbstractUIService
{
    public function __construct(
        protected LoginService $loginService
    ) {
    }

    protected string $store_email = 'admin@gmail.com';
    protected string $store_password = 'CHANGE_ME';
    protected string $store_token = '';
    protected LabelBuilder $lbl_login_result;

    public static function authorize(): bool
    {
        // This screen should only be accessible to guests (not authenticated users)
        return !self::requireAuth();
    }

    public static function getMenuLabel(): string
    {
        return 'Login';
    }

    public static function getMenuIcon(): ?string
    {
        return '🔑';
    }

    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container
            ->title('User Login')
            ->maxWidth('450px')
            ->centerHorizontal()
            ->shadow(3)
            ->padding('30px');

        $container->add(
            UIBuilder::input('login_email')
                ->label('Email')
                ->placeholder('Enter your email')
                ->value($this->store_email)
                ->type('email')
                ->required(true)
                ->width('100%')
        );

        $container->add(
            UIBuilder::input('login_password')
                ->label('Password')
                ->type('password')
                ->placeholder('Enter your password')
                ->value($this->store_password)
                ->required(true)
                ->width('100%')
        );

        $container->add(
            UIBuilder::label('lbl_login_result')->text('')
        );

        $buttonsContainer = UIBuilder::container('login_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->gap('10px')
            ->shadow(false)
            ->padding('20px 0 0 0');

        $buttonsContainer->add(
            UIBuilder::button('btn_cancel_login')
                ->label('Cancel')
                ->style('secondary')
                ->action('close_login_dialog')
        );

        $buttonsContainer->add(
            UIBuilder::button('btn_submit_login')
                ->label('Login')
                ->style('primary')
                ->action('submit_login')
        );

        $container->add($buttonsContainer);

        // Forgot Password Link
        $container->add(
            UIBuilder::container('forgot_pwd_container')
                ->layout(LayoutType::HORIZONTAL)
                ->justifyContent('center')
                ->padding('15px 0 0 0')
                ->add(
                    UIBuilder::button('btn_forgot_password')
                        ->label('¿Olvidaste tu contraseña?')
                        ->style('text-blue-600 hover:text-blue-800 bg-transparent border-0') // Style as link
                        ->action('navigate_forgot_password')
                )
        );
    }

    public function onNavigateForgotPassword(array $params): void
    {
        $this->redirect('/auth/forgot-password');
    }

    public function onSubmitLogin(array $params): void
    {
        $email = $params['login_email'] ?? '';
        $password = $params['login_password'] ?? '';
        $remember = $params['remember'] ?? false;

        $response = $this->loginService->login($email, $password, (bool) $remember);

        $message = $response['message'] ?? 'Login failed.';
        $status = $response['status'] ?? 'error';
        $this->toast($message, $status);
        $this->lbl_login_result->text($message)->style($status);

        if ($response['status'] === 'error') {
            return;
        }

        $this->store_token = $response['data']['token'] ?? '';
        $this->store_email = $email;
        $this->store_password = $password;

        // Store token in UIStateManager for HttpClient
        UIStateManager::setAuthToken($this->store_token);

        $user = $response['user'] ?? User::where('email', $email)->first();
        Auth::login($user);

        // Disparar evento - TODOS los servicios en ui-services.php lo recibirán
        event(new UsimEvent('logged_user', [
            'user' => $user,
            'timestamp' => now(),
        ]));

        $this->redirect();
    }
}
