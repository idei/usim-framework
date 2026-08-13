<?php

namespace Idei\Usim\Console\Commands\Support;

use Illuminate\Filesystem\Filesystem;
use Throwable;

class InstallStateManager
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly string $statePath = ''
    ) {
    }

    public function getPath(): string
    {
        if ($this->statePath !== '') {
            return $this->statePath;
        }

        return storage_path('framework/cache/.usim-install-state.json');
    }

    /** @return array<string, mixed>|null */
    public function read(): ?array
    {
        $path = $this->getPath();
        if (!$this->files->exists($path)) {
            return null;
        }

        $raw = $this->files->get($path);
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return null;
        }

        $state = [];
        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $state[$key] = $value;
            }
        }

        return $state;
    }

    public function start(int $totalSteps): void
    {
        $this->write([
            'status' => 'in_progress',
            'started_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'current_step' => null,
            'completed_steps' => [],
            'total_steps' => $totalSteps,
            'error' => null,
        ]);
    }

    public function setCurrentStep(string $key, string $label, int $index, int $total): void
    {
        $state = $this->read() ?? [];
        $state['status'] = 'in_progress';
        $state['current_step'] = [
            'key' => $key,
            'label' => $label,
            'index' => $index,
            'total' => $total,
        ];
        $state['updated_at'] = now()->toIso8601String();

        $this->write($state);
    }

    public function markStepCompleted(string $key): void
    {
        $state = $this->read() ?? [];
        $completed = is_array($state['completed_steps'] ?? null) ? $state['completed_steps'] : [];

        if (!in_array($key, $completed, true)) {
            $completed[] = $key;
        }

        $state['completed_steps'] = $completed;
        $state['updated_at'] = now()->toIso8601String();

        $this->write($state);
    }

    /**
     * @param array<string, int> $syncStats
     */
    public function finish(array $syncStats): void
    {
        $state = $this->read() ?? [];
        $state['status'] = 'completed';
        $state['current_step'] = null;
        $state['completed_at'] = now()->toIso8601String();
        $state['updated_at'] = now()->toIso8601String();
        $state['sync_stats'] = $syncStats;

        $this->write($state);
    }

    public function fail(Throwable $exception): void
    {
        $state = $this->read() ?? [];
        $state['status'] = 'failed';
        $state['updated_at'] = now()->toIso8601String();
        $state['error'] = [
            'message' => $exception->getMessage(),
            'class' => $exception::class,
        ];

        $this->write($state);
    }

    public function markRolledBack(string $reason): void
    {
        $state = $this->read() ?? [];
        $completed = is_array($state['completed_steps'] ?? null) ? $state['completed_steps'] : [];

        $state['status'] = 'rolled_back';
        $state['updated_at'] = now()->toIso8601String();
        $state['current_step'] = null;
        $state['rolled_back_at'] = now()->toIso8601String();
        $state['rolled_back_steps'] = $completed;
        $state['completed_steps'] = [];
        $state['rollback_reason'] = $reason;

        $this->write($state);
    }

    /**
     * @param array<string, mixed> $state
     */
    private function write(array $state): void
    {
        $path = $this->getPath();
        $directory = dirname($path);

        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $this->files->put(
            $path,
            (string) json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
