<?php

use App\Models\User;
use App\Services\User\UsimUserService;
use App\UI\Layouts\MainLayout;
use App\UI\Layouts\WorkbenchLayout;
use App\UI\Navigation\Menus\MainMenu;
use App\UI\Screens\Admin\TranslateManager;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Demo\RightPanelDemo;
use App\UI\Screens\Demo\RightPanelInspector;
use App\UI\Screens\Device\DevicePairingScreen;
use App\UI\Screens\Home;
use App\UI\Screens\SidebarMenu;
use App\UI\Screens\StatusBar;
use Idei\Usim\Components\Container;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Navigation\MenuBuilder;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UI;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();
    AbstractLayout::setCurrent(null);
    UIStateManager::clearClientCurrentScreen();
    UIStateManager::clearClientActiveLayout();
    UIStateManager::clearClientActiveModal();
});

class TestRightPanelInspectorScreen extends Screen
{
    public static ?string $layout = null;

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $title = is_string($params['title'] ?? null) ? $params['title'] : 'Inspector Details';
        $container->add(UI::newLabel('lbl_inspector_title')->text($title));
        $container->add(
            UI::button('btn_close_inspector')
                ->label('Close')
                ->action('close_inspector')
        );
    }

    public function onCloseInspector(): void
    {
        $this->closeSlot('right_panel');
    }
}

class TestWorkbenchHostScreen extends Screen
{
    public static ?string $layout = WorkbenchLayout::class;

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(UI::newLabel('lbl_host_marker')->text('Workbench Host'));
        $container->add(
            UI::button('btn_open_inspector')
                ->label('Open Inspector')
                ->action('open_inspector')
        );
        $container->add(
            UI::button('btn_go_kiosk')
                ->label('Go Kiosk')
                ->action('go_kiosk')
        );
    }

    public function onOpenInspector(): void
    {
        $this->showInto(TestRightPanelInspectorScreen::class, slot: 'right_panel', params: ['title' => 'User #42']);
    }

    public function onGoKiosk(): void
    {
        $this->showInto(DevicePairingScreen::class);
    }
}

it('resolves layout precedence correctly for kiosk, guest, member, and admin users', function () {
    // 1. $layout = null always wins (kiosk / pairing)
    expect(DevicePairingScreen::getLayoutClass())->toBeNull();

    // 2. Guest uses guest_layout (MainLayout) for default screens
    Auth::logout();
    expect(Home::getLayoutClass())->toBe(MainLayout::class);

    // 3. Authenticated member whose home_screen is Home gets WorkbenchLayout
    /** @var Tests\TestCase $this */
    $member = User::factory()->create(['name' => 'Member User']);
    $this->actingAs($member);
    expect(Home::getLayoutClass())->toBe(WorkbenchLayout::class);
    expect(UsersManager::getLayoutClass())->toBe(WorkbenchLayout::class);

    // 4. Even when authenticated, $layout = null still wins
    expect(DevicePairingScreen::getLayoutClass())->toBeNull();

    // 5. When guest_layout is null, guests also use default_layout (WorkbenchLayout)
    Auth::logout();
    config()->set('usim.guest_layout', null);
    expect(Home::getLayoutClass())->toBe(WorkbenchLayout::class);
});

it('renders WorkbenchLayout declaratively from YAML with nested Splits, Activity Bar collapse size, 0px right panel, NavTree sidebar, and StatusBar', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'Workbench Tester']);
    $this->actingAs($user);

    $response = $this->getJson(screenApiUrl(Home::class, ['reset' => true]));
    $response->assertOk();
    $payload = $response->json();

    // Outer left-sidebar Split collapses to 52px Activity Bar
    $splitShell = findComponentByName($payload, 'split_shell');
    expect($splitShell)->not->toBeNull();
    expect($splitShell['type'] ?? null)->toBe('split');
    expect($splitShell['collapse_target'] ?? null)->toBe('first');
    expect($splitShell['collapse_size'] ?? null)->toBe('52px');

    // Inner right-panel Split starts collapsed at 0px with hidden divider
    $splitWorkspace = findComponentByName($payload, 'split_workspace');
    expect($splitWorkspace)->not->toBeNull();
    expect($splitWorkspace['type'] ?? null)->toBe('split');
    expect($splitWorkspace['collapse_target'] ?? null)->toBe('second');
    expect($splitWorkspace['collapse_size'] ?? null)->toBe('0px');
    expect($splitWorkspace['collapsed_panel'] ?? null)->toBe('second');
    expect($splitWorkspace['hide_divider_when_collapsed'] ?? null)->toBeTrue();

    // Sidebar embeds SidebarMenu with NavTree (not MenuDropdown) and VS Code footer popovers
    $navTree = findComponentByName($payload, 'main_menu');
    expect($navTree)->not->toBeNull();
    expect($navTree['type'] ?? null)->toBe('navtree');
    expect($navTree['accordion'] ?? null)->toBeTrue();

    $userMenu = findComponentByName($payload, 'user_menu');
    expect($userMenu)->not->toBeNull();
    expect($userMenu['position'] ?? null)->toBe('right-top');
    expect($userMenu['floating'] ?? null)->toBeTrue();
    expect($userMenu['trigger']['label'] ?? '')->toContain('Workbench Tester');

    $settingsMenu = findComponentByName($payload, 'settings_menu');
    expect($settingsMenu)->not->toBeNull();
    expect($settingsMenu['position'] ?? null)->toBe('right-top');
    expect($settingsMenu['floating'] ?? null)->toBeTrue();
    $langSubmenu = collect($settingsMenu['items'] ?? [])->first(
        static fn (array $item): bool => ($item['icon'] ?? null) === '🌐'
    );
    expect($langSubmenu)->not->toBeNull();
    expect($langSubmenu['submenu'] ?? [])->toBeArray();

    // StatusBar embeds unit_menu and dev-only btn_reset (testing env != production), without lang_menu
    expect(findComponentByName($payload, 'lang_menu'))->toBeNull();

    $resetBtn = findComponentByName($payload, 'btn_reset');
    expect($resetBtn)->not->toBeNull();
    expect($resetBtn['action'] ?? null)->toBe('reset_app');
});

it('omits reset button in StatusBar when application environment is production', function () {
    /** @var Tests\TestCase $this */
    $originalEnv = $this->app['env'];
    $this->app['env'] = 'production';

    try {
        $ui = uiScenario($this, StatusBar::class, ['reset' => true]);
        expect(fn () => $ui->component('btn_reset'))->toThrow(RuntimeException::class);
    } finally {
        $this->app['env'] = $originalEnv;
    }
});

it('transitions layouts automatically on SPA login (MainLayout -> WorkbenchLayout) and logout (WorkbenchLayout -> MainLayout)', function () {
    /** @var Tests\TestCase $this */
    Auth::logout();

    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main']);
    $admin = User::factory()->create([
        'name' => 'Transition Admin',
        'email' => 'transition.admin.'.uniqid().'@example.com',
        'password' => bcrypt('secret1234'),
    ]);
    $admin->usimUnits()->sync([$mainUnit->id]);
    setPermissionsTeamId($mainUnit->id);
    $admin->assignRole('admin');
    setPermissionsTeamId(null);

    // 1. Guest visits Home -> mounts MainLayout
    $guestResp = $this->getJson(screenApiUrl(Home::class, ['reset' => true]));
    $guestResp->assertOk();
    expect(findComponentByName($guestResp->json(), 'main_menu_container'))->not->toBeNull();
    expect(UIStateManager::getClientActiveLayout())->toBe(MainLayout::class);

    // 2. Open Login modal from Menu
    $userMenuId = UIIdGenerator::generateFromName(\App\UI\Screens\Menu::class, 'user_menu');
    $openLoginResp = $this->postJson('/api/ui-event', [
        'component_id' => $userMenuId,
        'event' => 'click',
        'action' => 'showScreen',
        'parameters' => ['screen' => Login::class, 'modal' => true],
    ]);
    $openLoginResp->assertOk();
    $submitBtn = findComponentByName($openLoginResp->json(), 'btn_submit_login');
    expect($submitBtn)->not->toBeNull();

    // 3. Submit login -> must automatically switch #main from MainLayout to WorkbenchLayout
    $loginResp = $this->postJson('/api/ui-event', [
        'component_id' => (int) $submitBtn['_json_key'],
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => $admin->email,
            'login_password' => 'secret1234',
        ],
    ]);
    $loginResp->assertOk();
    $loginPayload = $loginResp->json();

    expect($loginPayload['action'] ?? null)->toBe('close_modal');
    expect($loginPayload['clear_container'] ?? null)->toBe('main');
    expect($loginPayload['navigate']['url'] ?? null)->toBe(UsersManager::getRoutePath());
    expect(UIStateManager::getClientActiveLayout())->toBe(WorkbenchLayout::class);
    expect(findComponentByName($loginPayload, 'split_shell'))->not->toBeNull();
    expect(findComponentByName($loginPayload, 'main_menu')['type'] ?? null)->toBe('navtree');

    // 4. Logout from SidebarMenu -> must automatically switch #main back to MainLayout + Home
    $sidebarUserMenuId = UIIdGenerator::generateFromName(SidebarMenu::class, 'user_menu');
    $logoutResp = $this->postJson('/api/ui-event', [
        'component_id' => $sidebarUserMenuId,
        'event' => 'click',
        'action' => 'confirm_logout',
        'parameters' => [],
    ]);
    $logoutResp->assertOk();
    $logoutPayload = $logoutResp->json();

    expect(Auth::check())->toBeFalse();
    expect($logoutPayload['clear_container'] ?? null)->toBe('main');
    expect($logoutPayload['navigate']['url'] ?? null)->toBe(Home::getRoutePath());
    expect(UIStateManager::getClientActiveLayout())->toBe(MainLayout::class);
    expect(findComponentByName($logoutPayload, 'main_menu_container'))->not->toBeNull();
});

it('expands right_panel on showInto, collapses back to 0px on closeSlot, and supports transitioning to standalone screen', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'Panel Tester']);
    $this->actingAs($user);

    // 1. Render WorkbenchHostScreen
    $host = TestWorkbenchHostScreen::make();
    $host->render(shouldReset: true);
    UIStateManager::setClientActiveLayout(WorkbenchLayout::class);
    UIStateManager::setClientCurrentScreen('/test-workbench-host', TestWorkbenchHostScreen::class);

    $splitWorkspaceId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'split_workspace');
    $rightPanelId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'right_panel_container');

    // 2. Open inspector into right_panel
    $collector = app(\Idei\Usim\UIChangesCollector::class);
    $collector->reset();
    $host->callAction('open_inspector');
    $openChanges = $collector->all();

    expect(array_key_exists('collapsed_panel', $openChanges[$splitWorkspaceId] ?? []))->toBeTrue();
    expect($openChanges[$splitWorkspaceId]['collapsed_panel'])->toBeNull();
    expect(findComponentByName($openChanges, 'lbl_inspector_title')['text'] ?? null)->toBe('User #42');

    // 3. Close inspector from within right_panel screen -> clears slot and collapses back to 'second' (0px)
    $collector->reset();
    $inspector = TestRightPanelInspectorScreen::make();
    $inspector->callAction('close_inspector');
    $closeChanges = $collector->all();

    expect($closeChanges['clear_container'] ?? null)->toBe($rightPanelId);
    expect($closeChanges[$splitWorkspaceId]['collapsed_panel'] ?? null)->toBe('second');

    // 4. Transition from WorkbenchLayout to standalone DevicePairingScreen ($layout = null)
    $collector->reset();
    $host->callAction('go_kiosk');
    $kioskChanges = $collector->all();

    expect($kioskChanges['clear_container'] ?? null)->toBe('main');
    expect(UIStateManager::getClientActiveLayout())->toBeNull();
    expect(findComponentByName($kioskChanges, 'lbl_pin_display'))->not->toBeNull();
    expect(findComponentByName($kioskChanges, 'split_shell'))->toBeNull();
});

it('switches operational unit from StatusBar, navigates to role home screen, and updates status bar message via UsimEvent', function () {
    /** @var Tests\TestCase $this */
    $userService = app(UsimUserService::class);
    $oafaUnit = UsimUnit::firstOrCreate(['slug' => 'oafa'], ['type' => 'institute']);
    $ideiUnit = UsimUnit::firstOrCreate(['slug' => 'idei'], ['type' => 'institute']);
    $user = $userService->provisionFromConfig('admin'); // oafa => admin, idei => translator
    $this->actingAs($user);

    setPermissionsTeamId($oafaUnit->id);
    UIStateManager::setActiveUnit('oafa');

    $ui = uiScenario($this, StatusBar::class, ['reset' => true]);
    $unitData = $ui->component('unit_menu')->data();
    expect($unitData['visible'] ?? true)->toBeTrue();
    expect($unitData['trigger']['label'] ?? '')->toContain('🏢');

    // Switch unit to idei (translator role -> TranslateManager)
    $resp = $ui->action('unit_menu', 'changeUnit', ['unit' => 'idei', 'unit_id' => $ideiUnit->id]);
    $resp->assertOk();
    expect($resp->json('navigate.url'))->toBe(TranslateManager::getRoutePath());
    expect(UIStateManager::getActiveUnit())->toBe('idei');

    // Update status bar message via UsimEvent
    $collector = app(\Idei\Usim\UIChangesCollector::class);
    $collector->reset();
    event(new UsimEvent('status_bar_message', ['text' => 'Sync completed']));
    $msgId = UIIdGenerator::generateFromName(StatusBar::class, 'lbl_status_message');
    expect($collector->all()[$msgId]['text'] ?? null)->toBe('Sync completed');
});

it('renders MainMenu into NavTree via MenuBuilder::renderNavTree with submenus preserved', function () {
    $nav = MenuBuilder::make('sidebar_nav')
        ->provider(MainMenu::class)
        ->renderNavTree('sidebar_nav');

    $json = $nav->toJson();
    $payload = array_values($json)[0];

    expect($payload['type'] ?? null)->toBe('navtree');
    expect($payload['items'] ?? [])->not->toBeEmpty();

    $demosGroup = collect($payload['items'])->first(
        static fn (array $item): bool => isset($item['submenu']) && is_array($item['submenu']) && count($item['submenu']) > 0
    );
    expect($demosGroup)->not->toBeNull();
    expect(count($demosGroup['submenu']))->toBeGreaterThanOrEqual(10);
});

it('runs RightPanelDemo end-to-end opening RightPanelInspector, updating StatusBar, and closing back to 0px', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'Demo User']);
    $this->actingAs($user);

    $ui = uiScenario($this, RightPanelDemo::class, ['reset' => true]);
    $splitWorkspaceId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'split_workspace');
    $rightPanelId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'right_panel_container');

    // Open API Gateway in right_panel
    $inspectResp = $ui->click('btn_inspect_gateway', ['resource_id' => 'gateway']);
    $inspectResp->assertOk();
    $inspectPayload = $inspectResp->json();

    expect(array_key_exists('collapsed_panel', $inspectPayload[$splitWorkspaceId] ?? []))->toBeTrue();
    expect($inspectPayload[$splitWorkspaceId]['collapsed_panel'])->toBeNull();
    expect(findComponentByName($inspectPayload, 'lbl_inspector_title')['text'] ?? null)->toBe('API Gateway');

    // Close from inside RightPanelInspector -> clears right_panel and collapses split_workspace back to second (0px)
    $closeBtnId = UIIdGenerator::generateFromName(RightPanelInspector::class, 'btn_inspector_close');
    $closeResp = $this->postJson('/api/ui-event', [
        'component_id' => $closeBtnId,
        'event' => 'click',
        'action' => 'close_inspector',
        'parameters' => [],
    ]);
    $closeResp->assertOk();
    $closePayload = $closeResp->json();

    expect($closePayload['clear_container'] ?? null)->toBe($rightPanelId);
    expect($closePayload[$splitWorkspaceId]['collapsed_panel'] ?? null)->toBe('second');
});
