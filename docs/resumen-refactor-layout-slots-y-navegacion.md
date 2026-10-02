# Resumen de Modificaciones: Navegación de Pantallas en Slots de Layout y Limpieza de Contenedores

Este documento detalla todas las modificaciones implementadas para permitir que las pantallas del sistema (demos, login, etc.) se rendericen limpiamente dentro del slot correspondiente del layout activo (`content_container` / `main`), sin abrir modales ni superponerse al contenido previo en el DOM.

---

## 1. Problemas Identificados y Causas Raíz

### 1.1. Las pantallas se abrían como modal en lugar de montarse en el slot
- **Causa:** En `Menu.php`, los elementos del menú estaban configurados con `$builder->action(..., 'show_login_form', ...)` abriendo modales, o usando `onShowScreen()` sin una integración completa con el layout activo.
- **Causa en el ciclo de vida:** Durante las peticiones AJAX de eventos a `/api/ui-event`, `AbstractLayout::current()` era `null` porque se trata de un nuevo request HTTP. Aunque `Screen::getLayout()` instanciaba la clase del layout configurado (`MainLayout`), no ejecutaba su método `initializeEventContext()`. En consecuencia, el registro interno de slots del layout (`$layout->slots`) estaba vacío y las llamadas a `$layout->getSlot('content')` o `$layout->showInto(...)` no encontraban el contenedor de destino. Además, los argumentos en `Screen::showInto()` hacia `$layout->showInto()` estaban invertidos.

### 1.2. La pantalla se visualizaba al final ("Se visualiza al final")
- **Causa:** Al navegar desde `Home` a una pantalla demo (por ejemplo, `FormDemo`), el backend enviaba los componentes de la nueva pantalla con padre `content_container`. Sin embargo, en el DOM del navegador, el contenedor `content_container` ya contenía los componentes de la pantalla previa (`Home`, con su portada y pie de página de más de 2300 líneas de HTML).
- El cliente JavaScript (`ui-renderer.js`) no recibía ninguna instrucción para purgar el contenido previo del slot, por lo que insertaba los nuevos componentes con `appendChild` al final del contenedor, dejando la demo debajo del footer previo.

### 1.3. Mensaje en la consola: `ℹ️ No MENU_SERVICE defined, skipping menu load`
- **Causa:** En la función `loadMenuUI()`, el renderizador verificaba si existía `window.MENU_SERVICE` para consultar asíncronamente el menú tradicional. En la nueva arquitectura ("Layouts as Screens"), el menú se monta automáticamente dentro del árbol de componentes de la pantalla en `main_menu_container`, por lo que `window.MENU_SERVICE` deliberadamente no se define y esa llamada emitía dicho log informativo en cada carga de página.

### 1.4. Incompatibilidades en contratos de prueba de autenticación
- **Causa:** En commits previos se habían comentado las llamadas a `$this->redirect($redirectTo)` en `Login::onSubmitLogin()` y `$this->redirect()` en `Menu::onConfirmLogout()`. Esto ocasionó que las pruebas unitarias y de contratos esperaran la URL de redirección del rol asignado y recibieran `/` o `null`.

---

## 2. Modificaciones Realizadas

### 2.1. Backend (`packages/idei/usim/src/Screen.php`)
- **Inicialización del contexto de eventos en Layouts:** En `Screen::getLayout()`, tras instanciar la clase de layout (`app($layoutClass)`), ahora se invoca `$layout->initializeEventContext()`. Esto garantiza que los slots (`content_container`, `main_menu_container`) queden registrados y el árbol de componentes esté listo para recibir inserciones durante peticiones `/api/ui-event`.
- **Corrección de argumentos en `showInto()`:** Se normalizó el orden de llamada hacia el layout:
  ```php
  $layout->showInto($targetClass, $effectiveSlot, $params, $updateBrowserUrl);
  ```

### 2.2. Backend (`packages/idei/usim/src/Layout/AbstractLayout.php`)
- **Directivas de limpieza del contenedor:** En el método `showInto()`, se agregaron al colector de cambios (`UIChangesCollector`) las claves:
  - `clear_container => $targetContainer->getId()`: notifica explícitamente al cliente que debe vaciar el contenedor antes de montar nuevos componentes.
  - `slot_container_id => $targetContainer->getId()` dentro del objeto `navigate`: asegura que los navegadores tengan la referencia al ID exacto del DOM a limpiar.

### 2.3. Backend (`packages/idei/usim/src/Navigation/MenuBuilder.php`)
- **Nuevo helper `screenShow()`:** Permite asociar una pantalla al menú para que sea renderizada mediante navegación en slot:
  ```php
  public function screenShow(
      string $screenClass,
      Closure|bool|null $when = null
  ): MenuItem {
      $label = $screenClass::getMenuLabel();
      $icon = $screenClass::getMenuIcon();
      $item = MenuItem::make($label)
          ->action('showScreen', ['screen' => $screenClass])
          ->icon($icon)
          ->when($when);

      $this->items[] = $item;
      return $item;
  }
  ```

### 2.4. Aplicación (`app/UI/Screens/Menu.php`)
- **Manejador `onShowScreen()`:** Implementado con tipado estricto para PHPStan Nivel 9:
  ```php
  /**
   * @param array<string, mixed> $params
   */
  public function onShowScreen(array $params): void
  {
      /** @var class-string<Screen>|string|null $screen */
      $screen = $params['screen'] ?? null;
      if ($screen !== null && is_subclass_of($screen, Screen::class)) {
          /** @var string|null $slot */
          $slot = isset($params['slot']) && \is_string($params['slot']) ? $params['slot'] : null;

          $this->showInto($screen, slot: $slot);
      }
  }
  ```
- **Restauración de redirección en Logout:** En `onConfirmLogout()`, se descomentó `$this->redirect();` para emitir el contrato de navegación requerido por los tests.

### 2.5. Aplicación (`app/UI/Screens/Auth/Login.php`)
- **Restauración de redirección tras login exitoso:** En `onSubmitLogin()`, se restauró `$this->redirect($redirectTo);` para que tras autenticarse con éxito, el usuario sea redirigido a la pantalla asignada a su rol (e.g. `/admin/users-manager`, `/registered`).

### 2.6. Frontend (`packages/idei/usim/resources/assets/js/ui-renderer.js` y `public/vendor/idei/usim/js/ui-renderer.js`)
- **Registro en `SPECIAL_UI_KEYS`:** Se añadió `'clear_container'`.
- **Implementación de `clearContainer(containerId)`:**
  - Localiza el elemento del contenedor por `data-component-id` o por ID de DOM.
  - Encuentra todos los elementos hijos directos con `data-component-id` y ejecuta `removeComponentAndChildren(childId)`, purgándolos del DOM y del mapa `this.components`.
  - Limpia el DOM restante invocando `_buildShell()` si el componente es un container con estructura de pestañas/título, o reseteando `targetElement.innerHTML = ''`.
- **Integración en `handleUIUpdate()`:**
  - Al procesar `uiUpdate.clear_container` o `uiUpdate.navigate.slot_container_id`, se ejecuta `clearContainer()` antes de la fase de creación y montaje de nuevos componentes (`processComponentUpdates`).
- **Eliminación del log `loadMenuUI()`:**
  - Se removió `console.log('ℹ️ No MENU_SERVICE defined, skipping menu load');` para mantener limpia la consola en entornos con layouts modernos.

---

## 3. Verificación y Resultados de Calidad

1. **PHPStan (Nivel 9):**
   ```bash
   ./vendor/bin/phpstan analyse --memory-limit=2G
   # Note: Using configuration file /workspaces/usim-framework/phpstan.neon.
   # 694/694 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%
   # [OK] No errors
   ```

2. **Suite de pruebas Pest:**
   ```bash
   ./vendor/bin/pest
   # Tests: 238 passed (2247 assertions)
   # Duration: 13.87s
   ```

---

## 4. Estado Actual del Repositorio

- **Rama Git:** `menu-refactoring-0`
- **Archivos Modificados:**
  - `app/UI/Navigation/Menus/DemosMenuProvider.php`
  - `app/UI/Screens/Auth/Login.php`
  - `app/UI/Screens/Menu.php`
  - `packages/idei/usim/resources/assets/js/ui-renderer.js`
  - `packages/idei/usim/src/Layout/AbstractLayout.php`
  - `packages/idei/usim/src/Navigation/MenuBuilder.php`
  - `packages/idei/usim/src/Screen.php`
  - `public/vendor/idei/usim/js/ui-renderer.js`
