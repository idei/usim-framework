<?php

namespace Idei\Usim\Console\Commands\Support;

use Illuminate\Filesystem\Filesystem;

class InstallExecutionRollbackManager
{
    private string $snapshotRoot = '';

    /**
     * @var array<int, array{path:string,exists:bool,is_dir:bool,backup:string}>
     */
    private array $manifest = [];

    public function __construct(private readonly Filesystem $files)
    {
    }

    /**
     * @param array<int, string> $paths
     */
    public function begin(array $paths): void
    {
        $this->snapshotRoot = storage_path('framework/cache/.usim-install-rollback-' . uniqid());
        $this->files->makeDirectory($this->snapshotRoot, 0755, true, true);

        $uniquePaths = array_values(array_unique($paths));

        foreach ($uniquePaths as $index => $path) {
            $absolutePath = $this->resolveAbsolutePath($path);
            $exists = $this->files->exists($absolutePath);
            $isDir = $exists && $this->files->isDirectory($absolutePath);
            $backupPath = $this->snapshotRoot . '/item-' . $index;

            if ($exists) {
                if ($isDir) {
                    $this->files->copyDirectory($absolutePath, $backupPath);
                } else {
                    $this->files->makeDirectory(dirname($backupPath), 0755, true, true);
                    $this->files->copy($absolutePath, $backupPath);
                }
            }

            $this->manifest[] = [
                'path' => $absolutePath,
                'exists' => $exists,
                'is_dir' => $isDir,
                'backup' => $backupPath,
            ];
        }
    }

    public function rollback(): void
    {
        foreach ($this->manifest as $item) {
            $path = $item['path'];
            $existed = $item['exists'];
            $wasDir = $item['is_dir'];
            $backup = $item['backup'];

            if ($existed) {
                $this->removePathIfExists($path);

                if ($wasDir) {
                    if ($this->files->exists($backup)) {
                        $this->files->copyDirectory($backup, $path);
                    } else {
                        $this->files->makeDirectory($path, 0755, true, true);
                    }
                } else {
                    if ($this->files->exists($backup)) {
                        $this->files->makeDirectory(dirname($path), 0755, true, true);
                        $this->files->copy($backup, $path);
                    }
                }

                continue;
            }

            // If it did not exist before install, remove anything created during this run.
            $this->removePathIfExists($path);
        }
    }

    public function cleanup(): void
    {
        if ($this->snapshotRoot !== '' && $this->files->exists($this->snapshotRoot)) {
            $this->files->deleteDirectory($this->snapshotRoot);
        }

        $this->snapshotRoot = '';
        $this->manifest = [];
    }

    private function resolveAbsolutePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return base_path($path);
    }

    private function removePathIfExists(string $path): void
    {
        if (!$this->files->exists($path)) {
            return;
        }

        if ($this->files->isDirectory($path)) {
            $this->files->deleteDirectory($path);
            return;
        }

        $this->files->delete($path);
    }
}
