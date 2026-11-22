<?php

namespace App\UI\Screens;

use App\Services\Auth\AuthSessionService;
use App\Services\Units\UsimUnitsService;
use App\UI\Screens\Concerns\HandlesShellActions;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

/**
 * VS Code-style Bottom Status Bar for WorkbenchLayout.
 *
 * - Left: Organizational Unit badge & switcher (visible when user has operational units).
 * - Center: Dynamic status message updated via `UsimEvent('status_bar_message', ['text' => ...])`.
 * - Right: Dev-only Reset button (automatically omitted in production).
 */
class StatusBar extends Screen
{
    use HandlesShellActions;

    public static Visibility $visibility = Visibility::PUBLIC;
    public static ?string $layout = null;

    public function __construct(
        protected AuthSessionService $authSessionService,
        protected UsimUnitsService $usimUnitsService,
    ) {}

    protected MenuDropdown $unit_menu;
    protected NewLabel $lbl_status_message;
    protected ?Button $btn_reset = null;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->plain()
            ->customClass('usim-statusbar-shell')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->width(Size::full())
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0));

        // Left: Unit badge selector
        $left = UI::container('statusbar_left')
            ->plain()
            ->customClass('usim-statusbar-left')
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0));

        $this->unit_menu = UI::menuDropdown('unit_menu')
            ->position('top-left')
            ->floating(true)
            ->width(Size::px(220))
            ->customClass('usim-statusbar-unit');

        $this->populateUnitDropdown($this->unit_menu, $this->usimUnitsService->getAvailableUnits());
        $left->add($this->unit_menu);

        $leftSlot = UI::container('status_bar_left_slot')
            ->plain()
            ->customClass('usim-statusbar-slot usim-statusbar-slot-left')
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(6))
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0))
            ->layoutSlot('status_bar_left', [], null, false);
        $left->add($leftSlot);

        // Center: Status message + contextual screen projection slot
        $center = UI::container('statusbar_center')
            ->plain()
            ->customClass('usim-statusbar-center')
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0));

        $this->lbl_status_message = UI::newLabel('lbl_status_message')
            ->text('');
        $center->add($this->lbl_status_message);

        $centerSlot = UI::container('status_bar_center_slot')
            ->plain()
            ->customClass('usim-statusbar-slot usim-statusbar-slot-center')
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(8))
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0))
            ->layoutSlot('status_bar_center', ['status_bar_items'], null, false);
        $center->add($centerSlot);

        // Right: Contextual screen projection slot + Dev-only Reset button
        $right = UI::container('statusbar_right')
            ->plain()
            ->customClass('usim-statusbar-right')
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(6))
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0));

        $rightSlot = UI::container('status_bar_right_slot')
            ->plain()
            ->customClass('usim-statusbar-slot usim-statusbar-slot-right')
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(6))
            ->padding(Spacing::px(0))
            ->margin(Spacing::px(0))
            ->layoutSlot('status_bar_right', [], null, false);
        $right->add($rightSlot);

        if (! app()->isProduction()) {
            $resetBtn = UI::button('btn_reset')
                ->label('R')
                ->style('danger')
                ->size('xs')
                ->tooltip('Reset UI state')
                ->customClass('usim-statusbar-reset-btn')
                ->action('reset_app');
            $this->btn_reset = $resetBtn;
            $right->add($resetBtn);
        }

        $container->add($left);
        $container->add($center);
        $container->add($right);
    }

    protected function postLoadUI(): void
    {
        if (Auth::check()) {
            $this->populateUnitDropdown($this->unit_menu, $this->usimUnitsService->getAvailableUnits());
        } else {
            $this->unit_menu->clearItems();
            $this->unit_menu->visible(false);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onChangeUnit(array $params): void
    {
        $this->executeUnitSwitch(
            params: $params,
            usimUnitsService: $this->usimUnitsService,
            authSessionService: $this->authSessionService,
            onUpdated: function (): void {
                $this->populateUnitDropdown($this->unit_menu, $this->usimUnitsService->getAvailableUnits());
            }
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onResetApp(array $params): void
    {
        if (app()->isProduction()) {
            return;
        }

        event(new UsimEvent('reset_screen'));
        $this->redirect($this->buildResetRedirectUrl());
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onStatusBarMessage(array $params): void
    {
        $text = $this->stringParamOrDefault($params, 'text', '');
        $this->lbl_status_message->text($text);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onUnitChanged(array $params): void
    {
        if (Auth::check()) {
            $this->populateUnitDropdown($this->unit_menu, $this->usimUnitsService->getAvailableUnits());
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onLoggedUser(array $params): void
    {
        $unitValue = $params['unit'] ?? '';
        if (\is_string($unitValue) && $unitValue !== '') {
            UIStateManager::setActiveUnit($unitValue);
        }

        if (Auth::check()) {
            $this->populateUnitDropdown($this->unit_menu, $this->usimUnitsService->getAvailableUnits());
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onUserLoggedOut(array $params): void
    {
        $this->unit_menu->clearItems();
        $this->unit_menu->visible(false);
        $this->lbl_status_message->text('');
    }
}
