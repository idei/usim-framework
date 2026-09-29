# 🛠️ Guía Paso a Paso: Creación de Screens Reactivas Declarativas en USIM 2.0

> **Rama:** `feature/declarative-reactive-architecture`  
> **Patrón:** Server-Driven UI (SDUI) Declarativo Reactivo ($\text{UI} = f(\text{Estado})$) inspirado en Flutter  
> **Objetivo:** Aprender a implementar una nueva pantalla con un formulario, validación de datos en servidor y retroalimentación modal condicional sin tocar referencias del DOM ni mutaciones imperativas.

---

## 1. Conceptos Fundamentales: ¿Cómo funciona esta rama?

En la versión clásica de USIM (1.0), para actualizar la interfaz se debía guardar una referencia al componente como propiedad protegida de la clase (`protected Label $lbl_resultado`), y mutarla a mano en el handler (`$this->lbl_resultado->text('...')`).

En **USIM 2.0 (esta rama)**:
1. **El Estado es la única fuente de verdad:** La clase define propiedades de estado (`public string $name`, `public bool $showModal`, etc.).
2. **`build(): ?Widget` describe toda la pantalla:** El método `build()` devuelve el árbol de widgets que corresponde al estado actual.
3. **Reactividad automática:**
   * Cuando el usuario interactúa (por ejemplo, presiona un botón), el frontend emite un evento `POST /api/ui-event`.
   * El controlador ejecuta tu handler (por ejemplo, `onSubmitForm(array $params)`).
   * En el handler **solo modificas propiedades de estado** (`$this->showModal = true`).
   * Al finalizar el evento, el framework re-ejecuta `build()`, compara el árbol anterior con el nuevo usando `UIDiffer`, y envía al cliente **únicamente el delta JSON de lo que cambió**.
4. **Modales declarativos:** Los diálogos ya no requieren servicios estáticos desconectados (`ConfirmDialogService::open`). Son simplemente un widget `Modal` añadido condicionalmente al árbol:
   ```php
   if ($this->showModal) {
       $children[] = new Modal(...);
   }
   ```

---

## 2. Catálogo de Widgets Disponibles

Los widgets declarativos se encuentran en el namespace `Idei\Usim\Widgets\*`:

| Widget | Clase | Propósito y Parámetros Principales |
| :--- | :--- | :--- |
| **`Box`** | `Idei\Usim\Widgets\Box` | Contenedor principal de la pantalla. Recibe `child`, `title`, `maxWidth`, `padding`, `centerHorizontal`. |
| **`Card`** | `Idei\Usim\Widgets\Card` | Tarjeta con fondo y bordes. Recibe `title`, `subtitle`, `child`, `actions`. |
| **`Column`** | `Idei\Usim\Widgets\Column` | Dispone hijos en orientación vertical. Recibe `children: [...]`, `gap: int`. |
| **`Row`** | `Idei\Usim\Widgets\Row` | Dispone hijos en orientación horizontal. Recibe `children: [...]`, `gap: int`. |
| **`TextInput`** | `Idei\Usim\Widgets\TextInput` | Campo de entrada. Recibe `name`, `label`, `placeholder`, `value`, `inputType`, `required`, `disabled`, `width`. |
| **`Button`** | `Idei\Usim\Widgets\Button` | Botón interactivo. Recibe `label`, `onPressed` (nombre de acción), `params`, `style` (`primary`, `secondary`, `danger`, `warning`), `disabled`, `icon`. |
| **`Text`** | `Idei\Usim\Widgets\Text` | Etiqueta de texto. Recibe `text`, `style` (`h1`, `h2`, `h3`, `normal`, `secondary`, `success`, `danger`, `warning`), `markdown`. |
| **`Modal`** | `Idei\Usim\Widgets\Modal` | Diálogo emergente en el árbol visual. Recibe `title`, `icon`, `child`, `actions: [Button, ...]`, `onClose`. |

---

## 3. Guía Paso a Paso: Formulario con Validación y Modal

Sigue estos pasos para construir tu nueva Screen.

---

### Paso 1: Crear la clase Screen

Crea tu archivo en el directorio de pantallas, por ejemplo:  
📁 `app/UI/Screens/Demo/ValidationDemo.php`

Estructura básica:
```php
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
}
```

> [!TIP]
> Cualquier propiedad `public` o `protected` (escalar o array) que no comience con `store_` o `_` es guardada y restaurada automáticamente por el motor reactivo de USIM (`extractDeclarativeState` / `restoreDeclarativeState`).

---

### Paso 2: Implementar el método declarativo `build()`

En `build()` defines cómo debe verse la pantalla. Mantén los inputs de la columna de formulario con una estructura **fija y estable**, canalizando la retroalimentación de la validación exclusivamente a través del widget `Modal`:

```php
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
            child: new Column(
                gap: 16,
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
                timeout: 5,           // Auto-cierra el modal a los 5 segundos
                showCountdown: true,  // Muestra cuenta regresiva en el modal
                showCloseCross: true, // Muestra botón '✕' en la esquina superior derecha
                child: new Text(
                    text: $this->modalMensaje,
                    style: $this->esExitoso ? 'success' : 'danger',
                    key: 'modal_msg'
                ),
                actions: [
                    new Button(
                        label: $this->esExitoso ? 'Aceptar' : 'Entendido (5s)',
                        style: $this->esExitoso ? 'primary' : 'danger',
                        onPressed: 'cerrar_modal',
                        key: 'btn_modal_accion'
                    ),
                ],
                onClose: 'cerrar_modal',
                key: 'modal_validacion'
            );
        }

        return new Box(
            maxWidth: Size::px(650),
            padding: Spacing::px(24),
            centerHorizontal: true,
            title: 'Demostración de Formulario y Modal Declarativo',
            child: new Column(
                gap: 20,
                children: $pantallaChildren
            )
        );
    }
```

        return new Box(
            maxWidth: Size::px(650),
            padding: Spacing::px(24),
            centerHorizontal: true,
            title: 'Demostración de Formulario y Modal Declarativo',
            child: new Column(
                gap: 20,
                children: $pantallaChildren
            )
        );
    }
```

---

### Paso 3: Implementar los Handlers de Acción

Cuando el usuario hace clic en un botón con `onPressed: 'submit_form'`, el backend busca un método con la convención `onPascalCase`, es decir: `onSubmitForm(array $params)`.

El frontend envía automáticamente en el array `$params` los valores de los inputs presentes en la pantalla utilizando el atributo `name` de cada `TextInput`.

```php
    /**
     * Handler ejecutado al pulsar el botón "Validar y Enviar"
     * 
     * @param array<string, mixed> $params Contiene los inputs enviados desde el navegador
     */
    public function onSubmitForm(array $params): void
    {
        // 1. Extraer los datos enviados desde el formulario
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
            'nombre.required' => 'El nombre completo es obligatorio.',
            'nombre.min'      => 'El nombre debe contener al menos 3 caracteres.',
            'email.required'  => 'El correo electrónico es obligatorio.',
            'email.email'     => 'Debes ingresar un formato de correo electrónico válido.',
            'edad.required'   => 'Debes ingresar tu edad.',
            'edad.min'        => 'Debes ser mayor de 18 años para registrarte.',
        ]);

        // 3. Evaluar resultado y mutar exclusivamente el estado
        if ($validator->fails()) {
            $this->errores = $validator->errors()->toArray();
            $this->esExitoso = false;
            $this->modalTitulo = 'Errores en el Formulario';
            $this->modalMensaje = "Se encontraron errores al validar la información:\n\n• " .
                implode("\n• ", $validator->errors()->all());
            $this->mostrarModal = true;
        } else {
            $this->errores = [];
            $this->esExitoso = true;
            $this->modalTitulo = '¡Validación Exitosa!';
            $this->modalMensaje = "Los datos ingresados son correctos:\n" .
                "• Nombre: {$this->nombre}\n" .
                "• Correo: {$this->email}\n" .
                "• Edad: {$this->edad} años.\n\n" .
                "El registro se procesó satisfactoriamente.";
            $this->mostrarModal = true;
        }

        // ¡LISTO! No se tocan componentes. Al salir del método,
        // USIM ejecuta build() y envía el delta al cliente.
    }

    /**
     * Handler para cerrar el modal (por clic en la cruz '✕', botón o por expiración de los 5 segundos)
     */
    public function onCloseModal(array $params = []): void
    {
        $this->mostrarModal = false;
        $this->closeModal(); // Emite la meta-acción de cierre para desmontar el overlay en el cliente

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
```

---

### Paso 4: Cómo Probar la Screen en el Navegador

Gracias a la ruta comodín (Catch-All) de USIM en `routes/web.php`:

```
URL Web: http://localhost:8000/demo/validacion-demo
Endpoint API JSON: http://localhost:8000/api/ui/demo/validacion-demo
```

1. **Resolución automática de URLs:**  
   Cualquier path como `/demo/validacion-demo` se convierte automáticamente en `App\UI\Screens\Demo\ValidationDemo`. No necesitas editar `routes/web.php` ni crear controladores adicionales.
2. **Para forzar una recarga limpia sin cache previo:**  
   Añade el parámetro `?reset=true` a la URL:  
   `http://localhost:8000/demo/validacion-demo?reset=true`

---

### Paso 5 (Opcional): Agregar tu Screen al Menú de Navegación

Si deseas que aparezca en el menú desplegable superior:
1. Abre `app/UI/Screens/Menu.php`.
2. Localiza el método `buildDemosMenu()`.
3. Añade tu pantalla:
   ```php
   $submenu->screen(\App\UI\Screens\Demo\ValidationDemo::class, 'Validación Formulario', '📋');
   ```

---

### Paso 6: Escribir una Prueba Automatizada con Pest

Para asegurarte de que tu formulario funciona y previene regresiones:  
Crea el archivo `tests/Feature/ValidationDemoTest.php`:

```php
<?php

use App\UI\Screens\Demo\ValidationDemo;

it('carga la demo con valores iniciales limpios', function () {
    $ui = uiScenario($this, ValidationDemo::class, ['reset' => true]);

    $ui->component('input_nombre')->expect('value')->toBe('');
    $ui->component('input_email')->expect('value')->toBe('');
});

it('muestra modal de error si se envían datos inválidos', function () {
    $ui = uiScenario($this, ValidationDemo::class, ['reset' => true]);

    // Simular el evento submit_form con datos incompletos
    $response = $ui->component('btn_enviar')->action('submit_form', [
        'nombre' => 'A', // Demasiado corto
        'email'  => 'correo_invalido',
        'edad'   => '15', // Menor de edad
    ]);

    // Verificar que el modal condicional fue inyectado en el diff
    expect($response->json('diff'))->not()->toBeEmpty();
});
```

---

## 4. Reglas de Oro y Buenas Prácticas en USIM 2.0

1. **Nunca busques componentes a mano en los handlers:**  
   ❌ Evita `$this->component('input_nombre')->value('...')`.  
   ✅ Modifica tu propiedad de estado: `$this->nombre = '...'`.
2. **Usa `key` explícitas y estables en contenedores y componentes dinámicos:**  
   - En pantallas reactivas donde se montan o desmontan ramas condicionales (como un `Modal`), asigna siempre un parámetro `key` explícito tanto a los contenedores padres (`Card(key: 'card_form')`, `Row(key: 'row_actions')`, `Column(key: 'col_form')`) como a los widgets dinámicos (`Modal(key: 'modal_dialog')`).
   - Esto garantiza que el motor `UIDiffer` reconozca que la estructura del formulario permanece idéntica y no elimine (`parent: null`) los inputs ni los botones del DOM al abrir o cerrar el modal.
3. **Mapeo de nombres de acción:**  
   El string pasado a `onPressed: 'mi_accion'` siempre se invoca en PHP como `public function onMiAccion(array $params): void`.
4. **Validación pura de backend y conservación de inputs:**  
   Toda regla de negocio y validación reside en el backend PHP (Laravel). Cuando la validación falle, mantén las propiedades de estado con los valores enviados (`$this->nombre = ...`) para que el usuario no pierda lo que escribió.
5. **Cierre de Modales Declarativos:**  
   Para cerrar un modal, simplemente cambia la bandera en el handler (`$this->mostrarModal = false;`) y opcionalmente llama a `$this->closeModal();` para remover el overlay y emitir la reconciliación con el DOM.
