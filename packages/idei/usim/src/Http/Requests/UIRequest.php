<?php

namespace Idei\Usim\Http\Requests;

use ArrayAccess;
use Idei\Usim\Contracts\UIElement;
use Idei\Usim\Contracts\UsimTranslatorInterface;
use Idei\Usim\Exceptions\UIRequestValidationException;
use Idei\Usim\Screen;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Arr;
use Illuminate\Support\ValidatedInput;
use Illuminate\Validation\Factory;
use ReflectionClass;
use ReflectionProperty;

/**
 * Base UIRequest class for USIM.
 *
 * Encapsulates validation, authorization, type casting, field mapping,
 * and automatic UI error binding for Screen actions (POST) and queries (GET).
 *
 * @implements ArrayAccess<string, mixed>
 */
abstract class UIRequest implements ArrayAccess
{
    /**
     * Screen instance that originated the event/query.
     */
    protected ?Screen $screen = null;

    /**
     * ID of the component that triggered the event, if available.
     */
    protected ?int $triggerComponentId = null;

    /**
     * Raw parameters received from frontend before mapping and normalization.
     *
     * @var array<string, mixed>
     */
    protected array $rawParameters = [];

    /**
     * Incoming storage payload (X-USIM-Storage).
     *
     * @var array<string, mixed>
     */
    protected array $incomingStorage = [];

    /**
     * Query string parameters from the request.
     *
     * @var array<string, mixed>
     */
    protected array $queryParams = [];

    /**
     * Normalized request parameters available for validation and access.
     *
     * @var array<string, mixed>
     */
    protected array $parameters = [];

    /**
     * Validated data after successful validation.
     *
     * @var array<string, mixed>
     */
    protected array $validatedData = [];

    /**
     * Mapping from clean attribute names to component keys in $parameters.
     *
     * Example: ['name' => 'input_device_name', 'pin' => 'input_pin']
     *
     * @var array<string, string>
     */
    protected array $map = [];

    /**
     * Whether validation errors should automatically be bound to matching
     * components in the Screen on POST actions.
     */
    protected bool $autoBindComponentErrors = true;

    /**
     * Toast notification translation key or message shown on validation failure.
     * Set to null to disable toast notification.
     */
    protected ?string $errorToast = 'validation.check_form_errors';

    /**
     * Internal validator instance.
     */
    protected ?Validator $validator = null;

    /**
     * Initialize the request with context and parameters.
     *
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $incomingStorage
     * @param  array<string, mixed>  $queryParams
     */
    public function initialize(
        Screen $screen,
        array $parameters = [],
        array $incomingStorage = [],
        array $queryParams = [],
        ?int $triggerComponentId = null
    ): static {
        $this->screen = $screen;
        $this->triggerComponentId = $triggerComponentId;
        $this->rawParameters = $parameters;
        $this->incomingStorage = $incomingStorage;
        $this->queryParams = $queryParams;

        $normalized = $parameters;

        // Apply attribute mapping
        foreach ($this->map() as $cleanKey => $sourceKey) {
            if (array_key_exists($sourceKey, $normalized) && ! array_key_exists($cleanKey, $normalized)) {
                $normalized[$cleanKey] = $normalized[$sourceKey];
            }
        }

        $this->parameters = $normalized;

        $this->prepareForValidation();

        return $this;
    }

    /**
     * Get the attribute mapping for components.
     *
     * @return array<string, string>
     */
    public function map(): array
    {
        return $this->map;
    }

    /**
     * Validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Determine if the user is authorized to perform this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Custom validation error messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Custom attribute display names for validation errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Hook called before validation occurs.
     * Use to sanitize, trim, cast or transform parameters.
     */
    protected function prepareForValidation(): void {}

    /**
     * Hook called after successful validation.
     */
    protected function passedValidation(): void
    {
        if ($this->screen !== null && $this->shouldAutoBindErrors()) {
            $inverseMap = array_flip($this->map());
            foreach (array_keys($this->rules()) as $key) {
                $componentName = $this->map()[$key] ?? $key;
                $component = $this->findComponent($componentName);

                if ($component === null && isset($inverseMap[$key])) {
                    $component = $this->findComponent($inverseMap[$key]);
                }

                if ($component !== null) {
                    if (method_exists($component, 'error')) {
                        $component->error(null);
                    } elseif (method_exists($component, 'errorMessage')) {
                        $component->errorMessage('');
                    }
                }
            }
        }
    }

    /**
     * Hook called when validation fails in an action lifecycle.
     */
    public function failedValidation(Validator $validator): void
    {
        if ($this->screen !== null && method_exists($this->screen, 'onValidationFailed')) {
            $this->screen->onValidationFailed($this, $validator);
        }
    }

    /**
     * Get the data to be validated.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return $this->parameters;
    }

    /**
     * Validate the request and return validated data.
     *
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     * @throws UIRequestValidationException
     */
    public function validate(): array
    {
        if (! $this->authorize()) {
            throw new AuthorizationException('This UI request is unauthorized.');
        }

        $validator = $this->getValidator();

        if ($validator->fails()) {
            throw new UIRequestValidationException($validator, $this);
        }

        $this->validatedData = $validator->validated();
        $this->populatePublicProperties();
        $this->passedValidation();

        return $this->validatedData;
    }

    /**
     * Populate any declared public or asymmetric-visible properties matching validated data.
     */
    protected function populatePublicProperties(): void
    {
        $reflection = new ReflectionClass($this);
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || $property->getDeclaringClass()->getName() === self::class) {
                continue;
            }

            $name = $property->getName();

            if (array_key_exists($name, $this->validatedData)) {
                try {
                    $property->setValue($this, $this->validatedData[$name]);
                } catch (\Throwable) {
                    // Ignore type mismatch if cast isn't possible
                }
            } elseif (! $property->isInitialized($this) && $property->getType()?->allowsNull()) {
                try {
                    $property->setValue($this, null);
                } catch (\Throwable) {
                    // Ignore
                }
            }
        }
    }

    /**
     * Get or create the underlying validator instance.
     */
    public function getValidator(): Validator
    {
        if ($this->validator === null) {
            /** @var Factory $validatorFactory */
            $validatorFactory = app('validator');

            $this->validator = $validatorFactory->make(
                $this->validationData(),
                $this->rules(),
                $this->messages(),
                $this->resolvedAttributes()
            );
        }

        return $this->validator;
    }

    /**
     * Resolve attribute names, merging custom attributes with auto-inferred component labels.
     *
     * @return array<string, string>
     */
    public function resolvedAttributes(): array
    {
        $attributes = $this->attributes();

        foreach (array_keys($this->rules()) as $key) {
            if (! isset($attributes[$key])) {
                $inferred = $this->resolveAttributeLabel($key);
                if ($inferred !== null) {
                    $attributes[$key] = $inferred;
                }
            }
        }

        return $attributes;
    }

    /**
     * Invert component label from Screen component if available.
     */
    public function resolveAttributeLabel(string $key): ?string
    {
        if ($this->screen === null) {
            return null;
        }

        $componentName = $this->map()[$key] ?? $key;
        $component = $this->findComponent($componentName);

        if ($component !== null) {
            if (method_exists($component, 'getLabel')) {
                $label = $component->getLabel();
                if (is_string($label) && trim($label) !== '') {
                    return trim($label);
                }
            }

            if (method_exists($component, 'getPlaceholder')) {
                $placeholder = $component->getPlaceholder();
                if (is_string($placeholder) && trim($placeholder) !== '') {
                    return trim($placeholder);
                }
            }
        }

        $i18nKey = "validation.attributes.{$key}";
        if (function_exists('t') && app()->bound(UsimTranslatorInterface::class)) {
            $translator = app(UsimTranslatorInterface::class);
            if ($translator->has($i18nKey)) {
                return $translator->get($i18nKey);
            }
        }

        return null;
    }

    /**
     * Find a component on the screen by name or mapped identifier.
     */
    public function findComponent(string $name): ?UIElement
    {
        if ($this->screen === null) {
            return null;
        }

        // 1. Container search
        $container = $this->screen->getContainer();
        if ($container !== null) {
            $found = $container->findByName($name);
            if ($found !== null) {
                return $found;
            }
        }

        // 2. Screen property search via reflection
        $ref = new ReflectionClass($this->screen);
        if ($ref->hasProperty($name)) {
            $prop = $ref->getProperty($name);
            $prop->setAccessible(true);
            if ($prop->isInitialized($this->screen)) {
                $val = $prop->getValue($this->screen);
                if ($val instanceof UIElement) {
                    return $val;
                }
            }
        }

        // 3. Fallback with or without 'input_' prefix
        if (! str_starts_with($name, 'input_')) {
            $prefixedName = 'input_'.$name;
            if ($container !== null) {
                $found = $container->findByName($prefixedName);
                if ($found !== null) {
                    return $found;
                }
            }
            if ($ref->hasProperty($prefixedName)) {
                $prop = $ref->getProperty($prefixedName);
                $prop->setAccessible(true);
                if ($prop->isInitialized($this->screen)) {
                    $val = $prop->getValue($this->screen);
                    if ($val instanceof UIElement) {
                        return $val;
                    }
                }
            }
        } elseif (str_starts_with($name, 'input_')) {
            $unprefixed = substr($name, 6);
            if ($container !== null) {
                $found = $container->findByName($unprefixed);
                if ($found !== null) {
                    return $found;
                }
            }
            if ($ref->hasProperty($unprefixed)) {
                $prop = $ref->getProperty($unprefixed);
                $prop->setAccessible(true);
                if ($prop->isInitialized($this->screen)) {
                    $val = $prop->getValue($this->screen);
                    if ($val instanceof UIElement) {
                        return $val;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Whether errors should be bound automatically to components.
     */
    public function shouldAutoBindErrors(): bool
    {
        return $this->autoBindComponentErrors;
    }

    /**
     * Get the translated error toast message if configured.
     */
    public function errorToastMessage(): ?string
    {
        if ($this->errorToast === null || $this->errorToast === '') {
            return null;
        }

        if (function_exists('t')) {
            return t($this->errorToast);
        }

        return $this->errorToast;
    }

    /**
     * Automatically bind validation error messages to screen components.
     */
    public function bindErrorsToScreen(Screen $screen, MessageBag $errors): void
    {
        $inverseMap = array_flip($this->map());

        foreach ($errors->getMessages() as $field => $messages) {
            $componentName = $this->map()[$field] ?? $field;
            $component = $this->findComponent($componentName);

            if ($component === null && isset($inverseMap[$field])) {
                $component = $this->findComponent($inverseMap[$field]);
            }

            if ($component !== null) {
                $firstMessage = is_array($messages) ? ($messages[0] ?? '') : (string) $messages;

                if (method_exists($component, 'error')) {
                    $component->error($firstMessage);
                } elseif (method_exists($component, 'errorMessage')) {
                    $component->errorMessage($firstMessage);
                }
            }
        }
    }

    /**
     * Get validated data or a specific validated key.
     */
    public function validated(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->validatedData;
        }

        return data_get($this->validatedData, $key, $default);
    }

    /**
     * Get a ValidatedInput instance or filtered array of validated fields.
     *
     * @param  list<string>|null  $keys
     */
    public function safe(?array $keys = null): mixed
    {
        $data = $keys ? Arr::only($this->validatedData, $keys) : $this->validatedData;

        if (class_exists(ValidatedInput::class)) {
            return new ValidatedInput($data);
        }

        return $data;
    }

    /**
     * Convert validated data into a typed DTO instance.
     *
     * @template T of object
     *
     * @param  class-string<T>  $dtoClass
     * @return T
     */
    public function toDto(string $dtoClass): object
    {
        return new $dtoClass(...$this->validated());
    }

    /**
     * Get all normalized request parameters.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->parameters;
    }

    /**
     * Retrieve a parameter by key.
     */
    public function input(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->parameters;
        }

        return data_get($this->parameters, $key, $default);
    }

    /**
     * Merge additional parameters into the request.
     *
     * @param  array<string, mixed>  $input
     */
    public function merge(array $input): static
    {
        $this->parameters = array_merge($this->parameters, $input);

        return $this;
    }

    /**
     * Determine if a parameter exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->parameters);
    }

    /**
     * Retrieve raw parameter as received from frontend.
     */
    public function rawParameter(string $key, mixed $default = null): mixed
    {
        return data_get($this->rawParameters, $key, $default);
    }

    /**
     * Retrieve incoming storage (X-USIM-Storage) data.
     */
    public function storage(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->incomingStorage;
        }

        return data_get($this->incomingStorage, $key, $default);
    }

    /**
     * Retrieve query parameters.
     */
    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }

        return data_get($this->queryParams, $key, $default);
    }

    /**
     * Get the associated screen instance.
     */
    public function screen(): ?Screen
    {
        return $this->screen;
    }

    /**
     * Get the component ID that triggered this request.
     */
    public function triggerComponentId(): ?int
    {
        return $this->triggerComponentId;
    }

    // --- ArrayAccess implementation ---

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->input((string) $offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->parameters[(string) $offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->parameters[(string) $offset]);
    }

    // --- Magic property accessor ---

    public function __get(string $key): mixed
    {
        if (array_key_exists($key, $this->validatedData)) {
            return $this->validatedData[$key];
        }

        return $this->input($key);
    }
}
