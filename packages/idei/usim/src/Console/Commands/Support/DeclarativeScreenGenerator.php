<?php

namespace Idei\Usim\Console\Commands\Support;

use Illuminate\Support\Str;

/**
 * Generates clean, idiomatic declarative USIM Screens (PHP + sibling `.layout.yaml` +
 * optional `i18n: auto` language files + optional Pest feature test).
 */
class DeclarativeScreenGenerator
{
    /**
     * Canonical Workbench slots supported by `--slots=...`.
     *
     * @var list<string>
     */
    public const SUPPORTED_SLOTS = [
        'top_bar',
        'left_panel',
        'right_panel',
        'bottom_panel',
        'status_bar',
        'status_bar_right',
    ];

    /**
     * Supported visibility values for `--visibility=...` matching `Idei\Usim\Enums\Visibility`.
     *
     * @var array<string, string>
     */
    public const VISIBILITY_ENUM_MAP = [
        'public' => 'PUBLIC',
        'authenticated' => 'AUTHENTICATED',
        'auth' => 'AUTHENTICATED',
        'guest' => 'GUEST',
    ];

    /**
     * Built-in declarative preset types.
     *
     * @var list<string>
     */
    public const DECLARATIVE_TYPES = [
        'basic',
        'workspace',
        'modal',
        'kiosk',
    ];

    /**
     * Parse and validate a `--slots=...` option string into canonical slot names.
     *
     * @return array{slots: list<string>, invalid: list<string>}
     */
    public function parseSlots(?string $rawSlots): array
    {
        if ($rawSlots === null || trim($rawSlots) === '') {
            return ['slots' => [], 'invalid' => []];
        }

        $trimmed = strtolower(trim($rawSlots));
        if ($trimmed === 'none') {
            return ['slots' => [], 'invalid' => []];
        }

        if ($trimmed === 'all') {
            return ['slots' => self::SUPPORTED_SLOTS, 'invalid' => []];
        }

        $slots = [];
        $invalid = [];

        foreach (explode(',', $rawSlots) as $segment) {
            $token = strtolower(trim($segment));
            if ($token === '') {
                continue;
            }

            $canonical = match ($token) {
                'header', 'toolbar' => 'top_bar',
                'left', 'explorer' => 'left_panel',
                'right', 'inspector' => 'right_panel',
                'bottom', 'panel', 'console', 'terminal' => 'bottom_panel',
                'statusbar', 'status_bar_center' => 'status_bar',
                default => $token,
            };

            if (! in_array($canonical, self::SUPPORTED_SLOTS, true)) {
                $invalid[] = $token;

                continue;
            }

            if (! in_array($canonical, $slots, true)) {
                $slots[] = $canonical;
            }
        }

        return ['slots' => $slots, 'invalid' => $invalid];
    }

    /**
     * Normalize a `--layout=...` option into its PHP declaration and optional `use` import.
     *
     * @return array{declaration: ?string, import: ?string, normalized: string}
     */
    public function resolveLayoutDeclaration(?string $rawLayout, string $type): array
    {
        if ($rawLayout === null || trim($rawLayout) === '') {
            return match ($type) {
                'workspace' => [
                    'declaration' => "public static ?string \$layout = 'workbench';",
                    'import' => null,
                    'normalized' => 'workbench',
                ],
                'modal', 'kiosk' => [
                    'declaration' => 'public static ?string $layout = null;',
                    'import' => null,
                    'normalized' => 'none',
                ],
                default => [
                    'declaration' => null,
                    'import' => null,
                    'normalized' => 'default',
                ],
            };
        }

        $trimmed = trim($rawLayout);
        $lower = strtolower($trimmed);

        if ($lower === 'none' || $lower === 'null') {
            return [
                'declaration' => 'public static ?string $layout = null;',
                'import' => null,
                'normalized' => 'none',
            ];
        }

        if ($lower === 'default') {
            return [
                'declaration' => "public static ?string \$layout = 'default';",
                'import' => null,
                'normalized' => 'default',
            ];
        }

        if ($lower === 'workbench' || $lower === 'main') {
            return [
                'declaration' => "public static ?string \$layout = '{$lower}';",
                'import' => null,
                'normalized' => $lower,
            ];
        }

        $fqcn = ltrim($trimmed, '\\');
        $shortName = class_basename($fqcn);

        return [
            'declaration' => "public static ?string \$layout = {$shortName}::class;",
            'import' => $fqcn,
            'normalized' => $fqcn,
        ];
    }

    /**
     * Build the PHP source code for a declarative Screen.
     *
     * @param  list<string>  $slots
     */
    public function buildScreenPhp(
        string $namespace,
        string $className,
        string $humanName,
        string $type,
        ?string $layoutDeclaration,
        ?string $layoutImport,
        string $visibilityEnum,
        array $slots,
        bool $i18n
    ): string {
        $imports = [
            'Idei\\Usim\\Components\\NewLabel',
            'Idei\\Usim\\Enums\\Visibility',
            'Idei\\Usim\\Screen',
        ];

        if ($layoutImport !== null) {
            $imports[] = $layoutImport;
        }

        if (in_array('left_panel', $slots, true) || in_array('bottom_panel', $slots, true)) {
            $imports[] = 'Idei\\Usim\\Components\\Input';
        }

        sort($imports);
        $useBlock = implode("\n", array_map(static fn (string $fqcn): string => "use {$fqcn};", $imports));

        $properties = [];
        $properties[] = "    public static Visibility \$visibility = Visibility::{$visibilityEnum};";

        if ($layoutDeclaration !== null) {
            $properties[] = '';
            $properties[] = "    {$layoutDeclaration}";
        }

        $properties[] = '';
        $properties[] = '    protected NewLabel $lbl_title;';
        $properties[] = '';
        $properties[] = '    protected NewLabel $lbl_summary;';

        if (in_array('left_panel', $slots, true)) {
            $properties[] = '';
            $properties[] = '    protected Input $input_filter;';
        }

        if (in_array('right_panel', $slots, true)) {
            $properties[] = '';
            $properties[] = '    protected NewLabel $lbl_inspector_body;';
        }

        if (in_array('bottom_panel', $slots, true)) {
            $properties[] = '';
            $properties[] = '    protected NewLabel $lbl_console_output;';
            $properties[] = '';
            $properties[] = '    protected Input $input_command;';
        }

        if (in_array('status_bar', $slots, true)) {
            $properties[] = '';
            $properties[] = '    protected NewLabel $lbl_status_text;';
        }

        $methods = $this->buildHandlerMethods($humanName, $type, $slots, $i18n);
        $propertiesBlock = implode("\n", $properties);
        $bodyBlock = $methods !== []
            ? $propertiesBlock."\n\n".implode("\n\n", $methods)
            : $propertiesBlock;

        $yamlFileName = Str::kebab($className).'.layout.yaml';

        return <<<PHP
<?php

namespace {$namespace};

{$useBlock}

/**
 * {$humanName} Screen.
 *
 * UI structure is declaratively defined in sibling `{$yamlFileName}`.
 */
class {$className} extends Screen
{
{$bodyBlock}
}

PHP;
    }

    /**
     * Build the sibling `.layout.yaml` content.
     *
     * @param  list<string>  $slots
     */
    public function buildLayoutYaml(
        string $className,
        string $humanName,
        string $type,
        array $slots,
        bool $i18n
    ): string {
        $snakeName = Str::snake($className);
        $lines = [];
        $lines[] = "name: {$snakeName}_main";

        if ($type === 'kiosk') {
            $lines[] = 'appearance: plain';
            $lines[] = 'width: 100%';
            $lines[] = 'height: 100vh';
        } elseif ($type === 'modal') {
            $lines[] = 'appearance: card';
            $lines[] = 'max_width: 560';
            $lines[] = 'center_horizontal: true';
        } else {
            $lines[] = 'appearance: card';
            $lines[] = 'max_width: 960';
            $lines[] = 'center_horizontal: true';
        }

        if ($i18n) {
            $lines[] = 'i18n: auto';
        }

        $lines[] = '';
        $lines[] = 'grid:';

        if ($type === 'modal') {
            $lines[] = '  cols: [1fr, 1fr]';
            $lines[] = '  gap: m';
            $lines[] = '  padding: l';
            $lines[] = '  default_span: 2';
            $lines[] = '';
            $lines[] = '  children:';
            $lines[] = $this->yamlChild('lbl_title', ['size: xl', 'font_weight: bold'], $i18n ? null : $humanName, 'text');
            $lines[] = $this->yamlChild('lbl_summary', ['style: secondary', 'width: full'], $i18n ? null : "Configure {$humanName} details below.", 'text');
            $lines[] = $this->yamlChild('btn_cancel', ['span: 1', 'style: secondary', 'action: cancel', 'width: full'], $i18n ? null : 'Cancel', 'label');
            $lines[] = $this->yamlChild('btn_save', ['span: 1', 'style: primary', 'action: save', 'width: full'], $i18n ? null : 'Save', 'label');
        } elseif ($type === 'kiosk') {
            $lines[] = '  cols: [1fr]';
            $lines[] = '  gap: l';
            $lines[] = '  padding: xl';
            $lines[] = '';
            $lines[] = '  children:';
            $lines[] = $this->yamlChild('lbl_title', ['size: xl', 'font_weight: bold'], $i18n ? null : $humanName, 'text');
            $lines[] = $this->yamlChild('lbl_summary', ['style: info', 'width: full'], $i18n ? null : 'Standalone full-viewport display ready.', 'text');
        } else {
            $toggleButtons = $this->buildMainToggleButtons($slots, $i18n);
            $hasToggleButtons = $toggleButtons !== [];

            $lines[] = $hasToggleButtons ? '  cols: [1fr, 1fr]' : '  cols: [1fr]';
            $lines[] = '  gap: m';
            $lines[] = '  padding: l';
            if ($hasToggleButtons) {
                $lines[] = '  default_span: 2';
            }
            $lines[] = '';
            $lines[] = '  children:';
            $lines[] = $this->yamlChild('lbl_title', ['size: xl', 'font_weight: bold'], $i18n ? null : $humanName, 'text');
            $lines[] = $this->yamlChild('lbl_summary', ['style: secondary', 'width: full'], $i18n ? null : "{$humanName} workspace ready.", 'text');

            if ($hasToggleButtons) {
                foreach ($toggleButtons as $btnLine) {
                    $lines[] = $btnLine;
                }
            } else {
                $lines[] = $this->yamlChild('btn_primary_action', ['style: primary', 'action: primary_action'], $i18n ? null : 'Run Action', 'label');
            }
        }

        if ($slots !== []) {
            $lines[] = '';
            $lines[] = 'slots:';

            foreach ($slots as $slotName) {
                foreach ($this->buildSlotYamlLines($slotName, $humanName, $i18n) as $slotLine) {
                    $lines[] = $slotLine;
                }
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Build a localized PHP language file matching the generated declarative Screen.
     *
     * @param  list<string>  $slots
     */
    public function buildLangPhp(string $locale, string $humanName, string $type, array $slots): string
    {
        $isEs = str_starts_with(strtolower($locale), 'es');

        $labels = [
            'title' => $humanName,
            'summary' => match ($type) {
                'modal' => $isEs ? "Completá los datos de {$humanName}." : "Configure {$humanName} details below.",
                'kiosk' => $isEs ? 'Pantalla en modo kiosco lista.' : 'Standalone full-viewport display ready.',
                default => $isEs ? "Espacio de trabajo {$humanName} listo." : "{$humanName} workspace ready.",
            },
        ];

        $actions = [];

        if ($type === 'modal') {
            $actions['save'] = $isEs ? 'Guardar' : 'Save';
            $actions['cancel'] = $isEs ? 'Cancelar' : 'Cancel';
        } elseif ($type !== 'kiosk' && ! $this->hasToggleablePanelSlots($slots)) {
            $actions['primary_action'] = $isEs ? 'Ejecutar acción' : 'Run Action';
        }

        $extraSections = [];

        if (in_array('top_bar', $slots, true)) {
            $labels['top_bar_title'] = $humanName;
            $actions['toggle_top_bar'] = $isEs ? 'Alternar Barra Superior' : 'Toggle Top Bar';
        }

        if (in_array('left_panel', $slots, true)) {
            $labels['left_title'] = $isEs ? 'Filtros' : 'Filters';
            $actions['toggle_left'] = $isEs ? 'Alternar Panel Izquierdo' : 'Toggle Left Panel';
            $actions['apply_filter'] = $isEs ? 'Aplicar filtro' : 'Apply Filter';
            $actions['close_left'] = $isEs ? 'Ocultar panel' : 'Hide Panel';
            $extraSections['filters'] = [
                'placeholder' => $isEs ? 'Buscar o filtrar...' : 'Search or filter...',
                'applied' => $isEs ? 'Filtro aplicado: ":query"' : 'Filter applied: ":query"',
                'cleared' => $isEs ? 'Filtro limpiado.' : 'Filter cleared.',
            ];
        }

        if (in_array('right_panel', $slots, true)) {
            $labels['inspector_title'] = $isEs ? 'Inspector' : 'Inspector';
            $labels['inspector_body'] = $isEs
                ? 'Panel contextual sincronizado con la pantalla principal.'
                : 'Contextual inspector synchronized with the main screen.';
            $actions['toggle_right'] = $isEs ? 'Alternar Inspector' : 'Toggle Inspector';
        }

        if (in_array('bottom_panel', $slots, true)) {
            $labels['console_title'] = $isEs ? 'Consola' : 'Console';
            $labels['console_output'] = $isEs ? 'Consola lista.' : 'Console ready.';
            $actions['toggle_bottom'] = $isEs ? 'Alternar Panel Inferior' : 'Toggle Bottom Panel';
            $actions['run_command'] = $isEs ? 'Ejecutar' : 'Run';
            $extraSections['console'] = [
                'placeholder' => $isEs ? 'Escribí un comando...' : 'Type a command...',
                'executed' => $isEs ? 'Comando ejecutado: :command' : 'Executed command: :command',
            ];
        }

        if (in_array('status_bar', $slots, true)) {
            $extraSections['status'] = [
                'ready' => $isEs ? "{$humanName} activo" : "{$humanName} ready",
            ];
        }

        if (in_array('status_bar_right', $slots, true)) {
            $actions['status_action'] = $isEs ? 'Acción rápida' : 'Quick Action';
        }

        $extraSections['messages'] = [
            'action_done' => $isEs ? 'Acción ejecutada correctamente.' : 'Action executed successfully.',
            'saved' => $isEs ? 'Cambios guardados correctamente.' : 'Changes saved successfully.',
        ];

        $payload = [
            'title' => $humanName,
            'menu_title' => $humanName,
            'icon' => $type === 'modal' ? '🗂️' : ($type === 'kiosk' ? '📺' : '📄'),
            'labels' => $labels,
        ];

        if ($actions !== []) {
            $payload['actions'] = $actions;
        }

        foreach ($extraSections as $sectionKey => $sectionValues) {
            $payload[$sectionKey] = $sectionValues;
        }

        return "<?php\n\nreturn ".$this->exportPhpArray($payload).";\n";
    }

    /**
     * Build a sibling Pest feature test for the scaffolded Screen.
     *
     * @param  list<string>  $slots
     */
    public function buildFeatureTestPhp(
        string $screenFqcn,
        string $className,
        string $humanName,
        string $normalizedLayout,
        string $visibilityEnum,
        array $slots
    ): string {
        $imports = [
            $screenFqcn,
        ];

        $layoutAssertion = match ($normalizedLayout) {
            'none' => "    expect({$className}::getLayoutClass())->toBeNull();",
            'workbench' => "    expect({$className}::getLayoutClass())->toBe(Screen::resolveLayoutClass('workbench'));",
            'main' => "    expect({$className}::getLayoutClass())->toBe(Screen::resolveLayoutClass('main'));",
            default => "    expect({$className}::getLayoutClass())->not->toBeEmpty();",
        };

        if (in_array($normalizedLayout, ['workbench', 'main'], true)) {
            $imports[] = 'Idei\\Usim\\Screen';
        }

        $authSetup = '';
        if ($visibilityEnum === 'AUTHENTICATED') {
            $imports[] = 'Idei\\Usim\\Support\\RoleAndPermissionSyncService';
            $authSetup = <<<'PHP'
    app(RoleAndPermissionSyncService::class)->sync();
    $this->loginAs('root');

PHP;
        }

        sort($imports);
        $useBlock = implode("\n", array_map(static fn (string $fqcn): string => "use {$fqcn};", $imports));

        $slotTestBlock = '';
        if (in_array('right_panel', $slots, true)) {
            $slotTestBlock = <<<PHP


it('toggles projected Workbench slots on {$humanName}', function () {
    /** @var \\Tests\\TestCase \$this */
{$authSetup}    \$ui = uiScenario(\$this, {$className}::class, ['reset' => true]);
    \$response = \$ui->click('btn_toggle_right');
    \$response->assertOk();
});
PHP;
        }

        return <<<PHP
<?php

{$useBlock}

it('renders {$humanName} screen without issues', function () {
    /** @var \\Tests\\TestCase \$this */
{$authSetup}{$layoutAssertion}

    \$ui = uiScenario(\$this, {$className}::class, ['reset' => true]);
    expect(\$ui->component('lbl_title')->data()['text'] ?? null)->not->toBeEmpty();
    \$ui->assertNoIssues();
});{$slotTestBlock}

PHP;
    }

    /**
     * @param  list<string>  $slots
     * @return list<string>
     */
    private function buildHandlerMethods(string $humanName, string $type, array $slots, bool $i18n): array
    {
        $methods = [];

        if ($type === 'modal') {
            $savedMsg = $i18n ? "t('.messages.saved')" : "'Changes saved successfully.'";
            $methods[] = <<<PHP
    public function onSave(): void
    {
        \$this->toast({$savedMsg}, 'success');
        \$this->closeModal();
    }
PHP;
            $methods[] = <<<'PHP'
    public function onCancel(): void
    {
        $this->closeModal();
    }
PHP;

            return $methods;
        }

        if ($type === 'kiosk') {
            return [];
        }

        if (! $this->hasToggleablePanelSlots($slots)) {
            $doneMsg = $i18n ? "t('.messages.action_done')" : "'Action executed successfully.'";
            $methods[] = <<<PHP
    public function onPrimaryAction(): void
    {
        \$this->lbl_summary->text({$doneMsg});
        \$this->toast({$doneMsg}, 'success');
    }
PHP;
        }

        if (in_array('top_bar', $slots, true)) {
            $methods[] = <<<'PHP'
    public function onToggleTopBar(): void
    {
        $this->toggleSlot('top_bar');
    }
PHP;
            $methods[] = <<<'PHP'
    public function onCloseTopBar(): void
    {
        $this->closeSlot('top_bar');
    }
PHP;
        }

        if (in_array('left_panel', $slots, true)) {
            $appliedExpr = $i18n
                ? "t('.filters.applied', ['query' => \$query])"
                : "'Filter applied: \"'.\$query.'\"'";
            $clearedExpr = $i18n
                ? "t('.filters.cleared')"
                : "'Filter cleared.'";

            $methods[] = <<<'PHP'
    public function onToggleLeftPanel(): void
    {
        $this->toggleSlot('left_panel');
    }
PHP;
            $methods[] = <<<'PHP'
    public function onCloseLeftPanel(): void
    {
        $this->closeSlot('left_panel');
    }
PHP;
            $methods[] = <<<PHP
    public function onApplyFilter(string \$input_filter = ''): void
    {
        \$query = trim(\$input_filter !== '' ? \$input_filter : (string) \$this->input_filter->getValue());
        \$this->lbl_summary->text(\$query !== '' ? {$appliedExpr} : {$clearedExpr});
    }
PHP;
        }

        if (in_array('right_panel', $slots, true)) {
            $methods[] = <<<'PHP'
    public function onToggleRightPanel(): void
    {
        $this->toggleSlot('right_panel');
    }
PHP;
            $methods[] = <<<'PHP'
    public function onCloseRightPanel(): void
    {
        $this->closeSlot('right_panel');
    }
PHP;
        }

        if (in_array('bottom_panel', $slots, true)) {
            $executedExpr = $i18n
                ? "t('.console.executed', ['command' => \$command])"
                : "'Executed command: '.\$command";

            $methods[] = <<<'PHP'
    public function onToggleBottomPanel(): void
    {
        $this->toggleSlot('bottom_panel');
    }
PHP;
            $methods[] = <<<'PHP'
    public function onCloseBottomPanel(): void
    {
        $this->closeSlot('bottom_panel');
    }
PHP;
            $methods[] = <<<PHP
    public function onRunCommand(string \$input_command = ''): void
    {
        \$command = trim(\$input_command !== '' ? \$input_command : (string) \$this->input_command->getValue());
        if (\$command === '') {
            return;
        }

        \$this->lbl_console_output->text({$executedExpr});
        \$this->input_command->value('');
    }
PHP;
        }

        if (in_array('status_bar_right', $slots, true)) {
            $doneMsg = $i18n ? "t('.messages.action_done')" : "'Action executed successfully.'";
            $methods[] = <<<PHP
    public function onStatusAction(): void
    {
        \$this->toast({$doneMsg}, 'info');
    }
PHP;
        }

        return $methods;
    }

    /**
     * @param  list<string>  $slots
     */
    private function hasToggleablePanelSlots(array $slots): bool
    {
        return array_intersect($slots, ['top_bar', 'left_panel', 'right_panel', 'bottom_panel']) !== [];
    }

    /**
     * @param  list<string>  $slots
     * @return list<string>
     */
    private function buildMainToggleButtons(array $slots, bool $i18n): array
    {
        $buttons = [];

        if (in_array('left_panel', $slots, true)) {
            $buttons[] = $this->yamlChild('btn_toggle_left', ['span: 1', 'style: secondary', 'action: toggle_left_panel', 'width: full'], $i18n ? null : 'Toggle Left Panel', 'label');
        }
        if (in_array('right_panel', $slots, true)) {
            $buttons[] = $this->yamlChild('btn_toggle_right', ['span: 1', 'style: primary', 'action: toggle_right_panel', 'width: full'], $i18n ? null : 'Toggle Inspector', 'label');
        }
        if (in_array('bottom_panel', $slots, true)) {
            $buttons[] = $this->yamlChild('btn_toggle_bottom', ['span: 1', 'style: secondary', 'action: toggle_bottom_panel', 'width: full'], $i18n ? null : 'Toggle Bottom Panel', 'label');
        }
        if (in_array('top_bar', $slots, true)) {
            $buttons[] = $this->yamlChild('btn_toggle_top_bar', ['span: 1', 'style: secondary', 'action: toggle_top_bar', 'width: full'], $i18n ? null : 'Toggle Top Bar', 'label');
        }

        return $buttons;
    }

    /**
     * @return list<string>
     */
    private function buildSlotYamlLines(string $slotName, string $humanName, bool $i18n): array
    {
        return match ($slotName) {
            'top_bar' => [
                '  top_bar:',
                '    open: true',
                '    fallback: ignore',
                '    appearance: plain',
                '    padding: s',
                '    grid:',
                '      cols: [1fr, auto]',
                '      gap: s',
                '      align: center',
                '      children:',
                '        '.$this->yamlChild('lbl_top_bar_title', ['font_weight: bold'], $i18n ? null : $humanName, 'text', 0),
                '        - btn_close_top_bar: { label: "✕", size: xs, style: secondary, action: close_top_bar }',
                '',
            ],
            'left_panel' => [
                '  left_panel:',
                '    open: true',
                '    size: 270px',
                '    fallback: inline_top',
                '    appearance: subtle',
                '    padding: m',
                '    grid:',
                '      cols: [1fr]',
                '      gap: s',
                '      children:',
                '        '.$this->yamlChild('lbl_left_title', ['font_weight: bold'], $i18n ? null : 'Filters', 'text', 0),
                '        - input_filter: { width: full, placeholder: '.($i18n ? '.filters.placeholder' : '"Search or filter..."').' }',
                '        '.$this->yamlChild('btn_apply_filter', ['style: primary', 'size: small', 'action: apply_filter', 'width: full'], $i18n ? null : 'Apply Filter', 'label', 0),
                '        '.$this->yamlChild('btn_close_left', ['style: secondary', 'size: small', 'action: close_left_panel', 'width: full'], $i18n ? null : 'Hide Panel', 'label', 0),
                '',
            ],
            'right_panel' => [
                '  right_panel:',
                '    open: false',
                '    size: 340px',
                '    fallback: ignore',
                '    appearance: subtle',
                '    padding: m',
                '    grid:',
                '      cols: [1fr]',
                '      gap: s',
                '      children:',
                '        - cnt_inspector_header:',
                '            grid:',
                '              cols: [1fr, auto]',
                '              align: center',
                '              children:',
                '                '.$this->yamlChild('lbl_inspector_title', ['font_weight: bold'], $i18n ? null : 'Inspector', 'text', 0),
                '                - btn_close_right: { label: "✕", size: xs, style: secondary, action: close_right_panel }',
                '        '.$this->yamlChild('lbl_inspector_body', ['style: info', 'width: full'], $i18n ? null : 'Contextual inspector synchronized with the main screen.', 'text', 0),
                '',
            ],
            'bottom_panel' => [
                '  bottom_panel:',
                '    open: false',
                '    size: 220px',
                '    fallback: ignore',
                '    appearance: subtle',
                '    padding: m',
                '    grid:',
                '      cols: [1fr]',
                '      rows: [auto, 1fr, auto]',
                '      gap: s',
                '      children:',
                '        - cnt_console_header:',
                '            at: [1, 1]',
                '            grid:',
                '              cols: [1fr, auto]',
                '              align: center',
                '              children:',
                '                '.$this->yamlChild('lbl_console_title', ['font_weight: bold'], $i18n ? null : 'Console', 'text', 0),
                '                - btn_close_bottom: { label: "✕", size: xs, style: secondary, action: close_bottom_panel }',
                '        '.$this->yamlChild('lbl_console_output', ['at: [2, 1]', 'style: secondary', 'width: full'], $i18n ? null : 'Console ready.', 'text', 0),
                '        - cnt_console_prompt:',
                '            at: [3, 1]',
                '            width: full',
                '            grid:',
                '              cols: [1fr, auto]',
                '              gap: s',
                '              align: center',
                '              children:',
                '                - input_command: { width: full, placeholder: '.($i18n ? '.console.placeholder' : '"Type a command..."').' }',
                '                '.$this->yamlChild('btn_run_command', ['style: primary', 'size: small', 'action: run_command'], $i18n ? null : 'Run', 'label', 0),
                '',
            ],
            'status_bar' => [
                '  status_bar:',
                '    children:',
                '      - lbl_status_text: { text: '.($i18n ? '.status.ready' : '"'.addslashes($humanName).' ready"').' }',
                '',
            ],
            'status_bar_right' => [
                '  status_bar_right:',
                '    children:',
                '      '.$this->yamlChild('btn_status_action', ['size: xs', 'style: secondary', 'action: status_action'], $i18n ? null : 'Quick Action', 'label', 0),
                '',
            ],
            default => [],
        };
    }

    /**
     * @param  list<string>  $props
     */
    private function yamlChild(string $name, array $props, ?string $literalText, string $textProp, int $indent = 4): string
    {
        if ($literalText !== null) {
            $escaped = str_replace('"', '\\"', $literalText);
            array_unshift($props, "{$textProp}: \"{$escaped}\"");
        }

        $prefix = str_repeat(' ', $indent);

        return "{$prefix}- {$name}: { ".implode(', ', $props).' }';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function exportPhpArray(array $data, int $depth = 0): string
    {
        $indent = str_repeat('    ', $depth);
        $childIndent = str_repeat('    ', $depth + 1);
        $lines = ['['];

        foreach ($data as $key => $value) {
            $exportedKey = var_export($key, true);
            if (\is_array($value)) {
                /** @var array<string, mixed> $value */
                $exportedValue = $this->exportPhpArray($value, $depth + 1);
            } else {
                $exportedValue = var_export($value, true);
            }
            $lines[] = "{$childIndent}{$exportedKey} => {$exportedValue},";
        }

        $lines[] = "{$indent}]";

        return implode("\n", $lines);
    }
}
