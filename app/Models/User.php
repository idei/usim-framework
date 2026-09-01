<?php

// @usim: feature="admin", type="model"
namespace App\Models;

use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Notifications\CustomVerifyEmailNotification;
use Idei\Usim\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail, CanResetPassword
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;
    protected $fillable = ['name', 'email', 'password', 'terms_accepted_at'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'terms_accepted_at' => 'datetime'];
    }
    public function displayInfo(): string
    {
        return "User: {$this->name}, Email: {$this->email}";
    }
    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }
    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new CustomVerifyEmailNotification());
    }
    /**
     * Units that the user belongs to.
     * This manages MEMBERSHIP, independent of the roles (Spatie) they have within it
     *
     * @return BelongsToMany<UsimUnit, $this>
     */
    public function usimUnits(): BelongsToMany
    {
        return $this->belongsToMany(UsimUnit::class, 'usim_unit_user', 'user_id', 'usim_unit_id')->withTimestamps();
    }

    /**
     * Obtiene todos los roles del usuario a nivel global,
     * ignorando el filtro de equipos (teams/units) de Spatie.
     *
     * @return MorphToMany<Role, $this>
     */
    public function globalRoles()
    {
        /** @var class-string<Role> $roleModel */
        $roleModel = config('permission.models.role');
        /** @var string|null $modelHasRolesTable */
        $modelHasRolesTable = config('permission.table_names.model_has_roles');
        /** @var string|null $modelMorphKey */
        $modelMorphKey = config('permission.column_names.model_morph_key');
        return $this->morphToMany(
            $roleModel,
            'model',
            $modelHasRolesTable,
            $modelMorphKey,
            'role_id'
        );
    }

    /**
     * Checks if the user is a root user.
     *
     * @return bool
     */
    public function isRoot(): bool
    {
        return $this->globalRoles()->where('name', 'root')->exists();
    }
}
