# Refactorización: Sistema Extensible de Menús y Layouts por Composición para USIM

> **Propósito:** Documento de diseño e implementación del desacoplamiento del menú principal (`App\UI\Screens\Menu`), introducción del motor declarativo de navegación (`MenuBuilder`, `MenuItem`, `MenuProviderInterface`) en el core del framework (`Idei\Usim\Navigation`), arquitectura de Layouts por Composición (`Idei\Usim\Layout\AbstractLayout`) con slot `$main_menu_container`, y extracción de responsabilidades SRP (registro y demos).

---

## 1. Visión y Objetivos de Diseño Alcanzados

1. **Cumplir SOLID y Clean Code:**
   - **SRP (Single Responsibility):** Se extrajo la lógica de registro de usuarios, validaciones y manejo de errores fuera de `Menu.php` hacia el servicio dedicado `App\Services\Auth\RegisterActionHandler`. Se redujo `Menu.php` significativamente, enfocándolo exclusivamente en orquestar los triggers, dropdowns y preferencias (tema, idioma, unidad).
   - **OCP (Open/Closed):** Nuevos módulos y pantallas pueden registrar sus propios items mediante el contrato `MenuProviderInterface` (`MainMenu`, `DemosMenuProvider`, `AdminMenuProvider`) sin modificar el menú central.
   - **Composición sobre Herencia:** Las pantallas definen su layout mediante composición (`public static ?string $layout = MainLayout::class;`). Si una pantalla requiere modo limpio/kiosk/fullscreen, simplemente declara `public static ?string $layout = null;`.
2. **Motor Declarativo Nativo en el Core (`Idei\Usim\Navigation`):**
   - API fluida para definir menús:
     ```php
     MenuBuilder::make('main_menu')
         ->trigger('☰')
         ->position('bottom-left')
         ->link('Home', '/', '🏠')
         ->screen(UsersManager::class)
         ->provider(DemosMenuProvider::class)
         ->when(fn() => Auth::check());
     ```
   - Evaluación reactiva de permisos y `checkAccess()` dinámico por cada pantalla vinculada.
3. **Layout por Composición con Slot Embebido:**
   - `Idei\Usim\Layout\AbstractLayout` define los slots de estructura de la página:
     - Slot `$main_menu_container`: aloja el menú superior embebido.
     - Slot `$content_container`: aloja el contenido de la pantalla funcional.
   - Unificación del ciclo de vida: toda la pantalla (layout, menú y contenido) viaja en una sola llamada HTTP (`/api/ui/{screen}`), eliminando la dependencia de `window.MENU_SERVICE` y variables globales en Blade.

---

## 2. Componentes Implementados

### 2.1 Core de Navegación (`packages/idei/usim/src/Navigation`)
- **`Idei\Usim\Navigation\MenuItem`:** Value object fluido que soporta `label`, `url`, `action`, `params`, `screenClass`, `when(Closure|bool)`, `can(string $permission)`, `separator()`, y `submenu()`. Evalúa dinámicamente `screenClass::checkAccess()` y permisos de Laravel Gates.
- **`Idei\Usim\Navigation\MenuBuilder`:** Constructor fluido que permite configurar triggers, posición, ancho y poblar o renderizar instancias de `MenuDropdown`.
- **`Idei\Usim\Navigation\Contracts\MenuProviderInterface`:** Contrato con método `build(MenuBuilder $menu): void`.

### 2.2 Core de Layouts (`packages/idei/usim/src/Layout`)
- **`Idei\Usim\Layout\AbstractLayout`:** Clase abstracta base para layouts que define la construcción del contenedor raíz, el slot de menú (`$mainMenuContainer`) y el slot de contenido (`$contentContainer`).

### 2.3 Implementación de Layouts y Menús en la Aplicación
- **`App\UI\Layouts\MainLayout`:** Layout principal de la aplicación. Configura la barra superior con el contenedor `$main_menu_container` donde embebe el menú configurado (`Menu::class` o el menú personalizado de la pantalla), y el contenedor `$content_container` para el contenido.
- **`App\UI\Navigation\Menus\MainMenu`:** Clase declarativa del menú principal de la aplicación.
- **`App\UI\Navigation\Menus\DemosMenuProvider`:** Proveedor modular con los 12 demos disponibles en el sistema.
- **`App\UI\Navigation\Menus\AdminMenuProvider` y `App\UI\Screens\Admin\AdminMenu`:** Menú alternativo y específico para áreas administrativas.
- **`App\Services\Auth\RegisterActionHandler`:** Servicio desacoplado que gestiona la validación, alta de usuarios, asignación de roles y notificaciones del modal de registro.

### 2.4 Integración en `Screen.php`
- Propiedades estáticas de composición:
  ```php
  public static ?string $layout = 'default';
  public static ?string $menuScreen = null;
  ```
- Métodos de resolución y compatibilidad:
  - `Screen::getLayoutClass()`: Resuelve la clase de layout configurada (omite layout en pantallas de Device, Kiosk o menús).
  - `Screen::getMenuScreen()`: Resuelve la clase de menú personalizada o default.
  - `Screen::resolveScreenClassFromSlug(string $slug)` y `Screen::resolveScreenSlug(string $class)`: Resolución bidireccional entre slugs y FQCN.
  - `Screen::hasLayout()`: Retorna `true` si la pantalla cuenta con un layout activo (`getLayoutClass() !== null`).
  - `Screen::toast()`, `Screen::closeModal()`, `Screen::redirect()`, `Screen::updateModal()` convertidos a `public` para permitir su uso por Action Handlers externos.

---

## 3. Pruebas y Validación

Se crearon y ejecutaron suites de pruebas automatizadas:
- **`tests/Feature/NavigationMenuBuilderTest.php`:**
  - Construcción declarativa con links, actions, submenús y separadores.
  - Filtrado condicional diferido con `->when()` y gates con `->can()`.
  - Evaluación dinámica de `checkAccess()` en items de tipo pantalla.
  - Composición modular mediante `MenuProviderInterface`.
- **`tests/Feature/LayoutCompositionTest.php`:**
  - Enmarcado automático con `MainLayout` y slots `$main_menu_container` y `$content_container`.
  - Modo Kiosk / Standalone sin layout cuando `$layout = null`.
  - Soporte de menús alternativos por clase (`AdminMenu::class`) y por slug (`admin/admin-menu`).
  - Resolución bidireccional de slugs.
- **Suites de compatibilidad existentes:**
  - `tests/Feature/HomeMenuScreenTest.php` (17 pruebas pasando).
  - `tests/Feature/MenuRegisterDialogTest.php` (3 pruebas pasando, 84 aserciones).
