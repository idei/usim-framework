<?php

namespace Idei\Usim\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $role_id
 * @property string $home_screen
 * @property int $priority
 */
class UsimRoleSetting extends Model
{
    protected $fillable = [
        'role_id',
        'home_screen',
        'priority',
    ];

    /** @return BelongsTo<UsimRole, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(UsimRole::class);
    }
}
