# USIM Framework — Handover Document: Unificación de Active Unit y Fix de Logout

> **Fecha:** 2026-10-05  
> **Rama de trabajo:** `menu-refactoring-0`  
> **Estado:** 257 tests pasando (2393 aserciones) | Suite completa verde sin regresiones.

---

## 1. Resumen Ejecutivo y Objetivos de la Sesión

Esta sesión resolvió dos problemas arquitectónicos críticos en el framework USIM:

1. **Unificación del contexto de Unidad Activa (`active_unit`):** Eliminación completa de la propiedad `$state_unit` y variables de sesión/localStorage redundantes, consolidando `UIStateManager` como la **única fuente de verdad** (Single Source of Truth) para la unidad seleccionada en el sistema multitenant/multi-unidad.
2. **Corrección del bug de Logout tras interactuar con tablas/pestañas:** Diagnóstico y resolución de un fallo visual y de ciclo de vida donde, tras interactuar con la pantalla de gestión (cambiar tabs o tablas), el cierre de sesión (`onConfirmLogout`) dejaba congelada la pantalla anterior en el DOM emitiendo errores en consola (`Parent 104921240 not found`).

---

## 2. Parte 1: Unificación de `active_unit` en `UIStateManager`

### 2.1 Problema previo
- Existían múltiples representaciones de la unidad activa coexistiendo en la aplicación:
  - Propiedades locales en pantallas (`protected string $state_unit = ''` en [`Menu.php`](file:///workspaces/usim-framework/app/UI/Screens/Menu.php)).
  - Claves en sesión de Laravel (`session('state_unit')` / `session('active_unit')`).
  - Claves persistidas en el `localStorage` del cliente (`store_unit`).
  - Contexto de Spatie Permission Teams (`setPermissionsTeamId(...)`).
- Esta duplicidad provocaba que:
  - Distintas pantallas resolvieran unidades diferentes o vacías al evaluar permisos.
  - Al cambiar de unidad en el menú superior, las secciones CRUD (ej. `devices_table`) no sincronizaran de manera consistente el filtrado de registros.
  - Se filtrara `store_unit` en el payload de storage del cliente.

### 2.2 Arquitectura adoptada: Única Fuente de Verdad
Se estableció contractualmente que el **único** punto de lectura y escritura para la unidad activa en el backend es:

```php
// Establecer unidad activa:
UIStateManager::setActiveUnit(?string $slug);

// Consultar unidad activa:
UIStateManager::getActiveUnit(): ?string;
```

#### Reglas de implementación:
1. **Sin variables de sesión HTTP:** No se utiliza `session()` para la unidad activa, permitiendo consistencia estricta en arquitecturas sin estado, APIs y llamadas AJAX a `/api/ui-event`.
2. **Sincronización con Spatie Permissions:**
   Cada vez que se establece una unidad activa mediante `UIStateManager::setActiveUnit($slug)` o `Menu::onChangeUnit()`, se actualiza el ID del equipo en Spatie:
   ```php
   setPermissionsTeamId($unit->id);
   ```
   Y al hacer logout o limpiar la unidad:
   ```php
   UIStateManager::setActiveUnit(null);
   if (function_exists('setPermissionsTeamId') && config('permission.teams')) {
       setPermissionsTeamId(null);
   }
   ```
3. **Consolidación en [`ResolvesActiveUnitContext.php`](file:///workspaces/usim-framework/app/UI/Screens/Admin/Concerns/ResolvesActiveUnitContext.php):**
   El trait utilizado por pantallas de administración resuelve la unidad activa delegando directamente en `UIStateManager`:
   ```php
   protected function resolveActiveUnitSlug(): ?string
   {
       return UIStateManager::getActiveUnit();
   }
   ```
4. **Sincronización reactiva de componentes:**
   En [`ManagesDevicesSection.php`](file:///workspaces/usim-framework/app/UI/Screens/Admin/Concerns/ManagesDevicesSection.php#L338), se habilitó el listener `onUnitChanged`:
   ```php
   public function onUnitChanged(array $params): void
   {
       $this->devices_table->refresh();
   }
   ```
   Asegurando que la tabla de dispositivos actualice sus datos en tiempo real al conmutar de unidad.

---

## 3. Parte 2: Diagnóstico y Resolución del Bug de Logout

### 3.1 La Secuencia del Bug
1. El usuario inicia sesión como `root` en `/` o `/home`.
2. Es dirigido exitosamente a la pantalla de administración [`UsersManager`](file:///workspaces/usim-framework/app/UI/Screens/Admin/UsersManager.php) (`/admin/users-manager`).
3. **Escenario 3-a (Sin interacción):** El usuario hace clic directamente en Logout -> Se abre el diálogo de confirmación -> Confirma -> Vuelve a Home limpiamente.
4. **Escenario 3-b (Con interacción):** El usuario cambia de pestaña a "Devices" (o interactúa con la tabla) -> Hace clic en Logout -> Confirma -> **La tabla y pestañas de `UsersManager` permanecen visibles en pantalla**, la barra superior muestra el toast *"You have been logged out successfully"*, la URL cambia a `/home`, y la consola del navegador reporta:
   ```text
   ⚠️ Some components could not be mounted after retries: (2) ['24811602', '24813974']
   ❌ Parent 104921240 not found for component 24811602
   ❌ Parent 24811602 not found for component 24813974
   ```

### 3.2 Causa Raíz Identificada

#### A. Causa Directa: `showInto(Home::class)` en lugar de `redirect()`
En el commit `95d637a4e7fe124733e4030ec5633aaeeb735517` se había modificado [`app/UI/Screens/Menu.php`](file:///workspaces/usim-framework/app/UI/Screens/Menu.php#L539) en `onConfirmLogout`:
```php
// ANTERIORMENTE (y en packages/idei/usim/stubs/screens/MenuStub.php.stub):
$this->toast(t('screen.menu.logout_success'));
$this->redirect();

// SE HABÍA MODIFICADO A:
$this->toast(t('screen.menu.logout_success'));
$this->showInto(Home::class, force: true, updateBrowserUrl: true);
```

#### B. Por qué falló `showInto()` en 3-b pero parecía funcionar en 3-a:
1. **Límite de ciclo de vida:** El cierre de sesión destruye la sesión en el servidor (`Auth::logout()`, borrado de tokens de Sanctum, reseteo de roles y unidades). Pretender realizar una transición SPA *in-place* (`showInto()`) mantiene todo el entorno JavaScript, estado de componentes, DOM previo y listeners activos en el cliente en un estado ya no autenticado.
2. **Mutación del DOM de pestañas:**
   - En 3-a, el árbol DOM del slot `content_container` (`104921240`) permanecía exactamente como fue renderizado inicialmente por el servidor.
   - En 3-b, al cambiar al tab de Devices, el componente cliente `UsimContainerComponent` ejecutó `_activateTab()`, creando y manipulando nodos `div.ui-container-tab-panel`.
3. **Fallo de resolución de hijos y padres en el DOM:**
   - En [`packages/idei/usim/resources/assets/js/ui-renderer.js`](file:///workspaces/usim-framework/packages/idei/usim/resources/assets/js/ui-renderer.js#L1691), `clearContainer(104921240)` busca los hijos directos con:
     ```javascript
     node.parentElement?.closest('[data-component-id]') === containerElement
     ```
   - Al tener contenedores internos con pestañas y paneles intermedios (que no tienen `data-component-id`), la recursión de limpieza y desregistro en `this.components` desincronizó la jerarquía.
   - En consecuencia, el renderer:
     - No eliminó el subárbol DOM de `UsersManager` (quedó huérfano y visible).
     - No pudo resolver al contenedor padre `104921240` para montar los nuevos componentes de `Home` (`24811602` y `24813974`).
     - Agotó los 15 intentos de reintento (`pendingCreates`), emitiendo los errores en consola y dejando la pantalla congelada.

### 3.3 La Solución Aplicada
Se restauró el contrato canónico de logout en [`app/UI/Screens/Menu.php`](file:///workspaces/usim-framework/app/UI/Screens/Menu.php#L536-L541):

```php
$this->user_menu->trigger("⚙️");
$this->populateUserMenu($this->user_menu);
$this->populateMainMenu($this->main_menu);

$this->toast(t('screen.menu.logout_success'));
$this->redirect();
```

#### Por qué `redirect()` es la solución correcta:
- Cumple el contrato oficial verificado en [`tests/Feature/UiAuthEventsContractTest.php`](file:///workspaces/usim-framework/tests/Feature/UiAuthEventsContractTest.php#L85):
  ```php
  it('returns redirect contract on confirm_logout event from menu screen')
  ```
- El backend emite `['redirect' => '/', 'toast' => [...]]`.
- En [`ui-renderer.js:1593`](file:///workspaces/usim-framework/packages/idei/usim/resources/assets/js/ui-renderer.js#L1593), el cliente intercepta la redirección, preserva el toast en `sessionStorage.setItem('pendingToast', ...)`, y ejecuta `window.location.href = '/'`.
- Esto garantiza un **teardown absoluto** de todo el DOM previo, tablas, modales y memoria del cliente.
- Al cargar `/`, la pantalla Home inicializa limpia y consume `pendingToast` para mostrar el mensaje de éxito sin ningún residuo de pantallas autenticadas.

---

## 4. Guía y Convenciones para Futuros LLMs / Desarrolladores

Si estás modificando autenticación, menús o navegación en USIM, sigue rigurosamente estas reglas:

| Acción | ❌ INCORRECTO | ✅ CORRECTO |
|---|---|---|
| **Cierre de sesión (`logout`)** | `$this->showInto(Home::class)` | `$this->redirect();` o `$this->redirect('/')` |
| **Consultar unidad activa** | `$this->state_unit`, `session('active_unit')` | `UIStateManager::getActiveUnit()` |
| **Establecer unidad activa** | `$this->state_unit = $slug;`, `session(['unit' => ...])` | `UIStateManager::setActiveUnit($slug);` |
| **Payload del cliente** | Incluir `'store_unit'` en storage JSON | No usar `'store_unit'`; la unidad reside en `UIStateManager` |
| **Navegación entre pantallas autenticadas** | `$this->redirect('/admin/users')` (recarga innecesaria) | `$this->showInto(UsersManager::class, force: true, updateBrowserUrl: true)` |

---

## 5. Verificación de Pruebas

Toda la suite de pruebas del framework y aplicación fue ejecutada tras los cambios:

```bash
php artisan test
```

**Resultado:**
```text
Tests:    257 passed (2393 assertions)
Duration: ~9.3s
```
Zero errores, zero regresiones.
