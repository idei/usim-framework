# Arquitectura de Layouts como Screens en USIM Framework

Este documento describe la arquitectura, diseño e implementación del refactoring de Layouts en el framework USIM, donde los **Layouts son Screens de primer nivel** que implementan contratos de orquestación de slots (`LayoutInterface`) y componen pantallas hijas mediante el mecanismo nativo `Screen::embedInto()`.

---

## 1. Contexto y Problemática Previa

En la iteración anterior, se intentó implementar la navegación in-slot en el cliente mediante un router SPA en JavaScript (`loadScreenIntoSlot`, `clearSlot`) y un envoltorio ad-hoc de closures (`$contentBuilder`) en el backend. Este camino presentó graves fallas de estabilidad y diseño:

1. **Colisión de Estados y Flicker en Login**: Al autenticarse como usuario `root`, la pantalla de administración (`UsersManager`) parpadeaba brevemente y luego desaparecía, dejando la pantalla en negro o vacía.
2. **Caída de Componentes en el Renderizador (`maxPasses = 4`)**: Árboles con múltiples niveles de anidación (como tablas con toolbar, acciones y modales) excedían las 4 pasadas del bucle de montaje en `ui-renderer.js`, abortando con errores del tipo `Some components could not be mounted after retries: (102)`.
3. **Duplicación de Layouts**: Peticiones cliente no sincronizadas a `/api/ui/{screen}?parent=content_container` provocaban que `Screen.php` envolviera nuevamente la pantalla hija dentro de un segundo `MainLayout`, produciendo colisiones de identificadores y fallos en el árbol DOM.
4. **Acoplamiento disruptivo**: El uso de closures `$layout->build($root, fn($slot) => ...)` impedía que el Layout funcionara como un componente persistente con su propio ciclo de vida, estado reactivo e hidratación desde caché.

---

## 2. Decisión Arquitectónica: Layouts como Screens

Se rediseñó el sistema bajo la premisa: **Los Layouts son Screens que implementan `LayoutInterface`**.

```text
+-----------------------------------------------------------------+
|                       MainLayout (Screen)                       |
|                                                                 |
|  +-----------------------------------------------------------+  |
|  | Slot: 'top_menu' -> mainMenuContainer                     |  |
|  | (Screen::embedInto(MenuScreen::class, $mainMenuContainer))|  |
|  +-----------------------------------------------------------+  |
|                                                                 |
|  +-----------------------------------------------------------+  |
|  | Slot: 'main' / 'content' -> contentContainer              |  |
|  | (Screen::embedInto(TargetScreen::class, $contentContainer)|  |
|  +-----------------------------------------------------------+  |
+-----------------------------------------------------------------+
```

### Ventajas del Enfoque:
- **Homogeneidad**: Un Layout utiliza el mismo ciclo de vida (`buildBaseUI`, `postLoadUI`, `render`, `handleAction`) que cualquier otra pantalla.
- **Persistencia y Caché**: Se guarda en `UIStateManager` como un snapshot de UI, manteniendo sincronizado el menú y la estructura general sin reconstruir todo el árbol innecesariamente.
- **Composición Declarativa**: Permite anidar pantallas utilizando `Screen::embedInto()`, que garantiza aislamiento de identificadores vía `UIIdGenerator::pushCurrentContext()`.

---

## 3. Componentes Implementados

### 3.1. `LayoutInterface`
**Archivo**: `packages/idei/usim/src/Layout/LayoutInterface.php`

Define el contrato para registro y resolución de slots, así como la activación de pantallas en slots:

```php
namespace Idei\Usim\Layout;

use Idei\Usim\Components\Container;
use Idei\Usim\Screen;

interface LayoutInterface
{
    public function registerSlot(string $name, Container $container): Container;
    public function getSlot(string $name = 'content'): ?Container;
    public function getSlots(): array;
    public function setActiveScreen(string $slot, string $screenClass): self;
    public function getActiveScreen(string $slot = 'main'): ?string;
    public function showInto(string $screenClass, ?string $slot = null, array $params = [], bool $updateBrowserUrl = true): bool;
    public function show(string $screenClass, ?string $slot = null, array $params = []): bool;
}
```

---

### 3.2. `AbstractLayout`
**Archivo**: `packages/idei/usim/src/Layout/AbstractLayout.php`

Clase base abstracta que:
- Extiende de `Screen` e implementa `LayoutInterface`.
- Declara explícitamente `public static ?string $layout = null;` para evitar autorreferencias recursivas de layout.
- Mantiene el registro de slots (`$slots`), contenedores principales (`$mainMenuContainer`, `$contentContainer`) y pantallas activas (`$activeScreens`).
- **Recuperación Resiliente de Slots desde Caché**:
  Cuando el layout se restaura desde un snapshot de sesión (`UIStateManager`), no se vuelve a ejecutar `buildBaseUI()`. `AbstractLayout` implementa:
  - `postLoadUI()`: Descubre y re-registra automáticamente los contenedores `content_container` y `main_menu_container` dentro de `$this->container`.
  - Fallback en `getSlot()`: Si `$slots` no contiene el slot buscado, realiza una búsqueda por nombres canónicos (`['content_container', 'main_container', 'main', 'content']`) en el árbol reconstruido y lo cachea en memoria.
- Métodos de navegación y despliegue `showInto()` y `show()` con emisión de directiva de navegación HTML5 (`navigate`).

---

### 3.3. `MainLayout`
**Archivo**: `app/UI/Layouts/MainLayout.php`

Implementación de la plantilla por defecto de la aplicación:
- Construye la estructura vertical a pantalla completa (`minHeight(Size::vh(100))`).
- Registra el slot superior `top_menu` / `menu` en `$mainMenuContainer` e incrusta la pantalla de menú resuelta (`Menu::class` o menú personalizado definido por la pantalla anfitriona).
- Registra el slot central `main` / `content` en `$contentContainer`.
- Tipado estricto compatible con PHPStan Nivel 9 e Intelephense (resolución segura de clases y slugs sin falsos positivos de tipos).

---

### 3.4. Orquestación en `Screen::render()`
**Archivo**: `packages/idei/usim/src/Screen.php`

En el método `render()` de `Screen`:
1. Si la petición es para el contenedor principal (`$parent === 'main'`), la capa modal es 0 y la pantalla no es un layout (`!($this instanceof LayoutInterface)`), se resuelve la clase del layout configurado (`static::getLayoutClass()`).
2. Se instancia y renderiza el layout:
   ```php
   $layout = static::make($layoutClass);
   $layout->render(...);
   ```
3. Se obtiene el slot de contenido (`$layout->getSlot('content') ?? $layout->getSlot('main')`).
4. Se limpia el slot y se incrusta la pantalla destino:
   ```php
   if ($slot instanceof Container) {
       $slot->clear();
       self::embedInto(static::class, $slot);
   }
   ```
5. En `Screen::embedInto()`, se asegura que la definición del árbol de la pantalla incrustada se añada a la respuesta JSON vía `$instance->uiChanges()->add($instance->container->toJson())`.

---

### 3.5. Simplificación del Cliente (`ui-renderer.js`)
**Archivos**:
- `packages/idei/usim/resources/assets/js/ui-renderer.js`
- `public/vendor/idei/usim/js/ui-renderer.js`

1. **Eliminación del router experimental cliente**: Se eliminaron las funciones `loadScreenIntoSlot()` y `clearSlot()`. La carga de pantallas es orquestada por el backend en una única respuesta JSON atómica.
2. **Aumento de Pasadas de Montaje**: Se incrementó `maxPasses` de 4 a 15 pasadas para permitir que componentes con anidación profunda (tablas, toolbars, filtros, modales) se monten sin agotar los intentos.
3. **Manejo de `uiUpdate.navigate`**: Cuando llega una directiva `navigate`, el cliente actualiza el título del documento y el historial del navegador (`history.pushState`), dejando la sincronización visual al árbol de componentes.

---

### 3.6. Flujo de Autenticación (`Login.php`)
**Archivos**:
- `app/UI/Screens/Auth/Login.php`
- `packages/idei/usim/stubs/screens/Auth/Login.php.stub`

En `onSubmitLogin()`, tras validar las credenciales contra `LoginService`:
- Se reemplazó la navegación in-slot por `$this->redirect($redirectTo);`.
- El navegador efectúa una redirección limpia a la ruta autorizada (`/admin/users-manager`, etc.), permitiendo que la nueva pantalla y sus permisos se carguen de manera consistente y sin parpadeos.

---

### 3.7. Helpers de Pruebas
**Archivos**:
- `tests/Support/UiScreenTestHelpers.php`
- `packages/idei/usim/stubs/tests/Support/UiScreenTestHelpers.php.stub`

Se actualizó la función `serviceRootComponentId()` para ignorar contenedores que pertenezcan a Layouts (`LayoutInterface`) o al `Menu`, permitiendo que las aserciones de pruebas apunten con precisión al contenedor raíz de la pantalla bajo test.

---

## 4. Resolución de Casos Borde y Bugs Detectados

| Problema | Causa Raíz | Solución |
| :--- | :--- | :--- |
| **Flicker y caída de 102 componentes en Login** | Router cliente llamaba a `/api/ui/...` montando layouts duplicados; `maxPasses = 4` abortaba el árbol de `UsersManager`. | Redirección estándar post-login `$this->redirect()` y `maxPasses = 15`. |
| **Pantalla en negro (solo 9 componentes)** | Al restaurar `MainLayout` desde caché en la segunda petición, no se llamaba a `buildBaseUI()`, dejando `$slots` y `$contentContainer` en `null`. | Recuperación resiliente en `getSlot()` y descubrimiento de slots en `AbstractLayout::postLoadUI()`. |
| **Inversión de argumentos en `show()`** | `AbstractLayout::show()` invocaba `$this->showInto($effectiveSlot, $screenClass, $params)` con orden inverso. | Llamada normalizada con firma canónica `$this->showInto($screenClass, $effectiveSlot, $params)`. |
| **Falsos positivos en Intelephense** | `is_subclass_of($var, Screen::class)` en condicional causaba que el analizador infiriera `$var` como objeto en vez de `class-string`. | Resolución en asignación ternaria con anotación `@var class-string<Screen>\|null` y validación simple `!== null`. |

---

## 5. Verificación y Resultados

- **Análisis Estático (PHPStan Nivel 9 / Max)**:
  `694/694 files analysed [OK] No errors`.
- **Estándar de Código (Laravel Pint)**:
  Aprobado en todos los archivos modificados (`passed`).
- **Suite de Pruebas (Pest)**:
  `238 passed (2261 assertions)` sin fallos ni regresiones.

