# Arquitectura Declarativa y Reactiva de USIM: Guía Técnica y Estándar de Desarrollo

**Documento:** Especificación Arquitectónica y Guía de Evolución  
**Versión:** 2.0 (Septiembre 2026)  
**Estado:** Implementado en rama `feature/declarative-reactive-architecture`  
**Principios Fundamentales:** SOLID, DRY, KISS, YAGNI  

---

## 1. Introducción y Visión General

El framework **USIM (UI Services Implementation Model)** fue concebido como una solución de **Server-Driven UI (SDUI)** orientada a Laravel para permitir que el backend defina la interfaz y la transmita mediante deltas JSON a un cliente agnóstico, evitando la sobrecarga de re-renderizado HTML completo (como Livewire).

Sin embargo, en su versión 1.0, el modelo operativo interno se apoyaba en:
1. Una construcción procedimental mediante Builders encadenados (`UI::container()->layout()->add(...)`).
2. Una persistencia de estado fragmentada entre `localStorage` del cliente (`X-USIM-Storage`) y cache global por cookie (`clientId`).
3. Una "reactividad" simulada donde el desarrollador debía registrar propiedades protegidas mediante reflexión y mutar imperativamente los componentes individuales uno a uno (`$this->lbl_result->text('...')`).
4. Modales manejados mediante servicios estáticos desconectados (`ConfirmDialogService::open`) que inyectaban nodos huérfanos a un colector global (`UIChangesCollector`).

La **Arquitectura Declarativa 2.0** evoluciona USIM hacia un modelo **declarativo puro y reactivo** fuertemente inspirado en la filosofía de componentes de **Flutter**, formalizando la regla:

$$\text{UI} = f(\text{Estado})$$

---

## 2. Principios de Diseño de Software Aplicados

### 2.1 SOLID
* **Single Responsibility Principle (SRP):** Cada `Widget` es responsable exclusivamente de describir su configuración visual y montar su estructura correspondiente. Las pantallas (`Screen`) son responsables de su lógica de negocio y estado, dejando de gestionar referencias mutables a instancias del DOM virtual.
* **Open/Closed Principle (OCP):** El sistema de Widgets es extensible. Nuevos widgets (gráficos, mapas, selectores complejos) se integran heredando de `Widget` sin modificar el motor de diffing ni el ciclo de vida de `Screen`.
* **Liskov Substitution Principle (LSP):** Cualquier `Widget` puede actuar como hijo de contenedores de layout (`Column`, `Row`, `Box`) de manera transparente.
* **Interface Segregation Principle (ISP):** Se eliminó la dependencia obligatoria de reflection hooks para propiedades mágicas. Los contratos son mínimos y tipados.
* **Dependency Inversion Principle (DIP):** El motor reactivo depende de la abstracción `Widget`, desacoplado de las implementaciones concretas de componentes de bajo nivel.

### 2.2 DRY (Don't Repeat Yourself)
* Se erradicó la necesidad de declarar cada componente tres veces: (1) en `buildBaseUI`, (2) como propiedad de clase para reflexión (`protected Label $lbl_info`), y (3) en cada handler de evento para mutar su valor.
* El método declarativo `build()` describe la interfaz una única vez basándose en el estado de la clase.

### 2.3 KISS (Keep It Simple, Stupid)
* La jerarquía del código en PHP refleja visualmente la jerarquía en pantalla mediante constructores tipados con parámetros nombrados (PHP 8.2+).
* El desarrollador solo altera variables de estado en los handlers (`$this->counter++`), y el framework recalcula y difunde los cambios de forma automática.

### 2.4 YAGNI (You Aren't Gonna Need It)
* Se eliminó la sobre-ingeniería de encriptar/desencriptar variables por reflexión y enviarlas en cabeceras HTTP de ida y vuelta.
* El estado del servidor se mantiene en el servidor (Redis/Cache), transmitiendo al cliente únicamente lo estrictamente necesario: los deltas de interfaz.

---

## 3. Comparativa de Modelos Arquitectónicos: USIM 1.0 vs. USIM 2.0 (Flutter-Style)

| Dimensión | USIM 1.0 (Imperativo / Legacy) | USIM 2.0 (Declarativo Reactivo) |
| :--- | :--- | :--- |
| **Paradigma** | **Imperativo / Mutacional**.<br>Construcción manual y mutación directa de objetos. | **Declarativo Puro**.<br>$\text{UI} = f(\text{Estado})$. `build(): Widget` describe la interfaz. |
| **Sintaxis de UI** | Encadenamiento de métodos (Builder pattern verborrágico). | Composición jerárquica de Widgets tipados con named arguments. |
| **Reactividad** | Manual. El programador muta componentes en handlers de eventos. | Automática. El framework re-evalúa `build()` ante cambios de estado y calcula el diff. |
| **Modales** | Servicios externos estáticos que inyectan JSON a colectores globales. | Widgets declarativos condicionales (`Modal`) dentro del mismo árbol visual. |
| **Gestión de Estado** | Volcado a `localStorage` enviado en cabecera HTTP base64 (`X-USIM-Storage`). | Estado respaldado en servidor (Redis) aislado por pestaña (`X-USIM-Tab-Id`). |
| **Riesgo Multi-pestaña** | Alto. Pestañas del mismo navegador sobrescriben el estado mutuo. | Cero. Cada pestaña posee un espacio de estado aislado mediante UUID en sesión. |

---

## 4. Solución Integral a la Reactividad

La reactividad se estructuró en tres capas complementarias:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        CAPA 1: BACKEND (PHP)                           │
│                                                                        │
│   Estado de Pantalla ($this->state)  ──►  build(): Widget             │
│                                                  │                     │
│                                                  ▼                     │
│                                          Container Tree                │
│                                                  │                     │
│                        UIDiffer::compare(oldUI, newUI)                 │
│                                                  │                     │
│                                                  ▼                     │
│                                              JSON Delta                │
└────────────────────────────────────────┬───────────────────────────────┘
                                         │  HTTP / WebSocket
                                         ▼
┌────────────────────────────────────────────────────────────────────────┐
│                        CAPA 2: CLIENTE (JS)                            │
│                                                                        │
│   Input Binding Local  ──►  Reconciliation DOM  ──►  Optimistic UI     │
└────────────────────────────────────────────────────────────────────────┘
```

### Capa 1: Reactividad Declarativa en el Backend
1. **La clase `Screen` expone el método `build(): ?Widget`.**
2. Si una pantalla implementa `build()`, el framework la clasifica como declarativa (`isDeclarative() === true`).
3. En la carga inicial, `build()` monta la jerarquía de componentes inicial y almacena el snapshot en Cache/Redis.
4. Al recibir un evento en `POST /api/ui-event`, el handler del Screen modifica exclusivamente sus propiedades de estado.
5. Durante `finalizeEventContext()`, el framework:
   - Re-ejecuta automáticamente `build()` con el nuevo estado.
   - Monta el nuevo árbol en un contenedor limpio.
   - Ejecuta `UIDiffer::compare($oldUI, $newUI)` para detectar exactamente qué propiedades, textos o componentes cambiaron, se agregaron o se eliminaron.
   - Persiste el nuevo estado y snapshot en Redis/Cache.
   - Responde al cliente con los deltas quirúrgicos calculados.
6. **El desarrollador no busca componentes, no muta instancias a mano ni se preocupa por desincronizaciones.**

### Capa 2: Binding de Estado en el Cliente y Eliminación de Bloat de Storage
* **Aislamiento por Pestaña (`X-USIM-Tab-Id`):**
  Cada pestaña del navegador genera y conserva un identificador único en `sessionStorage` (`crypto.randomUUID()`).
  Cada petición HTTP (`GET /api/ui/...` y `POST /api/ui-event`) viaja con la cabecera `X-USIM-Tab-Id: <uuid>`.
  En el backend, [`UIStateManager`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/src/Support/UIStateManager.php) utiliza este ID para componer la clave de cache:
  $$\text{cacheKey} = \text{"ui\_state:"} + \text{ScreenClass} + \text{":"} + \text{clientId} + \text{":"} + \text{tabId}$$
  Esto permite que un mismo usuario trabaje con múltiples pestañas de la misma pantalla sin ninguna interferencia entre ellas.
* **Reducción del payload en red:**
  El estado ya no viaja serializado y cifrado en cada petición en `localStorage`. Vive en Redis en memoria del servidor, reduciendo el tamaño de las cabeceras HTTP de varios kilobytes a una simple cabecera de 36 caracteres.

### Capa 3: Reactividad de Empuje en Tiempo Real (Push / WebSockets)
* El diseño permite conectar eventos de dominio (`UsimEvent`) con un servidor WebSocket (Laravel Reverb o SSE).
* Cuando se dispara un evento externo en el servidor, este re-evalúa `build()` para las pantallas abiertas afectadas y empuja el delta JSON al socket del cliente, actualizando la vista sin necesidad de polling con componentes `Timer`.

---

## 5. El Catálogo de Widgets Declarativos

Los widgets declarativos residen en el namespace `Idei\Usim\Widgets` y extienden de la clase abstracta [`Widget`](file:///home/MartinVarela/Mis%20Proyectos/usim-framework/packages/idei/usim/src/Widgets/Widget.php).

### 5.1 Widgets Estructurales y de Layout
* **`Column`:** Disposición vertical. Soporta `children` (lista de widgets), `gap`, `padding`, `alignItems`, `justifyContent`, `plain`.
* **`Row`:** Disposición horizontal. Soporta `children`, `gap`, `padding`, `alignItems`, `justifyContent`, `plain`.
* **`Box`:** Contenedor de caja. Soporta `child`, dimensiones (`width`, `height`, `maxWidth`, `minHeight`), espaciados (`padding`, `margin`), sombra (`shadow`), bordes redondeados (`rounded`) y título.
* **`Card`:** Tarjeta visual que encapsula contenido estructurado con `title`, `subtitle`, `icon` y `child`.

### 5.2 Widgets de Interacción y Contenido
* **`Text`:** Etiqueta tipográfica. Soporta estilos (`h1`, `h2`, `h3`, `default`, `info`, `warning`, `error`, `success`), `fontSize`, renderizado Markdown y visibilidad.
* **`Button`:** Botón de acción. Soporta `label`, `onPressed` (nombre de acción en backend), `params`, `style`, `icon`, `disabled`, `tooltip` y `width`.
* **`TextInput`:** Campo de entrada de texto. Soporta `name`, `label`, `placeholder`, `value`, `inputType`, `required`, `disabled`, `onInput` con `debounce` y `width`.

### 5.3 Modales Declarativos (`Modal`)
El widget `Modal` resuelve el problema histórico de los modales en USIM:
* Se declara como cualquier otro widget dentro de `build()`.
* Al renderizarse, se conecta automáticamente al anclaje `'modal'` del protocolo USIM.
* Soporta `title`, `icon`, `child`, `actions` (lista de `Button`) y `onClose`.
* **Ciclo de vida natural:** Si la condición de estado es verdadera (`$this->showModal`), el modal se incluye en el árbol y aparece en pantalla. Si la condición es falsa, `UIDiffer` detecta su ausencia y envía la instrucción de destrucción (`parent: null`) al cliente.

---

## 6. Ejemplo Comparativo: Antes vs. Después

### Código en USIM 1.0 (Imperativo con Reflexión y Parche de Modal)
```php
class LegacyDemo extends Screen
{
    protected Label $lbl_counter;
    protected int $store_count = 0; // Guardado en localStorage

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->maxWidth(Size::px(600))->padding(Spacing::px(20));

        $container->add(
            UI::label('lbl_counter')
                ->text("Contador: {$this->store_count}")
                ->style('h2')
        );

        $container->add(
            UI::button('btn_add')
                ->label('Sumar')
                ->action('increment')
        );
    }

    public function onIncrement(array $params): void
    {
        $this->store_count++;
        // Mutación manual obligatoria por reflexión:
        $this->lbl_counter->text("Contador: {$this->store_count}");
    }

    public function onOpenConfirm(array $params): void
    {
        // Parche: bypass estático a un colector global
        ConfirmDialogService::open(
            title: 'Confirmar',
            message: '¿Está seguro?',
            callerServiceId: $this->getScreenComponentId()
        );
    }
}
```

### Código en USIM 2.0 (Declarativo Reactivo Flutter-Style)
```php
class DeclarativeDemo extends Screen
{
    // 1. Estado reactivo puro (respaldado en Redis por pestaña)
    public int $count = 0;
    public bool $showConfirm = false;

    // 2. Handlers: solo mutan el estado
    public function onIncrement(): void
    {
        $this->count++;
    }

    public function onToggleConfirm(): void
    {
        $this->showConfirm = !$this->showConfirm;
    }

    // 3. UI declarativa: función matemática del estado
    public function build(): ?Widget
    {
        $children = [
            new Text("Contador: {$this->count}", style: 'h2', key: 'counter_txt'),
            new Row(
                gap: 10,
                children: [
                    new Button(label: 'Sumar', onPressed: 'increment', key: 'btn_add'),
                    new Button(label: 'Confirmar', style: 'warning', onPressed: 'toggle_confirm', key: 'btn_modal'),
                ]
            )
        ];

        // Modal como nodo condicional del árbol
        if ($this->showConfirm) {
            $children[] = new Modal(
                title: 'Confirmación',
                child: new Text('¿Desea continuar con la operación?'),
                actions: [
                    new Button(label: 'Cerrar', style: 'secondary', onPressed: 'toggle_confirm'),
                ],
                key: 'confirm_dialog'
            );
        }

        return new Box(
            maxWidth: Size::px(600),
            padding: Spacing::px(20),
            child: new Column(gap: 15, children: $children)
        );
    }
}
```

---

## 7. Garantía de Retrocompatibilidad

La arquitectura fue diseñada de manera no destructiva:
1. **Pantallas heredadas (Legacy):** Cualquier pantalla que implemente `buildBaseUI()` continúa funcionando exactamente igual que antes. La clase `Screen` detecta si `build()` retorna `null` y mantiene el flujo imperativo tradicional.
2. **Protocolo JSON y Cliente JS:** La estructura del JSON transmitido al navegador (`component_id => {type, parent, ...}`) no cambia. El cliente existente (`ui-renderer.js`) sigue pintando componentes y deltas sin requerir una reescritura total.
3. **Migración progresiva:** Los desarrolladores pueden crear pantallas nuevas con la sintaxis de Widgets declarativos y migrar pantallas complejas antiguas a su propio ritmo.
