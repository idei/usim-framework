# 🏛️ Contratos, Inversión de Dependencias y Testing con Mocks (USIM Core)

> **Versión:** USIM Framework 0.7.x / 1.0.0 Architecture  
> **Ámbito:** `packages/idei/usim/src/Contracts` y `app/`

---

## 1. Motivación y Principios SOLID

Históricamente, el paquete core de USIM (`packages/idei/usim`) mantenía acoplamiento circular con la aplicación consumidora (`App\Models\User`, `App\Models\Device`, `App\Services\Units\...`). Esto generaba tres problemas graves:
1. **Falta de portabilidad:** El paquete no podía distribuirse a Composer sin exigir la presencia exacta de esos modelos y namespaces.
2. **Imposibilidad de testear en memoria:** Cualquier comprobación de permisos o emparejamiento requería instanciar modelos Eloquent reales, ejecutar migraciones de SQLite/MySQL y poblar tablas Spatie.
3. **Rigidez arquitectónica:** Violaba el **Principio de Inversión de Dependencias (DIP)**: los módulos de alto nivel (el motor de renderizado y las pantallas) dependían directamente de implementaciones concretas de bajo nivel.

### Principios Aplicados

* **DIP (Dependency Inversion Principle):** El núcleo de USIM ahora depende exclusivamente de abstracciones (`Idei\Usim\Contracts\*`). Los modelos y servicios de la aplicación host implementan estas interfaces.
* **ISP (Interface Segregation Principle):** En lugar de un contrato monolítico para el usuario, se crearon interfaces específicas y cohesivas (`UsimUserInterface`, `AuthorizableActorInterface`, `PairableActorInterface`).
* **LSP (Liskov Substitution Principle):** Cualquier actor o servicio que satisfaga los contratos puede sustituirse sin alterar el funcionamiento del motor de UI.

---

## 2. Mapa de Contratos Core (`Idei\Usim\Contracts`)

```mermaid
classDiagram
    class AuthorizableActorInterface {
        <<interface>>
        +getAuthIdentifier() mixed
    }
    class UsimUserInterface {
        <<interface>>
        +getEmail() ?string
        +getName() ?string
    }
    class PairableActorInterface {
        <<interface>>
        +isPaired() bool
        +getPairingIdentifier() string
        +getAssignedUnitSlug() ?string
    }
    class ScreenAuthorizerInterface {
        <<interface>>
        +can(AuthorizableActorInterface|null actor, string permission, mixed unit) bool
        +hasRole(AuthorizableActorInterface|null actor, string|array roles, mixed unit) bool
    }
    class UnitsServiceInterface {
        <<interface>>
        +sync(OutputStyle|null output) UnitSyncResult
    }
    class UnitContextResolverInterface {
        <<interface>>
        +resolveOperationalUnitSlug() ?string
        +isMultiUnitMode() bool
    }
    class UnitSyncResult {
        <<readonly DTO>>
        +bool skipped
        +string skipReason
        +int deletedCount
        +int upsertedCount
        +int hierarchyUpdatedCount
        +array generatedTranslationFiles
        +array errors
        +isSkipped() bool
        +isSuccess() bool
        +static skipped(string reason) static
    }
    class ComponentIdGeneratorInterface {
        <<interface>>
        +generateId(string prefix) string
        +pushContext(string context) void
        +popContext() ?string
        +reset() void
    }
    class UIStateRepositoryInterface {
        <<interface>>
        +getClientId() string
        +setClientId(string clientId) void
        +rememberTabToken(string tabToken) void
        +get(string key, mixed default) mixed
        +set(string key, mixed value) void
        +pushModal(string componentClass, array params) void
        +popModal() ?array
        +getModalStack() array
    }
    class UIDifferInterface {
        <<interface>>
        +diff(array oldTree, array newTree) array
    }

    AuthorizableActorInterface <|-- UsimUserInterface
    ScreenAuthorizerInterface ..> AuthorizableActorInterface
    UnitsServiceInterface ..> UnitSyncResult
```

### 2.1 `AuthorizableActorInterface` y `UsimUserInterface`
- **`AuthorizableActorInterface`**: Define a cualquier entidad que pueda autenticarse y ser evaluada por el motor de permisos (`getAuthIdentifier(): mixed`).
- **`UsimUserInterface`**: Extiende la anterior agregando los métodos necesarios para la presentación de identidad en la interfaz (`getEmail(): ?string`, `getName(): ?string`).
- **Implementación Host:** [`App\Models\User`](file:///workspaces/usim-framework/app/Models/User.php) implementa ambas interfaces.

### 2.2 `PairableActorInterface`
- Abstrae dispositivos físicos, tabletas o terminales de quiosco.
- Define `isPaired(): bool`, `getPairingIdentifier(): string`, y `getAssignedUnitSlug(): ?string`.
- **Implementación Host:** [`App\Models\Device`](file:///workspaces/usim-framework/app/Models/Device.php) implementa este contrato. El middleware [`PrepareUIContext`](file:///workspaces/usim-framework/packages/idei/usim/src/Http/Middleware/PrepareUIContext.php) y las pantallas ahora comprueban `instanceof PairableActorInterface` en lugar de una clase concreta.

### 2.3 `ScreenAuthorizerInterface`
- Abstrae el motor de autorización (como Spatie Permission o Gates de Laravel).
- Permite evaluar permisos y roles en screens sin acoplarse a Spatie ni requerir base de datos:
  ```php
  public function can(?AuthorizableActorInterface $actor, string $permission, mixed $unit = null): bool;
  public function hasRole(?AuthorizableActorInterface $actor, string|array $roles, mixed $unit = null): bool;
  ```
- **Implementación por Defecto:** [`Idei\Usim\Support\SpatieScreenAuthorizer`](file:///workspaces/usim-framework/packages/idei/usim/src/Support/SpatieScreenAuthorizer.php) se enlaza automáticamente en el Service Container cuando Spatie está disponible.

### 2.4 `UIStateRepositoryInterface` (Octane-Safe Scoped)
- Abstrae el repositorio de estado de cliente, tab tokens, cache de pantalla y pila de modales.
- **Implementación Concreta:** [`Idei\Usim\Support\UIStateRepository`](file:///workspaces/usim-framework/packages/idei/usim/src/Support/UIStateRepository.php).
- **Fachada Retrocompatible:** [`Idei\Usim\Support\UIStateManager`](file:///workspaces/usim-framework/packages/idei/usim/src/Support/UIStateManager.php) delega estáticamente en el repositorio resuelto del contenedor.

### 2.5 `ComponentIdGeneratorInterface` (Octane-Safe Scoped)
- Abstrae la generación de identificadores deterministas para componentes UI y el stack de contextos anidados.
- **Implementación Concreta:** [`Idei\Usim\Support\ComponentIdGenerator`](file:///workspaces/usim-framework/packages/idei/usim/src/Support/ComponentIdGenerator.php).
- **Fachada Retrocompatible:** [`Idei\Usim\Support\UIIdGenerator`](file:///workspaces/usim-framework/packages/idei/usim/src/Support/UIIdGenerator.php) delega estáticamente en la instancia scoped.

### 2.6 `UIDifferInterface`
- Abstrae el cálculo de diferencias de árbol de componentes para actualizaciones eficientes vía WebSocket/HTTP.
- **Implementación Concreta:** [`Idei\Usim\Support\ComponentDiffer`](file:///workspaces/usim-framework/packages/idei/usim/src/Support/ComponentDiffer.php).
- **Fachada Retrocompatible:** [`Idei\Usim\Support\UIDiffer`](file:///workspaces/usim-framework/packages/idei/usim/src/Support/UIDiffer.php) actúa como adaptador proxy.

### 2.7 DTO Inmutable: `UnitSyncResult`
- DTO `readonly` con constructor consistente (`@phpstan-consistent-constructor`) y tipos estrictos (`array<int, string>`).
- Provee métodos semánticos (`isSuccess()`, `isSkipped()`) y factory seguro (`UnitSyncResult::skipped(...)`).

---

## 3. Enlace en el Service Container y Laravel Octane Safety (`UsimServiceProvider`)

El proveedor de servicios registra las implementaciones por defecto con el ciclo de vida apropiado:

```php
// En packages/idei/usim/src/UsimServiceProvider.php

// 1. Singletons sin estado de solicitud
$this->app->singleton(
    \Idei\Usim\Contracts\ScreenAuthorizerInterface::class,
    \Idei\Usim\Support\SpatieScreenAuthorizer::class
);
$this->app->singleton(
    \Idei\Usim\Contracts\UIDifferInterface::class,
    \Idei\Usim\Support\ComponentDiffer::class
);

// 2. Scoped (Reinicio automático por cada solicitud HTTP / Octane Worker)
$this->app->scoped(
    \Idei\Usim\Contracts\ComponentIdGeneratorInterface::class,
    \Idei\Usim\Support\ComponentIdGenerator::class
);
$this->app->scoped(
    \Idei\Usim\Contracts\UIStateRepositoryInterface::class,
    \Idei\Usim\Support\UIStateRepository::class
);
```

> [!IMPORTANT]
> **Octane Safety:** El uso de `$this->app->scoped()` asegura que servicios con estado dependiente de la petición (como el ID del cliente o la pila de modales) sean destruidos y reiniciados en cada ciclo de trabajo de Laravel Octane / FrankenPHP / RoadRunner, evitando memory leaks y contaminación cruzada de sesiones.

---

## 4. Descomposición Modular de `Screen.php` en Concerns

Para cumplir con el **Principio de Responsabilidad Única (SRP)**, la clase monolítica [`Screen`](file:///workspaces/usim-framework/packages/idei/usim/src/Screen.php) fue reducida de 2,429 líneas a ~1,480 líneas delegando sus responsabilidades transversales en traits dedicados:

- **[`HandlesAuthorization`](file:///workspaces/usim-framework/packages/idei/usim/src/Concerns/HandlesAuthorization.php):** Gestión de permisos, roles requeridos, verificación de accesos y resolución del autorizador vía contrato.
- **[`HandlesModals`](file:///workspaces/usim-framework/packages/idei/usim/src/Concerns/HandlesModals.php):** Operaciones de apertura, cierre, anidamiento y restauración de la pila de diálogos y modales persistentes en estado.
- **[`HandlesNavigation`](file:///workspaces/usim-framework/packages/idei/usim/src/Concerns/HandlesNavigation.php):** Enrutamiento por slots (`showInto`), notificaciones toast, redirecciones forzadas y respuestas de aborto HTTP.

Todas las llamadas históricas (`$this->openModal(...)`, `$this->toast(...)`, `$this->authorize(...)`) permanecen intactas en las pantallas hijas sin ningún cambio destructivo.

---

## 5. Testing Unitario con Mocks (Sin Base de Datos)

### 5.1 Test Unitario con `ScreenAuthorizerInterface`

```php
use App\Models\User;
use App\UI\Screens\Admin\UsersManager;
use Idei\Usim\Contracts\ScreenAuthorizerInterface;
use Mockery;

it('authorizes admin user to access users manager via mock authorizer', function () {
    $user = new User(['id' => 1, 'email' => 'admin@example.com']);

    $authorizer = Mockery::mock(ScreenAuthorizerInterface::class);
    $authorizer->shouldReceive('can')
        ->with($user, 'users.manage', Mockery::any())
        ->once()
        ->andReturn(true);

    app()->instance(ScreenAuthorizerInterface::class, $authorizer);

    $screen = new UsersManager();
    expect($screen->authorize($user))->toBeTrue();
});
```

### 5.2 Test Unitario con `UIStateRepositoryInterface`

```php
use Idei\Usim\Contracts\UIStateRepositoryInterface;
use Idei\Usim\Support\UIStateManager;
use Mockery;

it('interacts with mock state repository through UIStateManager facade', function () {
    $mockRepo = Mockery::mock(UIStateRepositoryInterface::class);
    $mockRepo->shouldReceive('getClientId')->andReturn('mock-client-123');
    $mockRepo->shouldReceive('get')->with('my_key', 'default_val')->andReturn('mocked_val');

    app()->instance(UIStateRepositoryInterface::class, $mockRepo);

    expect(UIStateManager::getClientId())->toBe('mock-client-123');
    expect(UIStateManager::get('my_key', 'default_val'))->toBe('mocked_val');
});
```

*(Ver implementaciones de referencia en [`tests/Unit/ScreenAuthorizerMockTest.php`](file:///workspaces/usim-framework/tests/Unit/ScreenAuthorizerMockTest.php) y [`tests/Unit/UIStateRepositoryMockTest.php`](file:///workspaces/usim-framework/tests/Unit/UIStateRepositoryMockTest.php)).*

---

## 6. Estándar de Tipado PHPStan Nivel 9

Todos los contratos y DTOs cumplen con el nivel de máxima exigencia de **PHPStan (Nivel 9)**:
* **Cero `mixed` implícitos:** Todas las colecciones y arrays declaran tipos genéricos (`array<int, string>`, `list<string>`, etc.).
* **Constructores consistentes:** Clases base con métodos estáticos (`new static()`) están declaradas con `@phpstan-consistent-constructor` y constructor `final`.
* **Herencia `readonly` (PHP 8.2+):** Si una clase base es `readonly`, cualquier clase hija en la aplicación host debe ser explícitamente declarada como `readonly class`.

