<?php
// @usim: feature="admin", type="migration"
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Si las unidades están desactivadas en Spatie, abortamos la alteración.
        if (!config('permission.teams')) {
            return;
        }

        /** @var array<string, string> $tableNames */
        $tableNames = config('permission.table_names');
        /** @var string $columnName */
        $columnName = config('permission.team_foreign_key', 'usim_unit_id');

        if (empty($tableNames)) {
            throw new \Exception('Error: config/permission.php no está cargado.');
        }

        // 1. Alterar model_has_permissions
        if (
            Schema::hasTable($tableNames['model_has_permissions']) &&
            !Schema::hasColumn($tableNames['model_has_permissions'], $columnName)
        ) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnName) {
                // Agregar la columna
                $table->unsignedBigInteger($columnName)->nullable();
                $table->index($columnName, 'model_has_permissions_unit_id_index');

                // Eliminar la llave primaria anterior (generada por Spatie por defecto)
                $table->dropPrimary(['permission_id', 'model_id', 'model_type']);

                // Crear la nueva llave primaria compuesta incluyendo el usim_unit_id
                $table->primary(
                    [$columnName, 'permission_id', 'model_id', 'model_type'],
                    'model_has_permissions_unit_primary' // Nombre explícito para evitar nombres demasiado largos
                );
            });
        }

        // 2. Alterar model_has_roles
        if (Schema::hasTable($tableNames['model_has_roles']) && !Schema::hasColumn($tableNames['model_has_roles'], $columnName)) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnName) {
                // Agregar la columna
                $table->unsignedBigInteger($columnName)->nullable();
                $table->index($columnName, 'model_has_roles_unit_id_index');

                // Eliminar la llave primaria anterior
                $table->dropPrimary(['role_id', 'model_id', 'model_type']);

                // Crear la nueva llave primaria compuesta
                $table->primary(
                    [$columnName, 'role_id', 'model_id', 'model_type'],
                    'model_has_roles_unit_primary'
                );
            });
        }
    }

    public function down(): void
    {
        // 1. Si las unidades están desactivadas en Spatie, abortamos la alteración.
        if (!config('permission.teams')) {
            return;
        }

        /** @var array<string, string> $tableNames */
        $tableNames = config('permission.table_names');
        /** @var string $columnName */
        $columnName = config('permission.team_foreign_key', 'usim_unit_id');

        if (empty($tableNames)) {
            return;
        }

        if (Schema::hasTable($tableNames['model_has_permissions']) && Schema::hasColumn($tableNames['model_has_permissions'], $columnName)) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnName) {
                $table->dropPrimary('model_has_permissions_unit_primary');
                $table->primary(['permission_id', 'model_id', 'model_type']);
                $table->dropIndex('model_has_permissions_unit_id_index');
                $table->dropColumn($columnName);
            });
        }

        if (Schema::hasTable($tableNames['model_has_roles']) && Schema::hasColumn($tableNames['model_has_roles'], $columnName)) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnName) {
                $table->dropPrimary('model_has_roles_unit_primary');
                $table->primary(['role_id', 'model_id', 'model_type']);
                $table->dropIndex('model_has_roles_unit_id_index');
                $table->dropColumn($columnName);
            });
        }
    }
};
