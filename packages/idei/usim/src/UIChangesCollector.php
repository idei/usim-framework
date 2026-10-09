<?php

namespace Idei\Usim;

use Idei\Usim\Support\UIStateManager;

class UIChangesCollector
{
    /** @var array<array-key, mixed> */
    protected array $changes = [];

    /** @var array<string, mixed> */
    protected array $storage_changes = [];

    public function reset(): void
    {
        $this->changes = [];
        $this->storage_changes = [];
    }

    /**
     * @param  array<array-key, mixed>  $change
     */
    public function add(array $change = []): void
    {
        $this->changes = array_replace($this->changes, $change);
    }

    /**
     * @param  array<string, mixed>  $storageChange
     */
    public function setStorage(array $storageChange = []): void
    {
        $this->storage_changes = array_merge($this->storage_changes, $storageChange);
    }

    /**
     * @return array<string, mixed>
     */
    public function getStorage(): array
    {
        return $this->storage_changes;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $storage_key_config = config('usim.front_store_key', 'my-app');
        $storage_key = is_string($storage_key_config) && $storage_key_config !== ''
            ? $storage_key_config
            : 'my-app';
        $this->changes['storage'] = [
            $storage_key => json_encode($this->storage_changes),
        ];

        return $this->changes;
    }

    /**
     * Return all raw changes collected so far.
     *
     * @return array<array-key, mixed>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Determine if a change key exists.
     */
    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->changes);
    }

    /**
     * Get a change value by key.
     */
    public function get(int|string $key, mixed $default = null): mixed
    {
        return $this->changes[$key] ?? $default;
    }

    /**
     * Get redirect URL if one was collected (from 'redirect' or 'navigate.url').
     */
    public function getRedirect(): ?string
    {
        if (isset($this->changes['redirect']) && is_string($this->changes['redirect'])) {
            return $this->changes['redirect'];
        }

        if (isset($this->changes['navigate']) && is_array($this->changes['navigate']) && isset($this->changes['navigate']['url']) && is_string($this->changes['navigate']['url'])) {
            return $this->changes['navigate']['url'];
        }

        return null;
    }

    /**
     * Determine if a redirect has been recorded, optionally matching expected target URL.
     */
    public function hasRedirect(?string $url = null): bool
    {
        $target = $this->getRedirect();
        if ($target === null) {
            return false;
        }

        return $url === null || $target === $url;
    }

    /**
     * Return all toast notifications collected.
     *
     * @return list<array<string, mixed>>
     */
    public function getToasts(): array
    {
        if (! isset($this->changes['toast'])) {
            return [];
        }

        $toast = $this->changes['toast'];
        if (is_array($toast)) {
            if (isset($toast['message'])) {
                /** @var list<array<string, mixed>> */
                return [$toast];
            }

            /** @var list<array<string, mixed>> */
            return array_values(array_filter($toast, 'is_array'));
        }

        return [];
    }

    /**
     * Determine if a toast notification has been recorded matching message and/or type.
     */
    public function hasToast(?string $message = null, ?string $type = null): bool
    {
        $toasts = $this->getToasts();
        if (empty($toasts)) {
            return false;
        }

        if ($message === null && $type === null) {
            return true;
        }

        foreach ($toasts as $toast) {
            $toastMsg = isset($toast['message']) && is_string($toast['message']) ? $toast['message'] : '';
            $toastType = isset($toast['type']) && is_string($toast['type']) ? $toast['type'] : '';

            $msgMatches = $message === null || (str_contains($toastMsg, $message) || $toastMsg === $message);
            $typeMatches = $type === null || $toastType === $type;

            if ($msgMatches && $typeMatches) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get modal payload if one was recorded.
     *
     * @return array<string, mixed>|null
     */
    public function getModal(): ?array
    {
        if (isset($this->changes['modal']) && is_array($this->changes['modal'])) {
            /** @var array<string, mixed> $modal */
            $modal = $this->changes['modal'];

            return $modal;
        }

        if (isset($this->changes['update_modal']) && is_array($this->changes['update_modal'])) {
            /** @var array<string, mixed> $updateModal */
            $updateModal = $this->changes['update_modal'];

            return $updateModal;
        }

        $activeModal = UIStateManager::getClientActiveModal();
        if ($activeModal !== null) {
            return $activeModal;
        }

        return null;
    }

    /**
     * Determine if a modal has been opened, optionally matching modal class name or slug.
     */
    public function hasModal(?string $modalClass = null): bool
    {
        $modal = $this->getModal();
        if ($modal === null) {
            return false;
        }

        if ($modalClass === null) {
            return true;
        }

        $registered = $modal['modal_class'] ?? $modal['class'] ?? null;

        return is_string($registered) && ($registered === $modalClass || str_ends_with($registered, $modalClass));
    }

    /**
     * Determine if modal close action has been requested.
     */
    public function isModalClosed(): bool
    {
        return ($this->changes['action'] ?? null) === 'close_modal';
    }

    /**
     * Return element changes / diffs if any.
     *
     * @return array<string|int, mixed>
     */
    public function getElements(): array
    {
        if (isset($this->changes['elements']) && is_array($this->changes['elements'])) {
            return $this->changes['elements'];
        }

        return [];
    }

    /**
     * Determine if element diff contains the given component name or ID.
     */
    public function hasElement(string|int $elementId): bool
    {
        $elements = $this->getElements();

        return array_key_exists($elementId, $elements);
    }
}
