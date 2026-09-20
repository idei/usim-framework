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

