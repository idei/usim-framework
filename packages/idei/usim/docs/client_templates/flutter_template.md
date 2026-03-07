# USIM Client Template - Flutter

Template de referencia para implementar un cliente USIM en Flutter (mobile, desktop o TV).

## 1. Estructura sugerida

- `data/`: cliente HTTP, DTOs JSON.
- `domain/`: reglas de merge y contratos.
- `state/`: store reactivo (Bloc, Riverpod o ValueNotifier).
- `ui/`: renderer dinamico por tipo de componente.
- `events/`: builders de eventos USIM.

## 2. DTO base

```dart
class UiEventRequest {
  final int componentId;
  final String event;
  final String action;
  final Map<String, dynamic> parameters;
  final String? usim;
  final String? storage;

  UiEventRequest({
    required this.componentId,
    required this.event,
    required this.action,
    required this.parameters,
    this.usim,
    this.storage,
  });
}
```

## 3. Sesion y estado

- Persistir cookies de sesion (`dio_cookie_manager` o equivalente).
- Guardar `storage.usim` opaco.
- Reenviar `X-USIM-Storage` y fallback body cuando aplique.

## 4. Motor de merge

- `componentsByKey: Map<String, Map<String, dynamic>>`
- `keyByInternalId: Map<int, String>`
- merge superficial para deltas.
- resincronizar con `GET /api/ui{route}` ante inconsistencia.

## 5. Renderer dinamico

- Factory por `type`.
- Estructura de arbol por `parent`.
- `name` como id estable para pruebas.

## 6. Eventos soportados

- `click`
- `input`
- `change`
- `action`
- `timeout`

## 7. Recomendacion de QA

Implementar una suite de conformance que ejecute los casos del contrato principal antes de integrar features de negocio.
