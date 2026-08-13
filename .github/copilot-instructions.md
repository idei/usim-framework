# Instrucciones para Copilot en USIM Framework

## Contexto general del repositorio

- Este repositorio es un monorepo Laravel 11 + PHP 8.3+ que cumple dos funciones al mismo tiempo:
  1. la app consumidora o proyecto base (`/workspaces/usim-framework`), y
  2. el paquete reusable `idei/usim` desarrollado localmente en `packages/idei/usim/`.
- USIM (UI Services Implementation Model) es un framework backend-driven/server-driven UI.
- La UI se define en PHP con builders y pantallas; el frontend JavaScript es un renderizador generico que consume JSON inicial y diffs incrementales.
- La logica, validacion, autorizacion y estado deben vivir prioritariamente en backend.
- No asumas arquitectura React/Vue. El source of truth esta en el estado del servicio PHP y en los contratos JSON que USIM envia al cliente.
- Este repo usa `composer.json` con repositorio local tipo `path` apuntando a `packages/idei/usim`, asi que la app prueba el framework directamente desde el monorepo.

## Modelo mental obligatorio

- Piensa este workspace como dos capas:
  1. `packages/idei/usim/`: framework reusable, generico, publicable a Packagist.
  2. `app/`, `routes/`, `resources/`, `tests/`: app consumidora y banco de pruebas real del framework.
- Si el cambio es reusable, generico o afecta contratos/base classes/builders/renderizador/instalacion/stubs, revisa primero `packages/idei/usim/`.
- Si el cambio es especifico del producto actual, de una screen concreta o de servicios de dominio propios, revisa primero `app/` y `app/UI/`.
- Si el cambio toca ambos lados, explica el tradeoff y manten la separacion entre framework reusable y codigo especifico de la app.

## Estructura completa del proyecto base

Usa este mapa como contexto por defecto para cualquier chat:

```text
/workspaces/usim-framework
|- artisan
|- start.sh                         # flujo principal con RoadRunner
|- composer.json
|- package.json
|- README.md                        # vision general app + framework USIM
|- docs/
|  |- README.md                     # indice de documentacion del proyecto
|  |- framework/                    # docs tecnicas del framework en la app
|  |- api/                          # endpoints y contratos REST
|  |- deployment/                   # despliegue y produccion
|  |- tooling/                      # logs, colores de tests, utilidades
|  |- prompt-siguiente-sesion.md    # prompt de continuidad para refactor del framework
|  |- usim-laravel-package-refactoring.md
|- app/
|  |- Console/Commands/
|  |- Http/Controllers/
|  |- Http/Middleware/
|  |- Models/
|  |- Providers/
|  |- Services/
|  |  |- Auth/                      # AuthSessionService, LoginService, PasswordService, RegisterService
|  |  \- User/                      # UserService
|  \- UI/
|  |  |  |  |- component-registry.js
|  |  |  |  \- components/
|        |- Home.php
|        |- Menu.php
|        |- Admin/                  # UsersManager.php
|  |  |  |  \- components/
|- config/
|  \- usim.php               # configuracion/registro de pantallas USIM en la app
|- database/
|- public/
|- resources/
|  |- css/
|  |- js/
|  |- legal/
|  \- views/
|- routes/
|  |- web.php
|  |- api.php
|  \- console.php
|- scripts/
|  |- rebuild_dev.sh
|  |- release_usim_package          # flujo de release del paquete
|  \- utilidades de deploy/git/ssh
|- storage/
|- tests/
|  |- Feature/
|  |- Unit/
|  |- Support/
|  |- Traits/
|  |- SCREEN_TESTING_GUIDE.md
|  \- prompt.md                    # prompt base para generar tests de screens
\- packages/
	\- idei/
		\- usim/                     # paquete framework desarrollado en este monorepo
```

## Estructura completa del paquete `packages/idei/usim`

Cuando la tarea diga "framework", "componente reusable", "contrato JSON", "renderer", "installer" o "stubs", esta carpeta es el punto de partida:

```text
packages/idei/usim/
|- README.md
|- CHANGELOG.md
|- composer.json
|- LICENSE.md
|- .github/
|- config/
|  |- usim.php
|  \- users.php
|- docs/
|  |- SCREEN_TESTING_GUIDE.md
|  |- component_prompt.md
|  |- tests_prompt.md
|  |- usim_context_prompt.md
|  |- package-update-and-consumer-upgrade-guide.md
|  |- public-release-checklist.md
|  |- usim_json_contract.md
|  |- usim_json_contract_quickstart.md
|  |- usim_contract_v1_proposal.md
|  \- client_templates/
|- resources/
|  |- assets/
|  |  |- js/
|  |  |  |- ui-renderer.js
|  |  |  |- component-registry.js
|  |  |  \- components/
|  |  |- css/
|  |  |  |- ui-components.css
|  |  |  |- ui-theme-tokens.css
|  |  |  \- components/
|  |  \- images/
|  \- views/
|     \- app.blade.php             # shell que carga assets/renderizador del paquete
|- routes/
|  \- api.php
|- src/
|  |- UsimServiceProvider.php
|  |- Console/
|  |- Events/
|  |- Http/
|  |- Jobs/
|  |- Listeners/
|  |- Notifications/
|  |- Services/
|  |  |- Screen.php
|  |  |- UI.php
|  |  |- UIChangesCollector.php
|  |  |- Contracts/
|  |  |- DataTable/
|  |  |- Enums/
|  |  |- Modals/
|  |  |- Support/
|  |  |- Upload/
|  |  \- Components/
|  |     |- BaseUIBuilder.php
|  |     |- UIComponent.php
|  |     |- Container.php
|  |     |- Label.php
|  |     |- Button.php
|  |     |- Input.php
|  |     |- Select.php
|  |     |- Checkbox.php
|  |     |- Form.php
|  |     |- Card.php
|  |     |- Table.php y builders relacionados
|  |     |- Uploader.php
|  |     |- Calendar.php
|  |     |- Carousel.php
|  |     \- MenuDropdown.php
|  |- Support/
|  \- Traits/
|- stubs/
|  |- config/
|  |- controllers/
|  |- migrations/
|  |- models/
|  |- providers/
|  |- routes/
|  |- seeders/
|  |- services/
|  |  |- Auth/
|  |  \- User/
|  |- screens/
|  |  |- Home.php.stub
|  |  |- Menu.php.stub
|  |  |- Admin/UsersManager.php.stub
|  |  \- Auth/                     # Login, Profile, ForgotPassword, ResetPassword, EmailVerified
|  |- tests/
|  |  |- Feature/
|  |  |- Support/
|  |  |- Traits/
|  |  |- Pest.php.stub
|  |  \- TestCase.php.stub
|  \- views/
|- tests/
|  \- test-install-command.sh
\- routes/
```

## Fuentes de verdad que debes revisar segun la tarea

Antes de proponer arquitectura nueva o editar archivos, usa estas fuentes como contexto funcional:

- `README.md` del repo raiz: presenta la vision general del proyecto/app y resume beneficios, componentes disponibles y stack.
- `docs/README.md`: indice de la documentacion vigente de framework, API, deployment y tooling.
- `packages/idei/usim/README.md`: fuente principal del paquete reusable. Describe conceptos core, componentes, lifecycle, autorizacion, menu, helpers, modals, tablas, uploads, auth, tests, comandos, rutas y directorios.
- `packages/idei/usim/CHANGELOG.md`: fuente de cambios recientes del framework; si cambias API, comportamiento, stubs, renderer o contratos, normalmente debes actualizarlo.
- `docs/usim-laravel-package-refactoring.md`: documento de contexto historico/arquitectonico para la extraccion y productizacion del framework.

## Prompts y documentos operativos que debes usar como contexto

Estos archivos no son decorativos; describen flujos de trabajo esperados y deben influir en las respuestas:

- `tests/prompt.md`: prompt base para generar tests de screens de la app con Pest + `uiScenario(...)`.
- `docs/prompt-siguiente-sesion.md`: prompt de continuidad para sesiones enfocadas en refactor/seguridad del framework; remite a `docs/usim-laravel-package-refactoring.md`.
- `packages/idei/usim/docs/usim_context_prompt.md`: resumen reusable del modelo mental de USIM; util para arrancar cualquier chat tecnico sobre el framework.
- `packages/idei/usim/docs/component_prompt.md`: checklist end-to-end para agregar un componente nuevo al framework y probarlo en app + paquete.
- `packages/idei/usim/docs/tests_prompt.md`: prompt base para generar tests de screens USIM en el estilo esperado.
- `packages/idei/usim/docs/package-update-and-consumer-upgrade-guide.md`: flujo recomendado para cambiar, validar, publicar y actualizar el paquete en la app consumidora y en apps externas.

## Estado reciente del framework que debes tener presente

Segun `packages/idei/usim/CHANGELOG.md` y `packages/idei/usim/README.md`, el contexto reciente del paquete incluye:

- Version actual documentada: `0.7.0`.
- `Container` tiene API de apariencia con `appearance()`, `card()` y `plain()`.
- Calendar y Carousel usan CSS theme tokens para consistencia light/dark.
- `Screen` persiste el estado final luego de `postLoadUI()`, incluyendo recargas con `?reset=true`.
- Los checkboxes sincronizan correctamente su estado incremental desde el backend.
- El contrato de storage cambio: `store_*` ahora se serializa plain por defecto y solo valores sensibles deben usar sufijo `_crypt`.
- El framework soporta cambio de tema y usa `ui-theme-tokens.css` para light/dark mode.
- Soporte de **Headless Mode** con `USIM_HEADLESS_MODE=true`: el catch-all web devuelve `406` y los clientes deben usar `GET /api/ui/{screen}` + `POST /api/ui-event`.
- Soporte de **Agent Context** por Screen mediante `getAgentContext(): array`: si no esta vacio, se serializa en `agent_context` dentro del payload JSON para clientes IA/headless.

## Reglas para decidir donde editar

- Si agregas o cambias una pantalla de negocio, menu de la app, modal de la app o un servicio de dominio, edita primero `app/`.
- Si agregas o cambias un componente reusable de UI, builders, renderer JS, contratos JSON, comandos artisan, provider, middleware, rutas del framework, instalador o stubs, edita primero `packages/idei/usim/`.
- Si una feature del framework debe verse en la app actual, ademas del paquete considera demo screen, entrada de menu, assets publicados y tests de regresion en la app.
- No dupliques validaciones en frontend y backend salvo pedido explicito.
- Preserva backward compatibility en payloads y en meta keys reservadas: `storage`, `action`, `redirect`, `toast`, `abort`, `modal`, `update_modal`, `clear_uploaders`, `set_uploader_existing_file`.
- Si cambias docs de onboarding o guia visual de la app, considera actualizar tambien `resources/views/welcome-usim.blade.php` y sus claves i18n `welcome.*` en `database/translations/{en,es}.json`.

## Convenciones tecnicas USIM

- Usa `Screen` para screens y `UI::*` para crear componentes.
- Los handlers siguen la convencion `on<ActionName>` en PascalCase a partir del action snake_case.
- Piensa siempre en IDs deterministas, diffs incrementales y reconstruccion correcta del estado.
- Las propiedades `store_*` son persistidas entre requests; usa `_crypt` solo para valores sensibles.
- Prioriza implementaciones en PHP alineadas con Laravel y con la arquitectura server-driven del framework.
- Evita logica frontend ad hoc si el backend puede resolverlo de forma clara.
- En modo headless, asume integracion por contrato API: carga inicial con `GET /api/ui/{screen}`, eventos con `POST /api/ui-event`, y continuidad de estado reenviando `X-USIM-Storage`.

## Definicion operacional de "Screen" (obligatoria para el chat)

Cuando en este repo se hable de "Screen", debes interpretarlo siempre con este significado:

- Una `Screen` es una clase PHP que representa una pagina completa (no un componente aislado ni una vista pasiva).
- Su contrato base es extender `Screen` e implementar `buildBaseUI(Container $container, ...$params): void`.
- La `Screen` concentra interfaz + estado + reglas de interaccion en backend; el frontend solo renderiza el contrato JSON.
- El estado vive del lado servidor y se restaura entre requests; las propiedades `store_*` persisten entre eventos.
- El flujo reactivo esperado es: restaurar estado -> ejecutar handler -> calcular diff -> enviar solo delta al cliente.
- Los handlers se resuelven por convencion: `action` en snake_case -> metodo `onPascalCase(array $params)`.
- La autorizacion y acceso pertenecen a la `Screen` (`authorize`, `checkAccess`) y no al frontend.
- La ruta de una `Screen` se deriva por convencion del namespace/clase (`getRoutePath`), salvo personalizaciones explicitas.
- La metadata de navegacion tambien pertenece a la `Screen`: `getMenuLabel()`, `getMenuIcon()` y `getRoutePath()`.
- USIM no registra una ruta Laravel individual por cada `Screen`; usa una ruta catch-all y un loader API que traducen `URL <-> clase PHP` por convencion.

Implicancias para tus respuestas y cambios:

- No propongas mover logica de negocio de una `Screen` al cliente salvo pedido explicito.
- No trates una `Screen` como "template HTML" tradicional: es un servicio UI stateful.
- Si el usuario pide "agregar una screen", piensa en ciclo de vida, estado `store_*`, handlers, autorizacion y testing, no solo en markup.
- Si describes arquitectura, deja explicito que la fuente de verdad es backend + contrato JSON incremental.

## Cuando crees una Screen

Cuando el usuario te pida "crear una Screen" o "agregar una screen", debes entender que se refiere a crear una clase PHP que herede de `Screen` (no solo una vista pasiva). Sigue estos patrones observados en [UsersManager.php](app/UI/Screens/Admin/UsersManager.php) y [TranslateManager.php](app/UI/Screens/Admin/TranslateManager.php):

### Estructura base y ciclo de vida

1. **Namespace y clase**: ubica la Screen en `App\UI\Screens\{Category}\{ScreenName}` (e.g., `App\UI\Screens\Admin\UsersManager`).
2. **Heredar de Screen**: `extends Screen` e implementar obligatoriamente:
   - `buildBaseUI(Container $container, ...$params): void` - construir la interfaz inicial
   - `getMenuLabel(): string` - etiqueta para el menu de navegacion
   - `getMenuIcon(): ?string` - emoji o icono para el menu (opcional)
   - `authorize(): bool` - control de acceso basado en roles (opcional pero recomendado)
3. **Propiedades protegidas**: declara como `protected` los componentes que necesites persistir entre requests (e.g., `protected Table $users_table`, `protected Input $search_field`).
4. **Handlers**: implementa metodos en convención `on<ActionName>` (e.g., `onAddUserClicked`, `onSearchUsers`, `onSubmitRegister`). Reciben `array $params` con datos del evento frontend.
5. **postLoadUI()**: si necesitas sincronizar estado restaurado con valores de componentes, implementa este metodo.

### Control de roles

Si la Screen requiere un rol especifico, implementa el metodo `authorize()`:

```php
public static function authorize(): bool
{
    return self::requireRole('admin');
}
```

Usa `requireRole()` para un rol unico, o `requireAnyRole()` para multiples roles. El framework lanzará `AuthorizationException` automaticamente si el usuario no esta autorizado.

### Internacionalizacion (i18n)

Las claves de traduccion siguen un patrón jerarquico por pantalla:

**Estructura de directorios**: 
- `lang/{locale}/screen/{category}/{screen_name}.php`

**Ejemplos reales**:
- `lang/en/screen/admin/dashboard.php` → claves para `App\UI\Screens\Admin\UsersManager`
- `lang/en/screen/admin/translate_manager.php` → claves para `App\UI\Screens\Admin\TranslateManager`

**Estructura de archivos de traduccion** (retorna array asociativo):
```php
<?php
return [
    'menu_label' => 'Users',
    'add_user' => 'Add User',
    'search_placeholder' => 'Search users...',
    'table' => [
        'email' => 'Email',
        'name' => 'Name',
        'role' => 'Role',
    ],
    'edit' => [
        'title' => 'Edit User',
        'name_label' => 'Full Name',
        'email_label' => 'Email',
    ],
    'delete' => [
        'title' => 'Delete User',
        'confirm' => 'Are you sure you want to delete user "{user_name}"?',
        'success' => 'User deleted successfully',
    ],
    'errors' => [
        'user_not_found' => 'User not found',
        'update_failed' => 'Update failed',
    ],
];
```

**Uso en el codigo**:
- `t('screen.admin.dashboard.menu_label')` → "Users"
- `t('screen.admin.dashboard.table.email')` → "Email"
- Usar `t(..., ['placeholder' => '...'])` para interpolacion

**Generacion**: cuando crees una Screen nueva, debes:
1. Crear `lang/en/screen/{category}/{screen_name}.php` con todas las claves
2. Crear `lang/es/screen/{category}/{screen_name}.php` con las claves traducidas al español
3. Usar `t('screen.{category}.{screen_name}...')` consistentemente en el codigo

### Ciclo completo

Cuando se cree una Screen, considerar:
1. Clase Screen con `buildBaseUI()`, `authorize()`, handlers (`on*` methods).
2. Propiedades persistidas (`store_*` si el estado debe sobrevivir entre requests).
3. **Solo si explícitamente lo solicito**: Archivo de traducciones en `lang/{locale}/screen/{categoria}/{nombre}.php`.
4. Entrada en menu si aplica (actualizar `app/UI/Screens/Menu.php`).
5. Tests Pest siguiendo `tests/SCREEN_TESTING_GUIDE.md` y `tests/prompt.md`.

**Nota importante sobre i18n**: Por defecto, crea la clase Screen sin archivos de traducción. Solo genera archivos i18n (`lang/en/screen/...` y `lang/es/screen/...`) si el usuario explícitamente lo solicita o dice "con i18n" o "con traducciones". Cuando no se pida i18n, usa strings literales en español o en el idioma que tenga sentido en el contexto.

## Cuando el usuario diga "crea un componente que..."

Interpreta esta solicitud como "crear o extender una capacidad reusable del framework", no como agregar markup suelto.

Esta instrucción SOLO aplica si el workspace actual tiene acceso editable al framework `idei/usim`, por ejemplo dentro de este monorepo con `packages/idei/usim/` presente.

Antes de hacer cualquier cambio, verifica explícitamente si estás en uno de estos contextos:

### Contexto 1: monorepo o paquete editable disponible

Se cumple si puedes editar directamente `packages/idei/usim/` en el workspace actual.

Solo en este contexto sí debes crear o modificar componentes reusables del framework.

### Contexto 2: app consumidora sin acceso editable al paquete

Se cumple si la aplicación solo consume `idei/usim` como dependencia y el código fuente editable del paquete no está disponible en el workspace.

En este contexto, la petición "crea un componente" NO debe ejecutarse como si pudieras modificar el framework.

Debes hacer esto en su lugar:

1. explicar brevemente que un componente reusable de USIM pertenece al paquete `idei/usim` y no puede implementarse correctamente solo desde la app consumidora si no existe acceso editable al paquete
2. no inventar archivos parciales ni hacks frontend locales para simular un componente del framework sin tocar el paquete
3. ofrecer una de estas alternativas, según el pedido:
    - preparar el cambio para el repositorio del framework si el usuario abre ese workspace
    - proponer una solución a nivel Screen usando componentes ya existentes del framework
    - documentar exactamente qué habría que agregar en `idei/usim` para implementarlo bien
4. si el usuario pidió algo que sí puede resolverse solo en la app consumidora, limitarte a la Screen/app y dejar claro que NO estás creando un componente reusable del framework

Regla de oro: "crear componente" significa extender el framework reusable. Si no puedes editar el framework, no debes fingir que la tarea quedó resuelta con cambios locales de app que no agregan realmente el componente a USIM.

Antes de editar, determina cuál de estos dos casos aplica:

### Caso A: el tipo backend ya existe y solo falta frontend nuevo o refactor frontend

Ejemplos:

- "crea un componente frontend para renderizar mejor el button"
- "modulariza el componente table"
- "agrega estilos propios al uploader"

En este caso, NO inventes un builder PHP nuevo si el `type` ya existe en el contrato JSON.

Debes hacer esto:

1. **Confirmar el tipo backend existente**
    - revisar el builder/component PHP ya existente en `packages/idei/usim/src/Services/Components/`
    - verificar qué `type` serializa ese componente
2. **Crear o editar el módulo frontend correcto**
    - JS en `packages/idei/usim/resources/assets/js/components/<tipo>/index.js`
    - CSS en `packages/idei/usim/resources/assets/css/components/<tipo>/index.css` solo si realmente hace falta
3. **Implementar el componente con arquitectura actual**
    - heredar de `UIComponent` definida en `packages/idei/usim/resources/assets/js/ui-renderer.js`
    - implementar `render()`
    - implementar `update(newConfig)` si el componente participa en updates incrementales o si el renderer lo espera
    - reutilizar helpers compartidos en `packages/idei/usim/resources/assets/js/components/shared/` cuando aplique
4. **Registrar el tipo en el registry modular**
    - usar `window.USIM_COMPONENTS.register('<tipo>', factory, metadata)`
    - si reemplazas una implementación previa del mismo tipo, hacer antes `unregister('<tipo>')`
    - NO volver a meter un `switch` ni clases concretas nuevas dentro de `ui-renderer.js` salvo que el cambio sea realmente de infraestructura base
5. **Cargar assets en la shell del paquete**
    - agregar el `script` y/o `link` en `packages/idei/usim/resources/views/app.blade.php`
    - respetar el orden actual: registry -> renderer -> shared helpers -> componentes
6. **Integrar en app/demo si hace falta mostrarlo**
    - crear o actualizar demo screen en `app/UI/Screens/Demo/`
    - agregar entrada en `app/UI/Screens/Menu.php` solo si el usuario quiere exponerla navegablemente
7. **Validar y publicar**
    - `node --check` al archivo JS tocado y, si cambias base, también a `ui-renderer.js`
    - `php artisan vendor:publish --tag=usim-assets --force`
    - test puntual de la screen/demo afectada si existe

### Caso B: el usuario realmente está pidiendo un componente nuevo end-to-end

Ejemplos:

- "crea un componente carousel-like llamado timeline"
- "crea un nuevo componente badge con builder PHP y renderer frontend"
- "agrega un componente reusable al framework"

En este caso debes tocar backend y frontend del paquete.

#### Backend del paquete

1. crear builder/componente PHP en `packages/idei/usim/src/Services/Components/`
2. definir API fluida consistente con los builders existentes
3. asegurar que el componente serialice un `type` estable y determinista
4. registrar factory method en `packages/idei/usim/src/Services/UI.php`
5. registrar mapping del tipo y reconstrucción en `packages/idei/usim/src/Services/Screen.php` si el framework lo necesita para hydratar/restaurar componentes
6. revisar impacto en estado persistido, diffs incrementales y handlers si el componente envía eventos

#### Frontend del paquete

1. crear JS en `packages/idei/usim/resources/assets/js/components/<tipo>/index.js`
2. extender `UIComponent`
3. implementar `render()` y `update(newConfig)` cuando corresponda
4. si el componente envía eventos al backend, reutilizar `packages/idei/usim/resources/assets/js/components/shared/ui-event.js`
5. si comparte render de contenido con otros componentes, extraer o reutilizar helpers en `shared/`
6. crear CSS en `packages/idei/usim/resources/assets/css/components/<tipo>/index.css` solo si el estilo no cabe bien en `ui-components.css` o tokens base
7. registrar el tipo con `window.USIM_COMPONENTS.register(...)`
8. cargar assets en `packages/idei/usim/resources/views/app.blade.php`

#### Integración en la app consumidora

1. crear demo screen real en `app/UI/Screens/Demo/` para probar el componente desde una screen backend-driven
2. agregar entrada en `app/UI/Screens/Menu.php` si sirve para exploración manual
3. si el componente necesita datos o comportamiento de dominio, resolverlos desde la screen o servicios PHP, no desde JS ad hoc

#### Testing mínimo esperado

1. crear o actualizar tests Pest con `uiScenario(...)`
2. cubrir al menos:
    - contrato inicial del componente
    - evento principal, si existe
    - delta/update incremental
    - edge cases visibles del componente
3. si hay notificaciones o efectos Laravel, usar `Notification::fake()` o fakes equivalentes

#### Validación/ejecución

1. `composer dump-autoload`
2. `php artisan usim:discover`
3. `php artisan vendor:publish --tag=usim-assets --force`
4. `php artisan test ...` con foco en la screen/demo o tests del componente
5. si cambias documentación pública, actualizar también `packages/idei/usim/README.md` y `packages/idei/usim/CHANGELOG.md`

## Reglas obligatorias al crear componentes

- Primero verifica si `packages/idei/usim/` existe y es editable en el workspace. Si no lo es, no ejecutes la creación real del componente reusable.
- No pongas lógica específica de componentes nuevos dentro de `ui-renderer.js` si puede vivir en `js/components/<tipo>/index.js`.
- No vuelvas a crear assets legacy top-level como `resources/assets/js/<algo>-component.js` o `resources/assets/css/<algo>-component.css`.
- Usa siempre la estructura modular `components/<tipo>/index.js` y `components/<tipo>/index.css`.
- Si el problema es reusable del framework, empieza en `packages/idei/usim/`, no en `app/`.
- Si el usuario solo pidió el componente, normalmente debes entregar también la parte backend necesaria para que el framework pueda emitir ese `type`, salvo que confirmes que ese `type` ya existe.
- Si cambias assets del paquete, recuerda que sin `vendor:publish` la app consumidora local puede seguir usando archivos viejos en `public/vendor/idei/usim`.
- Si el componente requiere una demo o prueba manual clara, prefiere una Screen demo en lugar de inventar HTML aislado.
- En una app consumidora sin acceso al paquete, no presentes como "componente nuevo" una composición local de componentes existentes salvo que el usuario pida explícitamente una solución app-specific.

## Fuentes que debes usar cuando te pidan crear un componente

Lee y usa como contexto, según corresponda:

- `packages/idei/usim/docs/component_prompt.md`
- `docs/framework/FRONTEND_COMPONENTS_GUIDE.md`
- `packages/idei/usim/README.md`
- `packages/idei/usim/CHANGELOG.md`

Si las instrucciones de `component_prompt.md` contradicen la arquitectura modular actual del frontend, prevalece la arquitectura real del repo: registry modular + `js/components/*/index.js` + carga explícita en `app.blade.php`.

## Cuando actualices el framework o prepares release

Sigue `packages/idei/usim/docs/package-update-and-consumer-upgrade-guide.md`:

- Valida el paquete con `composer validate --strict --no-check-publish`.
- Valida PHP del paquete (`src`, `config`, `routes`) con `php -l`.
- Si cambias API, instalacion, stubs o comportamiento visible, actualiza `packages/idei/usim/README.md` y `packages/idei/usim/CHANGELOG.md`.
- Para refrescar la app consumidora local, usa `composer update idei/usim`, `php artisan optimize:clear` y `php artisan package:discover --ansi`.
- Si cambias assets del paquete, recuerda publicarlos en la app.
- No asumas que publicar el paquete basta; verifica tambien el impacto en esta app monorepo.

## Publicar el paquete ("publica el paquete")

Cuando el usuario diga "publica el paquete" o una frase equivalente, sigue estos pasos **en orden** sin pedir confirmacion intermedia:

1. **Leer el CHANGELOG** (`packages/idei/usim/CHANGELOG.md`) para identificar:
   - La ultima version publicada (ultimo encabezado `## [X.Y.Z]`).
   - El contenido de la seccion `## [Unreleased]` para clasificar los cambios.

2. **Calcular la nueva version** con Semantic Versioning (pre-1.0: `0.x`):
   - **Patch** (`0.Y.Z+1`): solo fixes, sin nuevas features ni breaking changes.
   - **Minor** (`0.Y+1.0`): nuevas features, breaking changes o eliminacion de API (en `0.x` el minor absorbe breaking changes).
   - Regla practica: si `[Unreleased]` incluye `### Added` o `### Changed` con eliminacion/renombrado de clases/metodos, sube minor. Si solo tiene `### Fixed`, sube patch.

3. **Confirmar la version calculada** brevemente al usuario antes de ejecutar el script (una sola linea: "Version calculada: vX.Y.Z — ejecutando release...").

4. **Ejecutar el script de release**:
   ```bash
   bash scripts/release_usim_package -v vX.Y.Z -f
   ```
   - Usa `-f` siempre para forzar limpieza del split branch previo.
   - No agregar `-p` salvo que el usuario lo pida explicitamente.

5. **Reportar el resultado**: indicar si el push y el tag tuvieron exito, y recordar al usuario que puede triggerear Packagist manualmente o con `-p` si tiene las variables `PACKAGIST_USERNAME` / `PACKAGIST_TOKEN` exportadas.

6. **Post-release obligatorio**: actualizar la seccion `## [Unreleased]` del CHANGELOG para moverla a `## [vX.Y.Z] - YYYY-MM-DD` con la fecha actual, y dejar una nueva seccion `## [Unreleased]` vacia encima. Hacer commit con mensaje `chore: mark vX.Y.Z as released in CHANGELOG`.

## Testing y validacion

- Para tests de UI/Screen en la app, sigue `tests/SCREEN_TESTING_GUIDE.md` y `tests/prompt.md`.
- Para tareas del paquete, usa tambien `packages/idei/usim/docs/SCREEN_TESTING_GUIDE.md` y `packages/idei/usim/docs/tests_prompt.md` si corresponde.
- El patron preferido es `uiScenario(...)->component(...)->expect(...)`.
- Evita parseo raw del payload salvo necesidad real.
- Si hay notificaciones, usa `Notification::fake()`.
- Si la tarea afecta auth, menus, modales, uploads o renderer, intenta validar con tests o al menos con comandos de descubrimiento/publicacion relevantes.

## Ejecucion local y entorno

- Para levantar el proyecto, ten presente que el flujo principal usa RoadRunner mediante `./start.sh`; no asumas `php artisan serve` como flujo principal.
- El frontend del framework depende de assets del paquete publicados/servidos; si cambias JS/CSS del paquete, considera el paso de publicacion de assets.
- Las pantallas de la app se descubren con el flujo USIM y conviven con las pantallas y stubs publicados por el paquete.

## Estilo de ayuda esperado

- Responde con foco practico y orientado a cambios reales en el codigo.
- Explica tradeoffs cuando una solucion pueda tocar framework y aplicacion al mismo tiempo.
- Si una solicitud contradice la arquitectura backend-driven de USIM, senalalo y propone una alternativa alineada con el proyecto.
- Cuando falte contexto, prioriza leer README, CHANGELOG, docs y prompts relevantes antes de inventar arquitectura.

## General

Al final de cada respuesta dime "Ready!" para que sepa que terminaste de responder.
