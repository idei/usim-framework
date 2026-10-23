<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Admin;

use App\Models\Device; // Tu modelo polimórfico de dispositivo
use App\UI\Requests\Device\ApprovePairingRequest;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Label;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Idei\Usim\Support\DevicePairingManager;

class AdminDevicePairingScreen extends Screen
{
    protected Label $lbl_instruction;
    protected Input $input_pin;
    protected Button $btn_submit;
    protected Label $lbl_result;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->maxWidth(Size::px(500))
            ->centerHorizontal()
            ->shadow(2)
            ->padding(Spacing::px(30));

        $container->add(
            UI::label('lbl_instruction')
                ->text('Ingresa el PIN de 4 dígitos que aparece en la pantalla del televisor o en la salida del sensor.')
                ->style('info')
        );

        $container->add(
            UI::input('input_pin')
                ->label('PIN de Emparejamiento')
                ->placeholder('Ej: 4419')
                ->value('')
                ->required(true)
                ->type('number')
                ->width(Size::full())
        );

        $container->add(
            UI::button('btn_submit')
                ->label('Vincular Dispositivo')
                ->action('approve_pairing')
                ->style('primary')
        );

        $container->add(
            UI::label('lbl_result')
                ->text('')
                ->style('secondary')
                ->width(Size::full())
        );
    }

    /**
     * Handler que recibe el PIN validado mediante ApprovePairingRequest.
     */
    public function onApprovePairing(ApprovePairingRequest $request): void
    {
        $pin = (string) $request->pin;

        // En una versión final, aquí también seleccionarías de un dropdown
        // a qué registro de la tabla `devices` corresponde este PIN.
        // Por ahora, para la prueba, tomamos el primer dispositivo disponible (ej: el Smart TV de la DB)
        $device = Device::first();

        if (!$device) {
            $this->toast('No hay dispositivos registrados en la base de datos.', 'error');
            return;
        }

        $manager = app(DevicePairingManager::class);
        $success = $manager->approve($pin, $device);

        if ($success) {
            $this->lbl_result
                ->text("¡El dispositivo '{$device->name}' ha sido autorizado exitosamente!")
                ->style('success');

            $this->input_pin->value(''); // Limpiamos el campo
            $this->toast('Emparejamiento exitoso.', 'success');
        } else {
            $this->lbl_result
                ->text('El PIN ingresado es incorrecto, expiró, o no hay ningún dispositivo esperando vinculación.')
                ->style('danger');
            $this->input_pin->error('PIN inválido');
        }
    }
}
