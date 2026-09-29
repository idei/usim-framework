<?php

use App\UI\Screens\Demo\ValidationDemo;

it('carga validation demo con inputs vacios y estructura inicial estable', function () {
    $ui = uiScenario($this, ValidationDemo::class, ['reset' => true]);

    $ui->component('nombre')->expect('value')->toBe('');
    $ui->component('email')->expect('value')->toBe('');
    $ui->component('edad')->expect('value')->toBe('');
});

it('muestra modal con cruz de cierre y lista de campos erróneos al enviar datos invalidos', function () {
    $ui = uiScenario($this, ValidationDemo::class, ['reset' => true]);

    $response = $ui->click('btn_enviar', [
        'nombre' => 'A', // Demasiado corto
        'email'  => 'no-es-un-correo',
        'edad'   => '12', // Menor de 18
    ]);

    $changes = $response->json();
    expect($changes)->toBeArray()->not()->toBeEmpty();

    // Verificamos que se renderice el modal con su cruz de cierre y timeout
    $modalJson = json_encode($changes, JSON_UNESCAPED_UNICODE);
    expect($modalJson)->toContain('modal_validacion');
    expect($modalJson)->toContain('modal_validacion_cross');
    expect($modalJson)->toContain('Campos Incompletos o Inválidos');
    expect($modalJson)->toContain('_timeout');
});

it('muestra modal de exito cuando los datos son correctos', function () {
    $ui = uiScenario($this, ValidationDemo::class, ['reset' => true]);

    $response = $ui->click('btn_enviar', [
        'nombre' => 'Martín Varela',
        'email'  => 'martin@example.com',
        'edad'   => '30',
    ]);

    $changes = $response->json();
    $modalJson = json_encode($changes, JSON_UNESCAPED_UNICODE);

    expect($modalJson)->toContain('¡Validación Exitosa!');
    expect($modalJson)->toContain('_timeout');
});

it('cierra el modal al pulsar la cruz o invocar close_modal', function () {
    $ui = uiScenario($this, ValidationDemo::class, ['reset' => true]);

    // 1. Provocar modal de error
    $ui->click('btn_enviar', [
        'nombre' => '',
        'email'  => '',
        'edad'   => '',
    ]);

    // 2. Cerrar el modal mediante la acción close_modal
    $closeResponse = $ui->click('modal_validacion_cross');
    $changes = $closeResponse->json();

    expect($changes)->toHaveKey('action');
    expect($changes['action'])->toBe('close_modal');
});

it('conserva intactos todos los inputs y botones del formulario tras cerrar el modal de error', function () {
    $ui = uiScenario($this, ValidationDemo::class, ['reset' => true]);

    // 1. Enviar inputs vacíos para abrir el modal de validación
    $submitResponse = $ui->click('btn_enviar', [
        'nombre' => '',
        'email'  => '',
        'edad'   => '',
    ]);
    $submitDiff = $submitResponse->json();

    // Comprobar que ningún componente del formulario fue eliminado al abrir el modal
    expect($submitDiff['nombre']['parent'] ?? false)->not->toBeNull();
    expect($submitDiff['email']['parent'] ?? false)->not->toBeNull();
    expect($submitDiff['edad']['parent'] ?? false)->not->toBeNull();
    expect($submitDiff['card_formulario']['parent'] ?? false)->not->toBeNull();
    expect($submitDiff['row_acciones']['parent'] ?? false)->not->toBeNull();

    // 2. Cerrar el modal mediante la acción close_modal
    $closeResponse = $ui->click('modal_validacion_cross');
    $closeDiff = $closeResponse->json();

    // El modal sí debe ser eliminado (parent = null)
    expect($closeDiff['modal_validacion']['parent'] ?? null)->toBeNull();

    // Pero NINGÚN componente del formulario debe ser eliminado
    expect($closeDiff['nombre']['parent'] ?? false)->not->toBeNull();
    expect($closeDiff['email']['parent'] ?? false)->not->toBeNull();
    expect($closeDiff['edad']['parent'] ?? false)->not->toBeNull();
    expect($closeDiff['card_formulario']['parent'] ?? false)->not->toBeNull();
    expect($closeDiff['col_formulario']['parent'] ?? false)->not->toBeNull();
    expect($closeDiff['row_acciones']['parent'] ?? false)->not->toBeNull();
    expect($closeDiff['btn_enviar']['parent'] ?? false)->not->toBeNull();
    expect($closeDiff['btn_limpiar']['parent'] ?? false)->not->toBeNull();
});
