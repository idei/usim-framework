<?php

namespace Idei\Usim\Console\Commands\Concerns;

use Idei\Usim\Console\Commands\Support\InstallEnvironmentManager;

/**
 * @property InstallEnvironmentManager $installEnvironmentManager
 * @property mixed $rootUserEnvValues
 *
 * Requiere que la clase consumidora extienda \Illuminate\Console\Command.
 */
trait ConfiguresRootEnvironment
{
    protected function configureRootStep(string &$envPath, callable $error): void
    {
        if ($envPath === '') {
            $envPath = $this->installEnvironmentManager->resolveEnvPath(
                true,
                $this->line(...)
            ) ?? '';
        }

        if ($envPath === '') {
            throw new \RuntimeException(
                'Unable to locate or create a .env file for root configuration.'
            );
        }

         $this->rootUserEnvValues = $this->installEnvironmentManager->promptAndPersistRootUserEnv(
            envPath: $envPath,
            interactive: $this->input->isInteractive(),
            ask: fn(string $question, string $default): string => (string) $this->ask($question, $default),
            secret: fn(string $prompt): string => (string) $this->secret($prompt),
            error: $error,
            line: $this->line(...),
        );
    }
}
