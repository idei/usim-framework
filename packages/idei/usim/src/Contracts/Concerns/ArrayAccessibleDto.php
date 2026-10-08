<?php

namespace Idei\Usim\Contracts\Concerns;

use Illuminate\Support\Str;

/**
 * Trait providing backwards-compatible ArrayAccess to readonly synchronization DTOs.
 */
trait ArrayAccessibleDto
{
    public function offsetExists(mixed $offset): bool
    {
        $key = Str::camel((string) $offset);

        return property_exists($this, $key);
    }

    public function offsetGet(mixed $offset): mixed
    {
        $key = Str::camel((string) $offset);

        /** @var mixed $value */
        $value = property_exists($this, $key) ? $this->{$key} : null;

        return $value;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \BadMethodCallException('Cannot modify readonly synchronization DTO.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \BadMethodCallException('Cannot modify readonly synchronization DTO.');
    }
}
