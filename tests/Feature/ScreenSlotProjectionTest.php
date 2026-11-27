<?php

use App\Models\User;
use Idei\Usim\Layout\MainLayout;
use Idei\Usim\Layout\WorkbenchLayout;
use App\UI\Screens\Demo\RightPanelInspector;
use App\UI\Screens\Demo\SlotProjectionDemo;
use App\UI\Screens\Home;
use Idei\Usim\Screens\SidebarMenu;
use Idei\Usim\Screens\SidebarSubmenuPanel;
use Idei\Usim\Screens\StatusBar;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Screen;
use Idei\Usim\Support\ContainerLayoutLoader;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UIChangesCollector;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
    AbstractLayout::setCurrent(null);
    UIStateManager::clearClientCurrentScreen();
    UIStateManager::clearClientActiveLayout();
    UIStateManager::clearClientActiveModal();
});

class TestSlotsMainAndDeclarativeScreenHost extends Screen
{
    public static ?string $layout = WorkbenchLayout::class;

    protected NewLabel $lbl_center_only;

    public static function authorize(): bool
    {
        return true;
    }

    protected function startingUI(): Container
    {
        return $this->layoutContainer(base_path('tests/Fixtures/slots-main-and-subscreen.layout.yaml'));
    }

    public function onGoHome(): void
    {
        $this->showInto(Home::class);
    }
}

class TestStandaloneFallbackScreen extends Screen
{
    public static ?string $layout = null;

    protected NewLabel $lbl_ignored_panel;

    protected NewLabel $lbl_inline_top;

    public static function authorize(): bool
    {
        return true;
    }

    protected function startingUI(): Container
    {
        return $this->layoutContainer(base_path('tests/Fixtures/slots-fallback-standalone.layout.yaml'));
    }

    public function onMutateIgnored(): void
    {
        $this->lbl_ignored_panel->text('Updated while unmounted');
        $this->lbl_inline_top->text('Updated inline top');
    }
}

class TestIncludeSlotPartialScreen extends Screen
{
    public static ?string $layout = WorkbenchLayout::class;

    protected NewLabel $lbl_partial_heading;

    public static function authorize(): bool
    {
        return true;
    }

    protected function startingUI(): Container
    {
        return $this->layoutContainer(base_path('tests/Fixtures/slots-include-partial.layout.yaml'));
    }
}

it('validates fail-fast when a layout declares both root grid and slots.main or invalid fallback', function () {
    expect(fn () => ContainerLayoutLoader::resolveMainDefinition([
        'grid' => ['cols' => [1], 'children' => ['lbl_a']],
        'slots' => [
            'main' => ['grid' => ['cols' => [1], 'children' => ['lbl_b']]],
        ],
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn () => ContainerLayoutLoader::extractSlotDefinitions([
        'slots' => [
            'right_panel' => ['fallback' => 'floating_popup', 'children' => ['lbl_a']],
        ],
    ]))->toThrow(InvalidArgumentException::class);
});

it('renders SlotProjectionDemo in WorkbenchLayout projecting left_panel (open + 270px), right_panel (closed + 340px), status_bar (hiding lbl_status_message), and status_bar_right', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'Projection User']);
    $this->actingAs($user);

    $response = $this->getJson(screenApiUrl(SlotProjectionDemo::class, ['reset' => true]));
    $response->assertOk();
    $payload = $response->json();

    $splitShellId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'split_shell');
    $splitWorkspaceId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'split_workspace');
    $leftPanelId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'left_panel_container');
    $rightPanelId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'right_panel_container');
    $statusCenterSlotId = UIIdGenerator::generateFromName(StatusBar::class, 'status_bar_center_slot');
    $statusRightSlotId = UIIdGenerator::generateFromName(StatusBar::class, 'status_bar_right_slot');
    $statusMessageId = UIIdGenerator::generateFromName(StatusBar::class, 'lbl_status_message');

    // left_panel Split expanded (open: true) with size: 270px targeting first pane
    expect(array_key_exists('collapsed_panel', $payload[$splitShellId] ?? []))->toBeTrue();
    expect($payload[$splitShellId]['collapsed_panel'])->toBeNull();
    expect($payload[$splitShellId]['split_size'] ?? null)->toBe('270px');
    expect($payload[$splitShellId]['split_size_target'] ?? null)->toBe('first');

    // right_panel Split stays collapsed (open: false) with size: 340px targeting second pane
    expect($payload[$splitWorkspaceId]['collapsed_panel'] ?? null)->toBe('second');
    expect($payload[$splitWorkspaceId]['split_size'] ?? null)->toBe('340px');
    expect($payload[$splitWorkspaceId]['split_size_target'] ?? null)->toBe('second');

    // Portal containers carry portal_slot and resolved portal_target_id
    $leftPortal = findComponentByName($payload, '__portal_left_panel');
    $rightPortal = findComponentByName($payload, '__portal_right_panel');
    $statusCenterPortal = findComponentByName($payload, '__portal_status_bar');
    $statusRightPortal = findComponentByName($payload, '__portal_status_bar_right');

    expect($leftPortal['portal_slot'] ?? null)->toBe('left_panel');
    expect($leftPortal['portal_target_id'] ?? null)->toBe($leftPanelId);
    expect($rightPortal['portal_slot'] ?? null)->toBe('right_panel');
    expect($rightPortal['portal_target_id'] ?? null)->toBe($rightPanelId);
    expect($statusCenterPortal['portal_slot'] ?? null)->toBe('status_bar_center');
    expect($statusCenterPortal['portal_target_id'] ?? null)->toBe($statusCenterSlotId);
    expect($statusRightPortal['portal_slot'] ?? null)->toBe('status_bar_right');
    expect($statusRightPortal['portal_target_id'] ?? null)->toBe($statusRightSlotId);

    // StatusBar permanent controls preserved, while built-in lbl_status_message is hidden
    expect(findComponentByName($payload, 'unit_menu'))->not->toBeNull();
    expect(findComponentByName($payload, 'btn_reset'))->not->toBeNull();
    expect($payload[$statusMessageId]['visible'] ?? true)->toBeFalse();

    // Projected components exist and auto-translated via i18n: auto
    expect(findComponentByName($payload, 'input_filter'))->not->toBeNull();
    expect(findComponentByName($payload, 'lbl_inspector_title'))->not->toBeNull();
    expect(findComponentByName($payload, 'lbl_status_counter'))->not->toBeNull();
    expect(findComponentByName($payload, 'btn_status_ping'))->not->toBeNull();
});

it('routes events from projected left_panel, right_panel, and status_bar directly to host Screen and preserves state across closeSlot / openSlot', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'Interactive Projection User']);
    $this->actingAs($user);

    $clientId = 'slot-projection-interactive-tab';
    $this->withCookie('ui_client_id', $clientId)
        ->getJson(screenApiUrl(SlotProjectionDemo::class, ['reset' => true]))
        ->assertOk();

    $splitWorkspaceId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'split_workspace');
    $rightPanelId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'right_panel_container');
    $btnApplyFilterId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'btn_apply_filter');
    $btnOpenInspectorId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'btn_open_inspector');
    $btnSaveNoteId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'btn_save_note');
    $btnCloseRightId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'btn_close_right');
    $btnStatusPingId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'btn_status_ping');

    // 1. Click button inside left_panel portal -> updates main summary AND status_bar counter on the same Screen
    $filterResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnApplyFilterId,
        'event' => 'click',
        'action' => 'apply_filter',
        'parameters' => ['input_filter' => 'invoices'],
    ]);
    $filterResp->assertOk();
    $filterPayload = $filterResp->json();

    $summaryId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'lbl_active_summary');
    $statusCounterId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'lbl_status_counter');
    expect($filterPayload[$summaryId]['text'] ?? '')->toContain('invoices');
    expect($filterPayload[$statusCounterId]['text'] ?? '')->toContain('invoices');

    // 2. Click button in status_bar_right portal -> invokes host Screen handler and updates status_bar label
    $pingResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnStatusPingId,
        'event' => 'click',
        'action' => 'ping_status',
        'parameters' => [],
    ]);
    $pingResp->assertOk();
    expect($pingResp->json()[$statusCounterId]['text'] ?? '')->toContain('1');

    // 3. Open right_panel portal from main Screen -> expands split_workspace and updates inspector labels
    $openResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnOpenInspectorId,
        'event' => 'click',
        'action' => 'open_inspector',
        'parameters' => [],
    ]);
    $openResp->assertOk();
    $openPayload = $openResp->json();
    $inspectorTitleId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'lbl_inspector_title');
    expect(array_key_exists('collapsed_panel', $openPayload[$splitWorkspaceId] ?? []))->toBeTrue();
    expect($openPayload[$splitWorkspaceId]['collapsed_panel'])->toBeNull();
    expect($openPayload[$inspectorTitleId]['text'] ?? '')->toContain('invoices');

    // 4. Click Save Note inside right_panel portal -> updates inspector & status_bar
    $noteResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnSaveNoteId,
        'event' => 'click',
        'action' => 'save_note',
        'parameters' => ['input_note' => 'Approved'],
    ]);
    $noteResp->assertOk();
    expect($noteResp->json()[$statusCounterId]['text'] ?? '')->toContain('Approved');

    // 5. Close own right_panel portal -> collapses Split WITHOUT emitting clear_container (preserves state)
    $closeResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnCloseRightId,
        'event' => 'click',
        'action' => 'close_right_panel',
        'parameters' => [],
    ]);
    $closeResp->assertOk();
    $closePayload = $closeResp->json();
    expect($closePayload[$splitWorkspaceId]['collapsed_panel'] ?? null)->toBe('second');
    expect($closePayload['clear_container'] ?? null)->not->toBe($rightPanelId);

    // 6. Re-open right_panel -> expands again without losing portal state
    $reopenResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnOpenInspectorId,
        'event' => 'click',
        'action' => 'open_inspector',
        'parameters' => [],
    ]);
    $reopenResp->assertOk();
    expect($reopenResp->json()[$splitWorkspaceId]['collapsed_panel'])->toBeNull();
});

it('coexists cleanly with SidebarSubmenuPanel in left_panel and restores Screen portal on openSlot, and cleans up all slots + sizes + lbl_status_message on navigation away', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'Coexistence User']);
    $this->actingAs($user);

    $clientId = 'slot-projection-coexistence-tab';
    $this->withCookie('ui_client_id', $clientId)
        ->getJson(screenApiUrl(SlotProjectionDemo::class, ['reset' => true]))
        ->assertOk();

    $splitShellId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'split_shell');
    $splitWorkspaceId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'split_workspace');
    $leftPanelId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'left_panel_container');
    $rightPanelId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'right_panel_container');
    $statusCenterSlotId = UIIdGenerator::generateFromName(StatusBar::class, 'status_bar_center_slot');
    $statusRightSlotId = UIIdGenerator::generateFromName(StatusBar::class, 'status_bar_right_slot');
    $statusMessageId = UIIdGenerator::generateFromName(StatusBar::class, 'lbl_status_message');
    $mainMenuId = UIIdGenerator::generateFromName(SidebarMenu::class, 'main_menu');
    $btnToggleLeftId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'btn_toggle_left');
    $demosLabel = t('screens.menu.items.demos');

    // 1. User clicks Demos in Activity Bar -> SidebarSubmenuPanel takes over left_panel and restores default split_shell size (250px)
    $demosResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $mainMenuId,
        'event' => 'click',
        'action' => 'open_menu_group',
        'parameters' => [
            'group_label' => $demosLabel,
            'left_panel_collapsed' => false,
        ],
    ]);
    $demosResp->assertOk();
    $demosPayload = $demosResp->json();
    expect(findComponentByName($demosPayload, 'submenu_tree'))->not->toBeNull();
    expect($demosPayload[$splitShellId]['split_size'] ?? null)->toBe('250px');

    // 2. Host Screen toggles/opens left_panel back -> closes first (since split_shell is open), then calling openSlot re-mounts portal!
    $toggleCloseResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnToggleLeftId,
        'event' => 'click',
        'action' => 'toggle_left_panel',
        'parameters' => [],
    ]);
    $toggleCloseResp->assertOk();
    expect($toggleCloseResp->json()[$splitShellId]['collapsed_panel'] ?? null)->toBe('first');

    $toggleOpenResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnToggleLeftId,
        'event' => 'click',
        'action' => 'toggle_left_panel',
        'parameters' => [],
    ]);
    $toggleOpenResp->assertOk();
    $reopenPayload = $toggleOpenResp->json();
    expect($reopenPayload['clear_container'] ?? null)->toBe($leftPanelId);
    expect(findComponentByName($reopenPayload, '__portal_left_panel'))->not->toBeNull();
    expect(findComponentByName($reopenPayload, 'input_filter'))->not->toBeNull();
    expect($reopenPayload[$splitShellId]['split_size'] ?? null)->toBe('270px');
    expect($reopenPayload[$splitShellId]['collapsed_panel'])->toBeNull();

    // 2b. Open right_panel too so we verify navigating away collapses BOTH left_panel and right_panel and restores both sizes
    $btnOpenInspectorId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'btn_open_inspector');
    $openRightResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnOpenInspectorId,
        'event' => 'click',
        'action' => 'open_inspector',
        'parameters' => [],
    ]);
    $openRightResp->assertOk();
    expect($openRightResp->json()[$splitWorkspaceId]['collapsed_panel'])->toBeNull();

    // 3. Navigate away to Home -> clears all projected slots, collapses splits, restores default sizes, and re-shows lbl_status_message
    $navResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $mainMenuId,
        'event' => 'click',
        'action' => 'showScreen',
        'parameters' => ['screen' => Home::class],
    ]);
    $navResp->assertOk();
    $navPayload = $navResp->json();

    $cleared = $navPayload['clear_containers'] ?? [];
    expect($cleared)->toContain($leftPanelId);
    expect($cleared)->toContain($rightPanelId);
    expect($cleared)->toContain($statusCenterSlotId);
    expect($cleared)->toContain($statusRightSlotId);
    expect($navPayload[$splitShellId]['collapsed_panel'] ?? null)->toBe('first');
    expect($navPayload[$splitShellId]['split_size'] ?? null)->toBe('250px');
    expect($navPayload[$splitWorkspaceId]['collapsed_panel'] ?? null)->toBe('second');
    expect($navPayload[$splitWorkspaceId]['split_size'] ?? null)->toBe('72%');
    expect($navPayload[$splitWorkspaceId]['split_size_target'] ?? null)->toBe('first');
    expect($navPayload[$statusMessageId]['visible'] ?? null)->toBeTrue();
});

it('supports fallback: inline_top and fallback: ignore gracefully on MainLayout (guest) and standalone ($layout = null)', function () {
    /** @var Tests\TestCase $this */
    // 1. Guest on SlotProjectionDemo uses MainLayout (no left_panel, right_panel, or status_bar)
    Auth::logout();
    expect(SlotProjectionDemo::getLayoutClass())->toBe(MainLayout::class);

    $guestResp = $this->getJson(screenApiUrl(SlotProjectionDemo::class, ['reset' => true]));
    $guestResp->assertOk();
    $guestPayload = $guestResp->json();

    // left_panel has fallback: inline_top -> portal metadata stripped and rendered inline in main
    $leftPortal = findComponentByName($guestPayload, '__portal_left_panel');
    expect($leftPortal)->not->toBeNull();
    expect($leftPortal['portal_slot'] ?? null)->toBeNull();
    expect($leftPortal['portal_unmounted'] ?? false)->toBeFalse();
    expect($leftPortal['_order'] ?? 99)->toBe(1);

    // right_panel & status_bar have fallback: ignore -> marked portal_unmounted: true without failing property injection
    $rightPortal = findComponentByName($guestPayload, '__portal_right_panel');
    $statusPortal = findComponentByName($guestPayload, '__portal_status_bar');
    expect($rightPortal['portal_unmounted'] ?? false)->toBeTrue();
    expect($statusPortal['portal_unmounted'] ?? false)->toBeTrue();

    // 2. Standalone screen ($layout = null) applies the same fallbacks and allows mutating unmounted components safely
    $standalone = TestStandaloneFallbackScreen::make();
    $collector = app(UIChangesCollector::class);
    $collector->reset();
    $standalone->render(shouldReset: true);
    $standalonePayload = $collector->all();

    $inlineTop = findComponentByName($standalonePayload, '__portal_left_panel');
    $ignored = findComponentByName($standalonePayload, '__portal_right_panel');
    expect($inlineTop['portal_slot'] ?? null)->toBeNull();
    expect($inlineTop['_order'] ?? 99)->toBe(1);
    expect($ignored['portal_unmounted'] ?? false)->toBeTrue();

    // Mutating both in an action handler succeeds without throwing
    $collector->reset();
    $standalone->callAction('mutate_ignored');
    $delta = $collector->all();
    $inlineLabelId = UIIdGenerator::generateFromName(TestStandaloneFallbackScreen::class, 'lbl_inline_top');
    $ignoredLabelId = UIIdGenerator::generateFromName(TestStandaloneFallbackScreen::class, 'lbl_ignored_panel');
    expect($delta[$inlineLabelId]['text'] ?? null)->toBe('Updated inline top');
    expect($delta[$ignoredLabelId]['text'] ?? null)->toBe('Updated while unmounted');
});

it('supports slots.main / slots.center symmetry, declarative screen: sub-screens, and include: partials inside slots', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'Declarative SubScreen User']);
    $this->actingAs($user);

    $collector = app(UIChangesCollector::class);
    $collector->reset();

    $host = TestSlotsMainAndDeclarativeScreenHost::make();
    $host->render(shouldReset: true);
    $payload = $collector->all();

    $splitWorkspaceId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'split_workspace');

    // slots.center rendered into main
    expect(findComponentByName($payload, 'lbl_center_only')['text'] ?? null)->toBe('Center Via Slots');

    // Declarative right_panel screen: RightPanelInspector auto-mounted, expanded, and sized to 380px
    expect(array_key_exists('collapsed_panel', $payload[$splitWorkspaceId] ?? []))->toBeTrue();
    expect($payload[$splitWorkspaceId]['collapsed_panel'])->toBeNull();
    expect($payload[$splitWorkspaceId]['split_size'] ?? null)->toBe('380px');
    expect($payload[$splitWorkspaceId]['split_size_target'] ?? null)->toBe('second');
    expect(findComponentByName($payload, 'lbl_inspector_title')['text'] ?? null)->toBe('Declarative Inspector');
    expect(AbstractLayout::current()?->getActiveScreen('right_panel'))->toBe(RightPanelInspector::class);

    // Partial include inside slots.left_panel
    $collector->reset();
    $partialScreen = TestIncludeSlotPartialScreen::make();
    $partialScreen->render(shouldReset: true);
    $partialPayload = $collector->all();

    $partialPortal = findComponentByName($partialPayload, '__portal_left_panel');
    expect($partialPortal['portal_slot'] ?? null)->toBe('left_panel');
    expect(findComponentByName($partialPayload, 'lbl_partial_heading')['text'] ?? null)->toBe('Included Partial Title');
});

it('projects top_bar (sticky visible toggle) and bottom_panel (vertical Split console) in WorkbenchLayout and cleans up on navigate', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'Center Slots User']);
    $this->actingAs($user);

    $clientId = 'slot-projection-center-slots-tab';
    $initialResp = $this->withCookie('ui_client_id', $clientId)
        ->getJson(screenApiUrl(SlotProjectionDemo::class, ['reset' => true]));
    $initialResp->assertOk();
    $initialPayload = $initialResp->json();

    $topBarId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'top_bar_container');
    $splitCenterId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'split_center');
    $bottomPanelId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'bottom_panel_container');

    // Initial render: top_bar expanded (visible: true), bottom_panel collapsed (open: false, size: 220px)
    expect($initialPayload[$topBarId]['visible'] ?? null)->toBeTrue();
    expect($initialPayload[$splitCenterId]['collapsed_panel'] ?? null)->toBe('second');
    expect($initialPayload[$splitCenterId]['split_size'] ?? null)->toBe('220px');
    expect($initialPayload[$splitCenterId]['split_size_target'] ?? null)->toBe('second');

    $topPortal = findComponentByName($initialPayload, '__portal_top_bar');
    $bottomPortal = findComponentByName($initialPayload, '__portal_bottom_panel');
    expect($topPortal['portal_slot'] ?? null)->toBe('top_bar');
    expect($topPortal['portal_target_id'] ?? null)->toBe($topBarId);
    expect($bottomPortal['portal_slot'] ?? null)->toBe('bottom_panel');
    expect($bottomPortal['portal_target_id'] ?? null)->toBe($bottomPanelId);

    // 1. Toggle bottom_panel open from status_bar_right shortcut button
    $btnStatusConsoleId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'btn_status_console');
    $openBottomResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnStatusConsoleId,
        'event' => 'click',
        'action' => 'toggle_bottom_panel',
        'parameters' => [],
    ]);
    $openBottomResp->assertOk();
    expect(array_key_exists('collapsed_panel', $openBottomResp->json()[$splitCenterId] ?? []))->toBeTrue();
    expect($openBottomResp->json()[$splitCenterId]['collapsed_panel'])->toBeNull();

    // 2. Run command inside bottom_panel portal -> updates console output and status bar on host Screen
    $btnRunCommandId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'btn_run_command');
    $consoleOutputId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'lbl_console_output');
    $statusCounterId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'lbl_status_counter');

    $runResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnRunCommandId,
        'event' => 'click',
        'action' => 'run_command',
        'parameters' => ['input_command' => 'status --verbose'],
    ]);
    $runResp->assertOk();
    expect($runResp->json()[$consoleOutputId]['text'] ?? '')->toContain('status --verbose');
    expect($runResp->json()[$statusCounterId]['text'] ?? '')->toContain('status --verbose');

    // 3. Close bottom_panel preserves portal DOM (no clear_container) and collapses split_center
    $btnCloseBottomId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'btn_close_bottom');
    $closeBottomResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnCloseBottomId,
        'event' => 'click',
        'action' => 'close_bottom_panel',
        'parameters' => [],
    ]);
    $closeBottomResp->assertOk();
    expect($closeBottomResp->json()[$splitCenterId]['collapsed_panel'] ?? null)->toBe('second');
    expect($closeBottomResp->json()['clear_container'] ?? null)->not->toBe($bottomPanelId);

    // 4. Close and re-open top_bar toggles visible false -> true on top_bar_container
    $btnCloseTopBarId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'btn_close_top_bar');
    $btnToggleTopBarId = UIIdGenerator::generateFromName(SlotProjectionDemo::class, 'btn_toggle_top_bar');

    $closeTopResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnCloseTopBarId,
        'event' => 'click',
        'action' => 'close_top_bar',
        'parameters' => [],
    ]);
    $closeTopResp->assertOk();
    expect($closeTopResp->json()[$topBarId]['visible'] ?? null)->toBeFalse();

    $reopenTopResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnToggleTopBarId,
        'event' => 'click',
        'action' => 'toggle_top_bar',
        'parameters' => [],
    ]);
    $reopenTopResp->assertOk();
    expect($reopenTopResp->json()[$topBarId]['visible'] ?? null)->toBeTrue();

    // 5. Re-open bottom_panel, then navigate away via WorkbenchLayout::showInto -> clears & collapses both slots
    $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $btnStatusConsoleId,
        'event' => 'click',
        'action' => 'toggle_bottom_panel',
        'parameters' => [],
    ])->assertOk();

    $mainMenuId = UIIdGenerator::generateFromName(SidebarMenu::class, 'main_menu');
    $navResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $mainMenuId,
        'event' => 'click',
        'action' => 'showScreen',
        'parameters' => ['screen' => Home::class],
    ]);
    $navResp->assertOk();
    $navPayload = $navResp->json();
    $cleared = $navPayload['clear_containers'] ?? [];

    expect($cleared)->toContain($topBarId);
    expect($cleared)->toContain($bottomPanelId);
    expect($navPayload[$topBarId]['visible'] ?? null)->toBeFalse();
    expect($navPayload[$splitCenterId]['collapsed_panel'] ?? null)->toBe('second');
    expect($navPayload[$splitCenterId]['split_size'] ?? null)->toBe('240px');
    expect($navPayload[$splitCenterId]['split_size_target'] ?? null)->toBe('second');
});
