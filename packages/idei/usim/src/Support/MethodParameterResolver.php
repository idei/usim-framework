<?php

namespace Idei\Usim\Support;

use BackedEnum;
use Idei\Usim\Http\Requests\UIRequest;
use Idei\Usim\Screen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Resolves and hydrates method parameters for Screen callback and query methods.
 *
 * Supports:
 * - Legacy untyped or array parameters ($params / $parameters)
 * - UIRequest form request objects
 * - Native scalar types (int, float, string, bool, array) with automatic coercion
 * - BackedEnums (string and int backed enums)
 * - Eloquent models (Route Model Binding style by {param}_id or id)
 * - Container-resolvable dependencies
 * - Default values and nullable parameters
 */
class MethodParameterResolver
{
    /**
     * Resolve argument values for a Screen method invocation.
     *
     * @param  ReflectionMethod  $reflectionMethod
     * @param  Screen  $screen
     * @param  array<int|string, mixed>  $parameters
     * @param  array<string, mixed>  $queryParams
     * @param  array<string, mixed>  $incomingStorage
     * @param  int|null  $triggerComponentId
     * @param  array<string, mixed>  $provided
     * @return array<int|string, mixed>
     *
     * @throws ValidationException
     */
    public function resolve(
        ReflectionMethod $reflectionMethod,
        Screen $screen,
        array $parameters = [],
        array $queryParams = [],
        array $incomingStorage = [],
        ?int $triggerComponentId = null,
        array $provided = []
    ): array {
        if ($reflectionMethod->getNumberOfParameters() === 0) {
            return [];
        }

        $paramsList = $reflectionMethod->getParameters();
        $totalParams = count($paramsList);

        // Backward compatibility: single legacy array/untyped parameter ($params, $parameters, etc.)
        if ($totalParams === 1 && empty($provided)) {
            $firstParam = $paramsList[0];
            $paramName = $firstParam->getName();
            $paramType = $firstParam->getType();

            if ($paramName === 'params' || $paramName === 'parameters') {
                if ($paramType === null || ($paramType instanceof ReflectionNamedType && $paramType->getName() === 'array')) {
                    return [$parameters];
                }
            }

            if (($paramType === null || ($paramType instanceof ReflectionNamedType && $paramType->getName() === 'array'))
                && ! array_key_exists($paramName, $parameters)
            ) {
                return [$parameters];
            }
        }

        $resolvedArgs = [];
        foreach ($paramsList as $param) {
            if ($param->isVariadic()) {
                if (! empty($parameters)) {
                    foreach ($parameters as $k => $val) {
                        if (is_string($k)) {
                            $resolvedArgs[$k] = $val;
                        } else {
                            $resolvedArgs[] = $val;
                        }
                    }
                }
                break;
            }

            $resolvedArgs[] = $this->resolveParameter(
                $param,
                $screen,
                $parameters,
                $queryParams,
                $incomingStorage,
                $triggerComponentId,
                $provided
            );
        }

        return $resolvedArgs;
    }

    /**
     * Resolve a single parameter.
     *
     * @param  array<int|string, mixed>  $parameters
     * @param  array<string, mixed>  $queryParams
     * @param  array<string, mixed>  $incomingStorage
     * @param  array<string, mixed>  $provided
     */
    protected function resolveParameter(
        ReflectionParameter $param,
        Screen $screen,
        array $parameters,
        array $queryParams,
        array $incomingStorage,
        ?int $triggerComponentId,
        array $provided = []
    ): mixed {
        $paramName = $param->getName();
        $type = $param->getType();

        // 0. Check explicitly provided dependencies (e.g. Container $container)
        if (array_key_exists($paramName, $provided)) {
            return $provided[$paramName];
        }

        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin()) {
            $typeName = $type->getName();
            if (array_key_exists($typeName, $provided)) {
                return $provided[$typeName];
            }
            foreach ($provided as $provInstance) {
                if (is_object($provInstance) && is_a($provInstance, $typeName)) {
                    return $provInstance;
                }
            }
        }

        // 1. UIRequest dependency injection
        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), UIRequest::class)) {
            $requestClass = $type->getName();
            /** @var UIRequest $uiRequest */
            $uiRequest = app($requestClass);
            /** @var array<string, mixed> $stringKeyParams */
            $stringKeyParams = array_filter($parameters, static fn ($k) => is_string($k), ARRAY_FILTER_USE_KEY);
            $uiRequest->initialize(
                screen: $screen,
                parameters: $stringKeyParams,
                incomingStorage: $incomingStorage,
                queryParams: $queryParams,
                triggerComponentId: $triggerComponentId
            );
            $uiRequest->validate();

            return $uiRequest;
        }

        // 2. Locate raw value from candidate sources in precedence order
        [$found, $rawValue] = $this->extractRawValue($paramName, $param, $parameters, $queryParams, $incomingStorage);

        // 3. Hydrate based on type
        return $this->hydrateValue($param, $paramName, $type, $found, $rawValue);
    }

    /**
     * Extract raw value by checking parameters, queryParams, and incomingStorage.
     *
     * @param  array<int|string, mixed>  $parameters
     * @param  array<string, mixed>  $queryParams
     * @param  array<string, mixed>  $incomingStorage
     * @return array{0: bool, 1: mixed} [found, value]
     */
    protected function extractRawValue(
        string $paramName,
        ReflectionParameter $param,
        array $parameters,
        array $queryParams,
        array $incomingStorage
    ): array {
        // Priority 1: Action/Component parameters (exact match or camel/snake)
        [$found, $val] = $this->findInArray($paramName, $parameters);
        if ($found) {
            return [true, $val];
        }

        // Priority 2: Query parameters from page URL
        [$found, $val] = $this->findInArray($paramName, $queryParams);
        if ($found) {
            return [true, $val];
        }

        // Priority 3: Incoming storage (session/store keys)
        [$found, $val] = $this->findInArray($paramName, $incomingStorage);
        if ($found) {
            return [true, $val];
        }
        [$found, $val] = $this->findInArray('store_'.$paramName, $incomingStorage);
        if ($found) {
            return [true, $val];
        }

        // Priority 4: Eloquent Model binding fallbacks (e.g. $user -> user_id -> id)
        $type = $param->getType();
        if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), Model::class)) {
            $idCandidates = [$paramName.'_id', 'id', Str::snake($paramName).'_id'];
            foreach ($idCandidates as $candidate) {
                [$found, $val] = $this->findInArray($candidate, $parameters);
                if ($found) {
                    return [true, $val];
                }
                [$found, $val] = $this->findInArray($candidate, $queryParams);
                if ($found) {
                    return [true, $val];
                }
            }
        }

        // Priority 5: Positional argument in parameters
        $position = $param->getPosition();
        if (array_key_exists($position, $parameters)) {
            return [true, $parameters[$position]];
        }
        if ($position > 0 && array_key_exists($position - 1, $parameters)) {
            return [true, $parameters[$position - 1]];
        }

        return [false, null];
    }

    /**
     * Search for a key in an array respecting snake_case and camelCase variants.
     *
     * @param  array<int|string, mixed>  $source
     * @return array{0: bool, 1: mixed}
     */
    protected function findInArray(string $key, array $source): array
    {
        if (array_key_exists($key, $source)) {
            return [true, $source[$key]];
        }

        $snake = Str::snake($key);
        if (array_key_exists($snake, $source)) {
            return [true, $source[$snake]];
        }

        $camel = Str::camel($key);
        if (array_key_exists($camel, $source)) {
            return [true, $source[$camel]];
        }

        return [false, null];
    }

    /**
     * Hydrate and validate value according to declared type.
     */
    protected function hydrateValue(
        ReflectionParameter $param,
        string $paramName,
        ?ReflectionType $type,
        bool $found,
        mixed $rawValue
    ): mixed {
        // Untyped parameter
        if ($type === null) {
            if ($found) {
                return $rawValue;
            }
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }

            throw ValidationException::withMessages([
                $paramName => ["The {$paramName} parameter is required."],
            ]);
        }

        // Union Type (PHP 8.0+)
        if ($type instanceof ReflectionUnionType) {
            return $this->hydrateUnionType($param, $paramName, $type, $found, $rawValue);
        }

        // Named Type
        if ($type instanceof ReflectionNamedType) {
            return $this->hydrateNamedType($param, $paramName, $type, $found, $rawValue);
        }

        return $rawValue;
    }

    /**
     * Hydrate a named type.
     */
    protected function hydrateNamedType(
        ReflectionParameter $param,
        string $paramName,
        ReflectionNamedType $type,
        bool $found,
        mixed $rawValue
    ): mixed {
        $typeName = $type->getName();
        $isNullable = $type->allowsNull();

        if (! $found || $rawValue === null) {
            if ($found && $isNullable) {
                return null;
            }
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            if ($isNullable) {
                return null;
            }

            throw ValidationException::withMessages([
                $paramName => ["The {$paramName} parameter is required."],
            ]);
        }

        // Built-in scalar and array types
        if ($type->isBuiltin()) {
            return match ($typeName) {
                'int' => $this->castToInt($paramName, $rawValue),
                'float' => $this->castToFloat($paramName, $rawValue),
                'string' => is_scalar($rawValue) || $rawValue instanceof \Stringable ? (string) $rawValue : '',
                'bool' => $this->castToBool($rawValue),
                'array' => $this->castToArray($rawValue),
                default => $rawValue,
            };
        }

        // BackedEnum (PHP 8.1+)
        if (is_subclass_of($typeName, BackedEnum::class)) {
            $enum = null;
            if (is_int($rawValue) || is_string($rawValue)) {
                /** @var class-string<BackedEnum> $typeName */
                $enum = $typeName::tryFrom($rawValue);
            }
            if ($enum === null && is_numeric($rawValue)) {
                /** @var class-string<BackedEnum> $typeName */
                $enum = $typeName::tryFrom((int) $rawValue);
            }
            if ($enum !== null) {
                return $enum;
            }
            if ($isNullable) {
                return null;
            }

            throw ValidationException::withMessages([
                $paramName => ["The selected {$paramName} is invalid."],
            ]);
        }

        // Eloquent Model
        if (is_subclass_of($typeName, Model::class)) {
            if ($rawValue instanceof $typeName) {
                return $rawValue;
            }
            if ($rawValue instanceof Model) {
                $rawValue = $rawValue->getKey();
            }
            if (is_array($rawValue) && isset($rawValue['id'])) {
                $rawValue = $rawValue['id'];
            }
            $model = (is_scalar($rawValue) || $rawValue instanceof \Stringable) ? $typeName::find($rawValue) : null;
            if ($model !== null) {
                return $model;
            }
            if ($isNullable) {
                return null;
            }

            throw ValidationException::withMessages([
                $paramName => ["The selected {$paramName} was not found."],
            ]);
        }

        // Service Container DI
        if (class_exists($typeName)) {
            return app($typeName);
        }

        return $rawValue;
    }

    /**
     * Hydrate a union type.
     */
    protected function hydrateUnionType(
        ReflectionParameter $param,
        string $paramName,
        ReflectionUnionType $unionType,
        bool $found,
        mixed $rawValue
    ): mixed {
        $allowsNull = $unionType->allowsNull();

        if (! $found || $rawValue === null) {
            if ($param->isDefaultValueAvailable()) {
                return $param->getDefaultValue();
            }
            if ($allowsNull) {
                return null;
            }

            throw ValidationException::withMessages([
                $paramName => ["The {$paramName} parameter is required."],
            ]);
        }

        foreach ($unionType->getTypes() as $subType) {
            if ($subType instanceof ReflectionNamedType && $subType->getName() !== 'null') {
                try {
                    return $this->hydrateNamedType($param, $paramName, $subType, $found, $rawValue);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $rawValue;
    }

    protected function castToInt(string $paramName, mixed $value): int
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                $paramName => ["The {$paramName} parameter must be an integer."],
            ]);
        }

        return (int) $value;
    }

    protected function castToFloat(string $paramName, mixed $value): float
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                $paramName => ["The {$paramName} parameter must be a number."],
            ]);
        }

        return (float) $value;
    }

    protected function castToBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<int|string, mixed>
     */
    protected function castToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return (array) $value;
    }
}
