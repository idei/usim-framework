# Especificación Técnica del Protocolo USIM (UI Services Implementation Model)

**Versión:** 1.0
**Tipo de Documento:** Especificación de Protocolo de Interfaz
**Ámbito:** Arquitectura Orientada a Servicios / Server-Driven UI

## 1. Introducción
El presente documento define formalmente la estructura y semántica del protocolo de comunicación utilizado en el framework USIM. Este protocolo implementa un patrón de arquitectura de **Interfaz de Usuario Dirigida por el Servidor** (*Server-Driven UI*), donde la lógica de presentación, el estado de los componentes y la jerarquía visual son determinados por el backend y transmitidos al cliente mediante mensajes serializados en formato JSON.

El objetivo principal es desacoplar la definición de la vista del motor de renderizado, permitiendo la actualización dinámica de la interfaz sin requerir cambios en el código fuente del cliente.

## 2. Arquitectura de Comunicación
La comunicación se establece mediante el intercambio de mensajes asíncronos donde el servidor actúa como la fuente de la verdad para el estado de la UI.

### 2.1 Estructura de Datos
El cuerpo del mensaje (*Payload*) se estructura como un **Diccionario de Componentes**. Cada clave en este diccionario representa un identificador único de componente ($ID$), y su valor asociado describe las propiedades, tipo y ubicación de dicho componente.

El cliente debe operar bajo un modelo de **Renderizado Reactivo**:
1.  Mantiene un árbol de componentes en memoria (Virtual DOM o State Tree).
2.  Recibe "deltas" o cambios desde el servidor.
3.  Aplica los cambios al árbol local y repinta la interfaz.

## 3. Sintaxis Formal (EBNF)
Para garantizar la independencia de la implementación, se define la sintaxis del protocolo utilizando la **Forma de Backus-Naur Extendida (EBNF)**. Esta gramática describe las reglas de formación de mensajes válidos.

```ebnf
(* Estructura del Mensaje *)
ProtocolMessage ::= "{" [ ComponentEntryList ] "}"
ComponentEntryList ::= ComponentEntry { "," ComponentEntry }

(* Definición de una Entrada de Componente *)
ComponentEntry  ::= ComponentID ":" ComponentBody

(* Identificadores *)
ComponentID     ::= IdentifierString
IdentifierString ::= Letter { Letter | Digit | "_" }

(* Cuerpo y Atributos *)
ComponentBody   ::= "{" AttributeList "}"
AttributeList   ::= Attribute { "," Attribute }
Attribute       ::= TypeAttr | ParentAttr | GenericAttr

(* Atributos Reservados *)
TypeAttr        ::= '"type"' ":" '"' ComponentType '"'
ParentAttr      ::= '"parent"' ":" ParentValue

(* Valores de Atributos *)
ComponentType   ::= "container" | "button" | "input" | "select" | "checkbox" | "card" | "table" | "tablerow" | "tablecell" | "tableheadercell" | "form" | "tableheaderrow" | "menudropdown" | "uploader" | "calendar" | "carousel" | "textarea" | "split" | "label" | CustomType
ParentValue     ::= '"' ComponentID '"' | '"' AnchorKeyword '"' | "null"
GenericAttr     ::= StringLiteral ":" ValueLiteral

(* Palabras Clave de Anclaje (Anchors) *)
AnchorKeyword   ::= "main" | "menu" | "modal"

(* Primitivas *)
CustomType      ::= StringLiteral
StringLiteral   ::= '"' { Character } '"'
ValueLiteral    ::= StringLiteral | Number | Boolean
```

## 4. Semántica Operacional

### 4.1 Ciclo de Vida del Componente
El cliente procesará el mensaje iterando sobre cada `ComponentID`. La operación a realizar se infiere de la existencia previa del componente en el registro del cliente ($C$) y de los atributos recibidos.

1.  **Instanciación ($ID \notin C$):**
    *   Requiere obligatoriamente los atributos `type` y `parent`.
    *   El cliente crea la instancia y la anexa al nodo especificado en `parent`.

2.  **Mutación ($ID \in C$):**
    *   Ocurre cuando se recibe un ID que ya existe en memoria.
    *   Se actualizan únicamente los atributos presentes en el mensaje (fusión de propiedades).
    *   **Garantía del framework (`Screen::buildDiffResponse`):** El backend de USIM siempre incluye el atributo `'type'` correspondiente al estado final del componente en cada entrada del delta, garantizando que el cliente conozca inequívocamente la fábrica de componente que debe gestionar la mutación sin depender exclusivamente de inferencias.

3.  **Eliminación (Destrucción):**
    *   Se activa explícitamente cuando el atributo `parent` tiene el valor `null`.
    *   **Efecto en Cascada:** La eliminación de un nodo implica la eliminación recursiva de todos sus nodos descendientes en el árbol de la UI.

### 4.2 Sistema de Anclajes (Anchors)
El atributo `parent` define la topología del árbol. Existen nodos raíz virtuales predefinidos por el framework, denominados **Anclajes**:

*   `main`: Contenedor principal de la vista (Screen).
*   `menu`: Contenedor para navegación lateral o persistente.
*   `modal`: Capa superior para diálogos emergentes (Z-index superior).

### 4.3 Reglas de Identificadores
*   **Determinismo:** El backend debe asegurar que, para un mismo estado lógico, se generen siempre los mismos `COMPONENT_ID`.
*   **Nomenclatura:** Los IDs definidos manualmente deben seguir la convención de nombres de variables (alfanuméricos, sin espacios).
*   **Unicidad:** El ID debe ser único dentro del contexto de la sesión de usuario actual.

### 4.4 Generación de Identificadores Determinísticos

En la práctica, la mayoría de los identificadores de componentes son generados automáticamente por el sistema, no definidos manualmente. El framework utiliza un **generador de IDs centralizado** (`UIIdGenerator`) que garantiza unicidad y determinismo sin depender de la base de datos o el estado de sesión.

#### 4.4.1 Mecanismo de Generación

El sistema combina dos estrategias para generar IDs numéricos únicos:

**1. Offsets por Contexto (Context-Based Offsets)**

Cada pantalla (Screen) o contexto de UI obtiene un "espacio de numeración" propio basado en su nombre de clase. Por ejemplo:
- `App\UI\Screens\Auth\LoginScreen` podría obtener el offset `50000000`
- `App\UI\Screens\DashboardScreen` podría obtener el offset `120000000`
- `App\UI\Screens\Admin\UsersManager` podría obtener el offset `230000000`

Estos offsets se calculan usando un hash CRC32 del nombre completo de la clase, escalado en múltiplos de 10,000. Esto significa que cada Screen tiene un "rango" de 10,000 IDs únicos disponibles (ejemplo: desde 50000000 hasta 50009999).

**2. IDs Locales Auto-incrementales o Determinísticos**

Dentro de cada contexto, se pueden generar IDs de dos formas:

- **Auto-incremental:** Para componentes dinámicos sin nombre específico (listas, tablas, elementos repetitivos), se usa un contador secuencial que se reinicia por request.
  ```
  Offset + AutoIncrement = ID Final
  50000000 + 1 = 50000001
  50000000 + 2 = 50000002
  ```

- **Basado en Nombre:** Para componentes con identificador semántico (botones principales, contenedores importantes), se genera un hash del nombre del componente.
  ```
  Offset + hash("submit_button") = 50006234
  Offset + hash("cancel_button") = 50001892
  ```

#### 4.4.2 Garantías del Sistema

- **Unicidad Global:** El offset por contexto evita colisiones entre pantallas diferentes.
- **Determinismo:** Para un mismo nombre de componente en el mismo contexto de Screen, siempre se genera el mismo ID.
- **Trazabilidad:** Dado un ID numérico, es posible determinar qué Screen lo generó mediante reverse lookup del offset (`UIIdGenerator::getContextFromId($id)`).
- **Sin Estado Persistente:** Los IDs se calculan en tiempo de ejecución sin consultar base de datos, lo que garantiza alto rendimiento.

#### 4.4.3 Ejemplo Conceptual

```php
// Componente con nombre explícito (minoría de casos)
UIIdGenerator::generateFromName('App\UI\Screens\Auth\LoginScreen', 'login_button');
// Resultado: 50006234 (siempre el mismo)

// Componente dinámico sin nombre (mayoría de casos)
UIIdGenerator::generate('App\UI\Screens\DashboardScreen');
// Primera llamada: 120000001
// Segunda llamada: 120000002
// Tercera llamada: 120000003
```

En la práctica, **la mayoría de los componentes** (elementos de listas, cards dinámicas, widgets generados en bucles) utilizan la generación auto-incremental sin nombre, ya que no requieren un identificador semántico estable. Solo los componentes críticos de la interfaz (botones de acción principales, contenedores raíz, modales importantes) utilizan IDs basados en nombre para facilitar el debugging y las referencias cruzadas.

## 5. Ejemplos de Implementación

A continuación se ilustra la aplicación de la sintaxis abstracta mediante objetos JSON concretos para los distintos estados del ciclo de vida. Los ejemplos utilizan **IDs numéricos generados automáticamente**, que representan el caso mayoritario en producción.

### 5.1 Renderizado Inicial (Instanciación)
El servidor envía la estructura de un formulario de Login. Nótese que solo el contenedor raíz tiene un ID nominal para facilitar referencias; el resto usa IDs generados automáticamente.

```json
{
  "50001234": {
    "type": "container",
    "parent": "main",
    "style": "flex_column",
    "padding": "20"
  },
  "50001235": {
    "type": "input",
    "parent": "50001234",
    "placeholder": "Correo electrónico",
    "inputType": "email"
  },
  "50001236": {
    "type": "input",
    "parent": "50001234",
    "placeholder": "Contraseña",
    "inputType": "password"
  },
  "50006789": {
    "type": "button",
    "parent": "50001234",
    "text": "Iniciar Sesión",
    "action": "auth_login",
    "variant": "primary"
  },
  "50001237": {
    "type": "button",
    "parent": "50001234",
    "text": "¿Olvidaste tu contraseña?",
    "action": "auth_recover",
    "variant": "link"
  }
}
```

**Nota:** El ID `50006789` corresponde al botón principal generado con `generateFromName('submit_button')` para mantener estabilidad. Los demás componentes (`50001235`, `50001236`, `50001237`) usan auto-incremento, ya que son elementos internos sin necesidad de referencia explícita.

### 5.2 Actualización de Estado (Mutación)
El usuario presiona el botón de login. El servidor responde con un delta que actualiza solo los componentes afectados.

```json
{
  "50006789": {
    "text": "Validando...",
    "disabled": true,
    "loading": true
  },
  "50001235": {
    "enabled": false
  },
  "50001236": {
    "enabled": false
  }
}
```

### 5.3 Renderizado de Lista Dinámica
El servidor responde con una lista de notificaciones. Cada item de la lista usa IDs auto-generados únicos.

```json
{
  "120004001": {
    "type": "container",
    "parent": "main",
    "orientation": "vertical"
  },
  "120004002": {
    "type": "card",
    "parent": "120004001",
    "title": "Nueva solicitud",
    "subtitle": "Hace 5 minutos",
    "icon": "bell"
  },
  "120004003": {
    "type": "card",
    "parent": "120004001",
    "title": "Documento aprobado",
    "subtitle": "Hace 2 horas",
    "icon": "check"
  },
  "120004004": {
    "type": "card",
    "parent": "120004001",
    "title": "Recordatorio",
    "subtitle": "Hace 1 día",
    "icon": "clock"
  }
}
```

**Nota:** Cada card recibe un ID secuencial generado automáticamente. No hay necesidad de IDs semánticos porque estos elementos son transitorios y se regeneran en cada request.

### 5.4 Limpieza de Interfaz (Eliminación)
Tras un login exitoso, el servidor ordena destruir el formulario completo. Al eliminar el contenedor padre, todos los hijos son eliminados en cascada.

```json
{
  "50001234": {
    "parent": null
  }
}
```

**Nota:** Solo se necesita eliminar el contenedor raíz (`50001234`). Los componentes `50001235`, `50001236`, `50006789` y `50001237` se destruyen automáticamente por la regla de cascada del árbol DOM.

## 6. Comunicación Frontend ↔ Backend (Endpoints y Ciclo de Vida)

La comunicación entre el cliente y el framework USIM se basa en dos endpoints HTTP principales: uno para la carga y renderizado inicial de pantallas (`Screen`), y otro para el despacho de eventos de interacción.

### 6.1 Carga Inicial de Pantalla (Screen Load)

**Endpoint:** `GET /api/ui{screen_route_path}?{query_params}`

Donde `screen_route_path` corresponde a la ruta canónica derivada por `Screen::getRoutePath()` (por ejemplo: `/admin/users-manager`, `/auth/login`).

#### 6.1.1 Parámetros de Query Especiales
- `reset=true`: Solicita reiniciar el estado almacenado en caché para la pantalla. Cuando está presente, el backend invoca el hook `Screen::onResetScreen()`, el cual limpia el snapshot en `UIStateManager` (`clearCachedScreenSnapshot()`) y fuerza la regeneración completa de la UI desde `buildBaseUI()`.

#### 6.1.2 Ciclo de Vida en el Backend durante la Carga
1. **Resolución de Screen:** `UIController` resuelve la clase PHP `Screen` correspondiente a la ruta.
2. **Control de Acceso:** Ejecuta `Screen::checkAccess()`. Si no es permitido, devuelve una respuesta de redirección o abort (ver Sección 8).
3. **Reset condicional:** Si `reset=true`, ejecuta `$screen->onResetScreen()`.
4. **Inicialización de Contexto:** Invoca `$screen->initializeEventContext($incomingStorage, $queryParams)`.
   - Si no hay snapshot en caché (o fue invalidado), invoca `buildBaseUI($container)`.
5. **Finalización con Reload:** Invoca `$screen->finalizeEventContext(reload: true)`, lo que a su vez dispara el hook `postLoadUI()` y persiste el snapshot en caché.
6. **Inyección de Contexto de Agente (Headless):** Si `Screen::getAgentContext()` devuelve metadatos, se inyecta la clave `agent_context` en la respuesta.
7. **Respuesta:** Devuelve un snapshot JSON con todos los componentes iniciales y las meta-keys requeridas.

### 6.2 Envío de Eventos de Interfaz (UI Events)

**Endpoint:** `POST /api/ui-event`

**Headers recomendados:**
```http
Content-Type: application/json
Accept: application/json
X-CSRF-TOKEN: {token}
X-Requested-With: XMLHttpRequest
X-USIM-Storage: {storage_json_string}
Cookie: {client_id_cookie}
```

**Body (JSON):**
```json
{
  "component_id": 50006789,
  "event": "click",
  "action": "submit_form",
  "parameters": {
    "email": "user@example.com",
    "password": "********",
    "remember_me": true
  }
}
```

### 6.3 Descripción de Campos del Request

#### 6.3.1 `component_id` (integer, required)
Identificador numérico único del componente que disparó el evento. A partir de este ID, el backend determina qué clase `Screen` debe procesar la acción mediante búsqueda inversa del hash en `UIIdGenerator::getContextFromId()`.

#### 6.3.2 `event` (string, required)
Tipo de interacción producida en el cliente:
- `"click"`: Clic en botones, links o elementos accionables.
- `"input"`: Entrada incremental en campos de texto.
- `"change"`: Modificación de valor en selects, checkboxes o radios.
- `"action"`: Disparo programático o directo de una acción sin evento nativo DOM.
- `"timeout"`: Evento generado al expirar un temporizador (común en modales de alerta o cuenta regresiva).

#### 6.3.3 `action` (string, required)
Nombre de la acción del componente en formato **snake_case**. En el backend, `UIEventController` convierte automáticamente este valor al método correspondiente del Screen en convención `onPascalCase`:
```
Frontend (action)    →    Backend (Screen método)
─────────────────────────────────────────────────
"submit_form"        →    onSubmitForm(array $params)
"delete_user"        →    onDeleteUser(array $params)
"change_theme"       →    onChangeTheme(array $params)
"close_modal"        →    onCloseModal(array $params)
```

#### 6.3.4 `parameters` (object, optional)
Diccionario asociativo de datos asociados al evento:
1. **Valores autocolectados de formulario:** Valores de inputs dentro del mismo contenedor o contexto de formulario.
2. **Parámetros explícitos:** Valores definidos en la declaración del componente (ej. `user_id: 42`).
3. **Identificador del Screen emisor:**
   - `_caller_screen_id`: ID del componente o contenedor raíz del Screen que abrió un modal (usado para enrutar el callback de regreso al Screen original mediante `Screen::getScreenComponentId()`).
   - `_caller_service_id`: Variante heredada (legacy), soportada por retrocompatibilidad.

### 6.4 Ciclo de Vida del Evento en `Screen.php`

Cuando se despacha un evento a `POST /api/ui-event`, la clase `Screen` ejecuta el siguiente flujo garantizado:

1. **`initializeEventContext($incomingStorage, $queryParams)`:**
   - Reconstruye el árbol de componentes desde la caché de sesión (`reconstructScreenTreeFromCache()`).
   - Captura el estado original serializado: `$this->oldUI = $this->container->toJson()`.
   - **Inyección de variables de storage:** Localiza propiedades declaradas con visibilidad `protected` cuyo nombre inicie con `store_`. Si existen en el payload de almacenamiento recibido, inyecta su valor. Aquellas propiedades con sufijo `_crypt` (ej. `protected string $store_token_crypt;`) son **desencriptadas automáticamente** usando `decrypt()` de Laravel antes de la inyección.
   - **Inyección de referencias de componentes:** Localiza propiedades protegidas tipadas con clases de componentes (ej. `protected Input $user_email;`) y las enlaza automáticamente al componente del árbol cuyo nombre coincida con la propiedad (`$container->findByName(...)`).
2. **Ejecución del Handler:**
   - El controlador invoca el método correspondiente `$screen->$method($parameters)`. Durante la ejecución, el handler puede mutar propiedades de componentes, invocar helpers de acción (`$this->toast(...)`, `$this->changeTheme(...)`, `$this->redirect(...)`, etc.) o modificar variables `store_*`.
3. **`finalizeEventContext()`:**
   - Serializa el estado mutado: `$this->newUI = $this->container->toJson()`.
   - Persiste el nuevo árbol en la caché de pantalla (`cacheScreenSnapshot()`).
   - **Cálculo de Deltas (`buildDiffResponse`):** Compara `$this->oldUI` con `$this->newUI` mediante `UIDiffer::compare()`. **Garantía:** cada componente mutado incluye obligatoriamente el atributo `'type'` de `$this->newUI` para guiar al frontend en la deserialización reactiva.
   - **Recolección de Storage (`getStorageVariables`):** Inspecciona las propiedades `protected store_*`. Si el nombre termina en `_crypt`, el valor se **encripta automáticamente** con `encrypt()` de Laravel.
   - Envía los deltas y el almacenamiento actualizado al recolector `UIChangesCollector`.

### 6.5 Gestión del Estado Persistente (`X-USIM-Storage`)

El framework USIM permite persistir estado entre peticiones sin requerir tablas adicionales de sesión.

#### 6.5.1 Envelope de Respuesta del Storage
En la respuesta JSON, el backend entrega el almacenamiento en el siguiente formato:
```json
{
  "storage": {
    "my-app": "{\"store_theme\":\"dark\",\"store_token_crypt\":\"eyJpdiI6...\"}"
  }
}
```
Donde `"my-app"` corresponde a la clave configurada en `config('usim.front_store_key')` (por defecto `'my-app'` o `'usim'`).

#### 6.5.2 Obligaciones del Cliente
1. Almacenar el valor string de `storage[front_store_key]` (en `localStorage` o memoria del cliente).
2. Reenviarlo en cada solicitud posterior mediante el header HTTP `X-USIM-Storage: <string>` (o como fallback en el cuerpo JSON en la clave `storage` o `usim`).
3. Tratar las variables con sufijo `_crypt` como cadenas **completamente opacas**. El cliente no debe intentar desencriptarlas ni modificarlas.

---

## 7. Acciones del Sistema y Meta-Contratos (System Actions)

Además de los deltas sobre componentes de la interfaz, el protocolo contempla un conjunto de **acciones del sistema (meta-keys)** que operan a nivel global de la aplicación. Se emiten desde `Screen.php` a través de métodos utilitarios nativos.

### 7.1 Catálogo de Acciones del Sistema

#### 7.1.1 `close_modal`
Cierra cualquier modal o diálogo activo en el cliente.
- **Emisión en Screen:** `$this->closeModal()` (o handler genérico `$this->onCloseModal($params)`).
- **Payload:**
  ```json
  {
    "action": "close_modal"
  }
  ```

#### 7.1.2 `redirect`
Ordena al frontend navegar a una nueva ruta o recargar la vista actual.
- **Emisión en Screen:** `$this->redirect(?string $url = null)`. Si `$url` es `null`, se utiliza la redirección prevista (`redirect()->intended('/')`).
- **Payload:**
  ```json
  {
    "redirect": "/dashboard"
  }
  ```

#### 7.1.3 `toast`
Muestra una notificación transitoria flotante en el cliente.
- **Emisión en Screen:**
  ```php
  $this->toast(
      message: 'Operación realizada con éxito',
      type: 'success',        // 'info' | 'success' | 'warning' | 'error'
      duration: 5000,         // milisegundos
      openEffect: 'fade',     // 'fade' | 'slide' | 'zoom'
      showEffect: 'bounce',   // 'bounce' | 'pulse' | 'shake'
      closeEffect: 'fade',    // 'fade' | 'slide'
      position: 'top-right'   // 'top-right' | 'top-left' | 'bottom-right' | 'bottom-left' | 'top-center' | 'bottom-center'
  );
  ```
- **Payload:**
  ```json
  {
    "toast": {
      "message": "Operación realizada con éxito",
      "type": "success",
      "duration": 5000,
      "open_effect": "fade",
      "show_effect": "bounce",
      "close_effect": "fade",
      "position": "top-right"
    }
  }
  ```

#### 7.1.4 `abort`
Indica que el flujo actual fue cancelado por una condición de error o denegación de permisos.
- **Emisión en Screen:** `$this->abort(int $statusCode, string $message = '')`.
- **Emisión en Control de Acceso:** Devuelto por `checkAccess()` cuando el usuario carece de permisos suficientes.
- **Payload:**
  ```json
  {
    "abort": {
      "status_code": 403,
      "message": "Unauthorized: Insufficient permissions."
    }
  }
  ```
  *(Nota: en respuestas de control de acceso, puede aparecer como `code` en lugar de `status_code`. Los clientes deben soportar ambos campos).*

#### 7.1.5 `change_theme`
Ordena al frontend conmutar dinámicamente el tema visual activo de la aplicación.
- **Emisión en Screen:** `$this->changeTheme(string $theme)`.
- **Payload:**
  ```json
  {
    "change_theme": "dark"
  }
  ```
- **Valores comunes:** `"light"`, `"dark"`, `"system"`.

#### 7.1.6 `change_language`
Solicita al cliente cambiar el idioma activo. En el backend, `Screen::changeLanguage` actualiza además el locale de Laravel vía `app()->setLocale($language)`.
- **Emisión en Screen:** `$this->changeLanguage(string $language)`.
- **Payload:**
  ```json
  {
    "change_language": "es"
  }
  ```

#### 7.1.7 `update_modal`
Actualiza dinámicamente los componentes o propiedades de un modal activo sin reiniciarlo ni cerrarlo.
- **Emisión en Screen:** `$this->updateModal(array $content)`.
- **Payload:**
  ```json
  {
    "update_modal": {
      "120005001": {
        "text": "Paso 2 completado. Verificando datos..."
      }
    }
  }
  ```

#### 7.1.8 `agent_context` (Headless / Clientes de Inteligencia Artificial)
Metadatos semánticos emitidos en la carga de la pantalla para describir su propósito, parámetros esperados y salidas posibles.
- **Emisión en Screen:** Sobrescribiendo `Screen::getAgentContext(): array`.
- **Payload:**
  ```json
  {
    "agent_context": {
      "purpose": "Autenticación de usuarios por credenciales",
      "inputs": ["email", "password"],
      "outputs": ["redirect", "toast", "abort"],
      "constraints": "Contraseña mínima de 8 caracteres"
    }
  }
  ```

#### 7.1.9 `clear_uploaders` y `set_uploader_existing_file`
Contratos auxiliares para la gestión del ciclo de vida de archivos en componentes de tipo `uploader`:
- `clear_uploaders`: Limpia la cola o estado de los componentes uploader especificados.
- `set_uploader_existing_file`: Notifica al frontend la presencia de un archivo ya almacenado en el servidor para mostrarlo como preview.

---

### 7.2 Composición de Respuestas

El servidor combina libremente cambios de componentes y múltiples acciones de sistema en una sola carga útil JSON:

```json
{
  "50001234": {
    "type": "container",
    "parent": null
  },
  "toast": {
    "message": "Tema actualizado y sesión iniciada",
    "type": "success"
  },
  "change_theme": "dark",
  "storage": {
    "my-app": "{\"store_theme\":\"dark\"}"
  },
  "redirect": "/dashboard"
}
```

### 7.3 Orden de Procesamiento en el Cliente

Para asegurar una experiencia coherente y predecible, los clientes del protocolo USIM deben procesar el mensaje en el siguiente orden secuencial:

1. **Abort:** Si `abort` está presente, interrumpir el renderizado y presentar el estado de error (`status_code` / `code`).
2. **Deltas de Componentes:** Aplicar creaciones, mutaciones y eliminaciones al árbol de componentes en memoria.
3. **Temas y Localización:** Aplicar `change_theme` y `change_language`.
4. **Modales y Diálogos:** Procesar `update_modal` y posteriormente `close_modal`.
5. **Uploaders:** Aplicar `clear_uploaders` y `set_uploader_existing_file`.
6. **Notificaciones (`toast`):** Desplegar notificaciones transitorias.
7. **Storage:** Guardar el valor actualizado de `storage` para peticiones futuras.
8. **Redirección (`redirect`):** Ejecutar cualquier redirección **siempre al final**, dado que invalida la vista y el árbol actual.

---

## 8. Contrato de Autorización y Acceso en Pantallas (`Screen`)

Toda pantalla en USIM hereda un mecanismo estático de control de acceso antes de ser instanciada:

```php
public static function checkAccess(): array
```

### 8.1 Respuestas de Acceso
Devuelve una estructura normalizada `array{allowed: bool, action: ?string, params: array<string, mixed>}`:

1. **Acceso Concedido:**
   ```json
   {
     "allowed": true,
     "action": null,
     "params": []
   }
   ```

2. **Acceso Denegado a Invitados (No autenticados):**
   ```json
   {
     "allowed": false,
     "action": "redirect",
     "params": {
       "url": "http://example.com/auth/login",
       "message": "Please login to access this page."
     }
   }
   ```

3. **Acceso Denegado a Usuarios Autenticados (Sin permisos suficientes):**
   ```json
   {
     "allowed": false,
     "action": "abort",
     "params": {
       "code": 403,
       "message": "Unauthorized: Insufficient permissions."
     }
   }
   ```

### 8.2 Helpers de Seguridad y Resolución de Permisos
- `Screen::$visibility`: Nivel de visibilidad (`Visibility::PUBLIC`, `Visibility::AUTHENTICATED`, `Visibility::GUEST`).
- `Screen::authorize()`: Hook estático donde el desarrollador define la regla de autorización (apoyándose en `requireAuth()`, `requireRole('admin')` o `requirePermission('manage-users')`).
- `Screen::getScreenSlug()`: Convierte la ubicación del Screen en un slug canónico (ej. `App\UI\Screens\Admin\UserManagerScreen` → `admin.user_manager`).
- `Screen::resolvedPermissions()`: Mapea automáticamente los permisos definidos en `permissions()` y `requiredPermissions()` combinándolos con el slug (ej. `admin.user_manager.access`).
- `Screen::userCan(string $permission)`: Determina si el usuario autenticado posee el permiso especificado en el contexto de dicha pantalla.
- `Screen::getRoutePath()`: Deriva la ruta URL pública del Screen de forma kebab-case a partir de su espacio de nombres.
