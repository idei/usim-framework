<?php
namespace Idei\Usim\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UsimUnit extends Model
{
    /**
     * Los atributos que son asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'type',
        'parent_id',
    ];

    /**
     * Relación: Una unidad puede tener una unidad "padre".
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(UsimUnit::class, 'parent_id');
    }

    /**
     * Relación: Una unidad puede tener múltiples unidades "hijas" directas.
     */
    public function children(): HasMany
    {
        return $this->hasMany(UsimUnit::class, 'parent_id');
    }

    /**
     * Relación Recursiva: Obtiene todos los descendientes (hijos, nietos, etc.).
     * Útil para cargar el árbol completo hacia abajo con eager loading: Unit::with('descendants')->get();
     */
    public function descendants(): HasMany
    {
        return $this->children()->with('descendants');
    }

    /**
     * Relación Recursiva: Obtiene todos los ancestros (padre, abuelo, etc.).
     * Útil para migas de pan (breadcrumbs) o validaciones de jerarquía hacia arriba.
     */
    public function ancestors(): BelongsTo
    {
        return $this->parent()->with('ancestors');
    }

    /**
     * Relación: Usuarios que pertenecen a esta unidad.
     * Esto gestiona la MEMBRESÍA, independiente de los roles (Spatie) que tengan dentro de ella.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Helper para obtener la clave de traducción autogenerada por el comando usim:sync
     * Por ejemplo, si el slug es 'software_dev', devolverá 'unit.software_dev.display_name'
     */
    public function getTranslationKeyAttribute(): string
    {
        return "unit.{$this->slug}.display_name";
    }

    /**
     * Helper para obtener el nombre traducido directamente.
     */
    public function getDisplayNameAttribute(): string
    {
        return trans($this->translation_key);
    }
}
