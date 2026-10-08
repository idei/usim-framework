<?php

namespace Idei\Usim\Support;

use Idei\Usim\Contracts\ComponentIdGeneratorInterface;

/**
 * Concrete ID generator for UI components.
 *
 * Implements ComponentIdGeneratorInterface with an isolated instance-level lifecycle,
 * making it safe for Laravel Octane / RoadRunner concurrent worker execution.
 */
class ComponentIdGenerator implements ComponentIdGeneratorInterface
{
    /** @var array<string, int> Auto-increment counter per context */
    private array $autoIncPerContext = [];

    /** @var array<string, array<int, true>> Reserved local IDs per context */
    private array $usedLocalIdsPerContext = [];

    /** @var array<string, array<string, int>> Stable local ID per named component and context */
    private array $namedLocalIdsPerContext = [];

    /** @var array<int, string> Mapping from offset to context class name */
    private array $offsetToContext = [];

    /** @var array<string, int> Mapping from context class name to offset */
    private array $contextOffsets = [];

    /** @var bool Flag to ensure services are loaded only once */
    private bool $servicesLoaded = false;

    /** @var list<string> Context execution stack */
    private array $contextStack = [];

    public function pushCurrentContext(string $context): void
    {
        $this->contextStack[] = $context;
    }

    public function popCurrentContext(): ?string
    {
        return array_pop($this->contextStack);
    }

    public function getCurrentContext(): ?string
    {
        if (empty($this->contextStack)) {
            return null;
        }

        return end($this->contextStack);
    }

    /**
     * Generate an auto-incremented unique ID for a component within a context.
     */
    public function generate(string $context): int
    {
        if (! isset($this->autoIncPerContext[$context])) {
            $this->autoIncPerContext[$context] = 0;
        }

        $localId = $this->autoIncPerContext[$context];
        do {
            $localId++;
        } while (isset($this->usedLocalIdsPerContext[$context][$localId]));

        $this->autoIncPerContext[$context] = $localId;
        $this->usedLocalIdsPerContext[$context][$localId] = true;

        $offset = $this->getContextOffset($context);

        // Register offset → base context mapping for reverse lookup
        $baseContext = explode('@', $context, 2)[0];
        $this->offsetToContext[$offset] = $baseContext;

        return $offset + $localId;
    }

    /**
     * Generate a deterministic ID based on component name and context.
     */
    public function generateFromName(string $context, string $name): int
    {
        $baseContext = explode('@', $context, 2)[0];

        if (isset($this->namedLocalIdsPerContext[$context][$name])) {
            $localId = $this->namedLocalIdsPerContext[$context][$name];
            $offset = $this->getContextOffset($context);

            $this->offsetToContext[$offset] = $baseContext;

            return $offset + $localId;
        }

        $offset = $this->getContextOffset($context);

        $hash = crc32($name);
        $localId = (abs($hash) % 9999) + 1;

        while (isset($this->usedLocalIdsPerContext[$context][$localId])) {
            $localId++;
            if ($localId > 9999) {
                $localId = 1;
            }
        }

        $this->namedLocalIdsPerContext[$context][$name] = $localId;
        $this->usedLocalIdsPerContext[$context][$localId] = true;

        $this->offsetToContext[$offset] = $baseContext;

        return $offset + $localId;
    }

    /**
     * Get debug context information.
     *
     * @return array<string, mixed>
     */
    public function getContextInfo(string $context): array
    {
        return [
            'context' => $context,
            'offset' => $this->getContextOffset($context),
            'current_count' => $this->autoIncPerContext[$context] ?? 0,
        ];
    }

    /**
     * Reserve an already-existing component ID for a context.
     */
    public function reserveContextId(string $context, int $id): void
    {
        $offset = $this->getContextOffset($context);
        $localId = $id - $offset;

        if ($localId < 1) {
            return;
        }

        $this->usedLocalIdsPerContext[$context][$localId] = true;

        if (! isset($this->autoIncPerContext[$context]) || $this->autoIncPerContext[$context] < $localId) {
            $this->autoIncPerContext[$context] = $localId;
        }
    }

    /**
     * Get context class name from component ID.
     */
    public function getContextFromId(int $id): ?string
    {
        $this->ensureServicesLoaded();

        $offset = (int) floor($id / 10000) * 10000;

        return $this->offsetToContext[$offset] ?? null;
    }

    /**
     * Get the ID offset assigned to a context.
     */
    public function getContextOffset(string $context): int
    {
        $this->ensureServicesLoaded();

        if ($context === 'default') {
            return 0;
        }

        if (isset($this->contextOffsets[$context])) {
            return $this->contextOffsets[$context];
        }

        $baseContext = explode('@', $context, 2)[0];

        $val = abs((int) crc32($context));
        $bucket = $val % 100000;
        $offset = $bucket * 10000;

        while (isset($this->offsetToContext[$offset]) && $this->offsetToContext[$offset] !== $baseContext) {
            $offset += 10000;
        }

        $this->contextOffsets[$context] = $offset;

        return $offset;
    }

    /**
     * Reset all counters and context stack.
     */
    public function reset(): void
    {
        $this->autoIncPerContext = [];
        $this->usedLocalIdsPerContext = [];
        $this->namedLocalIdsPerContext = [];
        $this->contextStack = [];
    }

    /**
     * Lazy load registered UI services from the manifest file.
     */
    private function ensureServicesLoaded(): void
    {
        if ($this->servicesLoaded) {
            return;
        }

        $manifestPath = app()->bootstrapPath('cache/usim_screens.php');

        if (! file_exists($manifestPath)) {
            $manifest = [];
        } else {
            /** @var array<string, array{id_offset: int}> $manifest */
            $manifest = require $manifestPath;
        }

        foreach ($manifest as $className => $metadata) {
            $offset = $metadata['id_offset'];
            $this->offsetToContext[$offset] = $className;
            $this->contextOffsets[$className] = $offset;
        }

        $this->servicesLoaded = true;
    }
}

