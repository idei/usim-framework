# Guía de TableModels en USIM Framework

Los **TableModels** en el framework USIM proporcionan la capa de datos, columnas y paginación para el componente visual `Table`.

---

## 1. Arquitectura de TableModels

El framework ofrece dos clases base:

1. **`Idei\Usim\DataTable\AbstractTableModel`**:
   Clase base de bajo nivel que define el contrato elemental (`getColumns()`, `getPageData()`, `getFormattedPageData()`, `countTotal()`). Útil cuando los datos provienen de fuentes no estándar (arrays en memoria, APIs externas, logs planos).

2. **`Idei\Usim\DataTable\AbstractListingTableModel`**:
   Clase base de alto nivel diseñada para modelos respaldados por servicios de listado (como `EloquentListingService` o `ModelQueryableService`).
   - Aplica el **Template Method Pattern**.
   - Maneja automáticamente paginación (`current_page`, `per_page`), orden (`sort_column`, `sort_direction`) y búsqueda (`search_term`).
   - Reduce en un **70%** el código repetitivo (*Boilerplate / DRY*) en cada modelo de tabla.
   - Admite inyección de dependencias para tests mediante `setListingService()`.

---

## 2. Cómo Implementar un `AbstractListingTableModel`

Para crear una tabla respaldada por un `EloquentListingService`, solo necesitas extender `AbstractListingTableModel` e implementar:
1. `resolveListingService()`: Servicio encargado de la consulta y conteo.
2. `getColumns()`: Definición de columnas y ordenamiento.
3. `formatRow(object $item)`: Transformación de una entidad en una fila formateada para la UI.

### Ejemplo Completo

```php
namespace App\UI\Screens\Admin\TableModels;

use App\Services\Role\RoleListingService;
use Idei\Usim\DataTable\AbstractListingTableModel;
use Idei\Usim\Models\UsimRole;

/**
 * @extends AbstractListingTableModel<UsimRole>
 */
class RoleTableModel extends AbstractListingTableModel
{
    /**
     * Resuelve el servicio de consulta asociado.
     */
    protected function resolveListingService(): RoleListingService
    {
        return app(RoleListingService::class);
    }

    /**
     * Define las columnas visibles en la tabla.
     */
    public function getColumns(): array
    {
        return [
            'name' => [
                'label' => t('screen.admin.users_manager.roles_column_name'),
                'sort_by' => 'name'
            ],
            'home_screen' => [
                'label' => t('screen.admin.users_manager.roles_column_home_screen'),
                'sort_by' => 'home_screen'
            ],
            'priority' => [
                'label' => t('screen.admin.users_manager.roles_column_priority'),
                'sort_by' => 'priority'
            ],
        ];
    }

    /**
     * Formatea un registro individual para su renderizado.
     * Siempre debe incluir `_model_id` para permitir selección y acciones.
     *
     * @param UsimRole $item
     * @return array<string, mixed>
     */
    protected function formatRow(object $item): array
    {
        return [
            '_model_id' => $item->id,
            'name' => t("role.{$item->name}.name"),
            'home_screen' => str_replace('App\\UI\\Screens\\', '', (string) $item->home_screen),
            'priority' => $item->priority,
        ];
    }
}
```

---

## 3. Uso en Componentes `Table`

```php
$table = UI::table('roles_table');
$table->pagination(10); // 10 registros por página (o 0 para deshabilitar paginación)
$table->sortedBy('name');
$table->dataModel(RoleTableModel::class);
$table->selectionMode(SelectionMode::SINGLE);
```

### Métodos Auxiliares Disponibles en `AbstractListingTableModel`

| Método | Descripción |
|---|---|
| `getListingService(): object` | Obtiene la instancia actual del servicio de listado. |
| `setListingService(object $service): self` | Permite inyectar un mock o servicio alternativo (DIP / Testing). |
| `getFilters(): array` | Puede sobrescribirse para pasar filtros adicionales a la consulta (`paginate`). |
| `countTotal(): int` | Ejecuta `countMatching()` delegando en el servicio. |
| `getFormattedPageData(int $page, int $perPage): array` | Devuelve las filas transformadas por `formatRow()`. |

