<?php

namespace App\UI\Screens\Device;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Idei\Usim\Support\DevicePairingManager;

class DevicePairingScreen extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    /**
     * La pantalla de emparejamiento no debe mostrar menú de navegación.
     */
    public static bool $hasMenu = false;

    // Variables de estado persistente (Zero-Database)
    protected string $store_session_token = '';
    protected string $store_pin = '';
    protected string $store_token = '';

    // Componentes interactivos que mutaremos en tiempo de ejecución
    protected Label $lbl_pin_display;
    protected Label $lbl_status;
    protected Button $btn_check_status;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        // 1. Construcción de la Interfaz Declarativa
        $container
            ->maxWidth(Size::px(800))
            ->centerHorizontal()
            ->padding(Spacing::px(50));

        $container->add(
            UI::label('lbl_title')
                ->text('Vincular este Dispositivo')
                ->style('primary')
            // Asumiendo que existen métodos para fuentes grandes, o usando clases CSS
        );

        $container->add(
            UI::label('lbl_instructions')
                ->text('Ingresa al panel de administración de USIM en tu computadora e introduce el siguiente PIN para autorizar este equipo.')
                ->style('secondary')
        );

        $container->add(
            UI::label('lbl_pin_display')
                ->text('')
                ->style('primary')
            // Idealmente un texto gigante
        );

        $container->add(
            UI::label('lbl_status')
                ->text('Esperando autorización...')
                ->style('warning')
        );

        // TODO: Reemplazar por un Timer si USIM soporta componentes ocultos de polling
        $container->add(
            UI::button('btn_check_status')
                ->label('Verificar Estado')
                ->action('check_status')
                ->style('outline-primary')
        );
    }

    /**
     * Inicialización dinámica de datos en cada carga/recarga con estado.
     * Se ejecuta después de la inyección de storage y componentes.
     */
    protected function postLoadUI(): void
    {
        /** @var DevicePairingManager $manager */
        $manager = app(DevicePairingManager::class);

        $hasActiveSession = false;
        if (!empty($this->store_session_token)) {
            $hasActiveSession = $manager->pollStatus($this->store_session_token) !== null;
        }

        if (!$hasActiveSession || empty($this->store_pin)) {
            $pairingData = $manager->initiate();
            $this->store_session_token = $pairingData['session_token'];
            $this->store_pin = $pairingData['pin'];
        }

        $this->lbl_pin_display->text($this->store_pin);
    }

    /**
     * Handle the polling action from the Smart TV
     *
     * @param array<string, mixed> $params
     */
    public function onCheckStatus(array $params): void
    {
        $manager = app(DevicePairingManager::class);
        $status = $manager->pollStatus($this->store_session_token);

        if ($status === null) {
            // Expiró
            $this->store_session_token = '';
            $this->store_pin = '';
            $this->toast('El PIN expiró. Generando uno nuevo...', 'warning');
            $this->redirect(self::getRoutePath());
            return;
        }

        if ($status !== 'pending') {
            // ¡Aprobado! $status contiene el token de Sanctum
            $this->store_session_token = '';
            $this->store_pin = '';

            // Persistimos el token en el storage del dispositivo
            $this->store_token = $status;

            // Iniciar sesión en el guard 'device' (manejado por sesión e inyectado por UsimServiceProvider)
            $tokenModel = \Laravel\Sanctum\PersonalAccessToken::findToken($status);
            if ($tokenModel && $tokenModel->tokenable instanceof \App\Models\Device) {
                \Illuminate\Support\Facades\Auth::guard('device')->login($tokenModel->tokenable);
            }

            $this->toast('¡Dispositivo vinculado exitosamente!', 'success');
            $this->redirect(\App\UI\Screens\Device\KioskScreen::getRoutePath());
            return;
        }

        // Si sigue pendiente, damos feedback visual
        $this->lbl_status
            ->text('Aún esperando autorización... (Última revisión: ' . now()->format('H:i:s') . ')')
            ->style('info');
    }
}
