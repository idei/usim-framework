# Plan de Implementación: Transición hacia la Arquitectura Declarativa y Reactiva de USIM

**Rama de Trabajo:** `feature/declarative-reactive-architecture`  
**Objetivo:** Modernizar la arquitectura de USIM eliminando cuellos de botella de reactividad, mutaciones manuales imperativas, dependencias de `localStorage` y parches de modales, adoptando un paradigma declarativo estilo Flutter que reduzca la deuda técnica sin romper la compatibilidad con el ecosistema actual.

---

## 1. Fases del Plan de Implementación

```
┌────────────────────────────────────────────────────────────────────────┐
│ FASE 1: Núcleo Declarativo y Retrocompatibilidad (COMPLETADA)           │
│ - Creación del namespace Widgets (Widget, Column, Row, Box, Text...)   │
│ - Integración en Screen.php (build(): ?Widget + ciclo reactivo)         │
│ - Tests unitarios automatizados aprobados                              │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ FASE 2: Aislamiento de Pestañas y Optimización de Redis (COMPLETADA)   │
│ - Header X-USIM-Tab-Id en cliente JS y persistencia en sessionStorage   │
│ - Claves de cache en UIStateManager aisladas por tab_id                │
│ - Persistencia del estado declarativo en Redis / Cache                 │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ FASE 3: Migración Gradual de Pantallas y Componentes Complejos (ROADMAP)│
│ - Migración de modales legacy a widgets Modal declarativos              │
│ - Refactorización de Screens clave (Login, UserManager, Demo)          │
│ - Soporte de Widgets para Tablas (TableWidget) y Formularios complejos │
└───────────────────────────────────┬────────────────────────────────────┘
                                    │
                                    ▼
┌────────────────────────────────────────────────────────────────────────┐
│ FASE 4: Reactividad Push en Tiempo Real y Optimizaciones Frontend       │
│ - Integración de canal WebSocket (Laravel Reverb / SSE) para UsimEvent │
│ - Optimistic UI en el cliente para interacciones locales inmediatas   │
└────────────────────────────────────────────────────────────────────────┘
```

---

## 2. Detalle de Tareas Realizadas en esta Rama

### 2.1 Backend / Framework (`packages/idei/usim`)
1. **Namespace `Idei\Usim\Widgets`:**
   - [`Widget.php`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/src/Widgets/Widget.php): Abstracción base extensible para todos los widgets.
   - [`Column.php`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/src/Widgets/Column.php) y [`Row.php`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/src/Widgets/Row.php): Layouts declarativos con gestión de espaciado y alineación.
   - [`Box.php`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/src/Widgets/Box.php): Manejo unificado de dimensiones, rellenos, márgenes y títulos.
   - [`Text.php`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/src/Widgets/Text.php): Componente tipográfico estandarizado.
   - [`Button.php`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/src/Widgets/Button.php): Botón declarativo vinculado a acciones de backend.
   - [`TextInput.php`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/src/Widgets/TextInput.php): Input de formulario con debounce y validaciones.
   - [`Card.php`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/src/Widgets/Card.php): Tarjeta contenedora visual.
   - [`Modal.php`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/src/Widgets/Modal.php): Widget modal nativo integrado al árbol del protocolo.
2. **Ciclo de Vida Reactivo en [`Screen.php`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/src/Screen.php):**
   - Agregado método `build(): ?Widget`.
   - Modificado `buildBaseUI()` para delegar en `build()` si el screen es declarativo.
   - En `finalizeEventContext()`: re-evaluación automática de `build()`, cálculo del delta mediante `UIDiffer::compare()` y persistencia de estado.
   - En `initializeEventContext()`: restauración transparente del estado de pantalla.
3. **Aislamiento de Sesiones en [`UIStateManager.php`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/src/Support/UIStateManager.php):**
   - Soporte para la cabecera `X-USIM-Tab-Id`.
   - Claves de cache compuestas: `ui_state:ScreenClass:clientId:tabId`, eliminando colisiones de pestañas.
   - Métodos `storeScreenState()` y `getScreenState()` para almacenar el estado en Redis.

### 2.2 Frontend / Assets
1. **Identificador de Pestaña (`tab_id`):**
   - [`ui-renderer.js`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/resources/assets/js/ui-renderer.js): Envío de `X-USIM-Tab-Id` en `fetch` de inicialización de pantalla, eventos de usuario y timers.
   - [`ui-event.js`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/resources/assets/js/components/shared/ui-event.js): Integración de `X-USIM-Tab-Id` en llamadas de eventos modulares.

### 2.3 Demostraciones y Pruebas Automatizadas
1. **Showcase Declarativo:**
   - [`DeclarativeDemo.php`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/app/UI/Screens/Demo/DeclarativeDemo.php): Ejemplo completo de contador reactivo con modal condicional y feedback de acciones.
2. **Pruebas Unitarias:**
   - [`DeclarativeWidgetsAndReactivityTest.php`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/tests/Unit/DeclarativeWidgetsAndReactivityTest.php): Validación con Pest de montaje de widgets, reactividad de estado y aislamiento por tab.

---

## 3. Próximos Pasos (Roadmap de Transición)

### Paso 1: Evaluación y Revisión del Creador
- Presentar la arquitectura implementada en esta rama y los benchmarks de claridad de código.
- Correr la suite de pruebas automatizadas (`composer test`) para constatar la estabilidad del monorepo.

### Paso 2: Adición de Widgets Avanzados
- Implementar `TableWidget` para permitir listas y tablas dinámicas de forma declarativa sin lidiar con builders de `TableCell` y `TableHeaderRow`.
- Implementar `FormWidget` que agrupe inputs y gestione validaciones automáticas de Laravel FormRequest.

### Paso 3: Migración Gradual de Pantallas de Producción
- Migrar primero pantallas de menor complejidad (como `Login`, `Profile`, `ForgotPassword`).
- Progresar hacia pantallas administrativas complejas (`UsersManager`, `TranslateManager`).
- Mantener las pantallas legacy intactas mientras se transiciona componente por componente gracias al diseño retrocompatible.

### Paso 4: Habilitación de Redis y Canales en Tiempo Real
- Configurar Redis como store principal en `config/cache.php` para entornos de producción con Octane.
- Conectar `UsimEvent` con Laravel Reverb para habilitar push en tiempo real a clientes conectados.
