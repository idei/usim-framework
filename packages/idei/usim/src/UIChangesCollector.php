<?php

namespace Idei\Usim;

class UIChangesCollector
{
    protected array $changes = [];
    protected array $storage_changes = [];

    public function reset(): void
    {
        $this->changes = [];
        $this->storage_changes = [];
    }

    public function add(array $change = []): void
    {
        $this->changes += $change;
    }

    public function setStorage(array $storageChange = []): void
    {
        $this->storage_changes = array_merge($this->storage_changes, $storageChange);
    }

    public function all(): array
    {
        $storage_key = config('ui-services.app_id');
        $this->changes['storage'] = [
            $storage_key => json_encode($this->storage_changes),
        ];
        return $this->changes;
    }
}
