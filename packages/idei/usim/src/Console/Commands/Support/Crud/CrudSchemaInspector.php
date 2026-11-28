<?php

declare(strict_types=1);

namespace Idei\Usim\Console\Commands\Support\Crud;

use Idei\Usim\Attributes\Crud\BelongsTo;
use Idei\Usim\Attributes\Crud\BelongsToMany;
use Idei\Usim\Attributes\Crud\CrudField;
use Idei\Usim\Components\Checkbox;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Components\Select;
use Idei\Usim\Components\Textarea;
use Idei\Usim\Contracts\ItemList\Deletable as ItemListDeletable;
use Idei\Usim\Contracts\ItemList\Listable;
use Idei\Usim\Contracts\MasterDetail\CreatableDetail;
use Idei\Usim\Contracts\MasterDetail\DeletableDetail;
use Idei\Usim\Contracts\MasterDetail\Detailable;
use Idei\Usim\Contracts\MasterDetail\UpdatableDetail;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

/**
 * Inspects predefined USIM Services and DTOs via PHP Reflection, and parses CLI `--fields` specifications.
 */
final class CrudSchemaInspector
{
    private const TEXTAREA_FIELD_NAMES = [
        'description',
        'notes',
        'summary',
        'body',
        'content',
        'bio',
        'comment',
        'comments',
        'details',
    ];

    public function __construct(
        private readonly Filesystem $filesystem = new Filesystem,
    ) {}

    /**
     * Normalize a user-supplied service class reference (FQCN or short path like `Catalog/ProductService`).
     */
    public function resolveServiceFqcn(string $rawService, string $defaultNamespace = 'App\\Services'): string
    {
        $trimmed = ltrim(trim($rawService), '\\');
        if ($trimmed === '') {
            throw new InvalidArgumentException('Service class name cannot be empty.');
        }

        $normalized = str_replace('/', '\\', $trimmed);
        if (class_exists($normalized)) {
            return $normalized;
        }

        $prefixed = rtrim($defaultNamespace, '\\').'\\'.$normalized;
        if (class_exists($prefixed)) {
            return $prefixed;
        }

        return str_starts_with($normalized, 'App\\') || str_starts_with($normalized, 'Idei\\') || str_starts_with($normalized, 'Tests\\')
            ? $normalized
            : $prefixed;
    }

    /**
     * Inspect an existing Service class and its associated detail DTO.
     *
     * @param  list<CrudFieldDescriptor>|null  $overrideFields
     */
    public function inspectService(string $serviceFqcn, ?array $overrideFields = null): CrudServiceMetadata
    {
        if (! class_exists($serviceFqcn)) {
            throw new InvalidArgumentException("Service class [{$serviceFqcn}] does not exist.");
        }

        $ref = new ReflectionClass($serviceFqcn);
        $isListable = $ref->implementsInterface(Listable::class);
        $isDetailable = $ref->implementsInterface(Detailable::class);

        if (! $isListable) {
            throw new InvalidArgumentException(
                "Service [{$serviceFqcn}] must implement [".Listable::class.'] to feed the left-panel ItemList.'
            );
        }

        if (! $isDetailable) {
            throw new InvalidArgumentException(
                "Service [{$serviceFqcn}] must implement [".Detailable::class.'] to hydrate the central detail view.'
            );
        }

        $isCreatable = $ref->implementsInterface(CreatableDetail::class);
        $isUpdatable = $ref->implementsInterface(UpdatableDetail::class);
        $isDeletable = $ref->implementsInterface(DeletableDetail::class)
            || $ref->implementsInterface(ItemListDeletable::class);

        $dtoFqcn = $this->resolveDtoClassFromService($ref);
        $fields = $overrideFields ?? $this->inspectDtoFields($dtoFqcn);

        return new CrudServiceMetadata(
            serviceFqcn: $serviceFqcn,
            dtoFqcn: $dtoFqcn,
            isListable: $isListable,
            isDetailable: $isDetailable,
            isCreatable: $isCreatable,
            isUpdatable: $isUpdatable,
            isDeletable: $isDeletable,
            fields: $fields,
        );
    }

    /**
     * Inspect a DTO class's constructor parameters and public properties into field descriptors.
     *
     * @param  class-string  $dtoFqcn
     * @return list<CrudFieldDescriptor>
     */
    public function inspectDtoFields(string $dtoFqcn): array
    {
        if (! class_exists($dtoFqcn)) {
            throw new InvalidArgumentException("Detail DTO class [{$dtoFqcn}] does not exist.");
        }

        $ref = new ReflectionClass($dtoFqcn);
        $constructor = $ref->getConstructor();
        $fields = [];

        if ($constructor !== null && $constructor->getParameters() !== []) {
            foreach ($constructor->getParameters() as $param) {
                $name = $param->getName();
                if ($name === 'id') {
                    continue;
                }

                $property = $ref->hasProperty($name) ? $ref->getProperty($name) : null;
                $descriptor = $this->buildDescriptorFromReflection(
                    name: $name,
                    type: $param->getType(),
                    hasDefault: $param->isDefaultValueAvailable(),
                    defaultValue: $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                    paramAttributes: $param->getAttributes(),
                    propAttributes: $property !== null ? $property->getAttributes() : [],
                );

                if ($descriptor !== null) {
                    $fields[] = $descriptor;
                }
            }

            return $fields;
        }

        foreach ($ref->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            if ($name === 'id') {
                continue;
            }

            $descriptor = $this->buildDescriptorFromReflection(
                name: $name,
                type: $property->getType(),
                hasDefault: $property->hasDefaultValue(),
                defaultValue: $property->hasDefaultValue() ? $property->getDefaultValue() : null,
                paramAttributes: [],
                propAttributes: $property->getAttributes(),
            );

            if ($descriptor !== null) {
                $fields[] = $descriptor;
            }
        }

        return $fields;
    }

    /**
     * Parse a CLI `--fields="..."` specification into normalized field descriptors.
     *
     * Syntax examples:
     * - `sku:string,name:string,price:float,active:bool,notes:text?`
     * - `brandId:one(Catalog/BrandService),tagIds:many(Catalog/TagService)`
     *
     * @return list<CrudFieldDescriptor>
     */
    public function parseFieldsSpecification(?string $rawFields, string $defaultServicesNamespace = 'App\\Services'): array
    {
        $spec = $rawFields !== null && trim($rawFields) !== ''
            ? trim($rawFields)
            : 'name:string,description:text?,active:bool';

        $segments = $this->splitFieldSegments($spec);
        $descriptors = [];

        foreach ($segments as $segment) {
            $parts = explode(':', $segment, 2);
            $rawName = trim($parts[0]);
            $rawType = isset($parts[1]) ? trim($parts[1]) : 'string';

            if ($rawName === '' || $rawName === 'id') {
                continue;
            }

            $nullable = str_ends_with($rawType, '?');
            $cleanType = rtrim($rawType, '?');

            if (preg_match('/^(one|belongsTo|belongs_to)\(([^)]+)\)$/i', $cleanType, $matches) === 1) {
                $targetService = $this->resolveServiceFqcn($matches[2], $defaultServicesNamespace);
                $snake = Str::snake($rawName);
                $descriptors[] = new CrudFieldDescriptor(
                    property: $rawName,
                    snakeName: $snake,
                    phpType: 'string',
                    nullable: true,
                    hasDefault: true,
                    defaultValue: null,
                    widget: 'belongs_to',
                    componentName: 'sel_'.$snake,
                    componentClass: Select::class,
                    validationRules: $nullable ? 'nullable|string' : 'required|string',
                    span: 1,
                    relatedServiceFqcn: $targetService,
                    searchable: true,
                );

                continue;
            }

            if (preg_match('/^(many|belongsToMany|belongs_to_many)\(([^)]+)\)$/i', $cleanType, $matches) === 1) {
                $targetService = $this->resolveServiceFqcn($matches[2], $defaultServicesNamespace);
                $snake = Str::snake($rawName);
                $descriptors[] = new CrudFieldDescriptor(
                    property: $rawName,
                    snakeName: $snake,
                    phpType: 'array',
                    nullable: false,
                    hasDefault: true,
                    defaultValue: [],
                    widget: 'belongs_to_many',
                    componentName: 'lst_'.$snake,
                    componentClass: ItemList::class,
                    validationRules: 'sometimes|array',
                    span: 2,
                    relatedServiceFqcn: $targetService,
                    searchable: true,
                    maxHeight: 240,
                );

                continue;
            }

            $descriptors[] = $this->buildScalarDescriptorFromSpec($rawName, strtolower($cleanType), $nullable);
        }

        return $descriptors;
    }

    /**
     * Scan `app/Services` for existing concrete classes implementing both `Listable` and `Detailable`.
     *
     * @return list<class-string>
     */
    public function discoverCandidateServices(string $servicesDirectory, string $baseNamespace = 'App\\Services'): array
    {
        if (! $this->filesystem->isDirectory($servicesDirectory)) {
            return [];
        }

        $candidates = [];
        foreach ($this->filesystem->allFiles($servicesDirectory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(['/', '\\'], '\\', substr($file->getPathname(), strlen(rtrim($servicesDirectory, '/\\')) + 1));
            $classBase = substr($relative, 0, -4);
            $fqcn = rtrim($baseNamespace, '\\').'\\'.$classBase;

            if (! class_exists($fqcn)) {
                continue;
            }

            $ref = new ReflectionClass($fqcn);
            if ($ref->isInstantiable() && $ref->implementsInterface(Listable::class) && $ref->implementsInterface(Detailable::class)) {
                $candidates[] = $fqcn;
            }
        }

        sort($candidates);

        return $candidates;
    }

    /**
     * @param  ReflectionClass<object>  $serviceRef
     * @return class-string
     */
    private function resolveDtoClassFromService(ReflectionClass $serviceRef): string
    {
        if ($serviceRef->hasMethod('findDetail')) {
            $dtoFromReturn = $this->extractObjectClassFromMethodReturn($serviceRef->getMethod('findDetail'), $serviceRef);
            if ($dtoFromReturn !== null) {
                return $dtoFromReturn;
            }
        }

        foreach (['createDetail', 'updateDetail'] as $mutationMethod) {
            if (! $serviceRef->hasMethod($mutationMethod)) {
                continue;
            }

            $dtoFromParam = $this->extractObjectClassFromMethodParams($serviceRef->getMethod($mutationMethod), $serviceRef);
            if ($dtoFromParam !== null) {
                return $dtoFromParam;
            }
        }

        $docComment = (string) $serviceRef->getDocComment();
        if (preg_match('/@implements\s+Detailable<([^>\s]+)>/', $docComment, $matches) === 1) {
            $resolved = $this->resolveDocblockClassReference($matches[1], $serviceRef);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        throw new InvalidArgumentException(
            "Unable to infer detail DTO class from Service [{$serviceRef->getName()}]. "
            .'Add a concrete return type to findDetail() (e.g. `public function findDetail(int|string $id): ?ProductDTO`) '
            .'or annotate the service with `@implements Detailable<ProductDTO>`.'
        );
    }

    /**
     * @param  ReflectionClass<object>  $declaringService
     * @return class-string|null
     */
    private function extractObjectClassFromMethodReturn(ReflectionMethod $method, ReflectionClass $declaringService): ?string
    {
        $returnType = $method->getReturnType();
        $namedClass = $this->extractConcreteClassFromType($returnType);
        if ($namedClass !== null) {
            return $namedClass;
        }

        $doc = (string) $method->getDocComment();
        if (preg_match('/@return\s+([^\s|]+)/', $doc, $matches) === 1) {
            $candidate = ltrim(trim($matches[1], '?'), '\\');
            if ($candidate !== 'null' && $candidate !== 'object') {
                return $this->resolveDocblockClassReference($candidate, $declaringService);
            }
        }

        return null;
    }

    /**
     * @param  ReflectionClass<object>  $declaringService
     * @return class-string|null
     */
    private function extractObjectClassFromMethodParams(ReflectionMethod $method, ReflectionClass $declaringService): ?string
    {
        foreach ($method->getParameters() as $param) {
            $namedClass = $this->extractConcreteClassFromType($param->getType());
            if ($namedClass !== null) {
                return $namedClass;
            }
        }

        $doc = (string) $method->getDocComment();
        if (preg_match('/@param\s+([^\s|$]+)\s+\$dto/', $doc, $matches) === 1) {
            return $this->resolveDocblockClassReference(trim($matches[1], '?'), $declaringService);
        }

        return null;
    }

    /**
     * @return class-string|null
     */
    private function extractConcreteClassFromType(?ReflectionType $type): ?string
    {
        if ($type instanceof ReflectionNamedType) {
            if (! $type->isBuiltin()) {
                $name = $type->getName();
                if (class_exists($name)) {
                    return $name;
                }
            }

            return null;
        }

        if ($type instanceof ReflectionUnionType) {
            foreach ($type->getTypes() as $subType) {
                if ($subType instanceof ReflectionNamedType && ! $subType->isBuiltin()) {
                    $name = $subType->getName();
                    if (class_exists($name)) {
                        return $name;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  ReflectionClass<object>  $contextClass
     * @return class-string|null
     */
    private function resolveDocblockClassReference(string $typeToken, ReflectionClass $contextClass): ?string
    {
        $clean = ltrim(trim($typeToken), '\\');
        if (class_exists($clean)) {
            return $clean;
        }

        $sameNamespace = $contextClass->getNamespaceName().'\\'.$clean;
        if (class_exists($sameNamespace)) {
            return $sameNamespace;
        }

        $fileName = $contextClass->getFileName();
        if ($fileName !== false && $this->filesystem->exists($fileName)) {
            $source = $this->filesystem->get($fileName);
            $pattern = '/^use\s+([a-zA-Z0-9_\\\\]+\\\\'.preg_quote($clean, '/').')\s*;/m';
            if (preg_match($pattern, $source, $matches) === 1 && class_exists($matches[1])) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * @param  list<ReflectionAttribute<object>>  $paramAttributes
     * @param  list<ReflectionAttribute<object>>  $propAttributes
     */
    private function buildDescriptorFromReflection(
        string $name,
        ?ReflectionType $type,
        bool $hasDefault,
        mixed $defaultValue,
        array $paramAttributes,
        array $propAttributes,
    ): ?CrudFieldDescriptor {
        $fieldMeta = $this->findAttributeInstance(CrudField::class, $paramAttributes, $propAttributes);
        if ($fieldMeta !== null && $fieldMeta->ignore) {
            return null;
        }

        $snake = Str::snake($name);
        $belongsTo = $this->findAttributeInstance(BelongsTo::class, $paramAttributes, $propAttributes);
        if ($belongsTo !== null) {
            $nullable = $type === null || $type->allowsNull();
            $rules = ($fieldMeta !== null && $fieldMeta->rules !== null)
                ? $fieldMeta->rules
                : ($nullable ? 'nullable|string' : 'required|string');

            return new CrudFieldDescriptor(
                property: $name,
                snakeName: $snake,
                phpType: $this->resolvePrimaryBuiltinType($type) === 'int' ? 'int' : 'string',
                nullable: $nullable,
                hasDefault: $hasDefault,
                defaultValue: $defaultValue,
                widget: 'belongs_to',
                componentName: 'sel_'.$snake,
                componentClass: Select::class,
                validationRules: $rules,
                span: ($fieldMeta !== null && $fieldMeta->span !== null) ? $fieldMeta->span : 1,
                readonly: $fieldMeta !== null ? $fieldMeta->readonly : false,
                relatedServiceFqcn: $belongsTo->service,
                searchable: $belongsTo->searchable,
            );
        }

        $belongsToMany = $this->findAttributeInstance(BelongsToMany::class, $paramAttributes, $propAttributes);
        if ($belongsToMany !== null) {
            $rules = ($fieldMeta !== null && $fieldMeta->rules !== null)
                ? $fieldMeta->rules
                : 'sometimes|array';

            return new CrudFieldDescriptor(
                property: $name,
                snakeName: $snake,
                phpType: 'array',
                nullable: false,
                hasDefault: $hasDefault,
                defaultValue: is_array($defaultValue) ? $defaultValue : [],
                widget: 'belongs_to_many',
                componentName: 'lst_'.$snake,
                componentClass: ItemList::class,
                validationRules: $rules,
                span: ($fieldMeta !== null && $fieldMeta->span !== null) ? $fieldMeta->span : 2,
                readonly: $fieldMeta !== null ? $fieldMeta->readonly : false,
                relatedServiceFqcn: $belongsToMany->service,
                searchable: $belongsToMany->searchable,
                maxHeight: $belongsToMany->maxHeight,
            );
        }

        $phpType = $this->resolvePrimaryBuiltinType($type);
        $nullable = $type !== null && $type->allowsNull();
        $widget = $this->resolveScalarWidget($snake, $phpType, $fieldMeta);
        [$prefix, $componentClass] = $this->resolveComponentForWidget($widget);
        $rules = ($fieldMeta !== null && $fieldMeta->rules !== null)
            ? $fieldMeta->rules
            : $this->inferValidationRules($widget, $phpType, $nullable);
        $defaultSpan = in_array($widget, ['textarea', 'belongs_to_many'], true) ? 2 : 1;

        return new CrudFieldDescriptor(
            property: $name,
            snakeName: $snake,
            phpType: $phpType,
            nullable: $nullable,
            hasDefault: $hasDefault,
            defaultValue: $defaultValue,
            widget: $widget,
            componentName: $prefix.$snake,
            componentClass: $componentClass,
            validationRules: $rules,
            span: ($fieldMeta !== null && $fieldMeta->span !== null) ? $fieldMeta->span : $defaultSpan,
            readonly: $fieldMeta !== null ? $fieldMeta->readonly : false,
        );
    }

    /**
     * @template TAttr of object
     *
     * @param  class-string<TAttr>  $attributeClass
     * @param  list<ReflectionAttribute<object>>  $first
     * @param  list<ReflectionAttribute<object>>  $second
     * @return TAttr|null
     */
    private function findAttributeInstance(string $attributeClass, array $first, array $second): ?object
    {
        foreach (array_merge($first, $second) as $attr) {
            if ($attr->getName() === $attributeClass) {
                /** @var TAttr $instance */
                $instance = $attr->newInstance();

                return $instance;
            }
        }

        return null;
    }

    /**
     * @return 'string'|'int'|'float'|'bool'|'array'
     */
    private function resolvePrimaryBuiltinType(?ReflectionType $type): string
    {
        if ($type instanceof ReflectionNamedType) {
            return match ($type->getName()) {
                'int' => 'int',
                'float' => 'float',
                'bool' => 'bool',
                'array' => 'array',
                default => 'string',
            };
        }

        if ($type instanceof ReflectionUnionType) {
            $names = [];
            foreach ($type->getTypes() as $sub) {
                if ($sub instanceof ReflectionNamedType) {
                    $names[] = $sub->getName();
                }
            }

            if (in_array('array', $names, true)) {
                return 'array';
            }
            if (in_array('bool', $names, true)) {
                return 'bool';
            }
            if (in_array('float', $names, true)) {
                return 'float';
            }
            if (in_array('int', $names, true) && ! in_array('string', $names, true)) {
                return 'int';
            }
        }

        return 'string';
    }

    /**
     * @param  'string'|'int'|'float'|'bool'|'array'  $phpType
     * @return 'text'|'email'|'number'|'password'|'textarea'|'checkbox'
     */
    private function resolveScalarWidget(string $snakeName, string $phpType, ?CrudField $meta): string
    {
        $explicit = $meta?->type;
        if ($explicit !== null && in_array($explicit, ['text', 'email', 'number', 'password', 'textarea', 'checkbox'], true)) {
            /** @var 'text'|'email'|'number'|'password'|'textarea'|'checkbox' $explicit */
            return $explicit;
        }

        if ($phpType === 'bool') {
            return 'checkbox';
        }

        if ($phpType === 'int' || $phpType === 'float') {
            return 'number';
        }

        if ($snakeName === 'email' || str_ends_with($snakeName, '_email')) {
            return 'email';
        }

        if ($snakeName === 'password' || str_ends_with($snakeName, '_password')) {
            return 'password';
        }

        if (in_array($snakeName, self::TEXTAREA_FIELD_NAMES, true)) {
            return 'textarea';
        }

        return 'text';
    }

    /**
     * @param  'text'|'email'|'number'|'password'|'textarea'|'checkbox'  $widget
     * @return array{0: string, 1: class-string}
     */
    private function resolveComponentForWidget(string $widget): array
    {
        return match ($widget) {
            'textarea' => ['txt_', Textarea::class],
            'checkbox' => ['chk_', Checkbox::class],
            default => ['input_', Input::class],
        };
    }

    /**
     * @param  'text'|'email'|'number'|'password'|'textarea'|'checkbox'  $widget
     * @param  'string'|'int'|'float'|'bool'|'array'  $phpType
     */
    private function inferValidationRules(string $widget, string $phpType, bool $nullable): string
    {
        if ($widget === 'checkbox' || $phpType === 'bool') {
            return 'boolean';
        }

        $presence = $nullable ? 'nullable' : 'required';

        if ($phpType === 'int') {
            return "{$presence}|integer";
        }

        if ($phpType === 'float' || $widget === 'number') {
            return "{$presence}|numeric";
        }

        if ($widget === 'email') {
            return "{$presence}|email|max:255";
        }

        if ($widget === 'textarea') {
            return "{$presence}|string|max:2000";
        }

        return "{$presence}|string|max:255";
    }

    private function buildScalarDescriptorFromSpec(string $property, string $cleanType, bool $nullable): CrudFieldDescriptor
    {
        $snake = Str::snake($property);

        /** @var 'string'|'int'|'float'|'bool' $phpType */
        $phpType = match ($cleanType) {
            'int', 'integer' => 'int',
            'float', 'double', 'decimal', 'number', 'numeric' => 'float',
            'bool', 'boolean', 'checkbox' => 'bool',
            default => 'string',
        };

        /** @var 'text'|'email'|'number'|'password'|'textarea'|'checkbox' $widget */
        $widget = match ($cleanType) {
            'bool', 'boolean', 'checkbox' => 'checkbox',
            'int', 'integer', 'float', 'double', 'decimal', 'number', 'numeric' => 'number',
            'email' => 'email',
            'password' => 'password',
            'text', 'textarea' => 'textarea',
            default => $this->resolveScalarWidget($snake, $phpType, null),
        };

        [$prefix, $componentClass] = $this->resolveComponentForWidget($widget);
        $hasDefault = $nullable || $phpType === 'bool';
        $defaultValue = $phpType === 'bool' ? true : null;

        return new CrudFieldDescriptor(
            property: $property,
            snakeName: $snake,
            phpType: $phpType,
            nullable: $phpType === 'bool' ? false : $nullable,
            hasDefault: $hasDefault,
            defaultValue: $defaultValue,
            widget: $widget,
            componentName: $prefix.$snake,
            componentClass: $componentClass,
            validationRules: $this->inferValidationRules($widget, $phpType, $nullable),
            span: $widget === 'textarea' ? 2 : 1,
        );
    }

    /**
     * Split comma-separated field definitions while respecting parentheses (`many(FooService)`).
     *
     * @return list<string>
     */
    private function splitFieldSegments(string $spec): array
    {
        $segments = [];
        $current = '';
        $depth = 0;
        $length = strlen($spec);

        for ($i = 0; $i < $length; $i++) {
            $char = $spec[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')' && $depth > 0) {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                if (trim($current) !== '') {
                    $segments[] = trim($current);
                }
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $segments[] = trim($current);
        }

        return $segments;
    }
}
