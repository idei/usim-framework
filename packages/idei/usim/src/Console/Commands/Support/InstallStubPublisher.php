<?php

namespace Idei\Usim\Console\Commands\Support;

use Illuminate\Filesystem\Filesystem;

class InstallStubPublisher
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    /**
     * @param array<string, string> $replacements
     */
    public function publish(
        string $stubPath,
        string $targetPath,
        bool $force,
        bool $autoForce = false,
        array $replacements = [],
        ?callable $postInstallCallback = null
    ): void {
        if ($this->files->exists($targetPath) && !$force && !$autoForce) {
            if ($postInstallCallback) {
                $postInstallCallback($targetPath);
            }

            return;
        }

        $directory = dirname($targetPath);
        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $content = $this->files->get($stubPath);
        foreach ($replacements as $search => $replace) {
            $content = str_replace($search, $replace, $content);
        }

        $this->files->put($targetPath, $content);

        if ($postInstallCallback) {
            $postInstallCallback($targetPath);
        }
    }
}
