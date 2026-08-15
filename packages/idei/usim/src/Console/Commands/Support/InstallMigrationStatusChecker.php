<?php

namespace Idei\Usim\Console\Commands\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class InstallMigrationStatusChecker
{
    /**
     * Lightweight pre-install check: only verifies the database is reachable.
     * Unlike assess(), it does not require USIM-specific tables/migrations,
     * since those are only published later during the install run.
     *
     * @return array{exists:bool,issue:?string}
     */
    public function assessConnectivity(): array
    {
        return $this->assessDatabaseExistence();
    }

    /**
     * @return array{is_ready:bool,database_exists:bool,database_issue:?string,missing_tables:array<int,string>,missing_columns:array<string,array<int,string>>,missing_migrations:array<int,string>,notes:array<int,string>}
     */
    public function assess(): array
    {
        $databaseStatus = $this->assessDatabaseExistence();
        if ($databaseStatus['exists'] !== true) {
            $issue = (string) $databaseStatus['issue'];

            return [
                'is_ready' => false,
                'database_exists' => false,
                'database_issue' => $issue,
                'missing_tables' => [],
                'missing_columns' => [],
                'missing_migrations' => [],
                'notes' => [$issue],
            ];
        }

        $requiredTables = [
            'users',
            'roles',
            'permissions',
            'role_has_permissions',
            'model_has_roles',
            'usim_languages',
        ];

        $requiredColumns = [
            'users' => ['email', 'password'],
            'roles' => ['name', 'guard_name'],
            'permissions' => ['name', 'guard_name'],
            'role_has_permissions' => ['role_id', 'permission_id'],
            'model_has_roles' => ['role_id', 'model_type', 'model_id'],
            'usim_languages' => ['code', 'is_active', 'is_fallback'],
        ];

        $missingTables = [];
        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                $missingTables[] = $table;
            }
        }

        $missingColumns = [];
        foreach ($requiredColumns as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    $missingColumns[$table][] = $column;
                }
            }
        }

        $hasMigrationsTable = Schema::hasTable('migrations');
        $missingMigrations = $this->detectMissingCriticalMigrations($hasMigrationsTable);

        $notes = [];
        if (!$hasMigrationsTable) {
            $notes[] = 'The migrations table does not exist; migration history cannot be verified.';
        }

        $isReady = $missingTables === [] && $missingColumns === [] && $missingMigrations === [];

        return [
            'is_ready' => $isReady,
            'database_exists' => true,
            'database_issue' => null,
            'missing_tables' => $missingTables,
            'missing_columns' => $missingColumns,
            'missing_migrations' => $missingMigrations,
            'notes' => $notes,
        ];
    }

    /**
     * @return array{exists:bool,issue:?string}
     */
    private function assessDatabaseExistence(): array
    {
        $defaultConnection = config('database.default', 'mysql');
        if (!is_string($defaultConnection) || trim($defaultConnection) === '') {
            return [
                'exists' => false,
                'issue' => 'database.default is empty; cannot resolve database connection.',
            ];
        }

        $defaultConnection = trim($defaultConnection);
        $connectionConfig = config("database.connections.{$defaultConnection}");
        if (!is_array($connectionConfig)) {
            return [
                'exists' => false,
                'issue' => "Database connection [{$defaultConnection}] is not configured.",
            ];
        }

        $driver = isset($connectionConfig['driver']) && is_string($connectionConfig['driver'])
            ? strtolower(trim($connectionConfig['driver']))
            : '';

        return match ($driver) {
            'sqlite' => $this->assessSqliteDatabaseExistence($connectionConfig),
            'mysql', 'mariadb' => $this->assessMysqlDatabaseExistence($connectionConfig, $driver),
            'pgsql' => $this->assessPgsqlDatabaseExistence($connectionConfig),
            'sqlsrv' => $this->assessSqlsrvDatabaseExistence($connectionConfig),
            default => $this->assessByConnectionProbe($defaultConnection, $driver),
        };
    }

    /**
     * @param array<string, mixed> $connectionConfig
     * @return array{exists:bool,issue:?string}
     */
    private function assessSqliteDatabaseExistence(array $connectionConfig): array
    {
        $database = isset($connectionConfig['database']) && is_string($connectionConfig['database'])
            ? trim($connectionConfig['database'])
            : '';

        if ($database === '') {
            return [
                'exists' => false,
                'issue' => 'DB_DATABASE is empty for sqlite connection.',
            ];
        }

        if ($database === ':memory:') {
            return ['exists' => true, 'issue' => null];
        }

        $isAbsolutePath = str_starts_with($database, '/');
        $databasePath = $isAbsolutePath ? $database : base_path($database);

        if (!is_file($databasePath)) {
            return [
                'exists' => false,
                'issue' => "SQLite database file does not exist: {$databasePath}",
            ];
        }

        return ['exists' => true, 'issue' => null];
    }

    /**
     * @param array<string, mixed> $connectionConfig
     * @return array{exists:bool,issue:?string}
     */
    private function assessMysqlDatabaseExistence(array $connectionConfig, string $driver): array
    {
        $databaseName = isset($connectionConfig['database']) && is_string($connectionConfig['database'])
            ? trim($connectionConfig['database'])
            : '';

        if ($databaseName === '') {
            return [
                'exists' => false,
                'issue' => strtoupper($driver) . ' DB_DATABASE is empty in configuration.',
            ];
        }

        $probe = $this->makeProbeConnectionName($driver);
        $probeConfig = $connectionConfig;
        $probeConfig['database'] = null;

        try {
            Config::set("database.connections.{$probe}", $probeConfig);
            DB::purge($probe);
            $result = DB::connection($probe)
                ->selectOne('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ? LIMIT 1', [$databaseName]);

            return [
                'exists' => $result !== null,
                'issue' => $result !== null ? null : "Database [{$databaseName}] does not exist for {$driver}.",
            ];
        } catch (Throwable $exception) {
            return [
                'exists' => false,
                'issue' => "Unable to verify {$driver} database existence: {$exception->getMessage()}",
            ];
        } finally {
            DB::disconnect($probe);
            DB::purge($probe);
            Config::set("database.connections.{$probe}", null);
        }
    }

    /**
     * @param array<string, mixed> $connectionConfig
     * @return array{exists:bool,issue:?string}
     */
    private function assessPgsqlDatabaseExistence(array $connectionConfig): array
    {
        $databaseName = isset($connectionConfig['database']) && is_string($connectionConfig['database'])
            ? trim($connectionConfig['database'])
            : '';

        if ($databaseName === '') {
            return [
                'exists' => false,
                'issue' => 'PGSQL DB_DATABASE is empty in configuration.',
            ];
        }

        $probe = $this->makeProbeConnectionName('pgsql');
        $probeConfig = $connectionConfig;
        $probeConfig['database'] = 'postgres';

        try {
            Config::set("database.connections.{$probe}", $probeConfig);
            DB::purge($probe);

            $result = DB::connection($probe)
                ->selectOne('SELECT 1 FROM pg_database WHERE datname = ? LIMIT 1', [$databaseName]);

            return [
                'exists' => $result !== null,
                'issue' => $result !== null ? null : "Database [{$databaseName}] does not exist for pgsql.",
            ];
        } catch (QueryException) {
            // Some setups do not allow access to "postgres"; retry using template1.
            $probeConfig['database'] = 'template1';

            try {
                Config::set("database.connections.{$probe}", $probeConfig);
                DB::purge($probe);
                $result = DB::connection($probe)
                    ->selectOne('SELECT 1 FROM pg_database WHERE datname = ? LIMIT 1', [$databaseName]);

                return [
                    'exists' => $result !== null,
                    'issue' => $result !== null ? null : "Database [{$databaseName}] does not exist for pgsql.",
                ];
            } catch (Throwable $exception) {
                return [
                    'exists' => false,
                    'issue' => "Unable to verify pgsql database existence: {$exception->getMessage()}",
                ];
            }
        } catch (Throwable $exception) {
            return [
                'exists' => false,
                'issue' => "Unable to verify pgsql database existence: {$exception->getMessage()}",
            ];
        } finally {
            DB::disconnect($probe);
            DB::purge($probe);
            Config::set("database.connections.{$probe}", null);
        }
    }

    /**
     * @param array<string, mixed> $connectionConfig
     * @return array{exists:bool,issue:?string}
     */
    private function assessSqlsrvDatabaseExistence(array $connectionConfig): array
    {
        $databaseName = isset($connectionConfig['database']) && is_string($connectionConfig['database'])
            ? trim($connectionConfig['database'])
            : '';

        if ($databaseName === '') {
            return [
                'exists' => false,
                'issue' => 'SQLSRV DB_DATABASE is empty in configuration.',
            ];
        }

        $probe = $this->makeProbeConnectionName('sqlsrv');
        $probeConfig = $connectionConfig;
        $probeConfig['database'] = 'master';

        try {
            Config::set("database.connections.{$probe}", $probeConfig);
            DB::purge($probe);
            $result = DB::connection($probe)
                ->selectOne('SELECT 1 FROM sys.databases WHERE name = ?;', [$databaseName]);

            return [
                'exists' => $result !== null,
                'issue' => $result !== null ? null : "Database [{$databaseName}] does not exist for sqlsrv.",
            ];
        } catch (Throwable $exception) {
            return [
                'exists' => false,
                'issue' => "Unable to verify sqlsrv database existence: {$exception->getMessage()}",
            ];
        } finally {
            DB::disconnect($probe);
            DB::purge($probe);
            Config::set("database.connections.{$probe}", null);
        }
    }

    /**
     * @return array{exists:bool,issue:?string}
     */
    private function assessByConnectionProbe(string $connectionName, string $driver): array
    {
        try {
            DB::connection($connectionName)->select('SELECT 1');

            return ['exists' => true, 'issue' => null];
        } catch (Throwable $exception) {
            $label = $driver !== '' ? $driver : $connectionName;

            return [
                'exists' => false,
                'issue' => "Unable to connect using {$label} configuration: {$exception->getMessage()}",
            ];
        }
    }

    private function makeProbeConnectionName(string $driver): string
    {
        return 'usim_install_probe_' . $driver . '_' . substr(md5((string) microtime(true)), 0, 8);
    }

    /**
     * @return array<int, string>
     */
    private function detectMissingCriticalMigrations(bool $hasMigrationsTable): array
    {
        if (!$hasMigrationsTable) {
            return [];
        }

        $criticalSignatures = [
            'create_permission_tables',
            'create_usim_languages_table',
            'create_usim_text_keys_table',
            'create_usim_text_values_table',
            'create_usim_role_settings_table'
        ];

        $executed = DB::table('migrations')
            ->pluck('migration')
            ->filter(static fn ($name): bool => \is_string($name) && $name !== '')
            ->map(static fn (string $name): string => trim($name))
            ->values()
            ->all();

        $missing = [];
        foreach ($criticalSignatures as $signature) {
            $isExecuted = false;

            foreach ($executed as $migrationName) {
                if (str_ends_with($migrationName, '_' . $signature) || $migrationName === $signature) {
                    $isExecuted = true;
                    break;
                }
            }

            if (!$isExecuted) {
                $missing[] = $signature;
            }
        }

        return $missing;
    }
}
