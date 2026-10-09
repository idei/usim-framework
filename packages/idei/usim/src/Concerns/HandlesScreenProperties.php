<?php

namespace Idei\Usim\Concerns;

use Idei\Usim\Screen;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * Trait providing reflection-based inspection and injection of Screen properties:
 * - store_* variables: Client-synced storage variables (optionally encrypted with _crypt suffix).
 * - state_* variables: Server-side snapshot state variables cached across requests.
 *
 * @mixin Screen
 */
trait HandlesScreenProperties
{
    /**
     * Inject storage values into protected properties.
     *
     * Uses reflection to find protected properties whose names start with 'store_'.
     * If a matching key exists in the incoming storage array, the value is injected.
     * Properties ending with '_crypt' are automatically decrypted before injection.
     *
     * Convention: Property name must match storage key
     * Example: protected int $store_user_id; matches storage['store_user_id']
     * Example: protected string $store_token_crypt; decrypts storage['store_token_crypt'] before injection
     *
     * @param  array<string, mixed>  $incomingStorage  Storage data from frontend
     */
    public function injectStorageValues(array $incomingStorage): void
    {
        if (empty($incomingStorage)) {
            return;
        }

        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
            // Skip properties declared in Screen base class
            if ($property->getDeclaringClass()->getName() === Screen::class) {
                continue;
            }

            $propertyName = $property->getName();

            // Only process properties that start with 'store_'
            if (! str_starts_with($propertyName, 'store_')) {
                continue;
            }

            // Check if this key exists in incoming storage
            if (! array_key_exists($propertyName, $incomingStorage)) {
                continue;
            }

            $value = $incomingStorage[$propertyName];

            // If the propertyName ends with '_crypt' we attempt to decrypt it before injecting
            if (str_ends_with($propertyName, '_crypt')) {
                try {
                    if (! \is_string($value)) {
                        continue;
                    }

                    $value = decrypt($value);
                } catch (DecryptException $e) {
                    Log::warning("Failed to decrypt storage variable '{$propertyName}': ".$e->getMessage());

                    continue; // Skip injection if decryption fails
                }
            }

            // Set the value
            $property->setValue($this, $value);
        }
    }

    /**
     * Uses reflection to scan private and protected properties whose names start with the "store_"
     * prefix and whose type hints are non-nullable primitive types (int, float, string, bool) or array.
     * Properties ending with "_crypt" are automatically encrypted before storage.
     *
     * @return array<string, mixed> Associative array with the variables to be stored on the frontend
     */
    public function getStorageVariables(): array
    {
        $storage = [];
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PROTECTED);

        foreach ($properties as $property) {
            $propertyName = $property->getName();
            if (str_starts_with($propertyName, 'store_')) {
                $propertyType = $property->getType();
                if ($propertyType) {
                    if (! ($propertyType instanceof ReflectionNamedType)) {
                        continue;
                    }
                    $typeName = $propertyType->getName();
                    $isPrimitive = in_array($typeName, ['int', 'float', 'string', 'bool', 'array', 'mixed']);
                    if ($isPrimitive) {
                        $value = $property->getValue($this);
                        if ($value !== null && str_ends_with($propertyName, '_crypt')) {
                            $value = encrypt($value);
                        }
                        $storage[$propertyName] = $value;
                    }
                }
            }
        }

        return $storage;
    }

    /**
     * Get internal screen state variables to be persisted in UIStateManager snapshot cache.
     *
     * By convention, any protected or public property starting with 'state_' is automatically collected.
     * Unlike 'store_' variables, 'state_' variables are server-side only and never sent to the client payload.
     *
     * @return array<string, mixed> Associative array of state variables
     */
    public function getStateVariables(): array
    {
        $state = [];
        $reflection = new ReflectionClass($this);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            // Skip properties declared in Screen base class
            if ($property->getDeclaringClass()->getName() === Screen::class) {
                continue;
            }

            $propertyName = $property->getName();
            if (str_starts_with($propertyName, 'state_')) {
                if (! $property->isInitialized($this)) {
                    continue;
                }
                $state[$propertyName] = $property->getValue($this);
            }
        }

        return $state;
    }

    /**
     * Inject cached internal screen state variables into screen properties.
     *
     * @param  array<string, mixed>  $state
     */
    public function injectStateVariables(array $state): void
    {
        if (empty($state)) {
            return;
        }

        $reflection = new ReflectionClass($this);

        foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->getDeclaringClass()->getName() === Screen::class) {
                continue;
            }

            $propertyName = $property->getName();
            if (! str_starts_with($propertyName, 'state_')) {
                continue;
            }

            if (! array_key_exists($propertyName, $state)) {
                continue;
            }

            $value = $state[$propertyName];
            $type = $property->getType();

            if ($type instanceof ReflectionNamedType) {
                $typeName = $type->getName();
                if ($value === null && $type->allowsNull()) {
                    $property->setValue($this, null);

                    continue;
                }
                if ($typeName === 'int' && is_numeric($value)) {
                    $value = (int) $value;
                } elseif ($typeName === 'float' && is_numeric($value)) {
                    $value = (float) $value;
                } elseif ($typeName === 'bool') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif ($typeName === 'string' && (is_scalar($value) || is_null($value))) {
                    $value = (string) ($value ?? '');
                }
            }

            $property->setValue($this, $value);
        }
    }
}
