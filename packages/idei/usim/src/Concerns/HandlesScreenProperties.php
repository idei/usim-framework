<?php

namespace Idei\Usim\Concerns;

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
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
                // Defensive fallback: If store_token / store_token_crypt is declared on this screen
                // but not present in incoming storage, hydrate it from UIStateManager active token
                if ($propertyName === 'store_token' || $propertyName === 'store_token_crypt') {
                    $activeToken = UIStateManager::getAuthToken();
                    if (! empty($activeToken) && empty($property->getValue($this))) {
                        $property->setValue($this, $activeToken);
                    }
                }
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

            // If store_token was explicitly sent as empty/null, but we have an active token from UIStateManager
            // and the user is authenticated, preserve active token
            if (($propertyName === 'store_token' || $propertyName === 'store_token_crypt') && empty($value)) {
                $isAuth = Auth::guard('web')->check() || Auth::guard('device')->check();
                $activeToken = UIStateManager::getAuthToken();
                if ($isAuth && ! empty($activeToken)) {
                    $value = $activeToken;
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

        $isUserAuthenticated = Auth::guard('web')->check() || Auth::guard('device')->check();
        $activeAuthToken = UIStateManager::getAuthToken();

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

                        // Auth token protection:
                        // Prevent accidental clobbering of store_token / store_token_crypt
                        // when the user is actively authenticated.
                        if ($propertyName === 'store_token' || $propertyName === 'store_token_crypt') {
                            if (empty($value)) {
                                if (! empty($activeAuthToken)) {
                                    $value = $propertyName === 'store_token_crypt'
                                        ? encrypt($activeAuthToken)
                                        : $activeAuthToken;
                                } elseif ($isUserAuthenticated) {
                                    // User is authenticated via session guard but property has no token value;
                                    // do not emit an empty string that would clobber client storage.
                                    continue;
                                }
                            }
                        }

                        if ($value !== null && str_ends_with($propertyName, '_crypt') && $propertyName !== 'store_token_crypt') {
                            $value = encrypt($value);
                        } elseif ($value !== null && $propertyName === 'store_token_crypt' && ! empty($value)) {
                            // If value is not yet encrypted, encrypt it
                            try {
                                if (! \is_string($value)) {
                                    throw new \Exception('Value is not a string');
                                }
                                decrypt($value);
                            } catch (\Throwable) {
                                $value = encrypt($value);
                            }
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

    /**
     * Auto-wires uninitialized protected properties typed with instantiable UIElement components.
     * Sets each property to a new instance initialized with the property's name and deterministic ID.
     */
    public function autoWireComponentProperties(): void
    {
        $reflection = new ReflectionClass($this);
        $context = $this->getContextIdentifier();
        $pushed = false;

        if (UIIdGenerator::getCurrentContext() !== $context) {
            UIIdGenerator::pushCurrentContext($context);
            $pushed = true;
        }

        try {
            foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
                // Skip properties declared in Screen base class or AbstractLayout
                $declaringClass = $property->getDeclaringClass()->getName();
                if ($declaringClass === Screen::class || $declaringClass === AbstractLayout::class) {
                    continue;
                }

                // If already initialized (e.g. explicitly in constructor or with default value), skip
                if ($property->isInitialized($this)) {
                    continue;
                }

                $propertyType = $property->getType();
                if (! ($propertyType instanceof ReflectionNamedType) || $propertyType->isBuiltin()) {
                    continue;
                }

                $className = $propertyType->getName();
                if (! class_exists($className) || ! is_subclass_of($className, UIElement::class)) {
                    continue;
                }

                $refClass = new ReflectionClass($className);
                if (! $refClass->isInstantiable()) {
                    continue;
                }

                $propertyName = $property->getName();
                $constructor = $refClass->getConstructor();
                $firstParam = $constructor?->getParameters()[0] ?? null;

                /** @var UIElement $instance */
                if ($firstParam !== null && $firstParam->getName() === 'name') {
                    $instance = new $className($propertyName);
                } elseif ($constructor !== null && $this->constructorHasParameter($constructor, 'name')) {
                    $instance = new $className(name: $propertyName);
                } elseif ($constructor === null || $constructor->getNumberOfRequiredParameters() === 0) {
                    $instance = new $className();
                    $instance->setName($propertyName);
                } else {
                    continue;
                }

                $property->setValue($this, $instance);
            }
        } finally {
            if ($pushed) {
                UIIdGenerator::popCurrentContext();
            }
        }
    }

    /**
     * Reconciles component properties after buildBaseUI:
     * - Auto-names unnamed components assigned to protected UIElement properties.
     * - Ensures deterministic ID is synchronized and updates container children index.
     * - Detects naming mismatches (Fail-Fast LogicException).
     */
    public function reconcileComponentProperties(Container $container): void
    {
        $reflection = new ReflectionClass($this);
        $context = $this->getContextIdentifier();
        $pushed = false;

        if (UIIdGenerator::getCurrentContext() !== $context) {
            UIIdGenerator::pushCurrentContext($context);
            $pushed = true;
        }

        try {
            foreach ($reflection->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
                // Skip properties declared in Screen base class or AbstractLayout
                $declaringClass = $property->getDeclaringClass()->getName();
                if ($declaringClass === Screen::class || $declaringClass === AbstractLayout::class) {
                    continue;
                }

                if (! $property->isInitialized($this)) {
                    continue;
                }

                $propertyType = $property->getType();
                if (! ($propertyType instanceof ReflectionNamedType) || $propertyType->isBuiltin()) {
                    continue;
                }

                $className = $propertyType->getName();
                if (! is_subclass_of($className, UIElement::class)) {
                    continue;
                }

                $value = $property->getValue($this);
                if (! ($value instanceof UIElement)) {
                    continue;
                }

                $propertyName = $property->getName();
                $currentName = $value->getName();

                $matchesName = ($currentName === $propertyName)
                    || ($currentName !== null && Str::snake($propertyName) === Str::snake($currentName));

                if ($currentName === null) {
                    // Auto-assign property name to unnamed component
                    $oldId = $value->getId();
                    $value->setName($propertyName);

                    $deterministicId = UIIdGenerator::generateFromName($context, $propertyName);
                    $value->setId($deterministicId);

                    $container->reindexChild($oldId, $deterministicId);
                } elseif (! $matchesName) {
                    // Fail-fast detection: developer wrote $this->lbl_foo = UI::label('lbl_bar')
                    $screenClass = static::class;
                    throw new LogicException(
                        "Naming mismatch in Screen [{$screenClass}]: Property \${$propertyName} was assigned a component explicitly named '{$currentName}'. " .
                        "Either remove the explicit string name to enable auto-naming, or ensure the property name and component name match."
                    );
                }
            }
        } finally {
            if ($pushed) {
                UIIdGenerator::popCurrentContext();
            }
        }
    }

    private function constructorHasParameter(ReflectionMethod $constructor, string $paramName): bool
    {
        foreach ($constructor->getParameters() as $param) {
            if ($param->getName() === $paramName) {
                return true;
            }
        }

        return false;
    }
}
