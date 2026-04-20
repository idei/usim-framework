<?php

namespace Idei\Usim\Console\Commands\Concerns;

trait InstallsLangStubs
{
    /**
     * Copy package language stubs into app lang path without overwriting
     * existing files, preserving the directory structure from stubs/lang.
     */
    protected function installLangStubs(): void
    {
        $sourceRoot = $this->stubsPath('lang');
        $targetRoot = \lang_path();

        if (!$this->files->isDirectory($sourceRoot)) {
            $this->line('  <fg=yellow>!</> stubs/lang not found, skipping');
            return;
        }

        if (!$this->files->isDirectory($targetRoot)) {
            $this->files->makeDirectory($targetRoot, 0755, true);
        }

        $created = 0;
        $skipped = 0;

        foreach ($this->files->allFiles($sourceRoot) as $sourceFile) {
            $relativePath = $sourceFile->getRelativePathname();
            $targetPath = $targetRoot . DIRECTORY_SEPARATOR . $relativePath;

            if ($this->files->exists($targetPath)) {
                $skipped++;
                continue;
            }

            $targetDirectory = dirname($targetPath);
            if (!$this->files->isDirectory($targetDirectory)) {
                $this->files->makeDirectory($targetDirectory, 0755, true);
            }

            $this->files->copy($sourceFile->getPathname(), $targetPath);
            $created++;
            $relativeTarget = str_replace(\base_path() . '/', '', $targetPath);
            $this->line("  <fg=green>✓</> {$relativeTarget}");
        }

        if ($created === 0) {
            $this->line('  <fg=blue>→</> Lang stubs already present, no files copied');
            return;
        }

        $this->line("  <fg=blue>→</> Lang stubs copied: {$created} (skipped: {$skipped})");
    }
}
