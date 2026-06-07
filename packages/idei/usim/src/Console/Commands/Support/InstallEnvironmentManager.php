<?php

namespace Idei\Usim\Console\Commands\Support;

use Illuminate\Filesystem\Filesystem;

class InstallEnvironmentManager
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    public function assertNotProductionEnvironment(): void
    {
        $envPath = $this->resolveEnvPath(false, static function (): void {
        });
        $resolvedAppEnv = $this->readEnvValue('APP_ENV', $envPath);

        if ($resolvedAppEnv === null) {
            $configured = config('app.env');
            $resolvedAppEnv = is_string($configured) ? $configured : null;
        }

        if (is_string($resolvedAppEnv) && strtolower(trim($resolvedAppEnv)) === 'production') {
            throw new \RuntimeException('USIM install cannot run with APP_ENV=production. Use a non-production environment.');
        }

        if (app()->environment('production')) {
            throw new \RuntimeException('USIM install cannot run in the production runtime environment.');
        }
    }

    public function resolveEnvPath(bool $allowCreateFromExample, callable $line): ?string
    {
        $envPath = base_path('.env');
        if ($this->files->exists($envPath)) {
            return $envPath;
        }

        $examplePath = base_path('.env.example');
        if (!$allowCreateFromExample || !$this->files->exists($examplePath)) {
            return null;
        }

        $this->files->copy($examplePath, $envPath);
        $line('  <fg=yellow>!</> .env was missing and has been created from .env.example');

        return $envPath;
    }

    public function appendUsimEnvVariables(string $envStubPath, callable $info, callable $line): string
    {
        $envPath = $this->resolveEnvPath(true, $line);
        if ($envPath === null) {
            throw new \RuntimeException('.env and .env.example were not found.');
        }

        $envContent = $this->files->get($envPath);
        $stubContent = $this->files->get($envStubPath);
        $variables = explode("\n", $stubContent);

        $appendContent = '';
        foreach ($variables as $stubLine) {
            $stubLine = trim($stubLine);
            if ($stubLine === '' || str_starts_with($stubLine, '#')) {
                continue;
            }

            $parts = explode('=', $stubLine, 2);
            $key = trim($parts[0] ?? '');
            if ($key && !preg_match("/(^|\\n)\\s*" . preg_quote($key, '/') . "\\s*=/m", $envContent)) {
                $appendContent .= $stubLine . "\n";
            }
        }

        if ($appendContent !== '') {
            $info('  Appending missing environment variables...');
            $this->files->append($envPath, "\n# --- USIM Framework ---\n" . trim($appendContent) . "\n");
            $line('  <fg=green>✓</> .env updated');
        } else {
            $line('  <fg=blue>→</> USIM environment variables already present');
        }

        return $envPath;
    }

    /**
     * @return array<string, string>
     */
    public function promptAndPersistRootUserEnv(
        string $envPath,
        bool $interactive,
        callable $ask,
        callable $secret,
        callable $error,
        callable $line
    ): array {
        try {
            $defaults = [
                'ROOT_FIRST_NAME' => $this->readEnvValue('ROOT_FIRST_NAME', $envPath) ?? ((string) config('usim.users.root.first_name', 'Root')),
                'ROOT_LAST_NAME' => $this->readEnvValue('ROOT_LAST_NAME', $envPath) ?? ((string) config('usim.users.root.last_name', 'User')),
                'ROOT_EMAIL' => $this->readEnvValue('ROOT_EMAIL', $envPath) ?? ((string) config('usim.users.root.email', 'root@example.com')),
                'ROOT_PASSWORD' => $this->readEnvValue('ROOT_PASSWORD', $envPath) ?? '',
            ];

            $firstName = $this->askRootValue('Root first name', $defaults['ROOT_FIRST_NAME'], $interactive, $ask);
            $lastName = $this->askRootValue('Root last name', $defaults['ROOT_LAST_NAME'], $interactive, $ask);
            $email = $this->askRootEmail($defaults['ROOT_EMAIL'], $interactive, $ask, $error);
            $password = $this->askRootPassword($defaults['ROOT_PASSWORD'], $interactive, $secret, $line, $error);

            $this->upsertEnvEntries($envPath, [
                'ROOT_FIRST_NAME' => $firstName,
                'ROOT_LAST_NAME' => $lastName,
                'ROOT_EMAIL' => $email,
                'ROOT_PASSWORD' => $password,
            ]);

            $line('  <fg=green>✓</> Root credentials saved in .env');

            return [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'password' => $password,
            ];
        } catch (\RuntimeException $e) {
            $error($e->getMessage());
            return [];
        }
    }

    private function readEnvValue(string $key, ?string $envPath = null): ?string
    {
        $path = $envPath ?? $this->resolveEnvPath(false, static function (): void {
        });
        if ($path === null || !$this->files->exists($path)) {
            return null;
        }

        $content = $this->files->get($path);
        if (!preg_match('/^\s*' . preg_quote($key, '/') . '\s*=\s*(.*)$/m', $content, $matches)) {
            return null;
        }

        $value = trim((string) ($matches[1] ?? ''));
        if ($value === '') {
            return '';
        }

        if (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === '\'' && str_ends_with($value, '\''))) {
            $value = substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * @param array<string, string> $entries
     */
    private function upsertEnvEntries(string $envPath, array $entries): void
    {
        $content = $this->files->exists($envPath) ? $this->files->get($envPath) : '';

        foreach ($entries as $key => $value) {
            $entryLine = $key . '=' . $this->normalizeEnvValue($value);
            $pattern = '/^\s*' . preg_quote($key, '/') . '\s*=.*$/m';

            if (preg_match($pattern, $content) === 1) {
                $content = (string) preg_replace($pattern, $entryLine, $content);
                continue;
            }

            $content = rtrim($content) . "\n" . $entryLine . "\n";
        }

        $this->files->put($envPath, $content);
    }

    private function normalizeEnvValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (preg_match('/[\s#"\']/', $value) === 1) {
            $escaped = str_replace('"', '\\"', $value);
            return '"' . $escaped . '"';
        }

        return $value;
    }

    private function askRootValue(string $label, string $default, bool $interactive, callable $ask): string
    {
        if (!$interactive) {
            $value = trim($default);
            if ($value === '' || strtoupper($value) === 'CHANGE_ME') {
                throw new \RuntimeException("{$label} is required in .env for non-interactive install.");
            }

            return $value;
        }

        do {
            $value = trim((string) $ask($label, $default));
        } while ($value === '');

        return $value;
    }

    private function askRootEmail(string $default, bool $interactive, callable $ask, callable $error): string
    {
        if (!$interactive) {
            $value = trim($default);
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('ROOT_EMAIL must be a valid email in .env for non-interactive install.');
            }

            return $value;
        }

        while (true) {
            $value = trim((string) $ask('Root email', $default));
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }

            $error('Please provide a valid email address.');
        }
    }

    private function askRootPassword(string $default, bool $interactive, callable $secret, callable $line, callable $error): string
    {
        if (!$interactive) {
            $value = trim($default);
            if ($value === '' || strtoupper($value) === 'CHANGE_ME') {
                throw new \RuntimeException('ROOT_PASSWORD must be set in .env, and cannot be "CHANGE_ME" for non-interactive install.');
            }

            return $value;
        }

        while (true) {
            $hint = $default !== '' && strtoupper($default) !== 'CHANGE_ME' ? 'Press enter to keep current password' : null;
            $value = (string) $secret($hint ?? 'Root password');
            $value = trim($value) !== '' ? trim($value) : trim($default);

            if ($value === '' || strtoupper($value) === 'CHANGE_ME') {
                $line('Root password is required and cannot be CHANGE_ME.');
                continue;
            }

            if (mb_strlen($value) < 8) {
                $line('Root password must be at least 8 characters.');
                continue;
            }

            return $value;
        }
    }
}
