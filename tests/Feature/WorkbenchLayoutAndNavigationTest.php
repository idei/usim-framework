<?php

use App\Models\User;
use App\Services\User\UsimUserService;
use App\UI\Navigation\Menus\MainMenu;
use App\UI\Screens\Admin\TranslateManager;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Demo\RightPanelDemo;
use App\UI\Screens\Demo\RightPanelInspector;
use App\UI\Screens\Device\DevicePairingScreen;
use App\UI\Screens\Home;
use Idei\Usim\Layout\MainLayout;
use Idei\Usim\Layout\WorkbenchLayout;
use Idei\Usim\Screens\SidebarMenu;
use Idei\Usim\Screens\SidebarSubmenuPanel;
use Idei\Usim\Screens\StatusBar;
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

class TestContextualLeftPanelScreen extends Screen
{
    public static ?string $layout = null;

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $title = is_string($params['title'] ?? null) ? $params['title'] : 'Context Explorer';
        $container->add(UI::newLabel('lbl_left_context_title')->text($title));
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
            UI::button('btn_open_left_context')
                ->label('Open Left Context')
                ->action('open_left_context')
        );
        $container->add(
            UI::button('btn_go_home')
                ->label('Go Home')
                ->action('go_home')
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

    public function onOpenLeftContext(): void
    {
        $this->showInto(TestContextualLeftPanelScreen::class, slot: 'left_panel', params: ['title' => 'Project Files']);
    }

    public function onGoHome(): void
    {
        $this->showInto(Home::class);
    }

    public function onGoRightPanelDemo(): void
    {
        $this->showInto(RightPanelDemo::class);
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

it('renders WorkbenchLayout declaratively from YAML with fixed 52px Activity Bar, separate 0px left_panel Split, 0px right_panel Split, NavTree, and StatusBar', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'Workbench Tester']);
    $this->actingAs($user);

    $response = $this->getJson(screenApiUrl(Home::class, ['reset' => true]));
    $response->assertOk();
    $payload = $response->json();

    // Fixed Activity Bar container sits outside split_shell (sibling of split_shell, not its child)
    $sidebarContainer = findComponentByName($payload, 'sidebar_container');
    $splitShell = findComponentByName($payload, 'split_shell');
    expect($sidebarContainer)->not->toBeNull();
    expect($splitShell)->not->toBeNull();
    expect($sidebarContainer['parent'] ?? null)->toBe($splitShell['parent'] ?? null);
    expect($sidebarContainer['parent'] ?? null)->not->toBe((int) $splitShell['_json_key']);
    expect($sidebarContainer['width'] ?? null)->toBe('52px');

    // Left panel Split starts collapsed at 0px with hidden divider (independent from Activity Bar)
    expect($splitShell['type'] ?? null)->toBe('split');
    expect($splitShell['collapse_target'] ?? null)->toBe('first');
    expect($splitShell['collapse_size'] ?? null)->toBe('0px');
    expect($splitShell['collapsed_panel'] ?? null)->toBe('first');
    expect($splitShell['hide_divider_when_collapsed'] ?? null)->toBeTrue();

    // Inner right-panel Split starts collapsed at 0px with hidden divider
    $splitWorkspace = findComponentByName($payload, 'split_workspace');
    expect($splitWorkspace)->not->toBeNull();
    expect($splitWorkspace['type'] ?? null)->toBe('split');
    expect($splitWorkspace['collapse_target'] ?? null)->toBe('second');
    expect($splitWorkspace['collapse_size'] ?? null)->toBe('0px');
    expect($splitWorkspace['collapsed_panel'] ?? null)->toBe('second');
    expect($splitWorkspace['hide_divider_when_collapsed'] ?? null)->toBeTrue();

    // Center sticky top_bar starts hidden (visible: false -> 0px) until a Screen projects into it
    $topBar = findComponentByName($payload, 'top_bar_container');
    expect($topBar)->not->toBeNull();
    expect($topBar['visible'] ?? true)->toBeFalse();

    // Center vertical Split (main + bottom_panel) starts collapsed at 0px with hidden divider
    $splitCenter = findComponentByName($payload, 'split_center');
    expect($splitCenter)->not->toBeNull();
    expect($splitCenter['type'] ?? null)->toBe('split');
    expect($splitCenter['split_orientation'] ?? null)->toBe('vertical');
    expect($splitCenter['split_size'] ?? null)->toBe('240px');
    expect($splitCenter['split_size_target'] ?? null)->toBe('second');
    expect($splitCenter['collapse_target'] ?? null)->toBe('second');
    expect($splitCenter['collapse_size'] ?? null)->toBe('0px');
    expect($splitCenter['collapsed_panel'] ?? null)->toBe('second');
    expect($splitCenter['hide_divider_when_collapsed'] ?? null)->toBeTrue();
    expect(findComponentByName($payload, 'bottom_panel_container'))->not->toBeNull();

    // Activity Bar embeds SidebarMenu with compact NavTree delegating submenu group clicks to left_panel
    $navTree = findComponentByName($payload, 'main_menu');
    expect($navTree)->not->toBeNull();
    expect($navTree['type'] ?? null)->toBe('navtree');
    expect($navTree['compact'] ?? null)->toBeTrue();
    expect($navTree['on_group_click'] ?? null)->toBe('open_menu_group');

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
        static fn (array $item): bool => ($item['icon'] ?? null) === 'sliders'
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
    $user = User::factory()->create(['name' => 'Prod StatusBar User']);
    $this->actingAs($user);

    $originalEnv = $this->app['env'];
    $this->app['env'] = 'production';

    try {
        $response = $this->getJson(screenApiUrl(Home::class, ['reset' => true]));
        $response->assertOk();
        expect(findComponentByName($response->json(), 'btn_reset'))->toBeNull();
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
    $authMainMenu = findComponentByName($loginPayload, 'main_menu');
    expect($authMainMenu['type'] ?? null)->toBe('navtree');
    $authDemos = collect($authMainMenu['items'] ?? [])->first(
        static fn (array $item): bool => isset($item['submenu']) && is_array($item['submenu']) && ($item['icon'] ?? null) === '🎮'
    );
    $authDemoScreens = collect($authDemos['submenu'] ?? [])->pluck('params.screen')->filter()->all();
    expect($authDemoScreens)->toContain(RightPanelDemo::class);
    expect($authDemoScreens)->toContain(\App\UI\Screens\Demo\SlotProjectionDemo::class);

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

    // Unit selector must be hidden & cleared in Menu after logout (guest mode)
    $guestUnitMenu = findComponentByName($logoutPayload, 'unit_menu');
    expect($guestUnitMenu)->not->toBeNull();
    expect($guestUnitMenu['visible'] ?? true)->toBeFalse();
    expect($guestUnitMenu['items'] ?? [])->toBeEmpty();

    // Panel-dependent demos (RightPanelDemo, SlotProjectionDemo) must be hidden in guest Demos menu
    $guestMainMenu = findComponentByName($logoutPayload, 'main_menu');
    $guestDemos = collect($guestMainMenu['items'] ?? [])->first(
        static fn (array $item): bool => isset($item['submenu']) && is_array($item['submenu'])
    );
    $guestDemoScreens = collect($guestDemos['submenu'] ?? [])->pluck('params.screen')->filter()->all();
    expect($guestDemoScreens)->not->toContain(RightPanelDemo::class);
    expect($guestDemoScreens)->not->toContain(\App\UI\Screens\Demo\SlotProjectionDemo::class);
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

    $clientId = 'statusbar-unit-switch-'.uniqid();
    $homeResp = $this->withCookie('ui_client_id', $clientId)
        ->getJson(screenApiUrl(Home::class, ['reset' => true]));
    $homeResp->assertOk();

    $unitData = findComponentByName($homeResp->json(), 'unit_menu');
    expect($unitData)->toBeArray();
    expect($unitData['visible'] ?? true)->toBeTrue();
    expect($unitData['trigger']['icon'] ?? null)->toBe('layout-grid');

    // Switch unit to idei (translator role -> TranslateManager)
    $unitMenuId = UIIdGenerator::generateFromName(StatusBar::class, 'unit_menu');
    $resp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $unitMenuId,
        'event' => 'click',
        'action' => 'changeUnit',
        'parameters' => ['unit' => 'idei', 'unit_id' => $ideiUnit->id],
    ]);
    $resp->assertOk();
    expect($resp->json('navigate.url'))->toBe(TranslateManager::getRoutePath());
    expect(UIStateManager::getActiveUnit())->toBe('idei');

    // Regression: StatusBar::onUnitChanged() runs in the same request via UsimEvent('unit_changed')
    // and must NOT clobber unit_menu's updated trigger & items delta in UIChangesCollector.
    $unitMenuDelta = $resp->json((string) $unitMenuId);
    expect($unitMenuDelta)->toBeArray();
    expect($unitMenuDelta['trigger']['label'] ?? '')->toContain($ideiUnit->display_name);
    $checkedItems = collect($unitMenuDelta['items'] ?? [])
        ->filter(static fn (array $item): bool => str_starts_with((string) ($item['label'] ?? ''), '✓ '))
        ->pluck('params.unit')
        ->values()
        ->all();
    expect($checkedItems)->toBe(['idei']);

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

it('opens Demos submenu in left_panel Split from Activity Bar, persists across demo navigation, toggles closed, and yields cleanly to screen-owned contextual left panels', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'Activity Bar Tester']);
    $this->actingAs($user);

    $host = TestWorkbenchHostScreen::make();
    $host->render(shouldReset: true);
    UIStateManager::setClientActiveLayout(WorkbenchLayout::class);
    UIStateManager::setClientCurrentScreen('/test-workbench-host', TestWorkbenchHostScreen::class);

    $splitShellId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'split_shell');
    $leftPanelId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'left_panel_container');
    $demosLabel = t('screens.menu.items.demos');
    $collector = app(\Idei\Usim\UIChangesCollector::class);

    // 1. Clicking Demos group in Activity Bar opens SidebarSubmenuPanel in left_panel and expands split_shell
    $collector->reset();
    $sidebar = SidebarMenu::make();
    $sidebar->callAction('open_menu_group', [
        'group_label' => $demosLabel,
        'left_panel_collapsed' => true,
    ]);
    $openPayload = $collector->all();

    expect(array_key_exists('collapsed_panel', $openPayload[$splitShellId] ?? []))->toBeTrue();
    expect($openPayload[$splitShellId]['collapsed_panel'])->toBeNull();
    expect(findComponentByName($openPayload, 'lbl_panel_title')['text'] ?? null)->toBe($demosLabel);
    $submenuTree = findComponentByName($openPayload, 'submenu_tree');
    expect($submenuTree)->not->toBeNull();
    expect(count($submenuTree['items'] ?? []))->toBeGreaterThanOrEqual(10);

    // 2. Navigating main slot keeps SidebarSubmenuPanel open ($persistOnNavigate = true)
    $collector->reset();
    $host->callAction('go_home');
    $navChanges = $collector->all();
    expect($navChanges[$splitShellId]['collapsed_panel'] ?? null)->toBeNull();
    expect(AbstractLayout::current()?->getActiveScreen('left_panel'))->toBe(SidebarSubmenuPanel::class);

    // 3. A screen claiming left_panel replaces SidebarSubmenuPanel with its own contextual panel
    $collector->reset();
    $host->callAction('open_left_context');
    $ctxChanges = $collector->all();
    expect(findComponentByName($ctxChanges, 'lbl_left_context_title')['text'] ?? null)->toBe('Project Files');
    expect(AbstractLayout::current()?->getActiveScreen('left_panel'))->toBe(TestContextualLeftPanelScreen::class);

    // 4. Clicking Demos while a custom screen occupies left_panel switches left_panel back to Demos
    $collector->reset();
    $sidebar = SidebarMenu::make();
    $sidebar->callAction('open_menu_group', [
        'group_label' => $demosLabel,
        'left_panel_collapsed' => false,
    ]);
    $switchBackPayload = $collector->all();
    expect(findComponentByName($switchBackPayload, 'lbl_panel_title')['text'] ?? null)->toBe($demosLabel);
    expect(AbstractLayout::current()?->getActiveScreen('left_panel'))->toBe(SidebarSubmenuPanel::class);

    // 5. Clicking Demos again while already open toggles left_panel closed back to 0px
    $collector->reset();
    $sidebar = SidebarMenu::make();
    $sidebar->callAction('open_menu_group', [
        'group_label' => $demosLabel,
        'left_panel_collapsed' => false,
    ]);
    $togglePayload = $collector->all();
    expect($togglePayload['clear_container'] ?? null)->toBe($leftPanelId);
    expect($togglePayload[$splitShellId]['collapsed_panel'] ?? null)->toBe('first');

    // 6. Opening a screen-owned contextual left panel and navigating away auto-closes and collapses it ($persistOnNavigate = false)
    $collector->reset();
    $host->callAction('open_left_context');
    expect(AbstractLayout::current()?->getActiveScreen('left_panel'))->toBe(TestContextualLeftPanelScreen::class);

    $collector->reset();
    $host->callAction('go_right_panel_demo');
    $afterCtxNav = $collector->all();
    expect($afterCtxNav[$splitShellId]['collapsed_panel'] ?? null)->toBe('first');
    expect(AbstractLayout::current()?->getActiveScreen('left_panel'))->toBeNull();
});

it('navigates from SidebarSubmenuPanel submenu_tree and closes via HTTP /api/ui-event without 404', function () {
    /** @var Tests\TestCase $this */
    $user = User::factory()->create(['name' => 'HTTP Submenu Tester']);
    $this->actingAs($user);

    $clientId = 'workbench-submenu-http-tab';
    $this->withCookie('ui_client_id', $clientId)
        ->getJson(screenApiUrl(Home::class, ['reset' => true]))
        ->assertOk();

    $splitShellId = UIIdGenerator::generateFromName(WorkbenchLayout::class, 'split_shell');
    $mainMenuId = UIIdGenerator::generateFromName(SidebarMenu::class, 'main_menu');
    $submenuTreeId = UIIdGenerator::generateFromName(SidebarSubmenuPanel::class, 'submenu_tree');
    $closeBtnId = UIIdGenerator::generateFromName(SidebarSubmenuPanel::class, 'btn_close_left_panel');
    $demosLabel = t('screens.menu.items.demos');

    // 1. Open Demos in left_panel via HTTP /api/ui-event
    $openResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $mainMenuId,
        'event' => 'click',
        'action' => 'open_menu_group',
        'parameters' => [
            'group_label' => $demosLabel,
            'left_panel_collapsed' => true,
        ],
    ]);
    $openResp->assertOk();
    $openPayload = $openResp->json();
    expect(array_key_exists('collapsed_panel', $openPayload[$splitShellId] ?? []))->toBeTrue();
    expect($openPayload[$splitShellId]['collapsed_panel'])->toBeNull();
    expect(findComponentByName($openPayload, 'submenu_tree')['_caller_service_id'] ?? null)->toBeNull();

    // 2. Click a demo item inside SidebarSubmenuPanel's submenu_tree -> must resolve SidebarSubmenuPanel and navigate!
    $navResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $submenuTreeId,
        'event' => 'click',
        'action' => 'showScreen',
        'parameters' => [
            'screen' => RightPanelDemo::class,
        ],
    ]);
    $navResp->assertOk();
    expect($navResp->json('navigate.url'))->toBe(RightPanelDemo::getRoutePath());

    // 3. Click close button inside SidebarSubmenuPanel -> must collapse left_panel back to 0px and clear active_group
    $closeResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $closeBtnId,
        'event' => 'click',
        'action' => 'close_left_panel',
        'parameters' => [],
    ]);
    $closeResp->assertOk();
    $closePayload = $closeResp->json();
    expect($closePayload[$splitShellId]['collapsed_panel'] ?? null)->toBe('first');
    expect(array_key_exists('active_group', $closePayload[$mainMenuId] ?? []))->toBeTrue();
    expect($closePayload[$mainMenuId]['active_group'])->toBeNull();
});

it('preserves multi-level sub-sub-menus recursively in NavTree::setItems()', function () {
    $nav = UI::navTree('nested_panel_nav')->setItems([
        [
            'label' => 'Forms',
            'icon' => '📝',
            'submenu' => [
                [
                    'label' => 'Advanced Inputs',
                    'icon' => '⌨️',
                    'submenu' => [
                        ['label' => 'Masked Input', 'action' => 'showScreen', 'params' => []],
                    ],
                ],
            ],
        ],
    ]);

    $payload = array_values($nav->toJson())[0];
    expect($payload['items'][0]['label'] ?? null)->toBe('Forms');
    expect($payload['items'][0]['submenu'][0]['label'] ?? null)->toBe('Advanced Inputs');
    expect($payload['items'][0]['submenu'][0]['submenu'][0]['label'] ?? null)->toBe('Masked Input');
});

it('updates active_screen and layout main slot when loading Home directly after UsersManager so clicking UsersManager works again', function () {
    /** @var Tests\TestCase $this */
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main']);
    $admin = User::factory()->create([
        'name' => 'Home Nav Admin',
        'email' => 'home.nav.admin.'.uniqid().'@example.com',
    ]);
    $admin->usimUnits()->sync([$mainUnit->id]);
    setPermissionsTeamId($mainUnit->id);
    $admin->assignRole('admin');
    setPermissionsTeamId(null);
    $this->actingAs($admin);

    $clientId = 'workbench-home-after-users-tab';

    // 1. Load UsersManager first (populates cached WorkbenchLayout with state_active_screens['main'] = UsersManager)
    $usersResp = $this->withCookie('ui_client_id', $clientId)
        ->getJson(screenApiUrl(UsersManager::class, ['reset' => true]));
    $usersResp->assertOk();
    expect(findComponentByName($usersResp->json(), 'main_menu')['active_screen'] ?? null)->toBe(UsersManager::class);

    // 2. Full page load of Home ('/') -> SidebarMenu must report Home (not stale UsersManager), and layout active main screen must be Home
    $homeResp = $this->withCookie('ui_client_id', $clientId)
        ->getJson(screenApiUrl(Home::class));
    $homeResp->assertOk();
    expect(findComponentByName($homeResp->json(), 'main_menu')['active_screen'] ?? null)->toBe(Home::class);
    expect(AbstractLayout::current()?->getActiveScreen('main'))->toBe(Home::class);

    // 3. Clicking UsersManager in Activity Bar must actually navigate back to UsersManager (not no-op as "already active")
    $mainMenuId = UIIdGenerator::generateFromName(SidebarMenu::class, 'main_menu');
    $backToUsersResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $mainMenuId,
        'event' => 'click',
        'action' => 'showScreen',
        'parameters' => ['screen' => UsersManager::class],
    ]);
    $backToUsersResp->assertOk();
    expect($backToUsersResp->json('navigate.url'))->toBe(UsersManager::getRoutePath());

    // 4. MainMenu Home item uses SPA screenShow(Home::class) with SVG 'home' icon and navigates cleanly between UsersManager and Home
    $mainMenuItems = findComponentByName($homeResp->json(), 'main_menu')['items'] ?? [];
    expect($mainMenuItems[0]['action'] ?? null)->toBe('showScreen');
    expect($mainMenuItems[0]['params']['screen'] ?? null)->toBe(Home::class);
    expect($mainMenuItems[0]['icon'] ?? null)->toBe('home');

    $spaHomeResp = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
        'component_id' => $mainMenuId,
        'event' => 'click',
        'action' => 'showScreen',
        'parameters' => ['screen' => Home::class],
    ]);
    $spaHomeResp->assertOk();
    expect($spaHomeResp->json('navigate.url'))->toBe(Home::getRoutePath());
});

class TestAliasWorkbenchScreen extends Screen
{
    public static ?string $layout = 'workbench';

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(UI::newLabel('lbl_alias_workbench')->text('Workbench Alias'));
    }
}

class TestAliasMainScreen extends Screen
{
    public static ?string $layout = 'main';

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(UI::newLabel('lbl_alias_main')->text('Main Alias'));
    }
}

class TestAliasNoneScreen extends Screen
{
    public static ?string $layout = 'none';

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(UI::newLabel('lbl_alias_none')->text('None Alias'));
    }
}

class TestInheritedPackageWorkbenchLayout extends \Idei\Usim\Layout\WorkbenchLayout {}

it('ships built-in package WorkbenchLayout and MainLayout usable directly and inherited without sibling YAML', function () {
    $packageWorkbench = app(\Idei\Usim\Layout\WorkbenchLayout::class);
    $packageWorkbench->render();

    expect($packageWorkbench->getSlot('sidebar'))->not->toBeNull();
    expect($packageWorkbench->getSlot('left_panel'))->not->toBeNull();
    expect($packageWorkbench->getSlot('top_bar'))->not->toBeNull();
    expect($packageWorkbench->getSlot('main'))->not->toBeNull();
    expect($packageWorkbench->getSlot('bottom_panel'))->not->toBeNull();
    expect($packageWorkbench->getSlot('right_panel'))->not->toBeNull();
    expect($packageWorkbench->getSlot('status_bar'))->not->toBeNull();

    // A concrete subclass without its own sibling .layout.yaml inherits the package YAML definition
    expect(\Idei\Usim\Support\ContainerLayoutLoader::hasDefaultLayout(TestInheritedPackageWorkbenchLayout::class))->toBeTrue();
    $inheritedLayout = app(TestInheritedPackageWorkbenchLayout::class);
    $inheritedLayout->render();
    expect($inheritedLayout->getSlot('right_panel'))->not->toBeNull();
    expect($inheritedLayout->getSlot('bottom_panel'))->not->toBeNull();

    $packageMain = app(\Idei\Usim\Layout\MainLayout::class);
    $packageMain->render();
    expect($packageMain->getSlot('top_menu'))->not->toBeNull();
    expect($packageMain->getSlot('main'))->not->toBeNull();
});

it('resolves short layout aliases and app layout class names to built-in package layouts when no app override exists', function () {
    // Lab app consumes built-in package layouts directly (no App\UI\Layouts\* overrides)
    expect(TestAliasWorkbenchScreen::getLayoutClass())->toBe(\Idei\Usim\Layout\WorkbenchLayout::class);
    expect(TestAliasMainScreen::getLayoutClass())->toBe(\Idei\Usim\Layout\MainLayout::class);
    expect(TestAliasNoneScreen::getLayoutClass())->toBeNull();

    // Resilient fallback when config or a Screen references App\UI\Layouts\* or custom namespace layouts before publishing
    config()->set('usim.screens_namespace', 'CustomApp\\UI\\Screens');
    app()->forgetInstance(\Idei\Usim\Support\UsimConfig::class);

    try {
        expect(Screen::resolveLayoutClass('workbench'))->toBe(\Idei\Usim\Layout\WorkbenchLayout::class);
        expect(Screen::resolveLayoutClass('main'))->toBe(\Idei\Usim\Layout\MainLayout::class);
        expect(Screen::resolveLayoutClass('App\\UI\\Layouts\\WorkbenchLayout'))->toBe(\Idei\Usim\Layout\WorkbenchLayout::class);
        expect(Screen::resolveLayoutClass('App\\UI\\Layouts\\MainLayout'))->toBe(\Idei\Usim\Layout\MainLayout::class);
        expect(Screen::resolveLayoutClass('CustomApp\\UI\\Layouts\\WorkbenchLayout'))->toBe(\Idei\Usim\Layout\WorkbenchLayout::class);
        expect(Screen::resolveLayoutClass('CustomApp\\UI\\Layouts\\MainLayout'))->toBe(\Idei\Usim\Layout\MainLayout::class);
        expect(Screen::resolveLayoutClass('none'))->toBeNull();
    } finally {
        config()->set('usim.screens_namespace', 'App\\UI\\Screens');
        app()->forgetInstance(\Idei\Usim\Support\UsimConfig::class);
    }
});

it('scaffolds WorkbenchLayout, MainLayout, MainMenu, and Workbench shell screens via InstallAppScaffoldingManager', function () {
    $tempRoot = sys_get_temp_dir().'/usim_install_workbench_test_'.uniqid();
    $files = app(\Illuminate\Filesystem\Filesystem::class);
    $files->makeDirectory($tempRoot, 0755, true);

    try {
        $resolver = app(\Idei\Usim\Console\Commands\Support\InstallContextResolver::class);
        $publisher = app(\Idei\Usim\Console\Commands\Support\InstallStubPublisher::class);
        $manager = app(\Idei\Usim\Console\Commands\Support\InstallAppScaffoldingManager::class);

        $context = $resolver->buildScaffoldingContext(
            stubsBasePath: $resolver->stubsPath(),
            namespaces: [
                'screensNamespace' => 'SampleApp\\UI\\Screens',
                'screensPath' => $tempRoot.'/UI/Screens',
                'componentsNamespace' => 'SampleApp\\UI\\Components',
                'componentsPath' => $tempRoot.'/UI/Components',
                'layoutsNamespace' => 'SampleApp\\UI\\Layouts',
                'layoutsPath' => $tempRoot.'/UI/Layouts',
                'navigationNamespace' => 'SampleApp\\UI\\Navigation',
                'navigationPath' => $tempRoot.'/UI/Navigation',
            ],
            userModelImport: 'App\\Models\\User',
            userModelClass: 'User',
            force: true,
        );

        $manager->installCoreScreens(
            context: $context,
            publishStub: static function (string $stubPath, string $targetPath, bool $autoForce, array $replacements) use ($publisher): void {
                $publisher->publish($stubPath, $targetPath, true, $autoForce, $replacements);
            },
            line: static function (string $message): void {},
            installTranslationManagerScaffolding: static function (): void {},
            installActorsManagerScaffolding: static function (): void {},
        );

        $expectedFiles = [
            $tempRoot.'/UI/Layouts/MainLayout.php',
            $tempRoot.'/UI/Layouts/WorkbenchLayout.php',
            $tempRoot.'/UI/Layouts/workbench-layout.layout.yaml',
            $tempRoot.'/UI/Navigation/Menus/MainMenu.php',
            $tempRoot.'/UI/Screens/Concerns/HandlesShellActions.php',
            $tempRoot.'/UI/Screens/Home.php',
            $tempRoot.'/UI/Screens/Menu.php',
            $tempRoot.'/UI/Screens/SidebarMenu.php',
            $tempRoot.'/UI/Screens/SidebarSubmenuPanel.php',
            $tempRoot.'/UI/Screens/StatusBar.php',
            $tempRoot.'/UI/Screens/Registered.php',
        ];

        foreach ($expectedFiles as $path) {
            expect(is_file($path))->toBeTrue("Expected scaffolded file [{$path}] to exist.");
            $content = (string) file_get_contents($path);
            expect($content)->not->toContain('{{ ');

            if (str_ends_with($path, '.php')) {
                $output = [];
                $exitCode = 0;
                exec('php -l '.escapeshellarg($path).' 2>&1', $output, $exitCode);
                expect($exitCode)->toBe(0, implode("\n", $output));
            }
        }

        $parsedYaml = \Symfony\Component\Yaml\Yaml::parseFile($tempRoot.'/UI/Layouts/workbench-layout.layout.yaml');
        expect($parsedYaml)->toBeArray();
        expect($parsedYaml['name'] ?? null)->toBe('workbench_layout');
    } finally {
        $files->deleteDirectory($tempRoot);
    }
});

it('falls back to built-in package SidebarMenu and StatusBar with SVG icons when app shell screens are missing', function () {
    /** @var Tests\TestCase $this */
    // Existing app shell screens take precedence
    expect(Screen::resolveShellScreenClass('sidebar'))->toBe(SidebarMenu::class);
    expect(Screen::resolveShellScreenClass('status_bar'))->toBe(StatusBar::class);

    // When configured to non-existent app shell screens (e.g. upgraded consumer app), fall back to package screens
    config()->set('usim.default_sidebar_menu_screen', 'MissingApp\\UI\\Screens\\SidebarMenu');
    config()->set('usim.default_status_bar_screen', 'MissingApp\\UI\\Screens\\StatusBar');

    try {
        expect(Screen::resolveShellScreenClass('sidebar'))->toBe(\Idei\Usim\Screens\SidebarMenu::class);
        expect(Screen::resolveShellScreenClass('status_bar'))->toBe(\Idei\Usim\Screens\StatusBar::class);

        $mainUnit = UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
        $registeredUser = User::factory()->create([
            'name' => 'Fallback Shell User',
            'email' => 'fallback.shell.'.uniqid().'@example.com',
        ]);
        $registeredUser->usimUnits()->sync([$mainUnit->id]);
        setPermissionsTeamId($mainUnit->id);
        $registeredUser->assignRole('registered');
        setPermissionsTeamId(null);
        $this->actingAs($registeredUser);

        $response = $this->getJson(screenApiUrl(Home::class, ['reset' => true]));
        $response->assertOk();
        $payload = $response->json();

        // Package SidebarMenu rendered with SVG icons (bolt, user, settings)
        $brandIcon = findComponentByName($payload, 'sidebar_brand_icon');
        $userMenu = findComponentByName($payload, 'user_menu');
        $settingsMenu = findComponentByName($payload, 'settings_menu');

        expect($brandIcon['icon'] ?? null)->toBe('bolt');
        expect($userMenu['trigger']['icon'] ?? null)->toBe('user');
        expect($settingsMenu['trigger']['icon'] ?? null)->toBe('settings');

        // Package StatusBar rendered with contextual sub-slots and dev reset button
        expect(findComponentByName($payload, 'status_bar_left_slot'))->not->toBeNull();
        expect(findComponentByName($payload, 'status_bar_center_slot'))->not->toBeNull();
        expect(findComponentByName($payload, 'status_bar_right_slot'))->not->toBeNull();
    } finally {
        config()->set('usim.default_sidebar_menu_screen', SidebarMenu::class);
        config()->set('usim.default_status_bar_screen', StatusBar::class);
    }
});

it('auto-discovers accessible screens with SVG home icon and respects user permissions in AutoDiscoveredMenuProvider', function () {
    /** @var Tests\TestCase $this */
    // 1. Registered user sees Home (with SVG 'home' icon) and does NOT see Admin screens (UsersManager/TranslateManager)
    $lobbyUnit = UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
    $registeredUser = User::factory()->create([
        'name' => 'AutoNav Registered',
        'email' => 'autonav.reg.'.uniqid().'@example.com',
    ]);
    $registeredUser->usimUnits()->sync([$lobbyUnit->id]);
    setPermissionsTeamId($lobbyUnit->id);
    $registeredUser->assignRole('registered');
    setPermissionsTeamId(null);
    $this->actingAs($registeredUser);

    $registeredNav = MenuBuilder::make('main_menu')
        ->provider(\Idei\Usim\Navigation\AutoDiscoveredMenuProvider::class)
        ->renderNavTree('auto_registered_nav');
    $registeredItems = $registeredNav->getItems();

    expect($registeredItems)->not->toBeEmpty();
    expect($registeredItems[0]['params']['screen'] ?? null)->toBe(Home::class);
    expect($registeredItems[0]['icon'] ?? null)->toBe('home');

    $collectScreens = static function (array $items) use (&$collectScreens): array {
        $screens = [];
        foreach ($items as $item) {
            if (is_string($item['params']['screen'] ?? null)) {
                $screens[] = $item['params']['screen'];
            }
            if (is_array($item['submenu'] ?? null)) {
                $screens = array_merge($screens, $collectScreens($item['submenu']));
            }
        }

        return $screens;
    };

    $registeredScreens = $collectScreens($registeredItems);
    expect($registeredScreens)->toContain(Home::class);
    expect($registeredScreens)->not->toContain(UsersManager::class);
    expect($registeredScreens)->not->toContain(TranslateManager::class);
    expect($registeredScreens)->not->toContain(Login::class);
    expect($registeredScreens)->not->toContain(SidebarMenu::class);
    expect($registeredScreens)->not->toContain(StatusBar::class);

    // 2. Root user sees Home + Admin screens (UsersManager & TranslateManager grouped under Admin)
    $mainUnit = UsimUnit::firstOrCreate(['slug' => 'main'], ['type' => 'system']);
    $rootUser = User::factory()->create([
        'name' => 'AutoNav Root',
        'email' => 'autonav.root.'.uniqid().'@example.com',
    ]);
    $rootUser->usimUnits()->sync([$mainUnit->id]);
    setPermissionsTeamId($mainUnit->id);
    $rootUser->assignRole('root');
    $this->actingAs($rootUser);

    $rootNav = MenuBuilder::make('main_menu')
        ->provider(\Idei\Usim\Navigation\AutoDiscoveredMenuProvider::class)
        ->renderNavTree('auto_root_nav');
    $rootScreens = $collectScreens($rootNav->getItems());

    expect($rootScreens)->toContain(Home::class);
    expect($rootScreens)->toContain(UsersManager::class);
    expect($rootScreens)->toContain(TranslateManager::class);
    expect($rootScreens)->not->toContain(Login::class);
    expect($rootScreens)->not->toContain(DevicePairingScreen::class);
    setPermissionsTeamId(null);
});

it('resolves built-in package screen, layout, and modal contexts across fresh ComponentIdGenerator instances', function () {
    $classes = [
        \Idei\Usim\Screens\SidebarMenu::class,
        \Idei\Usim\Screens\SidebarSubmenuPanel::class,
        \Idei\Usim\Screens\StatusBar::class,
        \Idei\Usim\Layout\WorkbenchLayout::class,
        \Idei\Usim\Layout\MainLayout::class,
        \Idei\Usim\Modals\ConfirmDialog::class,
    ];

    foreach ($classes as $class) {
        $renderGenerator = new \Idei\Usim\Support\ComponentIdGenerator;
        $componentId = $renderGenerator->generateFromName($class, 'test_btn');

        // Brand-new generator simulating a separate HTTP request (POST /api/ui-event)
        $freshEventGenerator = new \Idei\Usim\Support\ComponentIdGenerator;
        expect($freshEventGenerator->getContextFromId($componentId))->toBe($class);
    }
});

it('handles POST /api/ui-event actions on built-in SidebarMenu and StatusBar across isolated HTTP requests', function () {
    /** @var Tests\TestCase $this */
    config()->set('usim.default_sidebar_menu_screen', 'MissingApp\\UI\\Screens\\SidebarMenu');
    config()->set('usim.default_status_bar_screen', 'MissingApp\\UI\\Screens\\StatusBar');

    $resetFreshIdGenerator = static function (): void {
        app()->forgetInstance(\Idei\Usim\Contracts\ComponentIdGeneratorInterface::class);
        app()->scoped(\Idei\Usim\Contracts\ComponentIdGeneratorInterface::class, static fn () => new \Idei\Usim\Support\ComponentIdGenerator);
        AbstractLayout::setCurrent(null);
    };

    try {
        $lobbyUnit = UsimUnit::firstOrCreate(['slug' => 'lobby'], ['type' => 'system']);
        $user = User::factory()->create([
            'name' => 'Cross Request User',
            'email' => 'cross.req.'.uniqid().'@example.com',
        ]);
        $user->usimUnits()->sync([$lobbyUnit->id]);
        setPermissionsTeamId($lobbyUnit->id);
        $user->assignRole('registered');
        setPermissionsTeamId(null);
        $this->actingAs($user);

        $clientId = 'builtin-workbench-cross-request-'.uniqid();

        // Request 1: Initial render mounts Idei\Usim\Screens\SidebarMenu and Idei\Usim\Screens\StatusBar
        $renderResponse = $this->withCookie('ui_client_id', $clientId)
            ->getJson(screenApiUrl(Home::class, ['reset' => true]));
        $renderResponse->assertOk();
        $payload = $renderResponse->json();

        $findIdByName = static function (array $uiPayload, string $name): int {
            foreach ($uiPayload as $key => $node) {
                if (is_numeric($key) && is_array($node) && ($node['name'] ?? null) === $name) {
                    return (int) $key;
                }
            }

            return 0;
        };

        $resetBtnId = $findIdByName($payload, 'btn_reset');
        $settingsMenuId = $findIdByName($payload, 'settings_menu');
        $mainMenuId = $findIdByName($payload, 'main_menu');

        expect($resetBtnId)->toBeGreaterThan(0);
        expect($settingsMenuId)->toBeGreaterThan(0);
        expect($mainMenuId)->toBeGreaterThan(0);

        // Request 2 (fresh generator): Click 'show_about_info' in built-in SidebarMenu -> 200 OK (opens ConfirmDialog)
        $resetFreshIdGenerator();
        $aboutResponse = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
            'component_id' => $settingsMenuId,
            'event' => 'click',
            'action' => 'show_about_info',
            'parameters' => [],
        ]);
        $aboutResponse->assertOk();

        // Request 3 (fresh generator): Click 'showScreen' (Home) in built-in SidebarMenu -> 200 OK
        $resetFreshIdGenerator();
        $navResponse = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
            'component_id' => $mainMenuId,
            'event' => 'click',
            'action' => 'showScreen',
            'parameters' => ['screen' => Home::class],
        ]);
        $navResponse->assertOk();

        // Request 4 (fresh generator): Click 'reset_app' (R button) in built-in StatusBar -> 200 OK (returns redirect)
        $resetFreshIdGenerator();
        $resetResponse = $this->withCookie('ui_client_id', $clientId)->postJson('/api/ui-event', [
            'component_id' => $resetBtnId,
            'event' => 'click',
            'action' => 'reset_app',
            'parameters' => [],
        ]);
        $resetResponse->assertOk();
        expect($resetResponse->json('redirect'))->toContain('reset=true');
    } finally {
        config()->set('usim.default_sidebar_menu_screen', SidebarMenu::class);
        config()->set('usim.default_status_bar_screen', StatusBar::class);
    }
});
