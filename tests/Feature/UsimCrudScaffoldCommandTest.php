<?php

declare(strict_types=1);

use Idei\Usim\Screen;
use Idei\Usim\Testing\ScreenTestHarness;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use Tests\Fixtures\Crud\FixtureProductRepositoryInterface;
use Tests\Fixtures\Crud\FixtureProductService;
use Tests\Fixtures\Crud\FixtureReadOnlyCatalogService;
use Tests\Fixtures\Crud\InMemoryFixtureProductRepository;
use Tests\TestCase;

require_once __DIR__.'/../Fixtures/Crud/FixtureProductCrudStack.php';

$cleanupCrudScaffoldArtifacts = static function (): void {
    $dirs = [
        app_path('UI/Screens/Testing/CrudTemp'),
        app_path('UI/Requests/Testing/CrudTemp'),
        app_path('DTOs/Testing/CrudTemp'),
        app_path('Contracts/Repositories/Testing/CrudTemp'),
        app_path('Repositories/Testing/CrudTemp'),
        app_path('Services/Testing/CrudTemp'),
        lang_path('es/screens/testing/crud_temp'),
        lang_path('en/screens/testing/crud_temp'),
        lang_path('es/requests/testing/crud_temp'),
        lang_path('en/requests/testing/crud_temp'),
        base_path('tests/Feature/Testing/CrudTemp'),
        base_path('stubs/usim'),
        storage_path('app/usim'),
    ];

    foreach ($dirs as $dir) {
        if (File::isDirectory($dir)) {
            File::deleteDirectory($dir);
        }
    }
};

beforeEach(function () use ($cleanupCrudScaffoldArtifacts): void {
    $cleanupCrudScaffoldArtifacts();
});

afterEach(function () use ($cleanupCrudScaffoldArtifacts): void {
    $cleanupCrudScaffoldArtifacts();
});

/**
 * @param  array<string, mixed>  $parameters
 */
function runCrudArtisan(TestCase $test, string $command, array $parameters = []): PendingCommand
{
    $pending = $test->artisan($command, $parameters);
    assert($pending instanceof PendingCommand);

    return $pending;
}

it('scaffolds a full Master-Detail CRUD screen with 1:N and M:N DTO relations and executes full lifecycle', function (): void {
    /** @var TestCase $this */
    $repo = new InMemoryFixtureProductRepository;
    app()->instance(FixtureProductRepositoryInterface::class, $repo);

    runCrudArtisan($this, 'usim:crud', [
        'name' => 'Testing/CrudTemp/ProductExplorer',
        '--service' => FixtureProductService::class,
        '--visibility' => 'public',
    ])
        ->expectsOutputToContain('FixtureProductService')
        ->expectsOutputToContain('FixtureProductDTO')
        ->expectsOutputToContain('Screen created:')
        ->expectsOutputToContain('Layout YAML created:')
        ->expectsOutputToContain('UIRequest created:')
        ->assertSuccessful();

    $screenPath = app_path('UI/Screens/Testing/CrudTemp/ProductExplorer.php');
    $yamlPath = app_path('UI/Screens/Testing/CrudTemp/product-explorer.layout.yaml');
    $requestPath = app_path('UI/Requests/Testing/CrudTemp/SaveProductExplorerRequest.php');
    $testPath = base_path('tests/Feature/Testing/CrudTemp/ProductExplorerScreenTest.php');

    expect(File::exists($screenPath))->toBeTrue();
    expect(File::exists($yamlPath))->toBeTrue();
    expect(File::exists($requestPath))->toBeTrue();
    expect(File::exists($testPath))->toBeTrue();

    $yamlContent = File::get($yamlPath);
    expect($yamlContent)
        ->toContain('left_panel:')
        ->toContain('service: Tests\Fixtures\Crud\FixtureProductService')
        ->toContain('sel_brand_id:')
        ->toContain('lst_tag_ids:')
        ->toContain('selection_mode: multiple');

    require_once $requestPath;
    require_once $screenPath;

    /** @var class-string<Screen> $screenFqcn */
    $screenFqcn = 'App\\UI\\Screens\\Testing\\CrudTemp\\ProductExplorer';

    // 1. Initial render -> Empty state visible, detail form hidden, 1:N Select options populated
    $harness = ScreenTestHarness::for($screenFqcn)->render();
    $harness->assertComponentVisible('cnt_empty_state');
    $harness->assertComponentHidden('cnt_detail_form');
    $harness->assertComponentHidden('btn_delete');

    $brandSelect = $harness->getComponent('sel_brand_id');
    assert($brandSelect instanceof \Idei\Usim\Components\Select);
    $brandOptions = $brandSelect->get('options');
    expect($brandOptions)->toBeArray()->toHaveCount(2);

    // 2. Select item #1 in left panel -> Detail form visible & hydrated (including 1:N and M:N)
    $harness->call('item_selected', ['selected' => [1]]);
    $harness->assertComponentHidden('cnt_empty_state');
    $harness->assertComponentVisible('cnt_detail_form');
    $harness->assertComponentVisible('btn_delete');

    $skuInput = $harness->getComponent('input_sku');
    $nameInput = $harness->getComponent('input_name');
    $activeChk = $harness->getComponent('chk_active');
    $brandSelect = $harness->getComponent('sel_brand_id');
    $tagsList = $harness->getComponent('lst_tag_ids');
    assert($skuInput instanceof \Idei\Usim\Components\Input);
    assert($nameInput instanceof \Idei\Usim\Components\Input);
    assert($activeChk instanceof \Idei\Usim\Components\Checkbox);
    assert($brandSelect instanceof \Idei\Usim\Components\Select);
    assert($tagsList instanceof \Idei\Usim\Components\ItemList);

    expect($skuInput->get('value'))->toBe('SKU-100');
    expect($nameInput->get('value'))->toBe('Industrial Sensor');
    expect($activeChk->get('checked'))->toBeTrue();
    expect($brandSelect->get('value'))->toBe('acme');
    expect($tagsList->get('selected_items'))->toBe(['featured', 'fragile']);

    // 3. Direct-edit save -> Updates repository via DTO & refreshes left ItemList
    $harness->call('save_detail', [
        'input_sku' => 'SKU-100-REV2',
        'input_name' => 'Industrial Sensor Pro',
        'input_price' => '199.95',
        'chk_active' => false,
        'txt_notes' => 'Updated notes',
        'sel_brand_id' => 'globex',
        'lst_tag_ids' => ['clearance'],
    ]);

    $updatedDto = $repo->find(1);
    expect($updatedDto)->not->toBeNull();
    expect($updatedDto?->sku)->toBe('SKU-100-REV2');
    expect($updatedDto?->name)->toBe('Industrial Sensor Pro');
    expect($updatedDto?->price)->toBe(199.95);
    expect($updatedDto?->active)->toBeFalse();
    expect($updatedDto?->brandId)->toBe('globex');
    expect($updatedDto?->tagIds)->toBe(['clearance']);

    // 4. Create new item flow (`+ New` button -> save_detail)
    $harness->click('btn_new');
    $harness->assertComponentHidden('btn_delete');
    $skuAfterNew = $harness->getComponent('input_sku');
    $masterListAfterNew = $harness->getComponent('lst_items');
    assert($skuAfterNew instanceof \Idei\Usim\Components\Input);
    assert($masterListAfterNew instanceof \Idei\Usim\Components\ItemList);
    expect($skuAfterNew->get('value'))->toBe('');
    expect($masterListAfterNew->get('selected_items'))->toBe([]);

    $harness->call('save_detail', [
        'input_sku' => 'SKU-300',
        'input_name' => 'Pressure Gauge',
        'input_price' => '45.00',
        'chk_active' => true,
        'txt_notes' => '',
        'sel_brand_id' => 'acme',
        'lst_tag_ids' => ['featured'],
    ]);

    expect($repo->all())->toHaveCount(3);
    $masterListAfterCreate = $harness->getComponent('lst_items');
    assert($masterListAfterCreate instanceof \Idei\Usim\Components\ItemList);
    /** @var list<int|string> $selectedAfterCreate */
    $selectedAfterCreate = $masterListAfterCreate->get('selected_items');
    expect($selectedAfterCreate)->toHaveCount(1);

    // 5. Confirmed deletion -> Removes active item and returns to empty state without stale ghost data
    $createdId = $selectedAfterCreate[0];
    $harness->call('confirm_delete');
    expect($repo->find($createdId))->toBeNull();
    $harness->assertComponentVisible('cnt_empty_state');
    $harness->assertComponentHidden('cnt_detail_form');
});

it('scaffolds a clean read-only Master-Detail explorer without UIRequest or mutation buttons when Service is read-only', function (): void {
    /** @var TestCase $this */
    runCrudArtisan($this, 'usim:crud', [
        'name' => 'Testing/CrudTemp/CatalogAuditExplorer',
        '--service' => FixtureReadOnlyCatalogService::class,
        '--visibility' => 'public',
    ])->assertSuccessful();

    $screenPath = app_path('UI/Screens/Testing/CrudTemp/CatalogAuditExplorer.php');
    $yamlPath = app_path('UI/Screens/Testing/CrudTemp/catalog-audit-explorer.layout.yaml');
    $requestPath = app_path('UI/Requests/Testing/CrudTemp/SaveCatalogAuditExplorerRequest.php');

    expect(File::exists($screenPath))->toBeTrue();
    expect(File::exists($yamlPath))->toBeTrue();
    expect(File::exists($requestPath))->toBeFalse();

    $screenPhp = File::get($screenPath);
    expect($screenPhp)
        ->not->toContain('onSaveDetail')
        ->not->toContain('$btn_save')
        ->not->toContain('$btn_new');

    $yamlContent = File::get($yamlPath);
    expect($yamlContent)
        ->toContain('readonly: true')
        ->not->toContain('btn_save')
        ->not->toContain('btn_new')
        ->not->toContain('btn_delete');
});

it('scaffolds full DTO + RepositoryInterface + JSON Mock Repository + Service stack with --with-stack=json', function (): void {
    /** @var TestCase $this */
    runCrudArtisan($this, 'usim:crud', [
        'name' => 'Testing/CrudTemp/SupplierManager',
        '--with-stack' => 'json',
        '--fields' => 'code:string,companyName:string,creditLimit:float,preferred:bool,notes:text?',
        '--visibility' => 'public',
    ])
        ->expectsOutputToContain('DTO created:')
        ->expectsOutputToContain('Repository Contract created:')
        ->expectsOutputToContain('Json Repository created:')
        ->expectsOutputToContain('Service created:')
        ->expectsOutputToContain('Screen created:')
        ->assertSuccessful();

    $dtoPath = app_path('DTOs/Testing/CrudTemp/SupplierDTO.php');
    $repoInterfacePath = app_path('Contracts/Repositories/Testing/CrudTemp/SupplierRepositoryInterface.php');
    $repoJsonPath = app_path('Repositories/Testing/CrudTemp/JsonSupplierRepository.php');
    $servicePath = app_path('Services/Testing/CrudTemp/SupplierService.php');
    $screenPath = app_path('UI/Screens/Testing/CrudTemp/SupplierManager.php');
    $requestPath = app_path('UI/Requests/Testing/CrudTemp/SaveSupplierManagerRequest.php');

    foreach ([$dtoPath, $repoInterfacePath, $repoJsonPath, $servicePath, $screenPath, $requestPath] as $file) {
        expect(File::exists($file))->toBeTrue();
        require_once $file;
    }

    /** @var class-string<Screen> $screenFqcn */
    $screenFqcn = 'App\\UI\\Screens\\Testing\\CrudTemp\\SupplierManager';

    $harness = ScreenTestHarness::for($screenFqcn)->render();
    $masterList = $harness->getComponent('lst_items');
    assert($masterList instanceof \Idei\Usim\Components\ItemList);
    $items = $masterList->get('items');
    expect($items)->toBeArray()->toHaveCount(3);

    $harness->call('item_selected', ['selected' => [1]]);
    $harness->assertComponentVisible('cnt_detail_form');
    $codeInput = $harness->getComponent('input_code');
    assert($codeInput instanceof \Idei\Usim\Components\Input);
    expect($codeInput->get('value'))->toBe('Code 1');
});

it('scaffolds session and memory mock repository stacks cleanly', function (): void {
    /** @var TestCase $this */
    runCrudArtisan($this, 'usim:crud', [
        'name' => 'Testing/CrudTemp/TicketManager',
        '--with-stack' => 'session',
        '--fields' => 'subject:string,priority:int,contactEmail:email,open:bool',
        '--visibility' => 'public',
        '--no-test' => true,
    ])->assertSuccessful();

    runCrudArtisan($this, 'usim:crud', [
        'name' => 'Testing/CrudTemp/TagManager',
        '--with-stack' => 'memory',
        '--fields' => 'label:string,active:bool',
        '--visibility' => 'public',
        '--no-test' => true,
    ])->assertSuccessful();

    expect(File::exists(app_path('Repositories/Testing/CrudTemp/SessionTicketRepository.php')))->toBeTrue();
    expect(File::exists(app_path('Repositories/Testing/CrudTemp/MemoryTagRepository.php')))->toBeTrue();
});

it('supports vendor:publish --tag=usim-crud-stubs with file-by-file cascading overrides and --package-stubs bypass', function (): void {
    /** @var TestCase $this */
    runCrudArtisan($this, 'vendor:publish', [
        '--tag' => 'usim-crud-stubs',
    ])->assertSuccessful();

    $publishedYamlStub = base_path('stubs/usim/crud/screen.layout.yaml.stub');
    $publishedScreenStub = base_path('stubs/usim/crud/screen.php.stub');
    expect(File::exists($publishedYamlStub))->toBeTrue();
    expect(File::exists($publishedScreenStub))->toBeTrue();

    // Delete all published stubs EXCEPT screen.layout.yaml.stub to verify file-by-file cascade without stub drift
    foreach (File::files(base_path('stubs/usim/crud')) as $stubFile) {
        if ($stubFile->getFilename() !== 'screen.layout.yaml.stub') {
            File::delete($stubFile->getPathname());
        }
    }

    // Customize ONLY the published YAML stub (e.g. custom left_panel size: 360px)
    $customYaml = str_replace('size: 300px', 'size: 360px', File::get($publishedYamlStub));
    File::put($publishedYamlStub, $customYaml);

    runCrudArtisan($this, 'usim:crud', [
        'name' => 'Testing/CrudTemp/CustomStubExplorer',
        '--service' => FixtureProductService::class,
        '--no-test' => true,
        '--no-i18n' => true,
    ])
        ->expectsOutputToContain('Using custom stub:')
        ->assertSuccessful();

    $generatedYaml = File::get(app_path('UI/Screens/Testing/CrudTemp/custom-stub-explorer.layout.yaml'));
    expect($generatedYaml)->toContain('size: 360px');

    // Verify --package-stubs ignores the published custom stub when requested
    runCrudArtisan($this, 'usim:crud', [
        'name' => 'Testing/CrudTemp/DefaultStubExplorer',
        '--service' => FixtureProductService::class,
        '--package-stubs' => true,
        '--no-test' => true,
        '--no-i18n' => true,
    ])->assertSuccessful();

    $defaultYaml = File::get(app_path('UI/Screens/Testing/CrudTemp/default-stub-explorer.layout.yaml'));
    expect($defaultYaml)->toContain('size: 300px');
});

it('supports --dry-run without writing files and fails fast when Service lacks required contracts', function (): void {
    /** @var TestCase $this */
    runCrudArtisan($this, 'usim:crud', [
        'name' => 'Testing/CrudTemp/DryRunScreen',
        '--service' => FixtureProductService::class,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Dry-run mode enabled')
        ->assertSuccessful();

    expect(File::exists(app_path('UI/Screens/Testing/CrudTemp/DryRunScreen.php')))->toBeFalse();

    runCrudArtisan($this, 'usim:crud', [
        'name' => 'Testing/CrudTemp/InvalidServiceScreen',
        '--service' => stdClass::class,
    ])
        ->expectsOutputToContain('must implement')
        ->assertFailed();
});
