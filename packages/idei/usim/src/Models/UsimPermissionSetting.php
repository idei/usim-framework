<?php

namespace Idei\Usim\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Models\Permission;

class UsimPermissionSetting extends Model
{
    protected $fillable = [
        'permission_id',
        'description',
    ];

    public function permission() : HasOne
    {
        return $this->hasOne(Permission::class);
    }
}
