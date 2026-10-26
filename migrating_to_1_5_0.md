# Guía de Actualización y Migración a `idei/usim` v1.5.0

Esta actualización es **100% retrocompatible** (no introduce *breaking changes*), e incorpora soporte nativo de parámetros en la URL (*deep-linking* y preservación en recargas `F5`), soporte para carruseles verticales/personalizados (`aspect()` y `objectFit()`) y estilos visuales para etiquetas `secondary` y `muted`.

---

## Paso 1: Actualizar el paquete y republicar assets

Debido a que esta versión incluye mejoras tanto en PHP como en JavaScript (`ui-renderer.js`, `carousel/index.js`) y CSS (`ui-components.css`, `carousel/index.css`), es **obligatorio** actualizar los assets publicados en `public/vendor/idei/usim`:

```bash
composer update idei/usim
php artisan vendor:publish --tag=usim-assets --force
```

*(Si tu proyecto utiliza un script de sincronización propio, asegúrate de copiar `resources/assets/js` y `resources/assets/css` del paquete hacia `public/vendor/idei/usim`).*

---

## Paso 2: Migrar pantallas con parámetros hacia URL Params + `state_*`

Anteriormente, para pasar un identificador entre pantallas y sobrevivir a una recarga (`F5`), era necesario recurrir a `session()`, variables `store_*` o leer manualmente `$this->getQueryParams()` por un lado y `$params` por otro.

A partir de la **v1.5.0**:
1. `$this->showInto(MiPantalla::class, params: ['post_id' => 5])` refleja automáticamente `?post_id=5` en la URL del navegador.
2. Al recargar con `F5` o abrir el enlace directo `/mi-pantalla?post_id=5`, el navegador **conserva** los query params (incluso cuando limpia el flag interno `reset=true`) y `UIController` los inyecta automáticamente en `buildBaseUI(Container $container, ...$params)`.

### Patrón recomendado en la pantalla origen:

```php
// Navega y actualiza la barra de direcciones a: /publish-post?post_id=5
$this->showInto(PublishPost::class, params: ['post_id' => 5], updateBrowserUrl: true, force: true);

// O usando el atajo navigate():
$this->navigate(PublishPost::class, params: ['post_id' => 5]);
```

### Patrón recomendado en la pantalla destino:

Unifica la recepción del parámetro en `buildBaseUI` y guárdalo en una propiedad `state_*` (persistida en el servidor) para utilizarlo en los manejadores de eventos (`on*`) posteriores:

```php
class PublishPost extends Screen
{
    protected ?int $state_post_id = null;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        // Funciona igual si llega por showInto() o por URL directa / F5 (?post_id=5)
        if (isset($params['post_id'])) {
            $this->state_post_id = (int) $params['post_id'];
        }

        // Ya no necesitas hacer fallback a $this->getQueryParams()['post_id'] ni usar store_*
    }

    public function onSave(): void
    {
        // $this->state_post_id se restaura automáticamente en cada evento desde el servidor
        $post = Post::findOrFail($this->state_post_id);
    }
}
```

> **Nota sobre firmas de `buildBaseUI`:** Asegúrate de que tus pantallas mantengan la firma estándar `protected function buildBaseUI(Container $container, ...$params): void` con el operador variádico `...$params`.

---

## Paso 3: Uso de Carruseles Verticales (`9:16`) y Ajuste de Medios

Si tenías parches CSS manuales para mostrar Reels, Shorts, TikToks o videos verticales en `UI::carousel()`, ahora puedes eliminarlos y usar los métodos fluidos nativos junto con el enum `Idei\Usim\Enums\AspectRatio`:

```php
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\ValueObjects\Size;

UI::carousel('reel_preview')
    ->width(Size::px(315))
    ->height(Size::px(560))
    ->aspect(AspectRatio::_9_16)
    ->objectFit('contain') // 'cover' | 'contain' | 'scale-down'
    ->items($mediaItems);
```

---

## Paso 4: Etiquetas `secondary` y `muted` con variante `badge`

Si definías colores inline (`->backgroundColor(...)`, `->borderColor(...)`) en `UI::label()` porque `->secondary()` o `->muted()` no tenían fondo ni borde al combinarse con `->variant('badge')`, ahora puedes usar directamente los métodos semánticos:

```php
UI::label('status_badge')
    ->text('Borrador')
    ->variant('badge')
    ->secondary(); // o ->muted()
```

