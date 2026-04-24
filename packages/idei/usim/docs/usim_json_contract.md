# USIM JSON Contract

Este documento define el contrato JSON observado en los archivos de soporte y testing de USIM:

- `tests/Support/UiScenario.php`
- `tests/Support/UiScreenTestHelpers.php`
- `tests/Support/UiPayloadHelpers.php`
- `tests/Pest.php`
- `tests/TestCase.php`
- `tests/SCREEN_TESTING_GUIDE.md`

Objetivo: permitir construir clientes frontend alternativos (mobile, iOS, SmartTV, desktop o web) que consuman USIM sin depender del renderer web actual.

## 1. Alcance y supuestos

- USIM es server-driven UI: el backend define estructura, estado y cambios de UI.
- El cliente renderiza componentes desde JSON y envia eventos al backend.
- El backend puede responder con snapshot completo (pantalla inicial) o delta parcial (cambios).
- Ademas de componentes, la respuesta puede incluir contratos meta (`toast`, `redirect`, `abort`, `modal`, etc.).
- Este documento describe el contrato inferido desde los helpers/tests listados arriba.

## 2. Endpoints y flujo general

## 2.1 Carga de pantalla

Request:

```http
GET /api/ui{screen_route_path}?{query_params}
```

Notas:

- `screen_route_path` proviene de `ScreenClass::getRoutePath()` (backend).
- `query_params` puede incluir `reset=true` para reiniciar estado inicial de pantalla.

Response esperada:

- HTTP `200 OK` con JSON.
- El JSON puede contener componentes y meta-keys.

## 2.2 Envio de eventos UI

Request:

```http
POST /api/ui-event
Content-Type: application/json
```

Body base:

```json
{
  "component_id": 123,
  "event": "click",
  "action": "submit_login",
  "parameters": {
    "field_a": "value"
  }
}
```

Campos:

- `component_id` (int, requerido): JSON key numérica del componente origen del evento.
- `event` (string, requerido): tipo de evento. Valores observados:
  - `click`
  - `input`
  - `change`
  - `action`
  - `timeout`
- `action` (string, requerido): accion backend a ejecutar.
- `parameters` (object, requerido): datos enviados por el cliente (valores de formulario, contexto, etc.).

Response esperada:

- Normalmente HTTP `200 OK` con JSON.
- Puede incluir solo meta-keys, o meta-keys + deltas de componentes.

## 3. Sesion de estado UI (storage + client id)

USIM depende de estado entre requests. Un cliente debe mantener y reenviar dos cosas:

## 3.1 Client ID cookie

- El backend usa cookie de client id (`UIStateManager::CLIENT_ID_COOKIE`).
- Debe persistirse y enviarse en todas las requests subsecuentes de la misma sesion UI.
- Si no se conserva, el backend puede perder continuidad de estado/diffs.

## 3.2 Storage serializado (`usim`)

En respuestas, puede venir:

```json
{
  "storage": {
    "usim": "{\"store_theme\":\"dark\",\"store_token_crypt\":\"<encrypted-value>\"}"
  }
}
```

Reglas:

- `storage.usim` es un `JSON string` serializado por backend.
- El cliente puede parsearlo para leer estado no sensible (`store_*` sin sufijo `_crypt`).
- Los keys con sufijo `_crypt` contienen datos protegidos por backend y deben tratarse como valores opacos (el cliente no debe intentar desencriptarlos).
- Reenviar siempre el string actualizado en eventos siguientes.
- Preservar keys desconocidas para compatibilidad forward.
- Soportes observados:
  - Header `X-USIM-Storage` (contrato esperado en backend).
  - Fallback en body (observado en tests):
    - `usim` para eventos tipo header-like (`click`, `action`, `change`).
    - `storage` para `input`.

Recomendacion para clientes nuevos:

- Implementar primero `X-USIM-Storage`.
- Mantener fallback por body si el backend/proxy lo requiere.

## 4. Formato de respuesta JSON

La respuesta es un objeto JSON con mezcla de:

- componentes UI (keys dinamicas, frecuentemente numericas serializadas como string)
- meta-keys reservadas

Ejemplo simplificado:

```json
{
  "10": {
    "type": "container",
    "parent": "root",
    "name": "main_container"
  },
  "11": {
    "type": "input",
    "parent": 10,
    "name": "login_email",
    "action": "submit_login"
  },
  "storage": {
    "usim": "{\"store_theme\":\"dark\",\"store_page\":2}"
  },
  "toast": {
    "type": "success",
    "message": "Login correcto"
  },
  "redirect": null,
  "abort": null
}
```

## 4.1 Meta-keys reservadas

Keys reservadas observadas:

- `storage`
- `action`
- `redirect`
- `toast`
- `abort`
- `modal`
- `update_modal`
- `clear_uploaders`
- `set_uploader_existing_file`
- `agent_context` (optional, headless mode)

Importante:

- Estas keys no son componentes.
- Deben procesarse con semantica propia.

## 4.2 Contratos meta (interpretacion cliente)

`storage`

- Contenedor de estado serializado en `storage.usim`.
- `storage.usim` representa un objeto JSON de variables persistidas `store_*`.
- `_crypt` indica valor protegido por backend.

`toast`

- Mensaje transitorio para UX.
- En tests se valida `toast.type` (por ejemplo `success`).

`redirect`

- Instruccion de navegacion.
- Si viene no-nulo, el cliente debe navegar segun el payload.

`abort`

- Instruccion de detener flujo por error o estado invalido.
- El cliente debe tratarlo como evento de error de negocio/UI.

`action`

- Meta accion de servidor; usar para instrumentacion si aplica.

`modal`

- Contrato de modal (contenido/estado modal).

`update_modal`

- Actualizacion de modal existente.

`clear_uploaders`

- Lista/instruccion para limpiar uploaders en cliente.

`set_uploader_existing_file`

- Instruccion para setear archivo existente en uploader.

Nota: la forma exacta interna de `modal`, `update_modal`, `clear_uploaders` y `set_uploader_existing_file` depende del backend concreto, pero su presencia es parte del contrato.

`agent_context` (Optional, Headless Mode)

- Metadata describing what the screen does, what inputs it expects, and what outputs it may produce.
- Only present if `Screen::getAgentContext()` returns non-empty array.
- Intended for AI agents, headless clients, and multi-client architectures.
- Example:
  ```json
  "agent_context": {
    "purpose": "User authentication with email/password",
    "inputs": ["email", "password"],
    "outputs": ["redirect", "toast", "abort"],
    "constraints": "Email must be valid format. Password 8+ chars."
  }
  ```
- Web renderer ignores this key automatically.

## 5. Contrato de componentes

## 5.1 Identificacion minima

Un nodo se considera componente UI cuando cumple alguno de estos indicios:

- Tiene `type`, o
- Tiene `parent`

El helper de tests usa como forma fuerte para detectar componente inicial:

- `type` + `parent`

## 5.2 Campos comunes observados

- `type` (string): tipo de componente (`container`, `button`, `input`, etc.).
- `parent` (string|int): jerarquia.
- `name` (string): identificador funcional para lookup en cliente/tests.
- `action` (string): accion asociada (frecuente en botones/inputs).
- `style` (string): estilo visual (opcional).
- `_timeout` (mixed): usado en componentes modales con timeout.
- `actions` (array): listado de acciones en ciertos componentes (por ejemplo cards).

Regla recomendada:

- El cliente debe ser tolerante a campos nuevos/desconocidos.

## 6. Snapshot inicial vs delta

USIM puede responder de dos formas:

- `initial`: snapshot completo de pantalla.
- `delta`: solo cambios respecto al estado previo.

## 6.1 Reglas de merge de delta

Regla observada en `UiMemoryRenderer::mergeComponent`:

- Merge superficial por campo (replace por key).
- Si llega `{ "11": { "label": "Nuevo" } }`, solo se actualiza `label` en componente `11`.

## 6.2 Resolucion de key en deltas

Un delta viene siempre con la misma key JSON numérica del componente.

Cliente robusto:

1. Mantener `jsonKey -> component`.
2. Al recibir key numérica en delta, resolver directamente por `jsonKey`.

## 6.3 Delta de componente desconocido

Si llega delta para componente no conocido:

- Si payload "parece componente" (`type` o `parent`), agregarlo.
- Si no parece componente, registrar issue de contrato y considerar resync.

## 6.4 Respuesta solo meta (sin componentes)

Hay flujos donde `POST /api/ui-event` devuelve solo meta-keys.

Comportamiento recomendado:

- Aplicar meta-keys.
- Mantener snapshot actual.
- Si el cliente necesita consistencia fuerte, hacer `GET /api/ui{route}` para resincronizar.

## 7. Eventos soportados (semantica)

`click`

- Se dispara desde componente con `action` configurada.
- Requiere `component_id` valido.

`input`

- Evento de tipeo/cambio incremental.
- En tests se envia `storage.usim` serializado en body key `storage`.

`change`

- Cambio confirmado de valor/seleccion.

`action`

- Disparo explicito de accion, aun sin click.

`timeout`

- Evento asociado a modales/dialogos con timeout.
- `component_id` corresponde al service/caller id del modal.

## 8. Helpers de contrato utiles (para validar cliente)

Funciones de `UiPayloadHelpers.php` representan asunciones de contrato:

- `uiPayloadContainsAction`: buscar `action` en payload anidado.
- `uiPayloadContainsText`: buscar texto en payload.
- `uiPayloadContainsStyle`: buscar estilos.
- `hasModalComponents`: detectar componentes con `parent == "modal"`.
- `firstTimeoutModalComponent`: detectar primer componente modal con `_timeout`.
- `modalPayloadHasNamedComponent`: buscar nombre dentro de modal.
- `cardHasAction`: buscar accion en `actions[]` de card.
- `menuItemsContainLabel`: buscar label en items de menu.

Un cliente alternativo puede usar la misma logica para tests de compatibilidad.

## 9. Ejemplo completo de ciclo (multiplataforma)

## 9.1 Cargar pantalla

```http
GET /api/ui/auth/login?reset=true
Cookie: <client-id-cookie>
```

Guardar:

- cookie de client id
- `storage.usim` si existe
- snapshot de componentes

## 9.2 Renderizar

- Buscar componentes por `type` y `name`.
- Construir arbol usando `parent`.

## 9.3 Usuario toca boton login

Enviar:

```json
{
  "component_id": 101,
  "event": "click",
  "action": "submit_login",
  "parameters": {
    "login_email": "admin@example.com",
    "login_password": "password"
  },
  "usim": "<serialized-storage-json-string>"
}
```

Procesar respuesta:

1. actualizar `storage.usim` si viene nuevo
2. aplicar `toast`/`redirect`/`abort`/`modal`
3. aplicar deltas de componentes si existen
4. rerender incremental

## 10. Recomendaciones de implementacion para clientes multiplataforma

## 10.1 Capa de red

- Usar un cliente HTTP con manejo persistente de cookies/sesion.
- Guardar y reenviar `X-USIM-Storage` y/o fallback body.
- Manejar retries de red sin perder estado local.

## 10.2 Motor de estado UI

Mantener estructuras:

- `componentsByKey: MutableMap<String, JsonObject>`
- `metaState` para `toast`, `redirect`, `modal`, etc.
- `rawUsim: String?`
- `parsedStorage: MutableMap<String, Any?>` (opcional, derivado de `rawUsim`)

Algoritmo:

1. `initial`: reset maps + cargar todos los componentes.
2. `delta`: merge por key/resolved id.
3. aplicar meta-keys en cada respuesta.
4. exponer stream de cambios para render.

## 10.3 Render

- Resolver factory por `type`.
- Si `name` existe, usarlo como id estable de automatizacion/testing.
- Soportar campos opcionales y desconocidos sin crashear.

## 10.4 Acciones

- Botones: usar `action` del componente.
- Inputs: enviar `input` o `change` segun UX.
- Modales con `_timeout`: programar timer y enviar `timeout`.

## 10.5 Compatibilidad futura

- No hardcodear conjunto cerrado de keys de componente.
- Ignorar meta-keys desconocidas con logging.
- Versionar internamente parser/renderer del cliente.

## 11. Errores y resiliencia

- Si `POST /api/ui-event` responde no-200: mostrar error y conservar ultimo snapshot valido.
- Si llega payload invalido: registrar diagnostico y forzar reload de pantalla (`GET /api/ui...`).
- Si no se encuentra `component_id` al disparar evento: recargar snapshot antes de reintentar.

## 12. Checklist de compatibilidad para un cliente USIM

- Puede cargar una screen con `GET /api/ui{route}`.
- Persiste cookie de client id entre requests.
- Persiste y reenvia `storage.usim` serializado.
- Puede parsear `storage.usim` para leer `store_*` no sensibles.
- Trata keys `*_crypt` como valores opacos.
- Envia eventos con `component_id`, `event`, `action`, `parameters`.
- Distingue componentes vs meta-keys reservadas.
- Aplica merge incremental de deltas.
- Interpreta `toast`, `redirect`, `abort`, `modal`, `update_modal`.
- Tolera campos y keys nuevos sin fallar.
- Tiene estrategia de resync por snapshot completo.

## 12.1 Suite minima de conformance tests

Estos casos deben automatizarse en cualquier cliente para validar compatibilidad real con USIM.

Caso 1: bootstrap de pantalla

- Ejecutar `GET /api/ui{route}`.
- Verificar `200` y al menos un componente con `type`.
- Guardar cookie de client id y `storage.usim` (si existe).

Caso 2: evento click exitoso

- Elegir componente clickable con `action`.
- Enviar `POST /api/ui-event` con `component_id`, `event=click`, `action`, `parameters`.
- Verificar `200`.
- Verificar que el cliente procesa meta-keys si aparecen.

Caso 3: preservacion de estado serializado

- Disparar dos eventos consecutivos.
- Validar que en el segundo se reenvia el ultimo `storage.usim` conocido.
- Verificar que no se pierde continuidad de estado.

Caso 3.1: parse de storage serializado

- Si `storage.usim` contiene JSON valido, parsearlo y exponer valores `store_*` no sensibles.
- Verificar que valores con sufijo `_crypt` se preservan sin transformacion.

Caso 4: merge de delta parcial

- Inyectar o capturar respuesta con delta parcial de componente existente.
- Verificar merge superficial por campo sin borrar keys no presentes en delta.

Caso 5: respuesta solo meta

- Ejecutar evento que devuelva solo meta-keys.
- Verificar que no se rompe el snapshot local.
- Verificar aplicacion de `toast`/`redirect`/`abort` si vienen.

Caso 6: resync por inconsistencia

- Simular `component_id` inexistente o payload invalido.
- Verificar fallback de resincronizacion via `GET /api/ui{route}`.

Caso 7: tolerancia a evolucion de contrato

- En respuesta con keys desconocidas (componente o meta), verificar:
  - no crash
  - logging diagnostico
  - continuidad de operacion

## 13. Resumen ejecutivo

Si implementas los puntos de este documento, cualquier cliente (mobile, iOS, SmartTV, desktop o web) deberia poder:

- renderizar pantallas USIM a partir de JSON,
- enviar eventos de interaccion al backend,
- mantener estado de sesion UI entre requests,
- aplicar diffs y contratos meta de forma compatible con el flujo actual de USIM.
