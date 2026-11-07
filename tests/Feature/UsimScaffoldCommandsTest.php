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
