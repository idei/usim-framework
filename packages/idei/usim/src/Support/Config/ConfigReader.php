<?php

declare(strict_types=1);

namespace Idei\Usim\Support\Config;

/**
 * Typed reader and normalizer for raw configuration arrays.
 */
final readonly class ConfigReader
{
    /**
     * @param  array<mixed, mixed>  $data
     */
    public function __construct(
        private array $data = [],
    ) {}

    public static function fromMixed(mixed $raw): self
    {
        return new self(\is_array($raw) ? $raw : []);
    }

    public static function stringValue(mixed $value, string $default): string
    {
        return \is_string($value) ? $value : $default;
    }

    public function string(string $key, string $default = ''): string
    {
        return self::stringValue($this->data[$key] ?? null, $default);
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return \is_string($value) ? $value : null;
    }

    public function nonEmptyString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        if (! \is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->data[$key] ?? null;

        return \is_bool($value) ? $value : $default;
    }

    public function int(string $key, int $default): int
    {
        $value = $this->data[$key] ?? null;

        return \is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @return array<mixed, mixed>
     */
    public function array(string $key): array
    {
        $value = $this->data[$key] ?? null;

        return \is_array($value) ? $value : [];
    }

    public function section(string $key): self
    {
        return new self($this->array($key));
    }

    /**
     * @return array<string, string>
     */
    public function stringMap(string $key): array
    {
        $result = [];

        foreach ($this->array($key) as $entryKey => $entryValue) {
            if (\is_string($entryKey) && \is_string($entryValue)) {
                $result[$entryKey] = $entryValue;
            }
        }

        return $result;
    }

    /**
     * @template T
     *
     * @param  array<mixed, mixed>  $items
     * @param  callable(array<mixed, mixed>): T  $factory
     * @return array<string, T>
     */
    public static function mapEntries(array $items, callable $factory): array
    {
        $mapped = [];

        foreach ($items as $key => $entry) {
            if (\is_array($entry)) {
                $mapped[(string) $key] = $factory($entry);
            }
        }

        return $mapped;
    }

    /**
     * @template T
     *
     * @param  array<mixed, mixed>  $items
     * @param  callable(array<mixed, mixed>): T  $factory
     * @return array<int, T>
     */
    public static function listEntries(array $items, callable $factory): array
    {
        $mapped = [];

        foreach ($items as $entry) {
            if (\is_array($entry)) {
                $mapped[] = $factory($entry);
            }
        }

        return $mapped;
    }
}
