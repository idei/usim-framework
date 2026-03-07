# Guia de Tests de Screens (USIM + Pest)

Este documento explica como escribir tests de screens de UI para este proyecto.
El objetivo es que cualquier persona del equipo pueda crear tests rapidos, legibles y estables.

## 1) Idea general

En este proyecto, los tests de screens se escriben sobre el contrato JSON de USIM.
En vez de testear HTML, se testea:

- estructura de componentes
- acciones/eventos
- cambios de estado (diffs)
- contratos meta (`toast`, `redirect`, `abort`, `modal`, etc.)

La herramienta principal es `uiScenario(...)`, que simula el flujo del frontend.

## 2) Estructura recomendada de un test

Patron base:

```php
it('does something in screen', function () {
    $ui = uiScenario($this, SomeScreen::class, ['reset' => true]);

    $ui->component('some_component')->expect('type')->toBe('button');

    $response = $ui->click('some_component', [
        'some_param' => 'value',
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');

    $ui->assertNoIssues();
});
```

## 3) Funciones y helpers disponibles

### 3.1 Flujo principal (`tests/Support/UiScenario.php`)

#### `uiScenario(TestCase $test, string $screenClass, array $query = []): UiScenario`
Crea un escenario UI, carga la screen via `/api/ui/...` y mantiene estado entre eventos.

Parametros frecuentes:
- `['reset' => true]`: fuerza estado limpio inicial.
- query extra: se pasa como query string a la screen.

#### Metodos de `UiScenario`

- `component(string $name): UiComponentRef`
  - Obtiene referencia por nombre de componente.
- `componentData(string $name): array`
  - Devuelve el array crudo del componente.
- `click(string $componentName, array $parameters = []): TestResponse`
  - Dispara evento `click` usando la `action` del componente.
- `action(string $componentName, string $action, array $parameters = [], bool $includeStorageHeader = true): TestResponse`
  - Dispara evento `action` con accion explicita.
- `change(string $componentName, string $action, array $parameters = [], bool $includeStorageHeader = true): TestResponse`
  - Simula evento `change`.
- `input(string $componentName, string $action, array $parameters = []): TestResponse`
  - Simula evento `input`.
- `timeout(int $callerServiceId, string $action, array $parameters = []): TestResponse`
  - Simula timeout de modal/dialog.
- `opaqueUsim(): string`
  - Devuelve storage USIM actual (debug/avanzado).
- `issues(): array`
  - Devuelve problemas detectados por el renderer en memoria.
- `assertNoIssues(): self`
  - Debe quedar al final de tests de screen para validar consistencia de estado.

#### Metodos de `UiComponentRef`

- `expect(string $field)`
  - Assertion directa sobre una propiedad del componente.
  - Ejemplo: `$ui->component('btn_save')->expect('action')->toBe('save');`
- `data(): array`
  - Devuelve todo el payload del componente.
- `click(array $parameters = []): self`
  - Shortcut para clickear esa referencia y assert `200`.

### 3.2 Helpers de payload (`tests/Support/UiPayloadHelpers.php`)

- `uiFind(mixed $value, callable $predicate): mixed`
  - DFS recursivo sobre estructuras anidadas.
- `uiPayloadContainsAction(mixed $value, string $action): bool`
- `uiPayloadContainsText(mixed $value, string $needle): bool`
- `uiPayloadContainsStyle(mixed $value, string $style): bool`
- `calendarEventsContainDate(array $events, string $date): bool`
- `calendarEventsContainTitle(array $events, string $fragment): bool`
- `hasModalComponents(array $payload): bool`
- `firstTimeoutModalComponent(array $payload): ?array`
- `modalPayloadHasNamedComponent(array $payload, string $name): bool`
- `cardHasAction(array $cardComponent, string $action): bool`
- `menuItemsContainLabel(array $items, string $label): bool`

Compatibilidad legacy:
- `payloadContainsAction`, `payloadContainsText`
- `demoUiPayloadContainsText`, `demoUiPayloadContainsStyle`

### 3.3 Helpers de screen raw (`tests/Support/UiScreenTestHelpers.php`)

- `screenApiUrl(string $screenClass, array $query = []): string`
- `getScreenJson(TestCase $test, string $screenClass, array $query = []): TestResponse`
- `findComponentByName(array $payload, string $name): ?array`
- `firstUiComponentFromPayload(array $payload): ?array`
- `serviceRootComponentId(array $payload): int`

Se usan para tests de contrato mas "raw" o debug puntual.
Para tests funcionales normales preferir `uiScenario`.

### 3.4 Helper de auth para tests (`tests/TestCase.php`)

- `loginAs(string $role): array{user: User, response: TestResponse, config: array}`

Roles soportados:
- `admin`
- `user`

Util para tests que necesitan estado autenticado de forma consistente.

## 4) Convenciones del repo

Para mantener homogeneidad:

- Preferir `uiScenario + component()` sobre parseos manuales de payload.
- Mantener nombres de tests descriptivos y centrados en comportamiento.
- Al final de cada test de screen, usar `->assertNoIssues()`.
- Para emails/notificaciones usar `Notification::fake()` (no depender de Mailpit).
- Para links sensibles (verificacion/reset) parsear query del URL y testear con esos parametros.

## 5) Ejemplos rapidos

### 5.1 Verificar carga inicial

```php
$ui = uiScenario($this, Login::class, ['reset' => true]);
$ui->component('login_email')->expect('type')->toBe('input');
$ui->component('btn_submit_login')->expect('action')->toBe('submit_login');
$ui->assertNoIssues();
```

### 5.2 Click + contrato de respuesta

```php
$response = $ui->click('btn_submit', [
    'name' => 'Alice',
]);

$response->assertOk();
expect($response->json('toast.type'))->toBe('success');
```

### 5.3 Flujo con notificacion y link

```php
Notification::fake();

// ... ejecutar accion que envia email ...

Notification::assertSentTo($user, SomeNotification::class, function ($notification) use ($user, &$url) {
    $mail = $notification->toMail($user);
    $url = $mail->viewData['someUrl'] ?? null;
    return is_string($url) && $url !== '';
});
```

## 6) Checklist antes de cerrar un test

- El nombre del test explica el comportamiento esperado.
- Se valida al menos un efecto funcional (UI o dominio).
- Si hay evento UI, se valida el `TestResponse` (`assertOk`, `toast`, `redirect`, etc.).
- Se valida estado final (componente/DB/notificacion segun corresponda).
- Termina con `$ui->assertNoIssues();` cuando aplique.
- Se ejecuto al menos el archivo del test localmente.

## 7) Comandos utiles

- Ejecutar un archivo:
  - `php artisan test tests/Feature/NombreDelTest.php`
- Ejecutar toda la suite:
  - `php artisan test`
