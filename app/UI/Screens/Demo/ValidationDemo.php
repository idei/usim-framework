<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Idei\Usim\Widgets\Box;
use Idei\Usim\Widgets\Button;
use Idei\Usim\Widgets\Card;
use Idei\Usim\Widgets\Column;
use Idei\Usim\Widgets\Modal;
use Idei\Usim\Widgets\Row;
use Idei\Usim\Widgets\Text;
use Idei\Usim\Widgets\TextInput;
use Idei\Usim\Widgets\Widget;
use Illuminate\Support\Facades\Validator;

class ValidationDemo extends Screen
{
    // Permisos de acceso: PUBLIC para pruebas directas
    public static Visibility $visibility = Visibility::PUBLIC;

    // =========================================================
    // 1. ESTADO REACTIVO (Propiedades públicas de clase)
    // =========================================================
    public string $nombre = '';
    public string $email = '';
    public string $edad = '';

    // Estado del Modal de retroalimentación
    public bool $mostrarModal = false;
    public bool $esExitoso = false;
    public string $modalTitulo = '';
    public string $modalMensaje = '';

    // Errores de validación en línea
    public array $errores = [];
    
    // ... continuará con build() y handlers

    public function build(): ?Widget
    {
        // 1. Árbol del formulario fijo y estable (sin intercalar textos que rompan los índices)
        $hijosColumna = [
            new TextInput(
                name: 'nombre',
                label: 'Nombre completo',
                placeholder: 'Ej: Martín Varela',
                value: $this->nombre,
                required: true,
                key: 'input_nombre'
            ),
            new TextInput(
                name: 'email',
                label: 'Correo electrónico',
                placeholder: 'correo@ejemplo.com',
                value: $this->email,
                inputType: 'email',
                required: true,
                key: 'input_email'
            ),
            new TextInput(
                name: 'edad',
                label: 'Edad (mayor de 18)',
                placeholder: 'Ej: 25',
                value: $this->edad,
                inputType: 'number',
                required: true,
                key: 'input_edad'
            ),
            new Row(
                gap: 12,
                key: 'row_acciones',
                children: [
                    new Button(
                        label: 'Validar y Enviar',
                        style: 'primary',
                        onPressed: 'submit_form',
                        icon: 'check',
                        key: 'btn_enviar'
                    ),
                    new Button(
                        label: 'Limpiar Campos',
                        style: 'secondary',
                        onPressed: 'reset_form',
                        key: 'btn_limpiar'
                    ),
                ]
            ),
        ];

        $tarjetaFormulario = new Card(
            title: 'Formulario de Registro con Validación Reactiva',
            subtitle: 'La validación se realiza mediante un modal emergente centrado.',
            key: 'card_formulario',
            child: new Column(
                gap: 16,
                key: 'col_formulario',
                children: $hijosColumna
            )
        );

        $pantallaChildren = [$tarjetaFormulario];

        // =========================================================
        // 2. MODAL DECLARATIVO CENTRADO CON AUTOCIERRE (5 SEGUNDOS) Y CRUZ '✕'
        // Se renderiza únicamente si $this->mostrarModal === true
        // =========================================================
        if ($this->mostrarModal) {
            $pantallaChildren[] = new Modal(
                title: $this->modalTitulo,
                icon: $this->esExitoso ? '✅' : '⚠️',
                timeout: 5,
                showCountdown: true,
                showCloseCross: true,
                child: new Text(
                    text: $this->modalMensaje,
                    style: $this->esExitoso ? 'success' : 'danger',
                    key: 'modal_msg'
                ),
                actions: [
                    new Button(
                        label: $this->esExitoso ? 'Aceptar' : 'Entendido (5s)',
                        style: $this->esExitoso ? 'primary' : 'danger',
                        onPressed: 'close_modal',
                        key: 'btn_modal_accion'
                    ),
                ],
                onClose: 'close_modal',
                key: 'modal_validacion'
            );
        }

        return new Box(
            maxWidth: Size::px(650),
            padding: Spacing::px(24),
            centerHorizontal: true,
            title: 'Demostración de Formulario y Modal Declarativo',
            key: 'box_root',
            child: new Column(
                gap: 20,
                key: 'col_root',
                children: $pantallaChildren
            )
        );
    }

    /**
     * Handler ejecutado al pulsar el botón "Validar y Enviar"
     * 
     * @param array<string, mixed> $params Contiene los inputs enviados desde el navegador
     */
    public function onSubmitForm(array $params): void
    {
        // 1. Extraer y mantener siempre los datos ingresados por el usuario
        $this->nombre = trim((string) ($params['nombre'] ?? ''));
        $this->email = trim((string) ($params['email'] ?? ''));
        $this->edad = trim((string) ($params['edad'] ?? ''));

        // 2. Ejecutar validación de Laravel
        $validator = Validator::make([
            'nombre' => $this->nombre,
            'email'  => $this->email,
            'edad'   => $this->edad,
        ], [
            'nombre' => ['required', 'string', 'min:3', 'max:50'],
            'email'  => ['required', 'email'],
            'edad'   => ['required', 'integer', 'min:18', 'max:120'],
        ], [
            'nombre.required' => 'El campo Nombre completo es obligatorio.',
            'nombre.min'      => 'El campo Nombre debe tener al menos 3 caracteres.',
            'email.required'  => 'El campo Correo electrónico es obligatorio.',
            'email.email'     => 'El formato del Correo electrónico no es válido.',
            'edad.required'   => 'El campo Edad es obligatorio.',
            'edad.integer'    => 'La Edad debe ser un número entero.',
            'edad.min'        => 'Debes ser mayor de 18 años.',
        ]);

        // 3. Evaluar resultado y presentar los campos erróneos en el modal
        if ($validator->fails()) {
            $this->errores = $validator->errors()->toArray();
            $this->esExitoso = false;
            $this->modalTitulo = 'Campos Incompletos o Inválidos';
            $this->modalMensaje = "Por favor revisa los siguientes campos:\n\n• " .
                implode("\n• ", $validator->errors()->all());
            $this->mostrarModal = true;
        } else {
            $this->errores = [];
            $this->esExitoso = true;
            $this->modalTitulo = '¡Validación Exitosa!';
            $this->modalMensaje = "Todos los campos fueron completados correctamente:\n\n" .
                "• Nombre: {$this->nombre}\n" .
                "• Correo: {$this->email}\n" .
                "• Edad: {$this->edad} años";
            $this->mostrarModal = true;
        }
    }

    /**
     * Handler para cerrar el modal (por clic en la cruz '✕', botón o por expiración de los 5 segundos)
     */
    public function onCloseModal(array $params = []): void
    {
        $this->mostrarModal = false;
        $this->closeModal();

        // Si fue exitoso, limpiamos los datos del formulario
        if ($this->esExitoso) {
            $this->nombre = '';
            $this->email = '';
            $this->edad = '';
            $this->errores = [];
            $this->esExitoso = false;
        }
        // Si hubo errores, los datos del usuario se conservan intactos en los inputs
    }

    public function onCerrarModal(array $params = []): void
    {
        $this->onCloseModal($params);
    }

    /**
     * Handler para reiniciar el formulario
     */
    public function onResetForm(array $params = []): void
    {
        $this->nombre = '';
        $this->email = '';
        $this->edad = '';
        $this->errores = [];
        $this->mostrarModal = false;
    }
}