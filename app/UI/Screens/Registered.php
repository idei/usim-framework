<?php

namespace App\UI\Screens;

use App\Models\User;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class Registered extends Screen
{
    /**
     * This screen requires an authenticated user.
     */
    public static function authorize(): bool
    {
        return self::requireAuth();
    }

    /**
     * Build base UI structure for the Registered user Home Screen.
     *
     * @param mixed ...$params
     */
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $user = Auth::user();
        $userName = $user instanceof User ? $user->name : '';

        $appNameConfig = config('usim.app_name') ?? config('app.name') ?? 'USIM Framework';
        $appName = is_string($appNameConfig) ? $appNameConfig : 'USIM Framework';

        $message = t('registered.message', ['app_name' => $appName]);
        if ($message === 'registered.message') {
            $message = __('screen/registered.message', ['app_name' => $appName]);
        }
        if ($message === 'screen/registered.message') {
            $message = "Ha sido satisfactoriamente registrado en el sistema {$appName} y pronto será asignado a una unidad con un rol.";
        }

        $container
            ->layout(LayoutType::VERTICAL)
            ->plain()
            ->justifyContent('start')
            ->alignItems('center')
            ->padding(Spacing::px(30))
            ->paddingTop(Spacing::px(60))
            ->minHeight(Size::vh(80));

        // State Icon
        $container->add(
            UI::label('registered_icon')
                ->text('⏳')
                ->style('h1')
                ->center()
                ->fontSize('64px')
        );

        $title = t('registered.title');
        if ($title === 'registered.title') {
            $title = __('screen/registered.title');
        }
        if ($title === 'screen/registered.title') {
            $title = '¡Registro Exitoso!';
        }

        // Title
        $container->add(
            UI::label('registered_title')
                ->text($title)
                ->style('h2')
                ->center()
                ->fontSize('26px')
                ->fontWeight('bold')
                ->color('#00d4aa')
                ->marginTop(Spacing::px(12))
        );

        $greeting = t('registered.greeting', ['name' => $userName]);
        if ($greeting === 'registered.greeting') {
            $greeting = __('screen/registered.greeting', ['name' => $userName]);
        }
        if ($greeting === 'screen/registered.greeting') {
            $greeting = "Hola, {$userName}";
        }

        // Greeting
        $container->add(
            UI::label('registered_greeting')
                ->text($greeting)
                ->style('h3')
                ->center()
                ->fontSize('18px')
                ->color('#7a8499')
                ->marginTop(Spacing::px(6))
        );

        $cardTitle = t('registered.card_title');
        if ($cardTitle === 'registered.card_title') {
            $cardTitle = __('screen/registered.card_title');
        }
        if ($cardTitle === 'screen/registered.card_title') {
            $cardTitle = 'Asignación pendiente';
        }

        $btnProfileLabel = t('registered.btn_profile');
        if ($btnProfileLabel === 'registered.btn_profile') {
            $btnProfileLabel = __('screen/registered.btn_profile');
        }
        if ($btnProfileLabel === 'screen/registered.btn_profile') {
            $btnProfileLabel = 'Ver Mi Perfil';
        }

        // Main informative Card with the required message
        $container->add(
            UI::card('registered_card')
                ->title($cardTitle)
                ->description($message)
                ->theme('info')
                ->maxWidth(Size::px(650))
                ->marginTop(Spacing::px(24))
                ->addAction($btnProfileLabel, 'go_to_profile', [], 'primary')
        );

        // Explicit Label for the message
        $container->add(
            UI::label('registered_message')
                ->text($message)
                ->style('p')
                ->center()
                ->maxWidth(Size::px(650))
                ->fontSize('15px')
                ->marginTop(Spacing::px(16))
                ->color('#7a8499')
        );
    }

    /**
     * Handler for going to profile
     *
     * @param array<string, mixed> $params
     */
    public function onGoToProfile(array $params): void
    {
        $this->redirect('/auth/profile');
    }
}

