<?php

namespace Idei\Usim;

class UIChangesCollector
{
    /** @var array<string, mixed> */
    protected array $changes = [];

    /** @var array<string, mixed> */
    protected array $storage_changes = [];

    public function reset(): void
    {
        $this->changes = [];
        $this->storage_changes = [];
    }

    /**
     * @param array<string, mixed> $change
     */
    public function add(array $change = []): void
    {
        $this->changes += $change;
    }

    /**
     * @param array<string, mixed> $storageChange
     */
    public function setStorage(array $storageChange = []): void
    {
        $this->storage_changes = array_merge($this->storage_changes, $storageChange);
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $storage_key = config('usim.front_store_key', 'my-app');
        $this->changes['storage'] = [
            $storage_key => json_encode($this->storage_changes),
        ];
        return $this->changes;
    }
}
