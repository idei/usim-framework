<?php

namespace Idei\Usim\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsimPermissionSetting extends Model
{
    protected $fillable = [
        'permission_id',
        'description',
    ];

    public function permission(): BelongsTo
    {
        return $this->belongsTo(UsimPermission::class);
    }
}
