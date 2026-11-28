<?php

declare(strict_types=1);

namespace Idei\Usim\Console\Commands\Support\Crud;

use Idei\Usim\Attributes\Crud\BelongsTo;
use Idei\Usim\Attributes\Crud\BelongsToMany;
use Idei\Usim\Components\Button;
use Illuminate\Support\Str;

/**
 * Builds clean, strongly-typed replacement maps for all USIM CRUD & Mock-Stack `.stub` templates.
 */
final class CrudStubRenderer
{
    /**
     * Build placeholder replacements for `screen.php.stub` or `screen_readonly.php.stub`.
     *
     * @param  array{declaration: ?string, import: ?string, normalized: string}  $layoutInfo
     * @return array<string, string>
     */
    public function buildScreenReplacements(
        string $namespace,
        string $className,
        string $visibilityEnum,
        array $layoutInfo,
        CrudServiceMetadata $meta,
        ?string $requestFqcn,
    ): array {
        $serviceShort = class_basename($meta->serviceFqcn);
        $dtoShort = class_basename($meta->dtoFqcn);
        $requestShort = $requestFqcn !== null ? class_basename($requestFqcn) : '';

        $imports = [
            'Idei\\Usim\\Concerns\\InteractsWithMasterDetail',
            'Idei\\Usim\\Contracts\\MasterDetail\\Detailable',
            'Idei\\Usim\\Enums\\Visibility',
            'Idei\\Usim\\Screen',
            $meta->dtoFqcn,
            $meta->serviceFqcn,
        ];

        if ($layoutInfo['import'] !== null) {
            $imports[] = $layoutInfo['import'];
        }

        if ($requestFqcn !== null && $meta->isMutable()) {
            $imports[] = $requestFqcn;
        }

        if ($meta->isCreatable || $meta->isDeletable || $meta->isMutable()) {
            $imports[] = Button::class;
        }

        foreach ($meta->fields as $field) {
            $imports[] = $field->componentClass;
            if ($field->widget === 'belongs_to' && $field->relatedServiceFqcn !== null) {
                $imports[] = $field->relatedServiceFqcn;
            }
        }

        $imports = array_values(array_unique($imports));
        sort($imports);
        $importsBlock = implode("\n", array_map(static fn (string $fqcn): string => "use {$fqcn};", $imports));

        $properties = [];
        if ($meta->isCreatable) {
            $properties[] = 'protected Button $btn_new;';
            $properties[] = '';
            $properties[] = 'protected Button $btn_empty_create;';
        }
        if ($meta->isDeletable) {
            if ($properties !== []) {
                $properties[] = '';
            }
            $properties[] = 'protected Button $btn_delete;';
        }
        if ($meta->isMutable()) {
            if ($properties !== []) {
                $properties[] = '';
            }
            $properties[] = 'protected Button $btn_save;';
            $properties[] = '';
            $properties[] = 'protected Button $btn_cancel;';
        }

        foreach ($meta->fields as $field) {
            if ($properties !== []) {
                $properties[] = '';
            }
            $compShort = class_basename($field->componentClass);
            $properties[] = "protected {$compShort} \${$field->componentName};";
        }

        $constructorLines = ["protected {$serviceShort} \$service,"];
        $relationLines = [];

        foreach ($meta->belongsToFields() as $relField) {
            if ($relField->relatedServiceFqcn === null) {
                continue;
            }
            $relServiceShort = class_basename($relField->relatedServiceFqcn);
            $relPropName = Str::camel($relField->snakeName).'OptionsService';
            $constructorLines[] = "protected {$relServiceShort} \${$relPropName},";
            $relationLines[] = "\$this->loadSelectOptionsFromService(\$this->{$relField->componentName}, \$this->{$relPropName});";
        }

        $hydrateLines = [];
        $resetLines = [];
        $clearErrorLines = [];

        foreach ($meta->fields as $field) {
            $hydrateLines[] = $this->buildFieldHydrateStatement($field, $meta->isReadOnly());
            if ($meta->isMutable()) {
                $resetLines[] = $this->buildFieldResetStatement($field);
                $clearErrorLines[] = $this->buildFieldClearErrorStatement($field);
            }
        }

        $relationsMethod = '';
        if ($relationLines !== []) {
            $relBody = implode("\n        ", $relationLines);
            $relationsMethod = <<<PHP
protected function initializeDetailRelations(): void
{
    {$relBody}
}
PHP;
        }

        $saveMethod = '';
        if ($meta->isMutable() && $requestShort !== '') {
            $saveMethod = <<<PHP
public function onSaveDetail({$requestShort} \$request): void
{
    \$this->persistDetailDto(\$request->toDto(\$this->state_selected_id));
}
PHP;
        }

        return [
            'namespace' => $namespace,
            'class' => $className,
            'visibility_enum' => $visibilityEnum,
            'layout_declaration' => $layoutInfo['declaration'] ?? "public static ?string \$layout = 'workbench';",
            'imports' => $importsBlock,
            'component_properties' => implode("\n", $properties),
            'service_class' => $serviceShort,
            'constructor_params' => implode("\n", $constructorLines),
            'dto_class' => $dtoShort,
            'request_class' => $requestShort,
            'hydrate_body' => $hydrateLines !== [] ? implode("\n", $hydrateLines) : '// No detail fields declared.',
            'reset_body' => $resetLines !== [] ? implode("\n", $resetLines) : '// No mutable fields declared.',
            'clear_errors_body' => $clearErrorLines !== [] ? implode("\n", $clearErrorLines) : '// No mutable fields declared.',
            'relations_method' => $relationsMethod,
            'save_method' => $saveMethod,
        ];
    }

    /**
     * Build placeholder replacements for `screen.layout.yaml.stub`.
     *
     * @return array<string, string>
     */
    public function buildLayoutYamlReplacements(string $className, CrudServiceMetadata $meta): array
    {
        $wrapperName = Str::snake($className).'_wrapper';

        $emptyActions = $meta->isCreatable
            ? '- btn_empty_create: { style: primary, justify: center, action: start_create }'
            : '';

        $fieldYamlLines = [];
        foreach ($meta->fields as $field) {
            $fieldYamlLines[] = $this->buildFieldYamlBlock($field, $meta->isReadOnly());
        }

        $detailActions = '';
        if ($meta->isMutable()) {
            $detailActions = <<<'YAML'
- cnt_form_actions:
    span: 2
    grid:
      cols: [auto, auto]
      gap: s
      justify: end
      children:
        - btn_cancel: { style: secondary, action: cancel_detail }
        - btn_save:   { style: primary, action: save_detail }
YAML;
        }

        $topBarCols = $meta->isDeletable ? '[1fr, auto]' : '[1fr]';
        $topBarActions = $meta->isDeletable
            ? '- btn_delete: { style: danger, size: small, action: request_delete, visible: false }'
            : '';

        $masterHeaderCols = $meta->isCreatable ? '[1fr, auto]' : '[1fr]';
        $masterHeaderActions = $meta->isCreatable
            ? '- btn_new: { style: primary, size: xs, action: start_create }'
            : '';

        return [
            'wrapper_name' => $wrapperName,
            'service_fqcn' => ltrim($meta->serviceFqcn, '\\'),
            'empty_state_actions_yaml' => $emptyActions,
            'detail_fields_yaml' => implode("\n", $fieldYamlLines),
            'detail_actions_yaml' => $detailActions,
            'top_bar_cols' => $topBarCols,
            'top_bar_actions_yaml' => $topBarActions,
            'master_header_cols' => $masterHeaderCols,
            'master_header_actions_yaml' => $masterHeaderActions,
        ];
    }

    /**
     * Build placeholder replacements for `request.php.stub`.
     *
     * @return array<string, string>
     */
    public function buildRequestReplacements(
        string $namespace,
        string $requestClassName,
        CrudServiceMetadata $meta,
        bool $i18n,
    ): array {
        $dtoShort = class_basename($meta->dtoFqcn);

        $phpdocLines = [];
        $typedProps = [];
        $mapLines = [];
        $prepareEntries = [];
        $rulesLines = [];
        $attrLines = [];
        $dtoArgs = ['id: $id,'];

        foreach ($meta->fields as $field) {
            if ($field->readonly) {
                continue;
            }

            $phpTypeHint = $this->resolveRequestPropertyType($field);
            $defaultExpr = $this->resolveRequestPropertyDefault($field);
            $phpdocType = $field->widget === 'belongs_to_many' ? 'list<int|string>' : $phpTypeHint;

            $phpdocLines[] = " * @property {$phpdocType} \${$field->property}";
            $typedProps[] = "public protected(set) {$phpTypeHint} \${$field->property} = {$defaultExpr};";
            $mapLines[] = "'{$field->property}' => '{$field->componentName}',";
            $prepareEntries[] = $this->buildPrepareMergeEntry($field);
            $rulesLines[] = "'{$field->property}' => '{$field->validationRules}',";

            $attrLines[] = $i18n
                ? "'{$field->property}' => t('.attributes.{$field->snakeName}'),"
                : "'{$field->property}' => '{$field->humanLabel()}',";
        }

        foreach ($meta->fields as $field) {
            $dtoArgs[] = $this->buildDtoConstructorArgument($field);
        }

        $prepareBody = '// No input normalization needed.';
        if ($prepareEntries !== []) {
            $entriesBlock = implode("\n            ", $prepareEntries);
            $prepareBody = <<<PHP
\$this->merge([
    {$entriesBlock}
]);
PHP;
        }

        $dtoArgsBlock = implode("\n            ", $dtoArgs);
        $toDtoBody = <<<PHP
\$validated = \$this->validated();

return new {$dtoShort}(
    {$dtoArgsBlock}
);
PHP;

        return [
            'namespace' => $namespace,
            'class' => $requestClassName,
            'dto_import' => "use {$meta->dtoFqcn};",
            'dto_class' => $dtoShort,
            'phpdoc_properties' => $phpdocLines !== [] ? implode("\n", $phpdocLines) : ' * Validated Master-Detail request.',
            'typed_properties' => implode("\n\n", $typedProps),
            'component_map' => implode("\n", $mapLines),
            'prepare_body' => $prepareBody,
            'rules_body' => implode("\n", $rulesLines),
            'attributes_body' => implode("\n", $attrLines),
            'error_toast_body' => $i18n ? "t('.toast.fix_errors')" : "'Please review the highlighted fields before saving.'",
            'to_dto_body' => $toDtoBody,
        ];
    }

    /**
     * Build placeholder replacements for `lang_screen_{es,en}.php.stub` and `lang_request_{es,en}.php.stub`.
     *
     * @return array<string, string>
     */
    public function buildLangReplacements(string $humanName, CrudServiceMetadata $meta): array
    {
        $fieldsEs = [];
        $fieldsEn = [];
        $listsEs = [];
        $listsEn = [];
        $reqAttrsEs = [];
        $reqAttrsEn = [];

        foreach ($meta->fields as $field) {
            $label = $field->humanLabel();
            $reqAttrsEs[] = "'{$field->snakeName}' => '{$label}',";
            $reqAttrsEn[] = "'{$field->snakeName}' => '{$label}',";

            if ($field->widget === 'belongs_to_many') {
                $listsEs[] = <<<PHP
'{$field->snakeName}' => [
    'title' => '{$label}',
    'search_placeholder' => 'Filtrar {$label}...',
    'empty_message' => 'Sin opciones disponibles',
],
PHP;
                $listsEn[] = <<<PHP
'{$field->snakeName}' => [
    'title' => '{$label}',
    'search_placeholder' => 'Filter {$label}...',
    'empty_message' => 'No options available',
],
PHP;

                continue;
            }

            if ($field->widget === 'checkbox') {
                $fieldsEs[] = "'{$field->snakeName}' => ['label' => '{$label}'],";
                $fieldsEn[] = "'{$field->snakeName}' => ['label' => '{$label}'],";

                continue;
            }

            if ($field->widget === 'belongs_to') {
                $fieldsEs[] = "'{$field->snakeName}' => ['label' => '{$label}', 'placeholder' => 'Seleccionar {$label}...'],";
                $fieldsEn[] = "'{$field->snakeName}' => ['label' => '{$label}', 'placeholder' => 'Select {$label}...'],";

                continue;
            }

            $fieldsEs[] = "'{$field->snakeName}' => ['label' => '{$label}', 'placeholder' => 'Ingresa {$label}...'],";
            $fieldsEn[] = "'{$field->snakeName}' => ['label' => '{$label}', 'placeholder' => 'Enter {$label}...'],";
        }

        return [
            'human_name' => $humanName,
            'fields_lang_es' => implode("\n", $fieldsEs),
            'fields_lang_en' => implode("\n", $fieldsEn),
            'lists_lang_es' => implode("\n", $listsEs),
            'lists_lang_en' => implode("\n", $listsEn),
            'request_attributes_es' => implode("\n", $reqAttrsEs),
            'request_attributes_en' => implode("\n", $reqAttrsEn),
        ];
    }

    /**
     * Build placeholder replacements for `test.pest.php.stub`.
     *
     * @return array<string, string>
     */
    public function buildTestReplacements(
        string $screenFqcn,
        string $screenClassName,
        string $visibilityEnum,
        CrudServiceMetadata $meta,
    ): array {
        $extraCases = [];

        $extraCases[] = <<<PHP
it('hydrates detail panel when selecting an item in {$screenClassName}', function (): void {
    \$harness = ScreenTestHarness::for({$screenClassName}::class)->render();
    \$list = \$harness->getComponent('lst_items');
    \$items = method_exists(\$list, 'get') ? \$list->get('items') : [];

    if (is_array(\$items) && isset(\$items[0]['id'])) {
        \$firstId = \$items[0]['id'];
        \$harness->call('item_selected', ['selected' => [\$firstId]]);

        \$harness->assertComponentHidden('cnt_empty_state');
        \$harness->assertComponentVisible('cnt_detail_form');
    } else {
        expect(\$items)->toBeArray();
    }
});
PHP;

        if ($meta->isCreatable) {
            $extraCases[] = <<<PHP
it('switches {$screenClassName} to creation mode when clicking new button', function (): void {
    \$harness = ScreenTestHarness::for({$screenClassName}::class)->render();

    \$harness->click('btn_new');

    \$harness->assertComponentHidden('cnt_empty_state');
    \$harness->assertComponentVisible('cnt_detail_form');
});
PHP;
        }

        return [
            'screen_fqcn' => ltrim($screenFqcn, '\\'),
            'screen_class' => $screenClassName,
            'visibility_enum' => $visibilityEnum,
            'test_extra_cases' => implode("\n\n", $extraCases),
        ];
    }

    /**
     * Build placeholder replacements for `dto.php.stub` when `--with-stack` is requested.
     *
     * @param  list<CrudFieldDescriptor>  $fields
     * @return array<string, string>
     */
    public function buildDtoReplacements(string $namespace, string $dtoClass, array $fields): array
    {
        $imports = [];
        $docLines = [];
        $ctorParams = ['public int|string|null $id = null,'];
        $fromArrayArgs = [
            "id: isset(\$data['id']) && (is_int(\$data['id']) || is_string(\$data['id'])) ? \$data['id'] : null,",
        ];
        $withIdArgs = ['id: $id,'];
        $toArrayEntries = ["'id' => \$this->id,"];

        foreach ($fields as $field) {
            $paramLines = [];

            if ($field->widget === 'belongs_to' && $field->relatedServiceFqcn !== null) {
                $imports[] = BelongsTo::class;
                $imports[] = $field->relatedServiceFqcn;
                $relShort = class_basename($field->relatedServiceFqcn);
                $paramLines[] = "#[BelongsTo({$relShort}::class)]";
            } elseif ($field->widget === 'belongs_to_many' && $field->relatedServiceFqcn !== null) {
                $imports[] = BelongsToMany::class;
                $imports[] = $field->relatedServiceFqcn;
                $relShort = class_basename($field->relatedServiceFqcn);
                $paramLines[] = "#[BelongsToMany({$relShort}::class)]";
                $docLines[] = " * @param  list<int|string>  \${$field->property}";
            }

            $phpType = $this->resolveDtoConstructorType($field);
            $defaultSuffix = $this->resolveDtoDefaultSuffix($field);
            $paramLines[] = "public {$phpType} \${$field->property}{$defaultSuffix},";

            $ctorParams[] = implode("\n", $paramLines);
            $fromArrayArgs[] = $this->buildDtoFromArrayArgument($field);
            $withIdArgs[] = "{$field->property}: \$this->{$field->property},";
            $toArrayEntries[] = "'{$field->property}' => \$this->{$field->property},";
        }

        $imports = array_values(array_unique($imports));
        sort($imports);
        $importsBlock = $imports !== []
            ? implode("\n", array_map(static fn (string $fqcn): string => "use {$fqcn};", $imports))
            : '';

        $docblock = '';
        if ($docLines !== []) {
            $joined = implode("\n", $docLines);
            $docblock = "/**\n{$joined}\n */";
        }

        $fromBody = "return new self(\n    ".implode("\n    ", $fromArrayArgs)."\n);";
        $withIdBody = "return new self(\n    ".implode("\n    ", $withIdArgs)."\n);";

        return [
            'namespace' => $namespace,
            'class' => $dtoClass,
            'imports' => $importsBlock,
            'constructor_docblock' => $docblock,
            'constructor_params' => implode("\n", $ctorParams),
            'from_array_body' => $fromBody,
            'with_id_body' => $withIdBody,
            'to_array_body' => implode("\n", $toArrayEntries),
        ];
    }

    /**
     * Build placeholder replacements for `repository_{json|session|memory}.php.stub`.
     *
     * @param  list<CrudFieldDescriptor>  $fields
     * @return array<string, string>
     */
    public function buildRepositoryMockReplacements(
        string $namespace,
        string $repoClass,
        string $interfaceFqcn,
        string $dtoFqcn,
        string $entitySlug,
        array $fields,
    ): array {
        $seedItems = [];
        for ($index = 1; $index <= 3; $index++) {
            $entries = ["'id' => {$index}"];
            foreach ($fields as $field) {
                $entries[] = "'{$field->property}' => ".$this->buildSampleSeedValueExpr($field, $index);
            }
            $seedItems[] = '['.implode(', ', $entries).'],';
        }

        return [
            'namespace' => $namespace,
            'class' => $repoClass,
            'interface_import' => "use {$interfaceFqcn};",
            'interface_class' => class_basename($interfaceFqcn),
            'dto_import' => "use {$dtoFqcn};",
            'dto_class' => class_basename($dtoFqcn),
            'storage_file' => $entitySlug,
            'session_key' => $entitySlug,
            'seed_items_body' => implode("\n", $seedItems),
        ];
    }

    /**
     * Build placeholder replacements for `service.php.stub`.
     *
     * @param  list<CrudFieldDescriptor>  $fields
     * @return array<string, string>
     */
    public function buildServiceReplacements(
        string $namespace,
        string $serviceClass,
        string $dtoFqcn,
        string $repoInterfaceFqcn,
        string $defaultRepoFqcn,
        array $fields,
    ): array {
        $labelField = null;
        $subtitleField = null;
        $boolField = null;

        foreach ($fields as $field) {
            if ($field->phpType === 'string' && ! $field->isRelation()) {
                if ($labelField === null) {
                    $labelField = $field;
                } elseif ($subtitleField === null) {
                    $subtitleField = $field;
                }
            } elseif ($field->phpType === 'bool' && $boolField === null) {
                $boolField = $field;
            }
        }

        $labelExpr = $labelField !== null
            ? "(string) (\$dto->{$labelField->property} ?? ('#'.(\$dto->id ?? '')))"
            : "'#'.(\$dto->id ?? '')";

        $subtitleExpr = $subtitleField !== null
            ? "(\$dto->{$subtitleField->property} !== null && \$dto->{$subtitleField->property} !== '' ? (string) \$dto->{$subtitleField->property} : null)"
            : 'null';

        $badgeLines = '';
        if ($boolField !== null) {
            $badgeLines = <<<PHP
    badge: \$dto->{$boolField->property} ? 'Active' : 'Inactive',
    badgeStyle: \$dto->{$boolField->property} ? Style::SUCCESS : Style::SECONDARY,
PHP;
        }

        $toListItemBody = <<<PHP
return ListItemDTO::make(
    id: \$dto->id ?? 0,
    label: {$labelExpr},
    subtitle: {$subtitleExpr},
{$badgeLines}
);
PHP;

        return [
            'namespace' => $namespace,
            'class' => $serviceClass,
            'dto_import' => "use {$dtoFqcn};",
            'dto_class' => class_basename($dtoFqcn),
            'repo_import' => "use {$repoInterfaceFqcn};",
            'repo_interface' => class_basename($repoInterfaceFqcn),
            'default_repo_import' => "use {$defaultRepoFqcn};",
            'default_repo_class' => class_basename($defaultRepoFqcn),
            'to_list_item_body' => $toListItemBody,
        ];
    }

    private function buildFieldHydrateStatement(CrudFieldDescriptor $field, bool $forceReadOnly): string
    {
        if ($field->widget === 'checkbox') {
            return "\$this->{$field->componentName}->checked((bool) \$dto->{$field->property});";
        }

        if ($field->widget === 'belongs_to_many') {
            return "\$this->{$field->componentName}->select(\$dto->{$field->property});";
        }

        if ($field->widget === 'belongs_to') {
            return "\$this->{$field->componentName}->value(\$dto->{$field->property} !== null ? (string) \$dto->{$field->property} : '');";
        }

        if ($field->nullable) {
            return "\$this->{$field->componentName}->value(\$dto->{$field->property} !== null ? (string) \$dto->{$field->property} : '');";
        }

        return "\$this->{$field->componentName}->value((string) \$dto->{$field->property});";
    }

    private function buildFieldResetStatement(CrudFieldDescriptor $field): string
    {
        if ($field->widget === 'checkbox') {
            $defaultBool = is_bool($field->defaultValue) ? ($field->defaultValue ? 'true' : 'false') : 'false';

            return "\$this->{$field->componentName}->checked({$defaultBool});";
        }

        if ($field->widget === 'belongs_to_many') {
            return "\$this->{$field->componentName}->select([]);";
        }

        if (is_scalar($field->defaultValue) && (string) $field->defaultValue !== '') {
            $escaped = addslashes((string) $field->defaultValue);

            return "\$this->{$field->componentName}->value('{$escaped}');";
        }

        return "\$this->{$field->componentName}->value('');";
    }

    private function buildFieldClearErrorStatement(CrudFieldDescriptor $field): string
    {
        if ($field->widget === 'checkbox' || $field->widget === 'belongs_to') {
            return "\$this->{$field->componentName}->errorMessage('');";
        }

        return "\$this->{$field->componentName}->error(null);";
    }

    private function buildFieldYamlBlock(CrudFieldDescriptor $field, bool $forceReadOnly): string
    {
        $isReadOnly = $forceReadOnly || $field->readonly;

        if ($field->widget === 'belongs_to_many' && $field->relatedServiceFqcn !== null) {
            $serviceFqcn = ltrim($field->relatedServiceFqcn, '\\');
            $searchable = $field->searchable ? 'true' : 'false';
            $readonlyLine = $isReadOnly ? "\n    readonly: true" : '';

            return <<<YAML
- {$field->componentName}:
    span: {$field->span}
    service: {$serviceFqcn}
    selection_mode: multiple
    searchable: {$searchable}
    keep_selected_visible: true
    max_height: {$field->maxHeight}{$readonlyLine}
YAML;
        }

        if ($field->widget === 'belongs_to') {
            $searchable = $field->searchable ? 'true' : 'false';
            $required = (! $field->nullable && ! $isReadOnly) ? 'true' : 'false';
            $disabled = $isReadOnly ? ', disabled: true' : '';

            return "- {$field->componentName}: { span: {$field->span}, searchable: {$searchable}, required: {$required}, width: full{$disabled} }";
        }

        if ($field->widget === 'checkbox') {
            $disabled = $isReadOnly ? ', disabled: true' : '';

            return "- {$field->componentName}: { span: {$field->span}, align: center{$disabled} }";
        }

        if ($field->widget === 'textarea') {
            $required = (! $field->nullable && ! $isReadOnly) ? 'true' : 'false';
            $readonly = $isReadOnly ? ', readonly: true' : '';

            return "- {$field->componentName}: { span: {$field->span}, required: {$required}, width: full{$readonly} }";
        }

        $required = (! $field->nullable && ! $isReadOnly) ? 'true' : 'false';
        $readonly = $isReadOnly ? ', readonly: true' : '';

        return "- {$field->componentName}: { span: {$field->span}, type: {$field->widget}, required: {$required}, width: full{$readonly} }";
    }

    private function resolveRequestPropertyType(CrudFieldDescriptor $field): string
    {
        if ($field->widget === 'belongs_to_many' || $field->phpType === 'array') {
            return 'array';
        }

        if ($field->phpType === 'bool') {
            return 'bool';
        }

        $base = match ($field->phpType) {
            'int' => 'int',
            'float' => 'float',
            default => 'string',
        };

        return $field->nullable ? '?'.$base : $base;
    }

    private function resolveRequestPropertyDefault(CrudFieldDescriptor $field): string
    {
        if ($field->widget === 'belongs_to_many' || $field->phpType === 'array') {
            return '[]';
        }

        if ($field->nullable) {
            return 'null';
        }

        return match ($field->phpType) {
            'bool' => is_bool($field->defaultValue) && $field->defaultValue ? 'true' : 'false',
            'int' => '0',
            'float' => '0.0',
            default => "''",
        };
    }

    private function buildPrepareMergeEntry(CrudFieldDescriptor $field): string
    {
        $prop = $field->property;

        if ($field->widget === 'belongs_to_many' || $field->phpType === 'array') {
            return "'{$prop}' => is_array(\$this->input('{$prop}')) ? array_values(array_filter(\$this->input('{$prop}'), static fn (mixed \$v): bool => is_int(\$v) || (is_string(\$v) && \$v !== ''))) : [],";
        }

        if ($field->phpType === 'bool') {
            return "'{$prop}' => filter_var(\$this->input('{$prop}', false), FILTER_VALIDATE_BOOLEAN),";
        }

        if ($field->nullable) {
            return "'{$prop}' => (\$raw = \$this->input('{$prop}')) === null || (is_string(\$raw) && trim(\$raw) === '') ? null : (is_string(\$raw) ? trim(\$raw) : \$raw),";
        }

        if ($field->phpType === 'int' || $field->phpType === 'float') {
            return "'{$prop}' => \$this->input('{$prop}'),";
        }

        return "'{$prop}' => is_scalar(\$raw = \$this->input('{$prop}')) ? trim((string) \$raw) : '',";
    }

    private function buildDtoConstructorArgument(CrudFieldDescriptor $field): string
    {
        $prop = $field->property;

        if ($field->widget === 'belongs_to_many' || $field->phpType === 'array') {
            return "{$prop}: is_array(\$validated['{$prop}'] ?? null) ? array_values(array_filter(\$validated['{$prop}'], static fn (mixed \$v): bool => is_int(\$v) || is_string(\$v))) : [],";
        }

        if ($field->phpType === 'bool') {
            return "{$prop}: (bool) (\$validated['{$prop}'] ?? false),";
        }

        if ($field->phpType === 'int') {
            if ($field->nullable) {
                return "{$prop}: isset(\$validated['{$prop}']) && is_numeric(\$validated['{$prop}']) ? (int) \$validated['{$prop}'] : null,";
            }

            return "{$prop}: isset(\$validated['{$prop}']) && is_numeric(\$validated['{$prop}']) ? (int) \$validated['{$prop}'] : 0,";
        }

        if ($field->phpType === 'float') {
            if ($field->nullable) {
                return "{$prop}: isset(\$validated['{$prop}']) && is_numeric(\$validated['{$prop}']) ? (float) \$validated['{$prop}'] : null,";
            }

            return "{$prop}: isset(\$validated['{$prop}']) && is_numeric(\$validated['{$prop}']) ? (float) \$validated['{$prop}'] : 0.0,";
        }

        if ($field->nullable) {
            return "{$prop}: isset(\$validated['{$prop}']) && is_scalar(\$validated['{$prop}']) && (string) \$validated['{$prop}'] !== '' ? (string) \$validated['{$prop}'] : null,";
        }

        return "{$prop}: isset(\$validated['{$prop}']) && is_scalar(\$validated['{$prop}']) ? (string) \$validated['{$prop}'] : '',";
    }

    private function resolveDtoConstructorType(CrudFieldDescriptor $field): string
    {
        if ($field->widget === 'belongs_to') {
            return 'int|string|null';
        }

        if ($field->widget === 'belongs_to_many' || $field->phpType === 'array') {
            return 'array';
        }

        $base = match ($field->phpType) {
            'int' => 'int',
            'float' => 'float',
            'bool' => 'bool',
            default => 'string',
        };

        return $field->nullable ? '?'.$base : $base;
    }

    private function resolveDtoDefaultSuffix(CrudFieldDescriptor $field): string
    {
        if ($field->widget === 'belongs_to') {
            return ' = null';
        }

        if ($field->widget === 'belongs_to_many' || $field->phpType === 'array') {
            return ' = []';
        }

        if ($field->nullable) {
            return ' = null';
        }

        if ($field->phpType === 'bool') {
            $boolStr = is_bool($field->defaultValue) && ! $field->defaultValue ? 'false' : 'true';

            return " = {$boolStr}";
        }

        return '';
    }

    private function buildDtoFromArrayArgument(CrudFieldDescriptor $field): string
    {
        $prop = $field->property;

        if ($field->widget === 'belongs_to') {
            return "{$prop}: isset(\$data['{$prop}']) && (is_int(\$data['{$prop}']) || is_string(\$data['{$prop}'])) && (string) \$data['{$prop}'] !== '' ? \$data['{$prop}'] : null,";
        }

        if ($field->widget === 'belongs_to_many' || $field->phpType === 'array') {
            return "{$prop}: isset(\$data['{$prop}']) && is_array(\$data['{$prop}']) ? array_values(array_filter(\$data['{$prop}'], static fn (mixed \$v): bool => is_int(\$v) || is_string(\$v))) : [],";
        }

        if ($field->phpType === 'bool') {
            return "{$prop}: (bool) (\$data['{$prop}'] ?? true),";
        }

        if ($field->phpType === 'int') {
            return $field->nullable
                ? "{$prop}: isset(\$data['{$prop}']) && is_numeric(\$data['{$prop}']) ? (int) \$data['{$prop}'] : null,"
                : "{$prop}: isset(\$data['{$prop}']) && is_numeric(\$data['{$prop}']) ? (int) \$data['{$prop}'] : 0,";
        }

        if ($field->phpType === 'float') {
            return $field->nullable
                ? "{$prop}: isset(\$data['{$prop}']) && is_numeric(\$data['{$prop}']) ? (float) \$data['{$prop}'] : null,"
                : "{$prop}: isset(\$data['{$prop}']) && is_numeric(\$data['{$prop}']) ? (float) \$data['{$prop}'] : 0.0,";
        }

        return $field->nullable
            ? "{$prop}: isset(\$data['{$prop}']) && is_scalar(\$data['{$prop}']) && (string) \$data['{$prop}'] !== '' ? (string) \$data['{$prop}'] : null,"
            : "{$prop}: isset(\$data['{$prop}']) && is_scalar(\$data['{$prop}']) ? (string) \$data['{$prop}'] : '',";
    }

    private function buildSampleSeedValueExpr(CrudFieldDescriptor $field, int $index): string
    {
        if ($field->widget === 'belongs_to_many' || $field->phpType === 'array') {
            return '[]';
        }

        if ($field->widget === 'belongs_to') {
            return 'null';
        }

        if ($field->phpType === 'bool') {
            return $index % 2 === 1 ? 'true' : 'false';
        }

        if ($field->phpType === 'int') {
            return (string) ($index * 10);
        }

        if ($field->phpType === 'float') {
            return number_format($index * 19.5, 2, '.', '');
        }

        if ($field->widget === 'email') {
            return "'sample{$index}@example.com'";
        }

        $label = $field->humanLabel();

        return "'{$label} {$index}'";
    }
}
