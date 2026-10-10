# 🚀 Tutorial: Creación de una Aplicación desde Cero con USIM Framework

> **Audiencia:** Desarrolladores PHP / Laravel que desean construir aplicaciones web interactivas, reactivas y orientadas al servidor sin escribir código JavaScript, componentes Vue/React o templates Blade complejos para la lógica de interfaz.

---

## 📋 Tabla de Contenidos

1. [¿Qué es USIM?](#1-qué-es-usim)
2. [Requisitos Previos](#2-requisitos-previos)
3. [Instalación del Paquete en una App Laravel](#3-instalación-del-paquete-en-una-app-laravel)
4. [Estructura del Proyecto Generada](#4-estructura-del-proyecto-generada)
5. [Tu Primera Screen Declarativa](#5-tu-primera-screen-declarativa)
6. [Layouts y Fluent UI Builder](#6-layouts-y-fluent-ui-builder)
7. [Interactividad y Reactividad en Tiempo Real](#7-interactividad-y-reactividad-en-tiempo-real)
8. [Integración en la Navegación y Menú](#8-integración-en-la-navegación-y-menú)
9. [Testing Headless Ultra-Rápido con Pest](#9-testing-headless-ultra-rápido-con-pest)
10. [Buenas Prácticas y Consejos Pro](#10-buenas-prácticas-y-consejos-pro)

---

## 1. ¿Qué es USIM?

**USIM** (*UI Services Implementation Model*) es un framework **Server-Driven UI (SDUI)** para Laravel. 

En USIM, **toda la interfaz de usuario, el estado y las transiciones se declaran en clases PHP puras llamadas Screens**. El servidor emite un árbol semántico de componentes hacia un cliente ligero en el navegador; cuando el usuario hace clic o interactúa, el navegador envía una petición de evento al servidor, la Screen muta sus componentes y el framework calcula automáticamente un **diff reactivo** (parche de cambios) que actualiza la pantalla al instante.

### ✨ Ventajas Clave
- **Cero JavaScript necesario:** Formularios, validaciones, diálogos, tablas y eventos se programan 100% en PHP.
- **Tipado estricto (PHP 8.2+):** Compatible con PHPStan / Larastan Nivel 9.
- **Testing Headless:** Las pantallas se prueban de extremo a extremo en memoria en milisegundos (< 10 ms), sin Selenium, Dusk o Chromium.
- **Server-Driven UI:** Cambia el diseño, campos o lógica en el backend y el cliente se actualiza sin redistribuir ni recompilar assets frontend.

---

## 2. Requisitos Previos

- **PHP 8.2** o superior con extensiones estándar (`mbstring`, `pdo_sqlite` o `pdo_mysql`, etc.).
- **Composer 2.x**.
- **Laravel 11.x** o superior.
- **Node.js 18+** (opcional, los assets precompilados de USIM se publican directamente).

---

## 3. Instalación del Paquete en una App Laravel

### Paso 3.1: Crear un nuevo proyecto Laravel (si no tienes uno)

```bash
composer create-project laravel/laravel mi-app-usim
cd mi-app-usim
```

> **Nota para desarrollo en el monorepo:** Si estás probando localmente en este repositorio, puedes usar el script de desarrollo que automatiza la creación de una app limpia:
> ```bash
> ./scripts/rebuild_dev.sh
> ```

### Paso 3.2: Requerir el paquete USIM

Instala el paquete oficial vía Composer:

```bash
composer require idei/usim
```

### Paso 3.3: Ejecutar el asistente de instalación

Ejecuta el comando interactivo de instalación de USIM:

```bash
php artisan usim:install
```

*(O de forma desatendida: `php artisan usim:install --force --no-interaction`)*

El instalador realiza automáticamente las siguientes acciones:
1. Publica la configuración en `config/usim.php`.
2. Publica las migraciones de base de datos (usuarios, roles, traducciones, auditoría).
3. Publica los assets de diseño e íconos en `public/vendor/idei/usim/`.
4. Crea la estructura de directorios en `app/UI/Screens/`.
5. Instala la Screen principal de menú (`app/UI/Screens/Menu.php`) y la pantalla inicial (`app/UI/Screens/Home.php`).
6. Configura la vista host (`resources/views/landing.blade.php`) y las rutas SPA en `routes/web.php`.
7. Ejecuta las migraciones de base de datos (`php artisan migrate`).
8. Sincroniza permisos, roles y traducciones iniciales (`php artisan usim:sync`).

---

## 4. Estructura del Proyecto Generada

Una vez instalado, tu aplicación Laravel contará con los siguientes elementos clave:

```
app/
├── UI/
│   ├── Screens/               # 📍 Aquí viven todas tus pantallas interactivas
│   │   ├── Home.php           # Pantalla de bienvenida
│   │   ├── Menu.php           # Menú principal y navegación global
│   │   └── Admin/             # Pantallas administrativas pre-scaffoldeadas (Usuarios, Roles, etc.)
│   └── Layouts/               # Contenedores y layouts reutilizables
config/
└── usim.php                   # Configuración global del framework
tests/
├── Feature/                   # Tests de integración
└── Unit/                      # Tests unitarios de Screens con el arnés headless
```

---

## 5. Tu Primera Screen Declarativa

Vamos a construir una pantalla de **Catálogo de Productos** interactiva en una subcarpeta `Products/Catalog`.

### Paso 5.1: Crear la Screen con el CLI

USIM proporciona un generador de scaffolding:

```bash
php artisan usim:scaf Products/Catalog
```

Esto generará el archivo `app/UI/Screens/Products/Catalog.php` con el namespace adecuado `App\UI\Screens\Products`.

### Paso 5.2: Descubrir la Screen

Para que el router de USIM y el menú reconozcan tu nueva pantalla, ejecuta el escáner de screens:

```bash
php artisan usim:discover
```

¡Listo! A partir de este momento, la URL:
```
http://localhost:8000/products/catalog
```
estará vinculada automáticamente a tu clase `App\UI\Screens\Products\Catalog`.

---

## 6. Layouts y Fluent UI Builder

Abre `app/UI/Screens/Products/Catalog.php`. Verás que hereda de `Idei\Usim\Screen`.

En USIM, el método `buildBaseUI()` define la interfaz declarativa. La clase `UI` ofrece un builder fluido e intuitivo:

```php
<?php

namespace App\UI\Screens\Products;

use Idei\Usim\Components\UI;
use Idei\Usim\Screen;

class Catalog extends Screen
{
    protected function buildBaseUI(): void
    {
        // 1. Configurar el contenedor raíz de la pantalla
        $this->rootContainer
            ->layout('vertical')
            ->align('stretch')
            ->gap('1.5rem')
            ->padding('2rem');

        // 2. Construir la cabecera
        $this->rootContainer->add(
            UI::label('📦 Catálogo de Productos')
                ->size('2xlarge')
                ->bold(),
            UI::label('Selecciona una categoría para filtrar nuestro catálogo en tiempo real.')
                ->color('var(--usim-text-muted)')
        );
    }
}
```

### Componentes más comunes del Fluent Builder (`UI::*`):
| Componente | Código | Descripción |
|---|---|---|
| **Contenedor Flex** | `UI::container()->layout('horizontal'|'vertical')->gap('1rem')` | Agrupa elementos con flexbox |
| **Etiqueta de Texto** | `UI::label('Texto')->size('large')->bold()` | Título, texto, subtítulo |
| **Botón** | `UI::button('Guardar')->style('primary')->action('onSave')` | Disparador de eventos |
| **Campo de Texto** | `UI::input('nombre')->placeholder('Tu nombre')` | Entrada de texto reactiva |
| **Selector** | `UI::select('categoria')->options(['1' => 'A', '2' => 'B'])` | Dropdown de selección |
| **HTML Libre** | `UI::html('<div class="badge">Nuevo</div>')` | Contenido HTML semántico |

---

## 7. Interactividad y Reactividad en Tiempo Real

El poder de USIM radica en cómo maneja los eventos. No necesitas escribir APIs REST manuales ni controladores con endpoints personalizados para cada interacción de la UI.

### Paso 7.1: Definir componentes reactivos como propiedades de la Screen

Cuando quieras mutar un componente dinámicamente en respuesta a un evento, defínelo como una propiedad protegida con su tipo correspondiente (por ejemplo `Label`, `Button`, `Container`):

```php
<?php

namespace App\UI\Screens\Products;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\UI;
use Idei\Usim\Screen;

class Catalog extends Screen
{
    // Propiedades tipadas para mutación reactiva
    protected Label $statusLabel;

    protected function buildBaseUI(): void
    {
        $this->rootContainer
            ->layout('vertical')
            ->align('stretch')
            ->gap('1.5rem')
            ->padding('2rem');

        // Instanciamos el label reactivo asignándole un nombre de componente
        $this->statusLabel = UI::label('Mostrando: Todos los productos')
            ->name('status_label')
            ->size('large')
            ->bold();

        // Barra de botones de categorías
        $filterBar = UI::container()
            ->layout('horizontal')
            ->gap('0.75rem')
            ->add(
                UI::button('💻 Tecnología')
                    ->name('filter_tech')
                    ->style('secondary')
                    ->action('onSelectCategory', ['category' => 'Tecnología']),

                UI::button('👕 Ropa')
                    ->name('filter_clothing')
                    ->style('secondary')
                    ->action('onSelectCategory', ['category' => 'Ropa']),

                UI::button('🔄 Resetear')
                    ->name('filter_reset')
                    ->style('ghost')
                    ->action('onSelectCategory', ['category' => 'Todos'])
            );

        // Agregamos todo al contenedor raíz
        $this->rootContainer->add(
            UI::label('📦 Catálogo de Productos')->size('2xlarge')->bold(),
            $filterBar,
            $this->statusLabel
        );
    }

    /**
     * Manejador de evento reactivo.
     * Convención: on + PascalCase del nombre de la acción.
     * En este caso: action('onSelectCategory') -> onSelectCategory(...)
     */
    public function onSelectCategory(string $category): void
    {
        // 1. Mutamos el componente directamente en PHP
        $this->statusLabel->text("Mostrando: {$category}");

        // 2. Notificamos al usuario con un Toast flotante
        $this->toast("Categoría filtrada: {$category}", 'info');
    }
}
```

### ¿Qué ocurre tras bambalinas?
1. El usuario hace clic en el botón `💻 Tecnología`.
2. El cliente envía un evento POST `/api/ui-event` con `{ action: 'onSelectCategory', parameters: { category: 'Tecnología' } }`.
3. USIM ejecuta `Catalog::onSelectCategory('Tecnología')`.
4. El motor de diffing detecta que `$statusLabel->text` cambió.
5. El servidor devuelve **únicamente el parche JSON**:
   ```json
   [
     { "type": "label", "id": 483386704, "text": "Mostrando: Tecnología" },
     { "type": "toast", "message": "Categoría filtrada: Tecnología", "style": "info" }
   ]
   ```
6. El cliente aplica el cambio en el DOM instantáneamente en menos de 30 ms.

---

## 8. Integración en la Navegación y Menú

Para que los usuarios puedan acceder fácilmente a tu pantalla desde la barra de menú global, edita `app/UI/Screens/Menu.php`:

Abre `app/UI/Screens/Menu.php` y busca el arreglo de items del menú en el método `buildBaseUI()`:

```php
$menuItems = [
    [
        'label' => 'Inicio',
        'url' => '/',
        'icon' => '🏠',
    ],
    [
        'label' => 'Catálogo',
        'url' => '/products/catalog',
        'icon' => '📦',
    ],
    [
        'type' => 'separator',
    ],
    // ... otros ítems
];
```

Al guardar los cambios, la opción "Catálogo" aparecerá automáticamente en el menú desplegable de toda la aplicación.

---

## 9. Testing Headless Ultra-Rápido con Pest

Una de las mayores innovaciones de USIM es su arnés de pruebas (`testScreen`). Te permite testear la interfaz y la reactividad completa en memoria sin necesidad de navegadores web pesados.

Crea un archivo de prueba en `tests/Unit/CatalogScreenTest.php`:

```php
<?php

use App\UI\Screens\Products\Catalog;

it('renderiza correctamente el catalogo de productos', function () {
    $screen = testScreen(Catalog::class);

    $screen->assertComponentExists('filter_tech')
           ->assertComponentExists('filter_clothing')
           ->assertComponentExists('status_label')
           ->assertComponentText('status_label', 'Mostrando: Todos los productos');
});

it('actualiza el label y muestra toast al hacer clic en una categoria', function () {
    $screen = testScreen(Catalog::class);

    $screen->click('filter_tech')
           ->assertComponentText('status_label', 'Mostrando: Tecnología')
           ->assertToast('Categoría filtrada: Tecnología');
});
```

### Ejecutar las pruebas:

```bash
./vendor/bin/pest tests/Unit/CatalogScreenTest.php
```

Resultado:
```bash
  PASS  Tests\Unit\CatalogScreenTest
  ✓ renderiza correctamente el catalogo de productos
  ✓ actualiza el label y muestra toast al hacer clic en una categoria

  Tests:    2 passed (7 assertions)
  Duration: 0.18s
```

> ⚡ **Velocidad extrema:** Ambos tests completos de interfaz y reactividad se ejecutan en menos de **200 milisegundos**.

---

## 10. Buenas Prácticas y Consejos Pro

### 1. Nombres Únicos de Componentes (`->name(...)`)
Asigna siempre un nombre semántico con `->name('mi_boton')` a los componentes que vayan a interactuar con eventos o que vayas a verificar en tests unitarios.

### 2. Separación de Responsabilidades
- Las **Screens** se encargan de la presentación declarativa y del estado de la interfaz.
- La lógica de negocio pesada, llamadas a base de datos y transacciones deben delegarse a **Services** o **Actions** inyectados en la Screen.

### 3. Diálogos de Confirmación Nativos
Para acciones destructivas (ej. eliminar un elemento), puedes usar el helper integrado `$this->confirmDialog()` antes de ejecutar la mutación.

### 4. Sincronización y Caché
Si agregas nuevas pantallas o renombras clases, mantén siempre sincronizado el catálogo con:
```bash
php artisan usim:discover
```

---

## 🏁 ¡Felicidades!

Has construido tu primera aplicación con **USIM Framework**. Disfruta de la velocidad de desarrollo de Laravel combinada con la reactividad del Server-Driven UI.

Para profundizar en arquitectura, contratos de eventos y componentes avanzados, consulta la documentación en el directorio `docs/`:
- [Guía de Sistema de Eventos USIM](framework/USIM_EVENTS_SYSTEM.md)
- [Contratos y Mocks](framework/CONTRACTS_AND_MOCKS.md)
- [Referencia de UI Builder](framework/UI_BUILDER_REFERENCE.md)

