<?php

use Idei\Usim\Console\Commands\Support\InstallContextResolver;
use Idei\Usim\Console\Commands\Support\InstallExecutionRollbackManager;
use Idei\Usim\Console\Commands\Support\InstallMigrationStatusChecker;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;

it('correctly resolves componentsPath when screensPath uses Windows backslashes', function () {
    Config::set('usim.screens_path', 'C:\\Users\\emili\\Desktop\\Prueba3\\app\\UI\\Screens');

    $resolver = new InstallContextResolver();
    $namespaces = $resolver->resolveNamespaces();

    expect($namespaces['screensPath'])->toBe('C:/Users/emili/Desktop/Prueba3/app/UI/Screens');
    expect($namespaces['componentsPath'])->toBe('C:/Users/emili/Desktop/Prueba3/app/UI/Components');
    expect($namespaces['componentsPath'])->not->toContain('/Screens/Components');
});

it('handles stubsPath with both leading forward slashes and backslashes', function () {
    $resolver = new InstallContextResolver();

    $pathWithSlash = $resolver->stubsPath('/resources');
    $pathWithBackslash = $resolver->stubsPath('\\resources');

    expect($pathWithSlash)->toBe($pathWithBackslash);
    expect($pathWithSlash)->toEndWith('/stubs/resources');
});

it('does not duplicate Windows absolute path in SQLite assessment when file does not exist', function () {
    $checker = new InstallMigrationStatusChecker();

    $windowsPath = 'C:\\Users\\emili\\Desktop\\Prueba3\\database\\database.sqlite';
    Config::set('database.default', 'sqlite');
    Config::set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => $windowsPath,
    ]);

    $connectivity = $checker->assessConnectivity();

    expect($connectivity['exists'])->toBeFalse();
    expect($connectivity['issue'])->toContain("SQLite database file does not exist: {$windowsPath}");
    expect($connectivity['issue'])->not->toContain('Prueba3\\C:');
    expect($connectivity['issue'])->not->toContain('Prueba3/C:');
});

it('resolves Windows absolute path correctly in InstallExecutionRollbackManager', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('makeDirectory')->andReturn(true);
    $files->shouldReceive('exists')->andReturn(false);

    $rollbackManager = new InstallExecutionRollbackManager($files);

    $reflector = new ReflectionClass($rollbackManager);
    $method = $reflector->getMethod('resolveAbsolutePath');
    $method->setAccessible(true);

    $windowsPath = 'C:\\Users\\emili\\Desktop\\Prueba3\\.env';
    $resolvedWindows = $method->invoke($rollbackManager, $windowsPath);
    expect($resolvedWindows)->toBe($windowsPath);

    $unixPath = '/var/www/html/.env';
    $resolvedUnix = $method->invoke($rollbackManager, $unixPath);
    expect($resolvedUnix)->toBe($unixPath);

    $relativePath = '.env';
    $resolvedRelative = $method->invoke($rollbackManager, $relativePath);
    expect($resolvedRelative)->toBe(base_path('.env'));
});

it('falls back to ask when secret returns empty for password', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->andReturn(true);
    $files->shouldReceive('get')->andReturn('');
    $files->shouldReceive('put')->andReturn(true);

    $envManager = new \Idei\Usim\Console\Commands\Support\InstallEnvironmentManager($files);

    $secretCalled = false;
    $askCalled = false;

    $result = $envManager->promptAndPersistRootUserEnv(
        envPath: base_path('.env'),
        interactive: true,
        ask: function (string $label, string $default) use (&$askCalled): string {
            if (str_starts_with($label, 'Root password')) {
                $askCalled = true;
                return 'fallbackPassword123';
            }
            return $default;
        },
        secret: function (string $prompt) use (&$secretCalled): string {
            $secretCalled = true;
            return ''; // Simulates hiddeninput.exe failing immediately on Windows
        },
        error: fn (string $msg) => null,
        line: fn (string $msg) => null,
    );

    expect($secretCalled)->toBeTrue();
    expect($askCalled)->toBeTrue();
    expect($result['password'])->toBe('fallbackPassword123');
});

it('prevents infinite loop when input is repeatedly empty', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->andReturn(true);
    $files->shouldReceive('get')->andReturn('');

    $envManager = new \Idei\Usim\Console\Commands\Support\InstallEnvironmentManager($files);

    $errorCalled = false;
    $errorMessage = '';

    $result = $envManager->promptAndPersistRootUserEnv(
        envPath: base_path('.env'),
        interactive: true,
        ask: fn (string $label, string $default) => '',
        secret: fn (string $prompt) => '',
        error: function (string $msg) use (&$errorCalled, &$errorMessage) {
            $errorCalled = true;
            $errorMessage = $msg;
        },
        line: fn (string $msg) => null,
    );

    expect($result)->toBe([]);
    expect($errorCalled)->toBeTrue();
    expect($errorMessage)->toContain('Too many failed attempts');
});

it('generates random root password in non-interactive environment when ROOT_PASSWORD is CHANGE_ME', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->andReturn(true);
    $files->shouldReceive('get')->andReturn("ROOT_PASSWORD=CHANGE_ME\nROOT_EMAIL=root@example.com\n");
    $files->shouldReceive('put')->andReturn(true);

    $envManager = new \Idei\Usim\Console\Commands\Support\InstallEnvironmentManager($files);

    $lines = [];
    $result = $envManager->promptAndPersistRootUserEnv(
        envPath: base_path('.env'),
        interactive: false,
        ask: fn (string $label, string $default) => $default,
        secret: fn (string $prompt) => '',
        error: fn (string $msg) => null,
        line: function (string $msg) use (&$lines) {
            $lines[] = $msg;
        },
    );

    expect($result)->toHaveKeys(['first_name', 'last_name', 'email', 'password']);
    expect($result['password'])->not->toBe('');
    expect($result['password'])->not->toBe('CHANGE_ME');
    expect(strlen($result['password']))->toBe(16);
    expect(collect($lines)->some(fn ($l) => str_contains($l, 'Non-interactive environment detected')))->toBeTrue();
});

it('preserves existing root password in non-interactive environment', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->andReturn(true);
    $files->shouldReceive('get')->andReturn("ROOT_PASSWORD=ExistingSecret123!\nROOT_EMAIL=root@example.com\n");
    $files->shouldReceive('put')->andReturn(true);

    $envManager = new \Idei\Usim\Console\Commands\Support\InstallEnvironmentManager($files);

    $result = $envManager->promptAndPersistRootUserEnv(
        envPath: base_path('.env'),
        interactive: false,
        ask: fn (string $label, string $default) => $default,
        secret: fn (string $prompt) => '',
        error: fn (string $msg) => null,
        line: fn (string $msg) => null,
    );

    expect($result['password'])->toBe('ExistingSecret123!');
});

it('generates random root password when user leaves password blank in interactive mode', function () {
    $files = Mockery::mock(Filesystem::class);
    $files->shouldReceive('exists')->andReturn(true);
    $files->shouldReceive('get')->andReturn('');
    $files->shouldReceive('put')->andReturn(true);

    $envManager = new \Idei\Usim\Console\Commands\Support\InstallEnvironmentManager($files);

    $lines = [];
    $result = $envManager->promptAndPersistRootUserEnv(
        envPath: base_path('.env'),
        interactive: true,
        ask: fn (string $label, string $default) => $default,
        secret: fn (string $prompt) => '', // User leaves blank / presses Enter
        error: fn (string $msg) => null,
        line: function (string $msg) use (&$lines) {
            $lines[] = $msg;
        },
    );

    expect($result)->toHaveKeys(['first_name', 'last_name', 'email', 'password']);
    expect($result['password'])->not->toBe('');
    expect(strlen($result['password']))->toBe(16);
    expect(collect($lines)->some(fn ($l) => str_contains($l, 'Generated root password')))->toBeTrue();
});


