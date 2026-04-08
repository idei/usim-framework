<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Resetear caché de roles y permisos
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'access-admin-panel',
            'manage-users',
            'edit-content',
            'remove-content',
            'view-reports',
            'view-logs'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roleUser = Role::firstOrCreate(['name' => 'user', 'guard_name' => 'web']);
        $roleUser->givePermissionTo(['edit-content']);

        $roleVerified = Role::firstOrCreate(['name' => 'verified', 'guard_name' => 'web']);
        $roleVerified->givePermissionTo(['access-admin-panel']);

        $roleAdmin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleAdmin->givePermissionTo(Permission::all());
    }
}
