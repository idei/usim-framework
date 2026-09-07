<?php

use App\Contracts\UnitsServiceContract;
use Idei\Usim\Models\UsimUnit;
use App\Services\Units\UnitsService;
use App\Services\Units\UnitTranslationGenerator;
use Illuminate\Filesystem\Filesystem;

beforeEach(function () {
    config(['permission.teams' => true]);
});

afterEach(function () {
    config(['permission.teams' => true]);
});

it('resolves UnitsServiceContract from the service container', function () {
    $service = app(UnitsServiceContract::class);

    expect($service)->toBeInstanceOf(UnitsService::class);
});

it('checks if teams are enabled', function () {
    /** @var UnitsService $service */
    $service = app(UnitsServiceContract::class);

    config(['permission.teams' => true]);
    expect($service->isTeamsEnabled())->toBeTrue();

    config(['permission.teams' => false]);
    expect($service->isTeamsEnabled())->toBeFalse();
});

it('skips sync when teams are disabled', function () {
    config(['permission.teams' => false]);

    /** @var UnitsService $service */
    $service = app(UnitsServiceContract::class);
    $result = $service->sync();

    expect($result->isSkipped())->toBeTrue();
    expect($result->skipReason)->toContain('Units are disabled');
});

it('upserts units from structure', function () {
    /** @var UnitsService $service */
    $service = app(UnitsServiceContract::class);

    $structure = [
        'hq' => ['type' => 'headquarters'],
        'branch_1' => ['type' => 'branch'],
    ];

    $processed = [];
    $count = $service->upsertUnits($structure, function (int $current, int $total, string $slug) use (&$processed) {
        $processed[] = [$slug, $current, $total];
    });

    expect($count)->toBe(2);
    expect($processed)->toBe([
        ['hq', 1, 2],
        ['branch_1', 2, 2],
    ]);

    expect(UsimUnit::where('slug', 'hq')->first()->type)->toBe('headquarters');
    expect(UsimUnit::where('slug', 'branch_1')->first()->type)->toBe('branch');
});

it('updates parent-child hierarchy correctly', function () {
    /** @var UnitsService $service */
    $service = app(UnitsServiceContract::class);

    $structure = [
        'parent_unit' => ['type' => 'department'],
        'child_unit' => ['type' => 'team', 'parent' => 'parent_unit'],
    ];

    $service->upsertUnits($structure);
    $service->updateHierarchy($structure);

    $parent = UsimUnit::where('slug', 'parent_unit')->first();
    $child = UsimUnit::where('slug', 'child_unit')->first();

    expect($parent->parent_id)->toBeNull();
    expect($child->parent_id)->toBe($parent->id);
    expect($child->parent->slug)->toBe('parent_unit');
});

it('deletes obsolete units while protecting child units from cascade deletion', function () {
    /** @var UnitsService $service */
    $service = app(UnitsServiceContract::class);

    $parent = UsimUnit::create(['slug' => 'obsolete_parent', 'type' => 'temp']);
    $child = UsimUnit::create(['slug' => 'kept_child', 'type' => 'temp', 'parent_id' => $parent->id]);

    // kept_child is kept in config, obsolete_parent is omitted
    $deleted = $service->deleteObsoleteUnits(['kept_child']);

    expect($deleted)->toBe(1);
    expect(UsimUnit::where('slug', 'obsolete_parent')->exists())->toBeFalse();
    // kept_child still exists and its parent_id was cleared before cascade could delete it
    $child->refresh();
    expect($child->exists)->toBeTrue();
    expect($child->parent_id)->toBeNull();
});

it('generates translation files using UnitTranslationGenerator', function () {
    $files = new Filesystem();
    $tempLangDir = sys_get_temp_dir() . '/usim_test_lang_' . uniqid();
    $generator = new UnitTranslationGenerator($files);

    $structure = [
        'unit_a' => [
            'default_translations' => [
                'en' => ['display_name' => 'Unit A', 'description' => 'Description A'],
                'es' => ['display_name' => 'Unidad A', 'description' => 'Descripción A'],
            ],
        ],
    ];

    $generated = $generator->generate($structure, 'unit', $tempLangDir);

    expect($generated)->toHaveCount(2);
    expect($files->exists("{$tempLangDir}/en/unit.php"))->toBeTrue();
    expect($files->exists("{$tempLangDir}/es/unit.php"))->toBeTrue();

    $enContent = require "{$tempLangDir}/en/unit.php";
    expect($enContent['unit_a']['display_name'])->toBe('Unit A');

    $esContent = require "{$tempLangDir}/es/unit.php";
    expect($esContent['unit_a']['display_name'])->toBe('Unidad A');

    // Cleanup
    $files->deleteDirectory($tempLangDir);
});

it('runs full sync with custom structure and returns UnitSyncResult', function () {
    /** @var UnitsService $service */
    $service = app(UnitsServiceContract::class);

    // Create an old unit that should be deleted
    UsimUnit::create(['slug' => 'old_unit']);

    $structure = [
        'company' => [
            'type' => 'organization',
            'default_translations' => [
                'en' => ['display_name' => 'My Company'],
            ],
        ],
        'tech' => [
            'type' => 'department',
            'parent' => 'company',
            'default_translations' => [
                'en' => ['display_name' => 'Tech Team'],
            ],
        ],
    ];

    $files = new Filesystem();
    $tempLangDir = sys_get_temp_dir() . '/usim_test_sync_' . uniqid();

    $progressCalls = 0;
    $result = $service->sync(
        $structure,
        onProgress: function () use (&$progressCalls) {
            $progressCalls++;
        },
        baseLangPath: $tempLangDir
    );

    expect($result->isSuccess())->toBeTrue();
    expect($result->deletedCount)->toBe(1);
    expect($result->upsertedCount)->toBe(2);
    expect($result->hierarchyUpdatedCount)->toBe(2);
    expect($progressCalls)->toBe(2);

    expect(UsimUnit::where('slug', 'old_unit')->exists())->toBeFalse();
    $tech = UsimUnit::where('slug', 'tech')->first();
    $company = UsimUnit::where('slug', 'company')->first();
    expect($tech->parent_id)->toBe($company->id);

    $files->deleteDirectory($tempLangDir);
});

