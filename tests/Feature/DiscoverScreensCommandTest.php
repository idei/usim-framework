<?php

use App\UI\Screens\Auth\Login;
use Idei\Usim\Contracts\ScreenDiscoveryScannerInterface;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

beforeEach(function () {
    $this->manifestPath = app()->bootstrapPath('cache/usim_screens.php');
});

afterEach(function () {
    if (isset($this->manifestPath) && File::exists($this->manifestPath)) {
        File::delete($this->manifestPath);
    }
});

it('discovers screens, writes manifest file, and outputs success message', function () {
    /** @var TestCase $this */
    $this->artisan('usim:discover')
        ->expectsOutput('Discovering USIM Screens...')
        ->expectsOutputToContain('Found ')
        ->expectsOutput('USIM manifest generated successfully!')
        ->assertSuccessful();

    expect(File::exists($this->manifestPath))->toBeTrue();

    $cachedManifest = require $this->manifestPath;
    expect($cachedManifest)->toBeArray();
    expect($cachedManifest)->not->toBeEmpty();
});

it('can run command with a swapped scanner contract in container', function () {
    $mockScanner = Mockery::mock(ScreenDiscoveryScannerInterface::class);
    $mockScanner->shouldReceive('scan')
        ->once()
        ->andReturn([Login::class]);

    app()->instance(ScreenDiscoveryScannerInterface::class, $mockScanner);

    /** @var TestCase $this */
    $this->artisan('usim:discover')
        ->expectsOutput('Discovering USIM Screens...')
        ->expectsOutput('Found 1 screens.')
        ->expectsOutput('USIM manifest generated successfully!')
        ->assertSuccessful();

    expect(File::exists($this->manifestPath))->toBeTrue();

    $cachedManifest = require $this->manifestPath;
    expect($cachedManifest)->toHaveKey(Login::class);
});
