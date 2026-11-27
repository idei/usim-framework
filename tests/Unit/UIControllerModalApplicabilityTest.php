<?php

use Idei\Usim\Components\Container;
use Idei\Usim\Contracts\UIStateRepositoryInterface;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Http\Controllers\UIController;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\Support\UIStateRepository;
use Idei\Usim\UIChangesCollector;

if (! class_exists('Tests_Unit_CustomBaseShellScreen')) {
    class Tests_Unit_CustomBaseShellScreen {}
}

if (! class_exists('Tests_Unit_CustomChildShellScreen')) {
    class Tests_Unit_CustomChildShellScreen extends Tests_Unit_CustomBaseShellScreen {}
}

if (! class_exists('Tests_Unit_UIControllerTestScreens_HostScreen')) {
    class Tests_Unit_UIControllerTestScreens_HostScreen extends Screen
    {
        public static Visibility $visibility = Visibility::PUBLIC;

        public static ?string $layout = null;

        protected function buildBaseUI(Container $container, ...$params): void
        {
            // Minimal public screen for controller flow verification
        }
    }
}

if (! class_exists('Tests_Unit_UIControllerTestScreens_SampleModal')) {
    class Tests_Unit_UIControllerTestScreens_SampleModal extends Screen
    {
        public static Visibility $visibility = Visibility::PUBLIC;

        public static ?string $layout = null;

        protected function buildBaseUI(Container $container, ...$params): void
        {
            // Minimal modal screen
        }
    }
}

/**
 * Invoke private UIController::isModalApplicableToScreen directly for exhaustive branch testing.
 *
 * @param  array<string, mixed>  $modalMeta
 */
function invokeIsModalApplicableToScreen(
    UIController $controller,
    array $modalMeta,
    string $screenRoute,
    string $screenClass
): bool {
    $method = new ReflectionMethod(UIController::class, 'isModalApplicableToScreen');
    $method->setAccessible(true);

    return (bool) $method->invoke($controller, $modalMeta, $screenRoute, $screenClass);
}

/**
 * Build a baseline modal metadata payload with optional overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function makeModalMeta(array $overrides = []): array
{
    return array_merge([
        'modal_class' => 'App\\UI\\Screens\\SampleModal',
        'caller_screen_id' => 1001,
        'caller_screen_class' => 'App\\UI\\Screens\\OtherScreen',
        'callback_action' => null,
        'params' => [],
        'layer_index' => 0,
        'page_screen_route' => null,
    ], $overrides);
}

describe('UIController::isModalApplicableToScreen', function () {
    beforeEach(function () {
        UIIdGenerator::reset();
        UIStateManager::clearClientActiveModal();
    });

    afterEach(function () {
        app()->instance(UIStateRepositoryInterface::class, new UIStateRepository);
        UIIdGenerator::reset();
        UIStateManager::clearClientActiveModal();
    });

    it('returns true when page_screen_route matches the current screen route', function (string $route) {
        $controller = new UIController(app(UIChangesCollector::class));

        $meta = makeModalMeta([
            'page_screen_route' => $route,
            'caller_screen_class' => 'App\\UI\\Screens\\CompletelyDifferentCaller',
        ]);

        expect(invokeIsModalApplicableToScreen(
            $controller,
            $meta,
            $route,
            'App\\UI\\Screens\\Admin\\UsersManager'
        ))->toBeTrue();
    })->with([
        'nested admin route' => ['admin/users-manager'],
        'root single segment route' => ['home'],
        'empty route exact match' => [''],
    ]);

    it('returns true when caller_screen_class is null as a fallback to host screen', function (?string $pageRoute) {
        $controller = new UIController(app(UIChangesCollector::class));

        $meta = makeModalMeta([
            'caller_screen_class' => null,
            'page_screen_route' => $pageRoute,
        ]);

        expect(invokeIsModalApplicableToScreen(
            $controller,
            $meta,
            'admin/dashboard',
            'App\\UI\\Screens\\Admin\\Dashboard'
        ))->toBeTrue();
    })->with([
        'no page route' => [null],
        'different page route' => ['other/route'],
    ]);

    it('returns true when caller_screen_class directly matches screenClass', function () {
        $controller = new UIController(app(UIChangesCollector::class));
        $screenClass = 'App\\UI\\Screens\\Admin\\UsersManager';

        $meta = makeModalMeta([
            'caller_screen_class' => $screenClass,
            'page_screen_route' => 'different/route',
        ]);

        expect(invokeIsModalApplicableToScreen(
            $controller,
            $meta,
            'admin/users-manager',
            $screenClass
        ))->toBeTrue();
    });

    it('returns true for persistent shell screens matched by class basename', function (string $callerClass) {
        $controller = new UIController(app(UIChangesCollector::class));

        $meta = makeModalMeta([
            'caller_screen_class' => $callerClass,
            'page_screen_route' => 'another/page',
        ]);

        expect(invokeIsModalApplicableToScreen(
            $controller,
            $meta,
            'admin/users-manager',
            'App\\UI\\Screens\\Admin\\UsersManager'
        ))->toBeTrue();
    })->with([
        'Menu' => ['Custom\\Namespace\\Menu'],
        'SidebarMenu' => ['Idei\\Usim\\Screens\\SidebarMenu'],
        'SidebarSubmenuPanel' => ['App\\UI\\Screens\\Shell\\SidebarSubmenuPanel'],
        'StatusBar' => ['Idei\\Usim\\Screens\\StatusBar'],
    ]);

    it('returns true when caller matches or extends a configured shell screen class', function (string $configKey, string $callerClass) {
        config([
            'usim.default_menu_screen' => null,
            'usim.default_sidebar_menu_screen' => null,
            'usim.default_status_bar_screen' => null,
            $configKey => Tests_Unit_CustomBaseShellScreen::class,
        ]);

        $controller = new UIController(app(UIChangesCollector::class));

        $meta = makeModalMeta([
            'caller_screen_class' => $callerClass,
            'page_screen_route' => 'old/route',
        ]);

        expect(invokeIsModalApplicableToScreen(
            $controller,
            $meta,
            'admin/dashboard',
            'App\\UI\\Screens\\Admin\\Dashboard'
        ))->toBeTrue();
    })->with([
        'default_menu_screen exact class' => ['usim.default_menu_screen', Tests_Unit_CustomBaseShellScreen::class],
        'default_menu_screen subclass' => ['usim.default_menu_screen', Tests_Unit_CustomChildShellScreen::class],
        'default_sidebar_menu_screen exact class' => ['usim.default_sidebar_menu_screen', Tests_Unit_CustomBaseShellScreen::class],
        'default_status_bar_screen subclass' => ['usim.default_status_bar_screen', Tests_Unit_CustomChildShellScreen::class],
    ]);

    it('ignores empty or non-string shell screen config values safely', function (mixed $invalidShellConfig) {
        config([
            'usim.default_menu_screen' => $invalidShellConfig,
            'usim.default_sidebar_menu_screen' => '',
            'usim.default_status_bar_screen' => null,
        ]);

        $controller = new UIController(app(UIChangesCollector::class));

        $meta = makeModalMeta([
            'caller_screen_class' => 'App\\UI\\Screens\\UnrelatedPanel',
            'page_screen_route' => 'different/route',
        ]);

        expect(invokeIsModalApplicableToScreen(
            $controller,
            $meta,
            'admin/dashboard',
            'App\\UI\\Screens\\Admin\\Dashboard'
        ))->toBeFalse();
    })->with([
        'empty string' => [''],
        'null' => [null],
        'integer' => [123],
        'array' => [['App\\UI\\Screens\\Menu']],
    ]);

    it('returns true when caller class belongs to one of the client opened embedded screens', function () {
        $embeddedCaller = 'App\\UI\\Screens\\EmbeddedWidget';
        $otherOpened = 'App\\UI\\Screens\\AnotherWidget';

        $otherId = UIIdGenerator::generate($otherOpened);
        $embeddedId = UIIdGenerator::generate($embeddedCaller);

        $repo = Mockery::mock(UIStateRepositoryInterface::class);
        $repo->shouldReceive('getClientOpenedScreens')
            ->once()
            ->with(null)
            ->andReturn([(string) $otherId, (string) $embeddedId]);

        app()->instance(UIStateRepositoryInterface::class, $repo);

        $controller = new UIController(app(UIChangesCollector::class));

        $meta = makeModalMeta([
            'caller_screen_class' => $embeddedCaller,
            'page_screen_route' => 'other/route',
        ]);

        expect(invokeIsModalApplicableToScreen(
            $controller,
            $meta,
            'admin/dashboard',
            'App\\UI\\Screens\\Admin\\Dashboard'
        ))->toBeTrue();
    });

    it('returns false when modal does not match route, screen, shell, or opened screens', function () {
        $openedOther = 'App\\UI\\Screens\\DifferentOpenedWidget';
        $openedId = UIIdGenerator::generate($openedOther);

        $repo = Mockery::mock(UIStateRepositoryInterface::class);
        $repo->shouldReceive('getClientOpenedScreens')
            ->once()
            ->with(null)
            ->andReturn([(string) $openedId, '99999999']);

        app()->instance(UIStateRepositoryInterface::class, $repo);

        $controller = new UIController(app(UIChangesCollector::class));

        $meta = makeModalMeta([
            'caller_screen_class' => 'App\\UI\\Screens\\ForeignCallerScreen',
            'page_screen_route' => 'foreign/route',
        ]);

        // Also test when page_screen_route key is omitted entirely
        $metaWithoutPageRoute = $meta;
        unset($metaWithoutPageRoute['page_screen_route']);

        expect(invokeIsModalApplicableToScreen(
            $controller,
            $meta,
            'admin/dashboard',
            'App\\UI\\Screens\\Admin\\Dashboard'
        ))->toBeFalse();

        // Second check without page_screen_route key and with empty opened screens
        $repoEmpty = Mockery::mock(UIStateRepositoryInterface::class);
        $repoEmpty->shouldReceive('getClientOpenedScreens')
            ->once()
            ->with(null)
            ->andReturn([]);
        app()->instance(UIStateRepositoryInterface::class, $repoEmpty);

        expect(invokeIsModalApplicableToScreen(
            $controller,
            $metaWithoutPageRoute,
            'admin/dashboard',
            'App\\UI\\Screens\\Admin\\Dashboard'
        ))->toBeFalse();
    });

    it('clears inapplicable modals and preserves applicable modals through UIController::show', function () {
        // Create alias in a dedicated namespace so resolveScreenClass('host-screen') resolves to our test screen
        if (! class_exists('Tests\\UIControllerFlow\\HostScreen')) {
            class_alias(
                Tests_Unit_UIControllerTestScreens_HostScreen::class,
                'Tests\\UIControllerFlow\\HostScreen'
            );
        }

        config(['usim.screens_namespace' => 'Tests\\UIControllerFlow']);

        $controller = app(UIController::class);

        // 1. Inapplicable modal -> cleared during show()
        UIStateManager::pushClientActiveModal(
            modalClass: Tests_Unit_UIControllerTestScreens_SampleModal::class,
            callerScreenId: 42,
            callerScreenClass: 'App\\UI\\Screens\\UnrelatedScreen',
            pageScreenRoute: 'other-screen',
        );

        $response = $controller->show('host-screen');
        expect($response->getStatusCode())->toBe(200)
            ->and(UIStateManager::getClientActiveModalStack())->toBeEmpty();

        // 2. Applicable modal (matching page_screen_route) without reset -> preserved/restored during show()
        UIStateManager::pushClientActiveModal(
            modalClass: Tests_Unit_UIControllerTestScreens_SampleModal::class,
            callerScreenId: 42,
            callerScreenClass: 'App\\UI\\Screens\\UnrelatedScreen',
            pageScreenRoute: 'host-screen',
        );

        $response = $controller->show('host-screen');
        expect($response->getStatusCode())->toBe(200)
            ->and(UIStateManager::getClientActiveModalStack())->toHaveCount(1);

        // 3. Applicable modal with reset=1 -> cleared during show()
        $request = request();
        $request->query->set('reset', '1');
        try {
            $response = $controller->show('host-screen');
            expect($response->getStatusCode())->toBe(200)
                ->and(UIStateManager::getClientActiveModalStack())->toBeEmpty();
        } finally {
            $request->query->remove('reset');
        }
    });
});
