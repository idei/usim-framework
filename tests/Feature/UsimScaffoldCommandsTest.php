<?php

use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

$cleanTempScaffoldDirs = function (): void {
    $dirs = [
        app_path('UI/Screens/Testing/ScaffoldTemp'),
        app_path('UI/Requests/Testing/ScaffoldTemp'),
        lang_path('es/screens/testing/scaffold_temp'),
        lang_path('en/screens/testing/scaffold_temp'),
        lang_path('es/requests/testing/scaffold_temp'),
        lang_path('en/requests/testing/scaffold_temp'),
        base_path('tests/Feature/Testing/ScaffoldTemp'),
    ];

    foreach ($dirs as $dir) {
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
};

beforeEach(function () use ($cleanTempScaffoldDirs) {
    $cleanTempScaffoldDirs();
});

afterEach(function () use ($cleanTempScaffoldDirs) {
    $cleanTempScaffoldDirs();
});

/**
 * @param  array<string, mixed>  $parameters
 */
function artisanCommand(TestCase $test, string $command, array $parameters = []): PendingCommand
{
    $pending = $test->artisan($command, $parameters);
    assert($pending instanceof PendingCommand);

    return $pending;
}

it('scaffolds a basic screen using usim:screen', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:screen', ['name' => 'Testing/ScaffoldTemp/SimpleScreen'])
        ->expectsOutput('Scaffolding a new screen...')
        ->expectsOutputToContain("Screen 'SimpleScreen' has been scaffolded at")
        ->expectsOutput("Don't forget to make php artisan usim:discover to register the new screen.")
        ->assertSuccessful();

    $expectedFile = app_path('UI/Screens/Testing/ScaffoldTemp/SimpleScreen.php');
    expect(File::exists($expectedFile))->toBeTrue();

    $content = File::get($expectedFile);
    expect($content)
        ->toContain('namespace App\UI\Screens\Testing\ScaffoldTemp;')
        ->toContain('class SimpleScreen extends Screen');
});

it('maintains backwards compatibility with usim:scaf alias', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:scaf', ['name' => 'Testing/ScaffoldTemp/LegacyScreen'])
        ->expectsOutput('Scaffolding a new screen...')
        ->expectsOutputToContain("Screen 'LegacyScreen' has been scaffolded at")
        ->assertSuccessful();

    $expectedFile = app_path('UI/Screens/Testing/ScaffoldTemp/LegacyScreen.php');
    expect(File::exists($expectedFile))->toBeTrue();

    $content = File::get($expectedFile);
    expect($content)
        ->toContain('namespace App\UI\Screens\Testing\ScaffoldTemp;')
        ->toContain('class LegacyScreen extends Screen');
});

it('scaffolds a UIRequest in the predefined requests folder using usim:request', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:request', ['name' => 'Testing/ScaffoldTemp/CreateUserRequest'])
        ->expectsOutput('Scaffolding a new UIRequest...')
        ->expectsOutputToContain("UIRequest 'CreateUserRequest' has been scaffolded at")
        ->expectsOutputToContain('You can type-hint it in your Screen handler: onSave(CreateUserRequest $request)')
        ->assertSuccessful();

    $expectedFile = app_path('UI/Requests/Testing/ScaffoldTemp/CreateUserRequest.php');
    expect(File::exists($expectedFile))->toBeTrue();

    $content = File::get($expectedFile);
    expect($content)
        ->toContain('namespace App\UI\Requests\Testing\ScaffoldTemp;')
        ->toContain('use Idei\Usim\Http\Requests\UIRequest;')
        ->toContain('class CreateUserRequest extends UIRequest')
        ->toContain('public function rules(): array')
        ->toContain('public function authorize(): bool');
});

it('automatically ensures Request suffix when scaffolding a UIRequest', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:request', ['name' => 'Testing/ScaffoldTemp/CreateUser'])
        ->assertSuccessful();

    $expectedFile = app_path('UI/Requests/Testing/ScaffoldTemp/CreateUserRequest.php');
    expect(File::exists($expectedFile))->toBeTrue();

    $content = File::get($expectedFile);
    expect($content)->toContain('class CreateUserRequest extends UIRequest');
});

it('scaffolds both screen and UIRequest when --request flag is passed to usim:screen', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:screen', [
        'name' => 'Testing/ScaffoldTemp/ProfileEdit',
        '--request' => true,
    ])
        ->expectsOutput('Scaffolding a new screen...')
        ->expectsOutputToContain("Screen 'ProfileEdit' has been scaffolded at")
        ->expectsOutput('Scaffolding a new UIRequest...')
        ->expectsOutputToContain("UIRequest 'ProfileEditRequest' has been scaffolded at")
        ->assertSuccessful();

    $screenFile = app_path('UI/Screens/Testing/ScaffoldTemp/ProfileEdit.php');
    $requestFile = app_path('UI/Requests/Testing/ScaffoldTemp/ProfileEditRequest.php');

    expect(File::exists($screenFile))->toBeTrue();
    expect(File::exists($requestFile))->toBeTrue();

    expect(File::get($screenFile))->toContain('class ProfileEdit extends Screen');
    expect(File::get($requestFile))->toContain('class ProfileEditRequest extends UIRequest');
});

it('prevents accidental overwrite unless --force is provided', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:screen', ['name' => 'Testing/ScaffoldTemp/DuplicateScreen'])
        ->assertSuccessful();

    artisanCommand($this, 'usim:screen', ['name' => 'Testing/ScaffoldTemp/DuplicateScreen'])
        ->expectsOutputToContain('already exists.')
        ->assertFailed();

    artisanCommand($this, 'usim:screen', [
        'name' => 'Testing/ScaffoldTemp/DuplicateScreen',
        '--force' => true,
    ])
        ->assertSuccessful();
});

it('fails gracefully when a non-existent screen type stub is specified', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:screen', [
        'name' => 'Testing/ScaffoldTemp/UnknownStubScreen',
        '--type' => 'nonexistent_template',
    ])
        ->expectsOutputToContain('does not exist')
        ->assertFailed();
});

it('scaffolds screen with --i18n and generates translation files compatible with t()', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:screen', [
        'name' => 'Testing/ScaffoldTemp/I18nScreen',
        '--i18n' => true,
    ])
        ->expectsOutput('Scaffolding a new screen...')
        ->expectsOutputToContain("Screen 'I18nScreen' has been scaffolded at")
        ->expectsOutputToContain('Translation created:')
        ->assertSuccessful();

    $screenFile = app_path('UI/Screens/Testing/ScaffoldTemp/I18nScreen.php');
    $langEsFile = lang_path('es/screens/testing/scaffold_temp/i18n_screen.php');
    $langEnFile = lang_path('en/screens/testing/scaffold_temp/i18n_screen.php');

    expect(File::exists($screenFile))->toBeTrue();
    expect(File::exists($langEsFile))->toBeTrue();
    expect(File::exists($langEnFile))->toBeTrue();

    $screenContent = File::get($screenFile);
    expect($screenContent)
        ->toContain("t('.labels.intro')")
        ->toContain("t('.inputs.name.placeholder')")
        ->toContain("t('.buttons.click_me')");

    $originalLocale = app()->getLocale();

    app()->setLocale('es');
    expect(t('screens.testing.scaffold_temp.i18n_screen.title'))->toBe('I18N Screen');
    expect(t('screens.testing.scaffold_temp.i18n_screen.buttons.click_me'))->toBe('¡Haz clic aquí!');
    expect(t('screens.testing.scaffold_temp.i18n_screen.messages.hello', ['name' => 'Carlos']))->toBe('¡Hola Carlos!!');

    app()->setLocale('en');
    expect(t('screens.testing.scaffold_temp.i18n_screen.buttons.click_me'))->toBe('Click me!');
    expect(t('screens.testing.scaffold_temp.i18n_screen.messages.hello', ['name' => 'Carlos']))->toBe('Hello Carlos!!');

    app()->setLocale($originalLocale);
});

it('scaffolds UIRequest with --i18n and generates request translation files compatible with t()', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:request', [
        'name' => 'Testing/ScaffoldTemp/I18nUser',
        '--i18n' => true,
    ])
        ->expectsOutput('Scaffolding a new UIRequest...')
        ->expectsOutputToContain("UIRequest 'I18nUserRequest' has been scaffolded at")
        ->expectsOutputToContain('Translation created:')
        ->assertSuccessful();

    $requestFile = app_path('UI/Requests/Testing/ScaffoldTemp/I18nUserRequest.php');
    $langEsFile = lang_path('es/requests/testing/scaffold_temp/i18n_user_request.php');
    $langEnFile = lang_path('en/requests/testing/scaffold_temp/i18n_user_request.php');

    expect(File::exists($requestFile))->toBeTrue();
    expect(File::exists($langEsFile))->toBeTrue();
    expect(File::exists($langEnFile))->toBeTrue();

    $requestContent = File::get($requestFile);
    expect($requestContent)
        ->toContain("t('.validation.name_required')")
        ->toContain("t('.attributes.name')")
        ->toContain("t('.toast.fix_errors')");

    $originalLocale = app()->getLocale();

    app()->setLocale('es');
    expect(t('requests.testing.scaffold_temp.i18n_user_request.validation.name_required', ['attribute' => 'nombre']))
        ->toBe('El campo nombre es obligatorio.');

    app()->setLocale('en');
    expect(t('requests.testing.scaffold_temp.i18n_user_request.validation.name_required', ['attribute' => 'name']))
        ->toBe('The name field is required.');

    app()->setLocale($originalLocale);
});

it('scaffolds screen and request together with --request --i18n flags', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:screen', [
        'name' => 'Testing/ScaffoldTemp/FullCombo',
        '--request' => true,
        '--i18n' => true,
    ])
        ->assertSuccessful();

    $screenFile = app_path('UI/Screens/Testing/ScaffoldTemp/FullCombo.php');
    $requestFile = app_path('UI/Requests/Testing/ScaffoldTemp/FullComboRequest.php');
    $screenLangEs = lang_path('es/screens/testing/scaffold_temp/full_combo.php');
    $screenLangEn = lang_path('en/screens/testing/scaffold_temp/full_combo.php');
    $requestLangEs = lang_path('es/requests/testing/scaffold_temp/full_combo_request.php');
    $requestLangEn = lang_path('en/requests/testing/scaffold_temp/full_combo_request.php');

    expect(File::exists($screenFile))->toBeTrue();
    expect(File::exists($requestFile))->toBeTrue();
    expect(File::exists($screenLangEs))->toBeTrue();
    expect(File::exists($screenLangEn))->toBeTrue();
    expect(File::exists($requestLangEs))->toBeTrue();
    expect(File::exists($requestLangEn))->toBeTrue();
});

it('scaffolds a declarative Workbench screen with sibling .layout.yaml, slots, i18n: auto, and Pest test', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:screen', [
        'name' => 'Testing/ScaffoldTemp/OrderExplorer',
        '--layout' => 'workbench',
        '--slots' => 'top_bar,left_panel,right_panel,bottom_panel,status_bar,status_bar_right',
        '--visibility' => 'authenticated',
        '--i18n' => true,
        '--test' => true,
    ])
        ->expectsOutput('Scaffolding a new screen...')
        ->expectsOutputToContain("Screen 'OrderExplorer' has been scaffolded at")
        ->expectsOutputToContain('Layout YAML created:')
        ->expectsOutputToContain('Test created:')
        ->expectsOutputToContain('Route path: /testing/scaffold-temp/order-explorer')
        ->assertSuccessful();

    $screenFile = app_path('UI/Screens/Testing/ScaffoldTemp/OrderExplorer.php');
    $yamlFile = app_path('UI/Screens/Testing/ScaffoldTemp/order-explorer.layout.yaml');
    $langEnFile = lang_path('en/screens/testing/scaffold_temp/order_explorer.php');
    $langEsFile = lang_path('es/screens/testing/scaffold_temp/order_explorer.php');
    $testFile = base_path('tests/Feature/Testing/ScaffoldTemp/OrderExplorerScreenTest.php');

    foreach ([$screenFile, $yamlFile, $langEnFile, $langEsFile, $testFile] as $path) {
        expect(File::exists($path))->toBeTrue("Expected [{$path}] to exist.");
    }

    foreach ([$screenFile, $langEnFile, $langEsFile, $testFile] as $phpFile) {
        $output = [];
        $exitCode = 0;
        exec('php -l '.escapeshellarg($phpFile).' 2>&1', $output, $exitCode);
        expect($exitCode)->toBe(0, implode("\n", $output));
    }

    $screenContent = File::get($screenFile);
    expect($screenContent)
        ->toContain('public static Visibility $visibility = Visibility::AUTHENTICATED;')
        ->toContain("public static ?string \$layout = 'workbench';")
        ->toContain('public function onToggleLeftPanel(): void')
        ->toContain('public function onToggleRightPanel(): void')
        ->toContain('public function onToggleBottomPanel(): void')
        ->toContain('public function onToggleTopBar(): void')
        ->toContain('public function onStatusAction(): void');

    $parsedYaml = \Symfony\Component\Yaml\Yaml::parseFile($yamlFile);
    expect($parsedYaml)->toBeArray();
    expect($parsedYaml['i18n'] ?? null)->toBe('auto');
    expect(array_keys($parsedYaml['slots'] ?? []))->toBe([
        'top_bar',
        'left_panel',
        'right_panel',
        'bottom_panel',
        'status_bar',
        'status_bar_right',
    ]);

    // Actually load and render the scaffolded Screen over HTTP to prove PHP + YAML + WorkbenchLayout + i18n: auto work end-to-end
    require_once $screenFile;
    $screenClass = 'App\\UI\\Screens\\Testing\\ScaffoldTemp\\OrderExplorer';
    expect($screenClass::getLayoutClass())->toBe(\Idei\Usim\Layout\WorkbenchLayout::class);
    expect(\Idei\Usim\Support\ContainerLayoutLoader::hasDefaultLayout($screenClass))->toBeTrue();

    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
    $this->loginAs('root');

    $response = $this->getJson(screenApiUrl($screenClass, ['reset' => true]));
    $response->assertOk();
    $payload = $response->json();

    $titleNode = findComponentByName($payload, 'lbl_title');
    expect($titleNode)->toBeArray();
    expect($titleNode['text'] ?? null)->toBe('Order Explorer');

    $statusNode = findComponentByName($payload, 'lbl_status_text');
    expect($statusNode)->toBeArray();
    expect($statusNode['text'] ?? null)->toBe('Order Explorer ready');

    expect(findComponentByName($payload, '__portal_left_panel'))->toBeArray();
    expect(findComponentByName($payload, '__portal_right_panel'))->toBeArray();
    expect(findComponentByName($payload, '__portal_top_bar'))->toBeArray();
    expect(findComponentByName($payload, '__portal_bottom_panel'))->toBeArray();
});

it('scaffolds workspace, modal, kiosk, and custom layout presets declaratively', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:screen', [
        'name' => 'Testing/ScaffoldTemp/SalesWorkspace',
        '--type' => 'workspace',
    ])->assertSuccessful();

    expect(File::get(app_path('UI/Screens/Testing/ScaffoldTemp/SalesWorkspace.php')))
        ->toContain("public static ?string \$layout = 'workbench';");
    $workspaceYaml = \Symfony\Component\Yaml\Yaml::parseFile(app_path('UI/Screens/Testing/ScaffoldTemp/sales-workspace.layout.yaml'));
    expect(array_keys($workspaceYaml['slots'] ?? []))->toBe(['top_bar', 'left_panel', 'right_panel', 'status_bar']);

    artisanCommand($this, 'usim:screen', [
        'name' => 'Testing/ScaffoldTemp/EditItemModal',
        '--type' => 'modal',
    ])->assertSuccessful();

    expect(File::get(app_path('UI/Screens/Testing/ScaffoldTemp/EditItemModal.php')))
        ->toContain('public static ?string $layout = null;')
        ->toContain('public function onSave(): void')
        ->toContain('$this->closeModal();');
    expect(File::exists(app_path('UI/Screens/Testing/ScaffoldTemp/edit-item-modal.layout.yaml')))->toBeTrue();

    artisanCommand($this, 'usim:screen', [
        'name' => 'Testing/ScaffoldTemp/LobbyKiosk',
        '--type' => 'kiosk',
    ])->assertSuccessful();

    expect(File::get(app_path('UI/Screens/Testing/ScaffoldTemp/LobbyKiosk.php')))
        ->toContain('public static ?string $layout = null;');
    expect(File::exists(app_path('UI/Screens/Testing/ScaffoldTemp/lobby-kiosk.layout.yaml')))->toBeTrue();

    artisanCommand($this, 'usim:screen', [
        'name' => 'Testing/ScaffoldTemp/SlotlessWorkbench',
        '--layout' => 'workbench',
        '--slots' => 'none',
    ])->assertSuccessful();

    $slotlessYaml = \Symfony\Component\Yaml\Yaml::parseFile(app_path('UI/Screens/Testing/ScaffoldTemp/slotless-workbench.layout.yaml'));
    expect($slotlessYaml['slots'] ?? null)->toBeNull();
    expect(File::get(app_path('UI/Screens/Testing/ScaffoldTemp/SlotlessWorkbench.php')))
        ->toContain('public function onPrimaryAction(): void');
});

it('supports --imperative with --layout and --visibility without generating a YAML file', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:screen', [
        'name' => 'Testing/ScaffoldTemp/ImperativeWorkbench',
        '--layout' => 'workbench',
        '--visibility' => 'authenticated',
        '--imperative' => true,
    ])->assertSuccessful();

    $screenFile = app_path('UI/Screens/Testing/ScaffoldTemp/ImperativeWorkbench.php');
    $yamlFile = app_path('UI/Screens/Testing/ScaffoldTemp/imperative-workbench.layout.yaml');

    expect(File::exists($screenFile))->toBeTrue();
    expect(File::exists($yamlFile))->toBeFalse();
    expect(File::get($screenFile))
        ->toContain('public static Visibility $visibility = Visibility::AUTHENTICATED;')
        ->toContain("public static ?string \$layout = 'workbench';")
        ->toContain('protected function startingUI(): Container');
});

it('validates invalid --visibility and --slots values gracefully', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:screen', [
        'name' => 'Testing/ScaffoldTemp/BadVisibility',
        '--visibility' => 'secret',
    ])
        ->expectsOutputToContain('Invalid visibility')
        ->assertFailed();

    artisanCommand($this, 'usim:screen', [
        'name' => 'Testing/ScaffoldTemp/BadSlots',
        '--layout' => 'workbench',
        '--slots' => 'left_panel,invalid_zone',
    ])
        ->expectsOutputToContain('Unknown Workbench slot(s): invalid_zone')
        ->assertFailed();
});

it('guides the developer in --interactive mode', function () {
    /** @var TestCase $this */
    artisanCommand($this, 'usim:screen', ['--interactive' => true])
        ->expectsQuestion('Screen name (e.g. Orders/OrderExplorer)', 'Testing/ScaffoldTemp/InteractiveScreen')
        ->expectsChoice('Which page layout should wrap this screen?', 'workbench', ['default', 'workbench', 'main', 'none'])
        ->expectsChoice('Which template preset do you want?', 'workspace', ['basic', 'workspace', 'modal', 'kiosk'])
        ->expectsQuestion('Workbench slots to project (comma-separated: top_bar,left_panel,right_panel,bottom_panel,status_bar,status_bar_right or none)', 'left_panel,right_panel')
        ->expectsChoice('Screen visibility?', 'authenticated', ['public', 'authenticated', 'guest'])
        ->expectsConfirmation('Generate declarative sibling .layout.yaml?', 'yes')
        ->expectsConfirmation('Generate i18n language files (en/es)?', 'yes')
        ->expectsConfirmation('Generate a Pest feature test?', 'no')
        ->expectsConfirmation('Run php artisan usim:discover now?', 'no')
        ->assertSuccessful();

    expect(File::exists(app_path('UI/Screens/Testing/ScaffoldTemp/InteractiveScreen.php')))->toBeTrue();
    expect(File::exists(app_path('UI/Screens/Testing/ScaffoldTemp/interactive-screen.layout.yaml')))->toBeTrue();
    expect(File::exists(lang_path('en/screens/testing/scaffold_temp/interactive_screen.php')))->toBeTrue();
    expect(File::exists(lang_path('es/screens/testing/scaffold_temp/interactive_screen.php')))->toBeTrue();
});
