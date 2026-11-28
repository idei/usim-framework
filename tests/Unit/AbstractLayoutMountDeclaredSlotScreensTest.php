<?php

use App\UI\Screens\Demo\RightPanelInspector;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Split;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Layout\AbstractLayout;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;

class MountDeclaredSlotTestLayout extends AbstractLayout
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected function buildBaseUI(Container $container, mixed ...$params): void
    {
        $container->plain()->layout(LayoutType::VERTICAL);

        $topBar = UI::container('top_bar_container')
            ->plain()
            ->visible(false)
            ->layoutSlot('top_bar', persistOnNavigate: false);

        $main = UI::container('content_container')
            ->plain()
            ->layoutSlot('main', aliases: ['content', 'center'], persistOnNavigate: true);

        $rightPanel = UI::container('right_panel_container')
            ->plain()
            ->layoutSlot('right_panel', aliases: ['right'], persistOnNavigate: false);

        $splitWorkspace = UI::split('split_workspace')
            ->horizontal()
            ->splitSize('72%')
            ->splitSizeTarget('first')
            ->collapseTarget('second')
            ->collapseSecond()
            ->add($main)
            ->add($rightPanel);

        $container->add($topBar);
        $container->add($splitWorkspace);
    }
}

class MountDeclaredSlotSubScreen extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    public static ?string $layout = null;

    protected function buildBaseUI(Container $container, mixed ...$params): void
    {
        $title = is_string($params['title'] ?? null) ? $params['title'] : 'Default SubTitle';
        $container->plain()->add(UI::newLabel('lbl_sub_title')->text($title));
    }
}

class MountDeclaredSlotPersistentSubScreen extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    public static ?string $layout = null;

    public static bool $persistOnNavigate = true;

    protected function buildBaseUI(Container $container, mixed ...$params): void
    {
        $container->plain()->add(UI::newLabel('lbl_persistent_sub')->text('Persistent'));
    }
}

class MountDeclaredSlotHostScreen extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    public static ?string $layout = null;

    protected function buildBaseUI(Container $container, mixed ...$params): void
    {
        $label = is_string($params['label'] ?? null) ? $params['label'] : 'Host Self';
        $container->plain()->add(UI::newLabel('lbl_host_self')->text($label));
    }
}

class MountDeclaredSlotStatusBarScreen extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    public static ?string $layout = null;

    protected function buildBaseUI(Container $container, mixed ...$params): void
    {
        $center = UI::container('status_bar_center_slot')
            ->plain()
            ->layoutSlot('status_bar_center', aliases: ['status_bar_items'], persistOnNavigate: false);

        $right = UI::container('status_bar_right_slot')
            ->plain()
            ->layoutSlot('status_bar_right', persistOnNavigate: false);

        $container->plain()
            ->add(UI::newLabel('lbl_status_message')->text('Ready'))
            ->add($center)
            ->add($right);
    }
}

/**
 * Helper to bootstrap an isolated layout instance with optional external status bar slots.
 *
 * @return array{layout: MountDeclaredSlotTestLayout, statusBar: ?MountDeclaredSlotStatusBarScreen, collector: UIChangesCollector}
 */
function createMountSlotTestHarness(bool $withExternalSlots = false): array
{
    $collector = app(UIChangesCollector::class);
    $collector->reset();

    /** @var MountDeclaredSlotTestLayout $layout */
    $layout = MountDeclaredSlotTestLayout::make();
    $layout->render(shouldReset: true);
    AbstractLayout::setCurrent($layout);

    $statusBar = null;
    if ($withExternalSlots) {
        /** @var MountDeclaredSlotStatusBarScreen $statusBar */
        $statusBar = MountDeclaredSlotStatusBarScreen::make();
        $statusBar->render(shouldReset: true);
        $statusBarContainer = $statusBar->getContainer();
        if ($statusBarContainer instanceof Container) {
            $layout->registerExternalSlotsFromTree($statusBarContainer, MountDeclaredSlotStatusBarScreen::class);
        }
    }

    $collector->reset();

    return [
        'layout' => $layout,
        'statusBar' => $statusBar,
        'collector' => $collector,
    ];
}

beforeEach(function () {
    AbstractLayout::setCurrent(null);
    AbstractLayout::setActiveHostScreen(null);
    UIStateManager::clearClientCurrentScreen();
    UIStateManager::clearClientActiveLayout();
    UIStateManager::clearClientActiveModal();
    app(UIChangesCollector::class)->reset();
});

afterEach(function () {
    AbstractLayout::setCurrent(null);
    AbstractLayout::setActiveHostScreen(null);
});

describe('AbstractLayout::mountDeclaredSlotScreens', function () {
    it('returns early without side effects when no declared slot screens exist', function () {
        ['layout' => $layout, 'collector' => $collector] = createMountSlotTestHarness();

        $screenRoot = UI::container('empty_screen_root');
        $layout->mountDeclaredSlotScreens($screenRoot);

        $changes = array_diff_key($collector->all(), ['storage' => true]);

        expect($changes)->toBe([])
            ->and($layout->getActiveScreen('right_panel'))->toBeNull()
            ->and($layout->getSlotOwner('right_panel'))->toBeNull();
    });

    it('preserves layout defaultScreen on slots without declared overrides and overrides defaultScreen when declared', function () {
        ['layout' => $layout] = createMountSlotTestHarness();

        $topBar = $layout->getSlot('top_bar');
        expect($topBar)->toBeInstanceOf(Container::class);
        assert($topBar instanceof Container);
        $topBar->layoutSlot('top_bar', screen: MountDeclaredSlotPersistentSubScreen::class, persistOnNavigate: false);
        $layout->setActiveScreen('top_bar', MountDeclaredSlotPersistentSubScreen::class);

        // Declaring a screen only on right_panel leaves top_bar's default active screen intact
        $screenRoot = UI::container('default_screen_root')->setDeclaredSlotScreens([
            [
                'slot' => 'right_panel',
                'screen' => MountDeclaredSlotSubScreen::class,
                'params' => ['title' => 'Right Declared'],
                'open' => true,
                'size' => null,
                'fallback' => 'ignore',
                'persist_on_navigate' => false,
            ],
        ]);

        $layout->mountDeclaredSlotScreens($screenRoot);

        expect($layout->getActiveScreen('top_bar'))->toBe(MountDeclaredSlotPersistentSubScreen::class)
            ->and($layout->getActiveScreen('right_panel'))->toBe(MountDeclaredSlotSubScreen::class);

        // Declaring a screen on top_bar mounts and updates its active screen and owner
        $overrideRoot = UI::container('override_default_root')->setDeclaredSlotScreens([
            [
                'slot' => 'top_bar',
                'screen' => MountDeclaredSlotSubScreen::class,
                'params' => ['title' => 'Top Override'],
                'open' => true,
                'size' => null,
                'fallback' => 'ignore',
                'persist_on_navigate' => false,
            ],
        ]);

        $layout->mountDeclaredSlotScreens($overrideRoot);

        expect($layout->getActiveScreen('top_bar'))->toBe(MountDeclaredSlotSubScreen::class)
            ->and($layout->getSlotOwner('top_bar'))->toBe([
                'mode' => 'screen',
                'owner' => MountDeclaredSlotSubScreen::class,
                'persist_on_navigate' => false,
            ]);
    });

    it('mounts declared screens on local slots with custom size, params, and open state', function () {
        ['layout' => $layout] = createMountSlotTestHarness();

        $screenRoot = UI::container('local_mount_root')->setDeclaredSlotScreens([
            [
                'slot' => 'right_panel',
                'screen' => MountDeclaredSlotSubScreen::class,
                'params' => ['title' => 'Mounted Panel'],
                'open' => true,
                'size' => '360px',
                'fallback' => 'ignore',
                'persist_on_navigate' => false,
            ],
        ]);

        $layout->mountDeclaredSlotScreens($screenRoot);

        $rightSlot = $layout->getSlot('right_panel');
        $split = $layout->getContainer()?->findByName('split_workspace');
        $owner = $layout->getSlotOwner('right_panel');

        expect($split)->toBeInstanceOf(Split::class);
        assert($split instanceof Split);

        expect($layout->getActiveScreen('right_panel'))->toBe(MountDeclaredSlotSubScreen::class)
            ->and($layout->isSlotExpanded('right_panel'))->toBeTrue()
            ->and($split->getSplitSize())->toBe('360px')
            ->and($split->getSplitSizeTarget())->toBe('second')
            ->and($owner)->toBe([
                'mode' => 'screen',
                'owner' => MountDeclaredSlotSubScreen::class,
                'persist_on_navigate' => false,
            ])
            ->and($rightSlot?->findByName('lbl_sub_title')?->toJson())->not->toBeEmpty();
    });

    it('restores default slot size when size is null, applies persist_on_navigate override, and collapses when open is false', function () {
        ['layout' => $layout] = createMountSlotTestHarness();

        // First override size so restoreSlotSize has saved defaults to restore
        $layout->setSlotSize('right_panel', '420px');
        $split = $layout->getContainer()?->findByName('split_workspace');
        expect($split)->toBeInstanceOf(Split::class);
        assert($split instanceof Split);
        expect($split->getSplitSize())->toBe('420px');

        $screenRoot = UI::container('local_collapsed_root')->setDeclaredSlotScreens([
            [
                'slot' => 'right_panel',
                'screen' => MountDeclaredSlotSubScreen::class,
                'params' => ['title' => 'Collapsed Persistent'],
                'open' => false,
                'size' => null,
                'fallback' => 'ignore',
                'persist_on_navigate' => true,
            ],
        ]);

        $layout->mountDeclaredSlotScreens($screenRoot);

        expect($split->getSplitSize())->toBe('72%')
            ->and($split->getSplitSizeTarget())->toBe('first')
            ->and($layout->isSlotExpanded('right_panel'))->toBeFalse()
            ->and($layout->getSlotOwner('right_panel'))->toBe([
                'mode' => 'screen',
                'owner' => MountDeclaredSlotSubScreen::class,
                'persist_on_navigate' => true,
            ]);

        // Persisted state in UIStateManager reflects updated slot owner
        $persistedState = UIStateManager::getScreenState(MountDeclaredSlotTestLayout::class);
        $persistedOwners = $persistedState['state_slot_owners'] ?? null;
        $rightOwner = is_array($persistedOwners) ? ($persistedOwners['right_panel'] ?? null) : null;
        expect(is_array($rightOwner) ? ($rightOwner['persist_on_navigate'] ?? null) : null)->toBeTrue();
    });

    it('force-remounts when the declared screen is already active in the slot or matches the host screen', function () {
        ['layout' => $layout] = createMountSlotTestHarness();

        // Pre-mount the host screen into right_panel and mark it as active host screen
        AbstractLayout::setActiveHostScreen(MountDeclaredSlotHostScreen::class);
        $layout->showInto(MountDeclaredSlotHostScreen::class, slot: 'right_panel', params: ['label' => 'Initial Host'], updateBrowserUrl: false);
        expect($layout->getActiveScreen('right_panel'))->toBe(MountDeclaredSlotHostScreen::class);

        $screenRoot = UI::container('remount_host_root')->setDeclaredSlotScreens([
            [
                'slot' => 'right_panel',
                'screen' => MountDeclaredSlotHostScreen::class,
                'params' => ['label' => 'Refreshed Host'],
                'open' => true,
                'size' => null,
                'fallback' => 'ignore',
                'persist_on_navigate' => false,
            ],
        ]);

        $layout->mountDeclaredSlotScreens($screenRoot);

        $rightSlot = $layout->getSlot('right_panel');
        $label = $rightSlot?->findByName('lbl_host_self');
        expect($label)->not->toBeNull();
        assert($label !== null);
        $labelJson = $label->toJson()[$label->getId()] ?? [];

        expect($layout->getActiveScreen('right_panel'))->toBe(MountDeclaredSlotHostScreen::class)
            ->and($labelJson['text'] ?? null)->toBe('Refreshed Host');
    });

    it('resolves valid screen slugs on local slots and throws RuntimeException for invalid screen classes on local slots', function () {
        ['layout' => $layout] = createMountSlotTestHarness();

        $slugRoot = UI::container('slug_local_root')->setDeclaredSlotScreens([
            [
                'slot' => 'right_panel',
                'screen' => 'demo/right-panel-inspector',
                'params' => ['title' => 'Resolved By Slug'],
                'open' => true,
                'size' => '300px',
                'fallback' => 'ignore',
                'persist_on_navigate' => false,
            ],
        ]);

        $layout->mountDeclaredSlotScreens($slugRoot);
        expect($layout->getActiveScreen('right_panel'))->toBe(RightPanelInspector::class);

        $invalidNonScreenRoot = UI::container('invalid_non_screen_root')->setDeclaredSlotScreens([
            [
                'slot' => 'right_panel',
                'screen' => stdClass::class,
                'params' => [],
                'open' => true,
                'size' => null,
                'fallback' => 'ignore',
                'persist_on_navigate' => false,
            ],
        ]);

        expect(fn () => $layout->mountDeclaredSlotScreens($invalidNonScreenRoot))
            ->toThrow(RuntimeException::class, 'is not a valid Screen instance.');

        $unknownSlugRoot = UI::container('unknown_slug_root')->setDeclaredSlotScreens([
            [
                'slot' => 'right_panel',
                'screen' => 'non-existent/does-not-exist',
                'params' => [],
                'open' => true,
                'size' => null,
                'fallback' => 'ignore',
                'persist_on_navigate' => false,
            ],
        ]);

        expect(fn () => $layout->mountDeclaredSlotScreens($unknownSlugRoot))
            ->toThrow(RuntimeException::class, 'is not a valid Screen instance.');
    });

    it('handles unregistered slots with ignore, inline, and inline_top fallbacks including slug resolution and invalid classes', function () {
        ['layout' => $layout] = createMountSlotTestHarness();

        // 1. fallback: ignore -> does not embed anything
        $ignoreRoot = UI::container('missing_ignore_root');
        $ignoreWrapper = UI::container('ignore_primary_wrapper')
            ->add(UI::newLabel('lbl_existing_ignore')->text('Existing'));
        $ignoreRoot->add($ignoreWrapper)->setDeclaredSlotScreens([
            [
                'slot' => 'unregistered_slot',
                'screen' => MountDeclaredSlotSubScreen::class,
                'params' => ['title' => 'Ignored'],
                'open' => true,
                'size' => null,
                'fallback' => 'ignore',
                'persist_on_navigate' => false,
            ],
        ]);

        $layout->mountDeclaredSlotScreens($ignoreRoot);
        expect($ignoreWrapper->getChildren())->toHaveCount(1);

        // 2. fallback: inline -> embeds at the end of primary wrapper (skipping portal children)
        $inlineRoot = UI::container('missing_inline_root');
        $portalChild = UI::container('portal_child')->setPortalSlotName('top_bar');
        $inlineWrapper = UI::container('inline_primary_wrapper')
            ->add(UI::newLabel('lbl_first_child')->text('First'));
        $inlineRoot->add($portalChild)->add($inlineWrapper)->setDeclaredSlotScreens([
            [
                'slot' => 'missing_drawer',
                'screen' => MountDeclaredSlotSubScreen::class,
                'params' => ['title' => 'Inline Bottom'],
                'open' => true,
                'size' => null,
                'fallback' => 'inline',
                'persist_on_navigate' => false,
            ],
        ]);

        $layout->mountDeclaredSlotScreens($inlineRoot);
        $inlineChildren = $inlineWrapper->getChildren();
        expect($inlineChildren)->toHaveCount(2)
            ->and($inlineChildren[0]->getName())->toBe('lbl_first_child')
            ->and($inlineWrapper->findByName('lbl_sub_title'))->not->toBeNull();

        // 3. fallback: inline_top with slug -> resolves slug, embeds, and moves child to start
        $inlineTopRoot = UI::container('missing_inline_top_root');
        $topWrapper = UI::container('inline_top_primary_wrapper')
            ->add(UI::newLabel('lbl_original_first')->text('Original First'));
        $inlineTopRoot->add($topWrapper)->setDeclaredSlotScreens([
            [
                'slot' => 'missing_sidebar',
                'screen' => 'demo/right-panel-inspector',
                'params' => ['title' => 'Inline Top Inspector'],
                'open' => true,
                'size' => null,
                'fallback' => 'inline_top',
                'persist_on_navigate' => false,
            ],
        ]);

        $layout->mountDeclaredSlotScreens($inlineTopRoot);
        $topChildren = $topWrapper->getChildren();
        expect($topChildren)->toHaveCount(2)
            ->and($topChildren[0]->getName())->not->toBe('lbl_original_first')
            ->and($topChildren[1]->getName())->toBe('lbl_original_first')
            ->and($topWrapper->findByName('lbl_inspector_title'))->not->toBeNull();

        // 4. fallback: inline / inline_top with invalid screen class or unknown slug -> silently omitted
        $invalidFallbackRoot = UI::container('missing_invalid_root');
        $invalidWrapper = UI::container('invalid_fallback_wrapper')
            ->add(UI::newLabel('lbl_untouched')->text('Untouched'));
        $invalidFallbackRoot->add($invalidWrapper)->setDeclaredSlotScreens([
            [
                'slot' => 'missing_slot_a',
                'screen' => stdClass::class,
                'params' => [],
                'open' => true,
                'size' => null,
                'fallback' => 'inline',
                'persist_on_navigate' => false,
            ],
            [
                'slot' => 'missing_slot_b',
                'screen' => 'unknown/non-existent-screen',
                'params' => [],
                'open' => true,
                'size' => null,
                'fallback' => 'inline_top',
                'persist_on_navigate' => false,
            ],
        ]);

        $layout->mountDeclaredSlotScreens($invalidFallbackRoot);
        expect($invalidWrapper->getChildren())->toHaveCount(1)
            ->and($invalidWrapper->getChildren()[0]->getName())->toBe('lbl_untouched');
    });

    it('mounts declared screens on external status_bar slot, emits clear_container on non-initial render, hides lbl_status_message, and persists owner metadata', function () {
        ['layout' => $layout, 'collector' => $collector] = createMountSlotTestHarness(withExternalSlots: true);

        $statusCenterTarget = $layout->resolveProjectionSlot('status_bar');
        expect($statusCenterTarget)->not->toBeNull();
        assert(is_array($statusCenterTarget));
        expect($statusCenterTarget['external'])->toBeTrue()
            ->and($statusCenterTarget['canonical'])->toBe('status_bar_center');

        $screenRoot = UI::container('external_status_bar_root')->setDeclaredSlotScreens([
            [
                'slot' => 'status_bar',
                'screen' => MountDeclaredSlotSubScreen::class,
                'params' => ['title' => 'Status Bar Widget'],
                'open' => true,
                'size' => null,
                'fallback' => 'ignore',
                'persist_on_navigate' => true,
            ],
        ]);

        $layout->mountDeclaredSlotScreens($screenRoot, isInitialRender: false);

        $payload = $collector->all();
        $msgId = UIIdGenerator::generateFromName(MountDeclaredSlotStatusBarScreen::class, 'lbl_status_message');
        $subLabelId = UIIdGenerator::generateFromName(MountDeclaredSlotSubScreen::class, 'lbl_sub_title');

        expect($payload['clear_container'] ?? null)->toBe($statusCenterTarget['container_id'])
            ->and($payload[$subLabelId]['text'] ?? null)->toBe('Status Bar Widget')
            ->and($payload[$msgId]['visible'] ?? null)->toBeFalse()
            ->and($layout->getActiveScreen('status_bar_center'))->toBe(MountDeclaredSlotSubScreen::class)
            ->and($layout->getSlotOwner('status_bar_center'))->toBe([
                'mode' => 'screen',
                'owner' => MountDeclaredSlotSubScreen::class,
                'persist_on_navigate' => true,
            ]);

        // StatusBar cached snapshot also keeps lbl_status_message hidden
        $cachedStatusBar = UIStateManager::get(MountDeclaredSlotStatusBarScreen::class);
        expect($cachedStatusBar[$msgId]['visible'] ?? null)->toBeFalse();
    });

    it('skips clear_container on initial render for external slots, resolves slugs, respects Screen::shouldPersistOnNavigate, and leaves lbl_status_message visible for non-center external slots', function () {
        ['layout' => $layout, 'collector' => $collector] = createMountSlotTestHarness(withExternalSlots: true);

        $statusRightTarget = $layout->resolveProjectionSlot('status_bar_right');
        expect($statusRightTarget)->not->toBeNull();
        assert(is_array($statusRightTarget));
        expect($statusRightTarget['external'])->toBeTrue();

        // 1. Slug-resolved screen on non-center external slot during initial render
        $slugExternalRoot = UI::container('external_slug_root')->setDeclaredSlotScreens([
            [
                'slot' => 'status_bar_right',
                'screen' => 'demo/right-panel-inspector',
                'params' => ['title' => 'Right External'],
                'open' => true,
                'size' => null,
                'fallback' => 'ignore',
                'persist_on_navigate' => false,
            ],
        ]);

        $layout->mountDeclaredSlotScreens($slugExternalRoot, isInitialRender: true);

        $payload = $collector->all();
        $msgId = UIIdGenerator::generateFromName(MountDeclaredSlotStatusBarScreen::class, 'lbl_status_message');

        expect(array_key_exists('clear_container', $payload))->toBeFalse()
            ->and(array_key_exists($msgId, $payload))->toBeFalse()
            ->and($layout->getActiveScreen('status_bar_right'))->toBe(RightPanelInspector::class)
            ->and($layout->getSlotOwner('status_bar_right'))->toBe([
                'mode' => 'screen',
                'owner' => RightPanelInspector::class,
                'persist_on_navigate' => false,
            ]);

        // 2. Persistent screen (shouldPersistOnNavigate() === true) sets persist_on_navigate = true even when entry has false
        $collector->reset();
        $persistentExternalRoot = UI::container('external_persistent_root')->setDeclaredSlotScreens([
            [
                'slot' => 'status_bar_right',
                'screen' => MountDeclaredSlotPersistentSubScreen::class,
                'params' => [],
                'open' => true,
                'size' => null,
                'fallback' => 'ignore',
                'persist_on_navigate' => false,
            ],
        ]);

        $layout->mountDeclaredSlotScreens($persistentExternalRoot, isInitialRender: true);

        expect($layout->getSlotOwner('status_bar_right'))->toBe([
            'mode' => 'screen',
            'owner' => MountDeclaredSlotPersistentSubScreen::class,
            'persist_on_navigate' => true,
        ]);
    });

    it('silently skips invalid or non-Screen classes declared for external slots', function (string $invalidScreen) {
        ['layout' => $layout, 'collector' => $collector] = createMountSlotTestHarness(withExternalSlots: true);

        $screenRoot = UI::container('external_invalid_root')->setDeclaredSlotScreens([
            [
                'slot' => 'status_bar',
                'screen' => $invalidScreen,
                'params' => [],
                'open' => true,
                'size' => null,
                'fallback' => 'ignore',
                'persist_on_navigate' => false,
            ],
        ]);

        $layout->mountDeclaredSlotScreens($screenRoot, isInitialRender: false);

        $changes = array_diff_key($collector->all(), ['storage' => true]);

        expect($changes)->toBe([])
            ->and($layout->getActiveScreen('status_bar_center'))->toBeNull()
            ->and($layout->getSlotOwner('status_bar_center'))->toBeNull();
    })->with([
        'unknown slug' => ['does/not-exist-anywhere'],
        'existing non-Screen class' => [stdClass::class],
    ]);
});
