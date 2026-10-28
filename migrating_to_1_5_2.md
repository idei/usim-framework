# Guía de Actualización y Migración a `idei/usim` v1.5.2

Esta actualización es **100% retrocompatible** y resuelve de manera definitiva el bug de inconsistencia de estado en barras de navegación (`Menu`) y omisión del ciclo de vida en recargas de página (`F5`) tras autenticarse o cerrar sesión.

---

## Resumen del Problema Resuelto

En versiones previas (`v1.5.1` e inferiores):
1. Al renderizar `MainLayout` por primera vez como visitante, la sub-pantalla `Menu` se embebía estáticamente y quedaba serializada dentro del snapshot del layout en caché (`ui_state:MainLayout:{clientId}`).
2. Al iniciar sesión vía SPA (`logged_user`), `Menu` actualizaba su propio snapshot, pero `MainLayout` conservaba la foto congelada de visitante.
3. Al presionar **F5** o ingresar directamente por URL, el orquestador servía el layout cacheado y solo re-hidrataba el slot de contenido (`content`), omitiendo por completo la instanciación de `Menu` y la ejecución de su ciclo de vida (`postLoadUI()`).

A partir de la **v1.5.2**:
- Los layouts desacoplan sus slots como anclajes (placeholders) limpios.
- `ScreenLifecycleOrchestrator` re-hidrata dinámicamente tanto el contenido como el menú y cualquier slot activo en cada petición.
- `UIStateManager::clearClientSnapshots()` purga snapshots y modales huérfanos al cruzar la frontera de autenticación.

---

## Paso 1: Actualizar la versión del paquete

Actualiza la dependencia de Composer en tu aplicación consumidora:

```bash
composer update idei/usim
```

Si publicas assets manualmente en tu flujo:

```bash
php artisan vendor:publish --tag=usim-assets --force
```

---

## Paso 2: Desacoplar el montaje del menú en `MainLayout.php`

📂 **Ruta:** `app/UI/Layouts/MainLayout.php`

En el método `buildBaseUI()`, retira la llamada directa a `Screen::embedInto($menuClass, $mainMenuContainer)`. El layout debe limitarse a registrar el contenedor como slot y definir cuál es la pantalla activa mediante `setActiveScreen()`.

### Diff de modificación:

```diff
          $this->mainMenuContainer = $mainMenuContainer;
          $this->registerSlot('top_menu', $mainMenuContainer);
          $this->registerSlot('menu', $mainMenuContainer);

          $container->add($mainMenuContainer);

          /** @var UsimConfig $usimConfig */
          $usimConfig = app(UsimConfig::class);
          $hostScreen = self::getActiveHostScreen();
          $menuScreen = ($hostScreen !== null ? $hostScreen::getMenuScreen() : null)
              ?? static::getMenuScreen()
              ?? $usimConfig->defaultMenuScreen;

          /** @var class-string<Screen>|null $menuClass */
          $menuClass = is_subclass_of($menuScreen, Screen::class)
              ? $menuScreen
              : Screen::resolveScreenClassFromSlug($menuScreen);

          if ($menuClass !== null) {
              $this->setActiveScreen('top_menu', $menuClass);
-             Screen::embedInto($menuClass, $mainMenuContainer);
          }
```

> **Efecto:** El snapshot de `MainLayout` se serializa en caché como un armazón limpio, sin duplicar ni congelar componentes de pantallas hijas que poseen su propio ciclo de vida.

---

## Paso 3: Invalidar snapshots en `AuthSessionService.php`

📂 **Ruta:** `app/Services/Auth/AuthSessionService.php`

Invoca `UIStateManager::clearClientSnapshots()` en los dos puntos donde la identidad del usuario cambia: al iniciar sesión (`establishSession`) y al cerrarla (`logout`).

### Diff en `establishSession()`:

```diff
      if (\is_string($token) && $token !== '') {
          UIStateManager::setAuthToken($token);
      }

+     UIStateManager::clearClientSnapshots();

      Auth::login($user);
```

### Diff en `logout()`:

```diff
      Auth::guard('web')->logout();
      UIStateManager::setAuthToken(null);
      UIStateManager::setActiveUnit(null);
+     UIStateManager::clearClientSnapshots();

      if (function_exists('setPermissionsTeamId') && config('permission.teams')) {
          setPermissionsTeamId(null);
      }
```

> **Efecto:** Al hacer login o logout, cualquier snapshot de layout, pantallas cacheadas o modales abiertos en la sesión anterior se descartan de forma segura.

---

## Paso 4: Garantizar la población dinámica en `Menu.php`

📂 **Ruta:** `app/UI/Screens/Menu.php`

En el método `postLoadUI()`, asegúrate de que tanto el menú principal como el menú de usuario y selector de unidad se pueblen dinámicamente en **todos los casos**, tanto para usuarios autenticados como para invitados:

### Código resultante recomendado para `postLoadUI()`:

```php
protected function postLoadUI(): void
{
    $this->updateThemeButton();
    $this->updateLangMenu();

    if (Auth::check()) {
        $user = Auth::user();
        if ($user instanceof User) {
            $this->updateUserMenuTrigger($user);
        } else {
            $this->user_menu->trigger("⚙️");
        }
    } else {
        $this->user_menu->trigger("⚙️");
    }

    $this->updateUnitMenu();
    $this->populateMainMenu($this->main_menu);
    $this->populateUserMenu($this->user_menu);
}
```

> **Efecto:** En cualquier petición de renderizado o recarga `F5`, `Menu::postLoadUI()` evalúa la sesión real de Laravel (`Auth::check()`) y configura el trigger (`👤 Nombre` o `⚙️`) y las opciones del menú (*Perfil / Salir* o *Login / Registro*) de manera 100% determinista.

---

## Paso 5: Purgar la caché del entorno

Ejecuta los siguientes comandos para limpiar snapshots residuales en el almacenamiento de caché (Redis, archivo o base de datos) y compilar la configuración:

```bash
php artisan cache:clear
php artisan optimize:clear
```

---

## Verificación del Flujo

Para validar que la migración ha sido exitosa:

1. **Visita inicial como invitado:** Carga la pantalla inicial (`/home`). Verifica que el disparador del menú muestre `⚙️` y las opciones sean *"Iniciar sesión"* y *"Registrarse"*.
2. **Login SPA:** Inicia sesión con tus credenciales. El menú superior debe cambiar de inmediato a `👤 Tu Nombre`.
3. **Prueba de F5:** Presiona **F5** (o recarga forzada) en el navegador. La barra superior debe **mantener** el avatar/nombre `👤 Tu Nombre` y las opciones *"Perfil"* y *"Cerrar sesión"*.
4. **Prueba de Logout + F5:** Haz clic en *"Cerrar sesión"*. Tras la navegación o recarga con **F5**, el menú debe regresar de inmediato al estado de visitante `⚙️`.

