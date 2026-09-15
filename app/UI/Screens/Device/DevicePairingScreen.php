<?php

namespace App\UI\Screens\Device;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Idei\Usim\Support\DevicePairingManager;

class DevicePairingScreen extends Screen
{
    // Variables de estado persistente (Zero-Database)
    protected string $store_session_token = '';
    protected string $store_pin = '';

    // Componentes interactivos que mutaremos en tiempo de ejecución
    protected Label $lbl_pin_display;
    protected Label $lbl_status;
    protected Button $btn_check_status;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $manager = app(DevicePairingManager::class);

        // 1. Iniciamos el caso de uso si no hay sesión
        if (empty($this->store_session_token) || empty($this->store_pin)) {
            $pairingData = $manager->initiate();
            $this->store_session_token = $pairingData['session_token'];
            $this->store_pin = $pairingData['pin'];
        }

        // 2. Construcción de la Interfaz Declarativa
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
                ->text($this->store_pin)
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
     * Handle the polling action from the Smart TV
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

            // Aquí podríamos guardar el token definitivo en otra variable store_
            // $this->store_device_token_crypt = $status;

            $this->toast('¡Dispositivo vinculado exitosamente!', 'success');
            $this->redirect('/device/kiosk-home'); // Ruta destino tras emparejar
            return;
        }

        // Si sigue pendiente, damos feedback visual
        $this->lbl_status
            ->text('Aún esperando autorización... (Última revisión: ' . now()->format('H:i:s') . ')')
            ->style('info');
    }
}
