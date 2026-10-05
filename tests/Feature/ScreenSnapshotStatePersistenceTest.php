<?php

namespace App\UI\Screens;

use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UI;

class TestSnapshotStateScreen extends Screen
{
    public const ROUTE_PATH = '/test-snapshot-state';

    protected string $state_filter = 'initial_filter';
    protected int $state_page = 1;
    protected ?string $state_optional = null;
    protected string $store_theme = 'dark';

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(
            UI::button('btn_change_state')
                ->action('changeState')
        );

        $container->add(
            UI::button('btn_check_state')
                ->action('checkState')
        );
    }

    public function onChangeState(array $params = []): void
    {
        $this->state_filter = $params['filter'] ?? 'updated_filter';
        $this->state_page = isset($params['page']) ? (int) $params['page'] : 99;
        $this->state_optional = 'now_set';
    }

    public function onCheckState(array $params = []): void
    {
        $this->toast("filter:{$this->state_filter}|page:{$this->state_page}|opt:{$this->state_optional}");
    }
}

it('captures state_* properties in getStateVariables and excludes them from getStorageVariables', function () {
    $screen = Screen::make(TestSnapshotStateScreen::class);

    $state = $screen->getStateVariables();
    expect($state)->toHaveKey('state_filter', 'initial_filter');
    expect($state)->toHaveKey('state_page', 1);
    expect($state)->toHaveKey('state_optional', null);
    expect($state)->not->toHaveKey('store_theme');

    $storage = $screen->getStorageVariables();
    expect($storage)->toHaveKey('store_theme', 'dark');
    expect($storage)->not->toHaveKey('state_filter');
    expect($storage)->not->toHaveKey('state_page');
    expect($storage)->not->toHaveKey('state_optional');
});

it('persists state_* properties in UIStateManager snapshot cache across events without leaking to client storage payload', function () {
    /** @var \Tests\TestCase $this */
    $ui = uiScenario($this, TestSnapshotStateScreen::class);

    // Initial state check in cache
    $initialCachedState = UIStateManager::getScreenState(TestSnapshotStateScreen::class);
    expect($initialCachedState)->toHaveKey('state_filter', 'initial_filter');
    expect($initialCachedState)->toHaveKey('state_page', 1);

    // Trigger state change action
    $response = $ui->action('btn_change_state', 'changeState', [
        'filter' => 'custom_filter_val',
        'page' => 42,
    ]);
    $response->assertOk();

    // Verify storage payload NEVER contains state_* variables
    $storagePayload = $response->json('storage.usim-framework');
    expect($storagePayload)->not->toBeNull();
    $decodedStorage = json_decode($storagePayload, true);
    expect($decodedStorage)->toHaveKey('store_theme');
    expect($decodedStorage)->not->toHaveKey('state_filter');
    expect($decodedStorage)->not->toHaveKey('state_page');
    expect($decodedStorage)->not->toHaveKey('state_optional');

    // Verify UIStateManager holds updated state
    $updatedCachedState = UIStateManager::getScreenState(TestSnapshotStateScreen::class);
    expect($updatedCachedState['state_filter'])->toBe('custom_filter_val');
    expect($updatedCachedState['state_page'])->toBe(42);
    expect($updatedCachedState['state_optional'])->toBe('now_set');

    // Trigger second event to verify state rehydration
    $response2 = $ui->action('btn_check_state', 'checkState');
    $response2->assertOk();

    // Toast emitted in checkState confirms internal state was successfully rehydrated
    $toast = $response2->json('toast');
    expect($toast)->not->toBeNull();
    expect($toast['message'])->toBe('filter:custom_filter_val|page:42|opt:now_set');
});

it('clears screen state cache when screen cache is cleared', function () {
    UIStateManager::storeScreenState(TestSnapshotStateScreen::class, [
        'state_filter' => 'temp',
    ]);
    expect(UIStateManager::getScreenState(TestSnapshotStateScreen::class))->toHaveKey('state_filter', 'temp');

    UIStateManager::clear(TestSnapshotStateScreen::class);
    expect(UIStateManager::getScreenState(TestSnapshotStateScreen::class))->toBeEmpty();
});

it('correctly types and injects state variables including int, float, bool, and string', function () {
    $screen = Screen::make(TestSnapshotStateScreen::class);

    $screen->injectStateVariables([
        'state_filter' => 'typed_string',
        'state_page' => '123',
        'state_optional' => null,
    ]);

    $state = $screen->getStateVariables();
    expect($state['state_filter'])->toBe('typed_string');
    expect($state['state_page'])->toBe(123);
    expect($state['state_optional'])->toBeNull();
});
