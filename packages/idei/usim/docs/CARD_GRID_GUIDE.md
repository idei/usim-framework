# Guía del Componente `CardGrid` (`UI::cardGrid()`)

`CardGrid` es el componente de **grilla 2D responsive de tarjetas** de USIM (introducido en **v2.20.0**), diseñado para catálogos visuales, directorios, galerías, productos, cursos o tableros de fichas.

---

## 1. Cuándo usar `CardGrid` vs `Table` vs `ItemList`

| Componente | Ideal para | Capacidades clave |
|---|---|---|
| `UI::table()` | Datos tabulares densos de backoffice | Columnas fijas, paginación y orden por columna |
| `UI::itemList()` | Listas 1D, selectores en formularios y CRUD inline | Selección simple/múltiple persistente, `Listable`/`Creatable`/`Updatable`/`Deletable` |
| **`UI::cardGrid()`** | **Catálogos visuales, fichas ricas y exploración responsive** | **Grilla CSS responsive, búsqueda + filtros integrados + ordenamiento, paginado (`pages` / `load_more`), acciones por tarjeta y parcheo quirúrgico (`patchCard`)** |

---

## 2. Arquitectura y Contratos (`CardListable`)

`CardGrid` sigue el mismo principio de desacoplamiento que `ItemList`: la `Screen` no construye consultas SQL ni formatea filas manualmente, sino que vincula un servicio que implementa `Idei\Usim\Contracts\CardGrid\CardListable`:

```php
namespace App\Services\Catalog;

use Idei\Usim\Contracts\CardGrid\CardListable;
use Idei\Usim\DTOs\CardActionDTO;
use Idei\Usim\DTOs\CardItemDTO;
use Idei\Usim\DTOs\CardPageResult;
use Idei\Usim\DTOs\CardQuery;
use Idei\Usim\Enums\Style;

class ProductCardService implements CardListable
{
    public function query(CardQuery $query): CardPageResult
    {
        $builder = Product::query()
            ->when($query->search !== '', fn ($q) => $q->where('name', 'like', "%{$query->search}%"))
            ->when($query->filter('category'), fn ($q, $cat) => $q->where('category', $cat))
            ->when($query->filter('in_stock'), fn ($q) => $q->where('stock', '>', 0))
            ->orderBy($query->sortBy ?? 'name', $query->sortDirection);

        $total = (clone $builder)->count();
        $rows = $builder->offset($query->offset())->limit($query->perPage)->get();

        $cards = $rows->map(fn (Product $p) => CardItemDTO::make(
            id: $p->id,
            title: $p->name,
            subtitle: $p->brand,
            eyebrow: $p->sku,
            description: $p->short_description,
            image: $p->cover_url,
            badge: $p->stock > 0 ? 'En stock' : 'Agotado',
            badgeStyle: $p->stock > 0 ? Style::SUCCESS : Style::DANGER,
            metric: '$' . number_format($p->price, 0, ',', '.'),
            metricLabel: 'ARS',
            metricStyle: Style::PRIMARY,
            attributes: [
                ['icon' => 'box', 'label' => 'Stock', 'value' => (string) $p->stock],
            ],
            tags: $p->tags ?? [],
            footerText: "Actualizado {$p->updated_at->diffForHumans()}",
            actions: [
                CardActionDTO::make(
                    label: 'Ver',
                    action: 'view_product',
                    parameters: ['product_id' => $p->id],
                    icon: 'eye',
                    style: Style::PRIMARY,
                    variant: 'solid',
                ),
                CardActionDTO::make(
                    label: 'Favorito',
                    action: 'toggle_favorite',
                    parameters: ['product_id' => $p->id],
                    icon: 'heart',
                ),
                // A partir de la 3ª acción (o con overflow: true), colapsa automáticamente en el menú ⋮
                CardActionDTO::make(
                    label: 'Archivar',
                    action: 'archive_product',
                    parameters: ['product_id' => $p->id],
                    icon: 'trash',
                    style: Style::DANGER,
                ),
            ],
            clickAction: 'view_product',
            clickParameters: ['product_id' => $p->id],
            style: $p->featured ? Style::PRIMARY : Style::DEFAULT,
            featured: (bool) $p->featured,
        ));

        return CardPageResult::make(
            items: $cards,
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
        );
    }
}
```

### Compatibilidad con servicios `Listable` existentes
Si ya tenés un servicio que implementa `Idei\Usim\Contracts\ItemList\Listable`, podés pasarlo directamente a `->service(MiListableService::class)`: `CardGrid` adapta automáticamente los `ListItemDTO` a `CardItemDTO` y pagina la colección con `CardPageResult::fromCollection()`.

---

## 3. Anatomía Estandarizada de la Tarjeta (`CardItemDTO`)

Para preservar jerarquía visual, alturas parejas por fila y compatibilidad automática con **Light y Dark mode**, cada tarjeta usa 6 zonas tipadas (sin HTML crudo):

1. **Media / Visual**: `image` (lazy-loaded con fallback automático si falla), `imageAlt`, `imageFit` (`cover` | `contain`), `aspectRatio` (ej. `'16/9'`, `'4/3'`, `'1/1'`), o `icon` / `avatar` cuando no hay foto.
2. **Header**: `eyebrow` (micro-etiqueta superior), `title` *(obligatorio, line-clamp 2)*, `subtitle` *(line-clamp 1)* y `badge` + `badgeStyle` (sobre la media si hay imagen, o alineado en cabecera si no).
3. **Highlight (opcional)**: `metric`, `metricLabel`, `metricStyle` (precio, puntaje, porcentaje).
4. **Body**: `description` *(line-clamp 3)*, `attributes` (pares `{ icon?, label, value }`) y `tags` (muestra hasta `max_visible_tags` + chip `+N`).
5. **Footer y Acciones**: siempre alineado al borde inferior (`margin-top: auto`). Muestra `footerText` y hasta `max_visible_actions` botones visibles (por defecto `2`); el resto pasa automáticamente al menú contextual **`⋮`**.
6. **Estilo y Estado**: `style` (`Idei\Usim\Enums\Style`), `featured` (destacada), `disabled`, `accentColor` (acento controlado en borde superior/izquierdo).

---

## 4. Toolbar Integrada: Búsqueda, Filtros y Ordenamiento

Los controles de consulta viven dentro del propio `CardGrid` y ejecutan peticiones de solo lectura hacia `GET /api/ui-data` (con `debounce`, cancelación automática vía `AbortController` y reset automático a `page = 1`):

```php
$grid = UI::cardGrid('products_grid')
    ->title('Catálogo de Productos')
    ->subtitle('Explora, filtra y gestiona los productos disponibles')
    ->service(ProductCardService::class)
    ->searchable(true, 'Buscar por nombre, SKU o marca...')
    ->debounce(250)
    ->sortOptions([
        ['field' => 'name', 'label' => 'Nombre'],
        ['field' => 'price', 'label' => 'Precio'],
    ])
    ->defaultSort('name', 'asc')
    ->addSelectFilter('category', 'Categoría', ['audio' => 'Audio', 'video' => 'Video'])
    ->addMultiSelectFilter('tags', 'Etiquetas', ['4K' => '4K', 'Oferta' => 'Oferta'])
    ->addToggleFilter('in_stock', 'Solo en stock')
    ->addChipsFilter('tier', 'Gama', ['basic' => 'Inicial', 'pro' => 'Pro'])
    ->paginationMode('pages') // o 'load_more'
    ->perPage(12)
    ->perPageOptions([12, 24, 36, 48])
    ->minCardWidth('280px')
    ->maxColumns(4)
    ->cardLayout('vertical') // o 'horizontal'
    ->variant('elevated')    // 'elevated' | 'outlined' | 'flat'
    ->density('comfortable') // 'compact' | 'comfortable' | 'spacious'
    ->showLayoutToggle(true)
    ->onCardClick('view_product');
```

- **Chips de filtros activos**: aparecen automáticamente al activar cualquier filtro, con borrado individual (`×`) y botón `"Limpiar filtros"`.
- **Responsive móvil**: en pantallas angostas (`<= 720px`) los filtros se colapsan bajo un botón `"Filtros (N)"` y las tarjetas horizontales pasan automáticamente a formato vertical.

---

## 5. Uso Declarativo en `.layout.yaml`

`CardGrid` soporta el prefijo automático **`grd_`** y el alias explícito **`component: card_grid`** (o `cardgrid`), junto con `i18n: auto`:

```yaml
name: catalog_wrapper
appearance: card
max_width: 1200
center_horizontal: true
i18n: auto

grid:
  cols: 12
  gap: m
  padding: l
  default_span: 12

  children:
    - movies_grid:
        component: card_grid
        service: App\Services\Movie\MovieCardGridService
        searchable: true
        debounce: 250
        per_page: 6
        per_page_options: [6, 12, 18]
        min_card_width: 270px
        max_columns: 3
        variant: elevated
        show_layout_toggle: true
        on_card_click: view_movie
```

> [!TIP]
> Gracias a la hidratación diferida de `CardGrid`, podés declarar `service:` antes que `per_page:`, `sort_by:` o `filters:` en YAML (o ajustarlos en `startingUI()`) y solo se ejecutará una única consulta inicial con la configuración final.

---

## 6. Parcheo Quirúrgico vs Recarga (`patchCard` vs `reload`)

Cuando una acción de tarjeta modifica **solo un ítem** (marcar favorito/estrella, fijar un pin, cambiar un estado o deshabilitar una acción), usá los métodos quirúrgicos para evitar un segundo `GET /api/ui-data` y evitar reconstruir el resto de la grilla:

```php
public function onToggleFavorite(array $params): void
{
    $movieId = (int) ($params['movie_id'] ?? $params['id'] ?? 0);
    $added = app(MovieCardGridService::class)->toggleFavorite($movieId);

    // Envía únicamente { card_patches: { seq, ops: [...] } } en el diff de POST /api/ui-event
    $this->movies_grid->patchCard($movieId, [
        'badge' => $added ? '★ Favorita' : 'Destacada',
        'badge_style' => $added ? Style::WARNING : Style::PRIMARY,
        'footer_text' => $added ? 'En tu lista' : '148 min',
    ]);
}
```

Métodos disponibles:
- `patchCard($id, array $changes)`: merge parcial en el cliente (los `null` explícitos limpian el slot; reutiliza el `<img>` existente si la URL no cambió).
- `patchCard($id, CardItemDTO $card)`: reemplaza únicamente esa tarjeta en el DOM.
- `removeCard($id)`: elimina solo esa tarjeta del DOM y decrementa el contador total.
- `prependCard(CardItemDTO|array $card)`: inserta una nueva tarjeta al inicio sin re-consultar.
- `reload()`: usar solo cuando la mutación altera el ordenamiento o el conjunto filtrado global de la página actual.

---

## 7. Demo y Referencia Rápida

- **Pantalla demo**: `App\UI\Screens\Demo\CardGridDemo` (accesible desde el menú **Demos → Grilla de Tarjetas**).
- **Servicio demo**: `App\Services\Movie\MovieCardGridService`.
- **Tests de referencia**: `tests/Unit/CardGridComponentTest.php` y `tests/Feature/CardGridServiceWiringTest.php`.
- **Referencia técnica para IA**: [CARD_GRID_AI_REFERENCE.md](CARD_GRID_AI_REFERENCE.md).
