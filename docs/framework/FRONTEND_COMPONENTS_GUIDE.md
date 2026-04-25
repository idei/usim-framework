# Guía de Componentes Frontend USIM

Esta guía explica cómo entender, agregar, modificar y fixear componentes frontend del framework USIM en su arquitectura actual modular.

Está pensada para alguien que entra al proyecto sin contexto previo y necesita tocar el renderer sin romper el contrato server-driven.

## Objetivo

El frontend de USIM no decide la UI: la renderiza.

La fuente de verdad sigue siendo backend + contrato JSON. El frontend tiene cuatro responsabilidades:

1. Resolver el tipo de componente recibido en el JSON.
2. Construir el DOM correspondiente.
3. Enviar eventos al backend.
4. Aplicar deltas devueltos por el backend sin recargar toda la pantalla.

Si un cambio puede resolverse mejor en PHP o en el contrato JSON, esa suele ser la opción correcta.

## Mapa actual del frontend

### Archivos base

- [packages/idei/usim/resources/views/app.blade.php](/workspaces/usim-framework/packages/idei/usim/resources/views/app.blade.php)
- [packages/idei/usim/resources/assets/js/ui-renderer.js](/workspaces/usim-framework/packages/idei/usim/resources/assets/js/ui-renderer.js)
- [packages/idei/usim/resources/assets/js/component-registry.js](/workspaces/usim-framework/packages/idei/usim/resources/assets/js/component-registry.js)
- [packages/idei/usim/resources/assets/css/ui-components.css](/workspaces/usim-framework/packages/idei/usim/resources/assets/css/ui-components.css)
- [packages/idei/usim/resources/assets/css/ui-theme-tokens.css](/workspaces/usim-framework/packages/idei/usim/resources/assets/css/ui-theme-tokens.css)

### Componentes modulares

- [packages/idei/usim/resources/assets/js/components](/workspaces/usim-framework/packages/idei/usim/resources/assets/js/components)
- [packages/idei/usim/resources/assets/css/components](/workspaces/usim-framework/packages/idei/usim/resources/assets/css/components)

Cada componente vive idealmente en su propia carpeta:

```text
resources/assets/
├── js/
│   ├── component-registry.js
│   ├── ui-renderer.js
│   └── components/
│       ├── button/index.js
│       ├── input/index.js
│       ├── table/index.js
│       ├── shared/ui-event.js
│       └── shared/content-render.js
└── css/
    ├── ui-components.css
    ├── ui-theme-tokens.css
    └── components/
        ├── uploader/index.css
        ├── carousel/index.css
        └── image-crop-editor/index.css
```

## Cómo se carga todo

La shell del paquete carga los assets en orden explícito desde [packages/idei/usim/resources/views/app.blade.php](/workspaces/usim-framework/packages/idei/usim/resources/views/app.blade.php).

El orden importa:

1. `component-registry.js`
2. `ui-renderer.js`
3. helpers compartidos (`shared/ui-event.js`, `shared/content-render.js`)
4. componentes modulares (`components/.../index.js`)
5. CSS base y CSS de componentes específicos

Consecuencia práctica:

- `ui-renderer.js` define la base (`UIComponent`, renderer, diffing, modales, bootstrap).
- Los componentes modulares se registran después y reemplazan implementaciones por tipo.
- Si agregas un nuevo archivo JS o CSS, debes cargarlo explícitamente en `app.blade.php`.

No hay autodiscovery de assets frontend.

## Modelo mental correcto

### 1. `UIComponent` es la base común

La clase base está en [packages/idei/usim/resources/assets/js/ui-renderer.js](/workspaces/usim-framework/packages/idei/usim/resources/assets/js/ui-renderer.js#L331).

Todo componente modular hereda de ella para reutilizar:

- `applyCommonAttributes(element)`
- `mount(parentElement)`
- `getComponentId()`
- utilidades de eventos ya existentes en el renderer

Si un componente necesita estilos, ids, atributos comunes, responsive metadata o posicionamiento, normalmente eso ya baja por `applyCommonAttributes()`.

### 2. `ComponentFactory` ya no conoce los componentes concretos

La resolución actual está en [packages/idei/usim/resources/assets/js/ui-renderer.js](/workspaces/usim-framework/packages/idei/usim/resources/assets/js/ui-renderer.js#L746).

El factory hace esto:

1. toma `config.type`
2. pregunta al registry global
3. si no encuentra factory, emite warning y devuelve `null`

Eso significa que agregar un componente nuevo ya no debería implicar tocar un `switch` en `ui-renderer.js`.

### 3. El registry es el punto de extensión

El registro global está en [packages/idei/usim/resources/assets/js/component-registry.js](/workspaces/usim-framework/packages/idei/usim/resources/assets/js/component-registry.js).

API disponible:

- `register(type, factory, metadata)`
- `unregister(type)`
- `has(type)`
- `list()`
- `create(type, id, config)`

Patrón habitual en un componente modular:

```js
if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.unregister('button');
    window.USIM_COMPONENTS.register('button', (id, config) => new UsimButtonComponent(id, config), {
        source: 'modular',
    });
}
```

Usa `unregister(...)` solo cuando estás reemplazando un tipo ya existente. Para tipos nuevos no hace falta.

## Flujo runtime de una pantalla

1. Laravel sirve la shell Blade.
2. La shell define globals como `window.SCREEN_NAME`, `window.MENU_SERVICE`, `window.PARAMS`.
3. `loadScreenUI()` pide `GET /api/ui/{screen}`.
4. `UIRenderer` recorre el JSON.
5. `ComponentFactory` crea instancias usando el registry.
6. El renderer monta componentes en `#main` o `#menu`.
7. Los eventos van a `POST /api/ui-event`.
8. El backend responde con un delta JSON.
9. `handleUIUpdate(...)` aplica cambios incrementales.

Los componentes no deben inventar un flujo alternativo si el helper compartido ya cubre ese caso.

## Dónde vive cada tipo de lógica

### Mantener en `ui-renderer.js`

- `UIComponent`
- renderer principal y diffing
- bootstrap general (`loadScreenUI`, `loadMenuUI`)
- modal manager global
- helpers verdaderamente transversales del renderer

### Mover a `components/<tipo>/index.js`

- render específico del componente
- listeners específicos de ese componente
- `update(newConfig)` del componente
- registro del tipo en `USIM_COMPONENTS`

### Mover a `components/shared/*`

- llamadas repetidas a `/api/ui-event`
- render repetido de iconos/texto/markdown
- helpers compartidos entre varios componentes modulares

Regla simple:

si el código depende del tipo concreto de componente, no debería vivir en `ui-renderer.js`.

## Cómo modificar un componente existente

Ejemplo real: [packages/idei/usim/resources/assets/js/components/button/index.js](/workspaces/usim-framework/packages/idei/usim/resources/assets/js/components/button/index.js).

### Paso 1. Encuentra el módulo correcto

Busca primero por tipo de JSON o por el archivo del componente en `resources/assets/js/components`.

No empieces editando `ui-renderer.js` si el comportamiento ya está encapsulado en un módulo.

### Paso 2. Revisa si ya existe un helper compartido

Antes de duplicar lógica, revisa:

- [packages/idei/usim/resources/assets/js/components/shared/ui-event.js](/workspaces/usim-framework/packages/idei/usim/resources/assets/js/components/shared/ui-event.js)
- [packages/idei/usim/resources/assets/js/components/shared/content-render.js](/workspaces/usim-framework/packages/idei/usim/resources/assets/js/components/shared/content-render.js)

Ejemplos:

- si envías eventos UI, usa `sendUiEvent(...)`
- si renderizas contenido de botón/icono, usa `renderButtonContent(...)`
- si renderizas label/markdown/html, usa `renderLabelContent(...)`

### Paso 3. Verifica el contrato que espera el renderer

Algunos componentes todavía dependen de métodos esperados por el diff updater.

Ejemplo importante:

- el botón debe conservar `_applyContent(...)`, porque el renderer lo usa durante updates incrementales.

Antes de renombrar un método público del componente, busca llamadas desde `ui-renderer.js`.

### Paso 4. Valida el cambio publicado

Después de editar un asset del paquete, ejecuta:

```bash
php artisan vendor:publish --tag=usim-assets --force
```

Si no publicas, el navegador seguirá usando los archivos de `public/vendor/idei/usim` y parecerá que tu cambio no funciona.

## Cómo agregar un componente nuevo

Hay dos escenarios distintos.

### Escenario A. Nuevo módulo para un tipo ya existente

Esto se usa cuando el tipo backend ya existe, pero quieres mover o reemplazar la implementación frontend.

Pasos:

1. crea `resources/assets/js/components/<tipo>/index.js`
2. haz que extienda `UIComponent`
3. implementa `render()` y, si aplica, `update(newConfig)`
4. registra el tipo con `window.USIM_COMPONENTS`
5. si necesita estilos propios, crea `resources/assets/css/components/<tipo>/index.css`
6. carga el JS y el CSS en `app.blade.php`
7. publica assets
8. prueba la pantalla real que usa ese tipo

### Escenario B. Nuevo tipo de componente end-to-end

Esto requiere frontend + backend.

Además del módulo JS/CSS, debes tocar:

- builder PHP en `packages/idei/usim/src/Services/Components/`
- factory `UI::...` en `packages/idei/usim/src/Services/UI.php`
- mapping del tipo en `packages/idei/usim/src/Services/Screen.php`

El frontend solo renderiza un `type` que ya viene desde backend. Si el backend no puede emitir ese `type`, el componente nunca se instanciará.

## Plantilla mínima de un componente nuevo

```js
class UsimExampleComponent extends UIComponent {
    render() {
        const element = document.createElement('div');
        element.className = 'ui-example';
        element.textContent = this.config.text || '';
        return this.applyCommonAttributes(element);
    }

    update(newConfig) {
        this.config = { ...this.config, ...newConfig };

        if (!this.element) {
            return;
        }

        this.element.textContent = this.config.text || '';
    }
}

if (window.USIM_COMPONENTS?.register) {
    window.USIM_COMPONENTS.register('example', (id, config) => new UsimExampleComponent(id, config), {
        source: 'modular',
    });
}
```

## Cómo fixear un bug correctamente

### 1. Empieza por el síntoma observable

Ejemplos:

- “el botón desaparece al cambiar theme”
- “el select dispara el evento pero no actualiza UI”
- “el menú no se monta”

### 2. Ubica el slice controlador

Pregunta clave:

¿el bug está en uno de estos lugares?

- carga de assets en Blade
- registry / resolución de tipo
- render específico del componente
- helper compartido
- diff update en `ui-renderer.js`
- publish de assets no actualizado

### 3. Revisa primero los contratos de integración

La mayoría de los bugs recientes en esta migración no fueron por HTML/CSS, sino por contratos de integración:

- un método esperado por el renderer ya no existía
- el helper resolvía mal `globalRenderer`
- una función global fue eliminada durante cleanup (`loadMenuUI`)
- el asset correcto no estaba publicado

### 4. Haz el fix en el nivel correcto

- si falla el envío de evento en varios componentes, corrige el helper compartido
- si falla solo uno, corrige el módulo de ese componente
- si el problema es de bootstrap, corrige `ui-renderer.js`

## Helpers compartidos existentes

### `shared/ui-event.js`

Archivo: [packages/idei/usim/resources/assets/js/components/shared/ui-event.js](/workspaces/usim-framework/packages/idei/usim/resources/assets/js/components/shared/ui-event.js)

Úsalo para:

- enviar eventos estándar a `/api/ui-event`
- evitar duplicar headers CSRF y `X-USIM-Storage`
- aplicar updates usando el renderer global

API:

- `sendUiEvent({ componentId, event, action, parameters, credentials })`
- `applyUiUpdate(result)`

### `shared/content-render.js`

Úsalo para:

- render de iconos y contenido en botones
- render de labels con texto, markdown o HTML

Si varios componentes repiten lógica de contenido, muévela aquí.

## CSS: reglas prácticas

### Usa CSS base cuando alcance

Muchos componentes simples no necesitan CSS propio y pueden vivir bien con:

- [packages/idei/usim/resources/assets/css/ui-components.css](/workspaces/usim-framework/packages/idei/usim/resources/assets/css/ui-components.css)
- [packages/idei/usim/resources/assets/css/ui-theme-tokens.css](/workspaces/usim-framework/packages/idei/usim/resources/assets/css/ui-theme-tokens.css)

### Crea CSS por componente cuando haya identidad real

Hoy está aplicado sobre todo a:

- uploader
- carousel
- image crop editor

Si agregas un CSS nuevo:

1. créalo en `css/components/<tipo>/index.css`
2. enlázalo en `app.blade.php`
3. publica assets

### No dejes duplicados top-level

La estructura vieja usaba archivos como `uploader-component.js` o `carousel-component.css` en la raíz de assets.

Ese patrón ya fue removido. No vuelvas a introducir:

- `resources/assets/js/<algo>-component.js`
- `resources/assets/css/<algo>-component.css`

Usa siempre carpetas modulares `components/<tipo>/index.*`.

## Checklist para agregar o modificar componentes

### Cambios frontend-only

```bash
node --check packages/idei/usim/resources/assets/js/ui-renderer.js
find packages/idei/usim/resources/assets/js/components -name "*.js" | sort | xargs -I{} node --check "{}"
php artisan vendor:publish --tag=usim-assets --force
```

### Si el cambio toca contrato backend también

```bash
composer dump-autoload
php artisan usim:discover
php artisan vendor:publish --tag=usim-assets --force
php artisan test
```

Si existe un test puntual de la screen o demo afectada, úsalo antes de correr toda la suite.

## Pitfalls frecuentes

### 1. Olvidar `vendor:publish`

Síntoma: “el archivo fuente cambió pero el navegador sigue igual”.

### 2. Romper un método esperado por el renderer

Síntoma: el componente se crea, pero falla en updates incrementales.

### 3. Duplicar lógica de fetch en cada componente

Síntoma: headers inconsistentes, bugs con storage, fixes repetidos.

### 4. Agregar lógica de negocio en el cliente

Síntoma: el frontend empieza a decidir estado o validaciones que deberían venir desde PHP.

### 5. Crear CSS/JS que nadie carga

Síntoma: el archivo existe pero nunca se ejecuta ni se aplica.

Siempre confirma la carga en `app.blade.php`.

## Estrategia recomendada para nuevos cambios

1. identifica el tipo JSON del componente afectado
2. ubica su módulo en `resources/assets/js/components`
3. revisa si hay helper compartido reutilizable
4. haz el cambio mínimo en el módulo correcto
5. valida sintaxis
6. publica assets
7. prueba la screen real
8. si el patrón se repite, recién ahí extrae helper compartido

## Qué archivo tocar según el problema

### “No se instancia el componente”

Revisa:

- `type` emitido por backend
- registry en `component-registry.js`
- registro del componente en su `index.js`
- script cargado en `app.blade.php`

### “Se renderiza pero no responde a eventos”

Revisa:

- listeners del módulo
- uso de `sendUiEvent(...)`
- payload enviado (`component_id`, `event`, `action`, `parameters`)

### “El backend responde pero la UI no cambia”

Revisa:

- `applyUiUpdate(...)`
- `globalRenderer`
- contratos de `update(newConfig)`
- métodos especiales esperados por el diff updater

### “Se ve mal o no toma estilos”

Revisa:

- clases CSS emitidas por el componente
- si el CSS vive en base global o en `css/components/...`
- carga del `link` en `app.blade.php`

## Resumen corto

- `ui-renderer.js` ahora es infraestructura, no catálogo de componentes.
- los componentes viven en `resources/assets/js/components/<tipo>/index.js`
- la extensión ocurre vía `window.USIM_COMPONENTS`
- los eventos compartidos van por `shared/ui-event.js`
- el contenido repetido va por `shared/content-render.js`
- si cambias assets del paquete, debes publicar
- si agregas un tipo nuevo, probablemente también debes tocar backend PHP

Con estas reglas, cualquier programador debería poder agregar, modificar o fixear componentes sin reabrir el renderer monolítico.
