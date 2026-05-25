<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Permission\Models\Role;

class UsimRoleSetting extends Model
{
    protected $fillable = [
        'role_id',
        'home_screen',
        'priority',
    ];

    protected $casts = [
        'priority' => 'integer',
    ];

    public function role(): HasOne
    {
        return $this->hasOne(Role::class);
    }
}
