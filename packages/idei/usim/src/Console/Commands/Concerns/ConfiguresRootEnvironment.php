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

         $isInteractive = $this->input->isInteractive() && defined('STDIN') && @stream_isatty(STDIN);

         $this->rootUserEnvValues = $this->installEnvironmentManager->promptAndPersistRootUserEnv(
            envPath: $envPath,
            interactive: $isInteractive,
            ask: function (string $question, string $default): string {
                $answer = $this->ask($question, $default);
                return is_string($answer) ? $answer : $default;
            },
            secret: function (string $prompt): string {
                if (PHP_OS_FAMILY === 'Windows') {
                    $answer = $this->ask($prompt);
                    return is_string($answer) ? $answer : '';
                }

                try {
                    $secret = $this->secret($prompt);
                    return is_string($secret) ? $secret : '';
                } catch (\Throwable) {
                    $answer = $this->ask($prompt);
                    return is_string($answer) ? $answer : '';
                }
            },
            error: $error,
            line: $this->line(...),
        );
    }
}
