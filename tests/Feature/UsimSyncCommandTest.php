<?php

use App\Contracts\UnitTranslationGeneratorContract;
use Idei\Usim\Models\UsimUnit;
use Tests\TestCase;

beforeEach(function () {
    config(['permission.teams' => true]);

    // Usamos una implementación aislada del generador de traducciones para no sobrescribir
    // los archivos físicos lang/ de la aplicación durante las pruebas de consola
    $fakeGenerator = new class implements UnitTranslationGeneratorContract {
        public function generate(array $structure, string $filePrefix = 'unit', ?string $baseLangPath = null): array
        {
            return [
                "lang/en/{$filePrefix}.php",
                "lang/es/{$filePrefix}.php",
            ];
        }
    };

    app()->instance(UnitTranslationGeneratorContract::class, $fakeGenerator);
});

it('warns and skips synchronization when teams are disabled', function () {
    config(['permission.teams' => false]);

    /** @var TestCase $this */
    $this->artisan('usim:sync units')
        ->expectsOutput('Units are disabled in the Spatie (permission.php) configuration. Skipping unit synchronization.')
        ->assertSuccessful();
});

it('syncs units when target is units', function () {
    config([
        'permission.teams' => true,
        'usim.units.structure' => [
            'hq' => [
                'type' => 'headquarters',
                'default_translations' => [
                    'en' => ['display_name' => 'HQ'],
                ],
            ],
            'marketing' => [
                'type' => 'department',
                'parent' => 'hq',
                'default_translations' => [
                    'en' => ['display_name' => 'Marketing'],
                ],
            ],
        ],
    ]);

    /** @var TestCase $this */
    $this->artisan('usim:sync units')
        ->expectsOutput('Synchronizing organizational units...')
        ->expectsOutput('Synchronization of organizational units completed successfully.')
        ->assertSuccessful();

    expect(UsimUnit::where('slug', 'hq')->exists())->toBeTrue();
    expect(UsimUnit::where('slug', 'marketing')->exists())->toBeTrue();

    $marketing = UsimUnit::where('slug', 'marketing')->first();
    $hq = UsimUnit::where('slug', 'hq')->first();
    expect($marketing->parent_id)->toBe($hq->id);
});

it('reports removed obsolete units when synchronizing', function () {
    UsimUnit::create(['slug' => 'deprecated_unit']);

    config([
        'permission.teams' => true,
        'usim.units.structure' => [
            'active_unit' => ['type' => 'team'],
        ],
    ]);

    /** @var TestCase $this */
    $this->artisan('usim:sync all')
        ->expectsOutput('Synchronizing organizational units...')
        ->expectsOutput('Removed 1 obsolete units.')
        ->expectsOutput('Synchronization of organizational units completed successfully.')
        ->assertSuccessful();

    expect(UsimUnit::where('slug', 'deprecated_unit')->exists())->toBeFalse();
    expect(UsimUnit::where('slug', 'active_unit')->exists())->toBeTrue();
});

it('syncs root role with configured permissions from config', function () {
    /** @var TestCase $this */
    $this->artisan('usim:sync roles')
        ->expectsOutput('Synchronizing roles and permissions...')
        ->assertSuccessful();

    $rootRole = \Idei\Usim\Models\UsimRole::where('name', 'root')->where('guard_name', 'web')->first();
    expect($rootRole)->not->toBeNull();

    $permissionNames = $rootRole->permissions->pluck('name')->toArray();
    expect($permissionNames)->toContain('admin.users_manager.access');
    expect($permissionNames)->toContain('manage.roles');
});

it('discovers screens and prunes obsolete permissions when target is screens or discover flag is set', function () {
    // Manually create an obsolete public screen permission
    \Spatie\Permission\Models\Permission::firstOrCreate([
        'name' => 'demo.temporary_obsolete.access',
        'guard_name' => 'web',
    ]);

    expect(\Spatie\Permission\Models\Permission::where('name', 'demo.temporary_obsolete.access')->exists())->toBeTrue();

    /** @var TestCase $this */
    $this->artisan('usim:sync screens')
        ->assertSuccessful();

    expect(\Spatie\Permission\Models\Permission::where('name', 'demo.temporary_obsolete.access')->exists())->toBeFalse();
});
