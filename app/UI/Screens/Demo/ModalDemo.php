<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\TimeUnit;
use Idei\Usim\Modals\ConfirmDialogService;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Modal Demo Service
 *
 * Demonstrates modal functionality:
 * - Opening confirmation dialogs
 * - Handling user responses from modals
 * - Modal lifecycle (open → user action → close)
 */
class ModalDemo extends Screen
{
    protected Label $lbl_result;
    protected Label $lbl_instruction;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->title(t('screen.demo.modal_demo.title'))
            ->maxWidth(Size::px(600))
            ->centerHorizontal()
            ->shadow(2)
            ->padding(Spacing::px(30));

        $container->add(
            UI::label('lbl_instruction')
                ->text(t('screen.demo.modal_demo.instruction'))
                ->style('info')
        );

        $container->add(
            UI::label('lbl_result')
                ->text('')
                ->style('default')
        );

        $buttonContainer = UI::container('button_container')
            ->layout(LayoutType::HORIZONTAL)
            ->centerContent()
            ->gap(Spacing::px(15))
            ->shadow(false)
            ->add(
                UI::button('btn_open_modal')
                    ->label(t('screen.demo.modal_demo.actions.open_confirmation'))
                    ->style('primary')
                    ->action('open_confirmation')
                    ->width(Size::px(230))
            )->add(
                UI::button('btn_error_dialog')
                    ->label(t('screen.demo.modal_demo.actions.open_error'))
                    ->style('danger')
                    ->action('show_error_dialog')
                    ->width(Size::px(230))
            )->add(
                UI::button('btn_timeout_dialog')
                    ->label(t('screen.demo.modal_demo.actions.open_timeout_with_button'))
                    ->style('warning')
                    ->action('show_timeout_dialog', ['duration' => 10])
                    ->width(Size::px(230))
            )->add(
                UI::button('btn_timeout_no_button')
                    ->label(t('screen.demo.modal_demo.actions.open_timeout_without_button'))
                    ->style('warning')
                    ->action('show_timeout_no_button')
                    ->width(Size::px(230))
            )->add(
                UI::button('btn_show_settings')
                    ->label(t('screen.demo.modal_demo.actions.settings'))
                    ->style('secondary')
                    ->icon('settings')
                    ->action('show_settings_confirm')
                    ->width(Size::px(230))
            );
        $container->add($buttonContainer);
    }

    /**
     * Handle "Open Confirmation" button click
     * Opens a confirmation dialog modal
     *
    * @param array<string, mixed> $params
     * @return void
     */
    public function onOpenConfirmation(array $params): void
    {
        // Get this screen ID to receive the callback.
        $screenId = $this->getScreenComponentId();

        ConfirmDialogService::open(
            type: DialogType::CONFIRM,
            title: t('screen.demo.modal_demo.confirm_dialog.title'),
            message: t('screen.demo.modal_demo.confirm_dialog.message'),
            confirmAction: 'handle_confirm',
            confirmParams: ['action_type' => 'demo_action'],
            confirmLabel: t('screen.demo.modal_demo.confirm_dialog.confirm_label'),
            cancelAction: 'handle_cancel',
            cancelLabel: t('screen.demo.modal_demo.confirm_dialog.cancel_label'),
            callerServiceId: $screenId
        );
    }

    /**
     * Handle user confirmation from modal
     *
    * @param array<string, mixed> $params
     * @return void
     */
    public function onHandleConfirm(array $params): void
    {
        $actionType = $params['action_type'] ?? 'unknown';

        $this->lbl_result
            ->text(t('screen.demo.modal_demo.result.confirmed', ['type' => $actionType]))
            ->style('success');

        $this->closeModal();
    }

    /**
     * Handle user cancellation from modal
     *
    * @param array<string, mixed> $params
     * @return void
     */
    public function onHandleCancel(array $params): void
    {
        $this->lbl_result
            ->text(t('screen.demo.modal_demo.result.cancelled'))
            ->style('warning');

        $this->closeModal();
    }

    /**
     * Handler for Error dialog demo
     */
    /**
     * @param array<string, mixed> $params
     */
    public function onShowErrorDialog(array $params): void
    {
        // Get this screen ID to receive the callback.
        $screenId = $this->getScreenComponentId();

        ConfirmDialogService::open(
            type: DialogType::ERROR,
            title: t('screen.demo.modal_demo.error_dialog.title'),
            message: t('screen.demo.modal_demo.error_dialog.message'),
            confirmAction: 'close_error_dialog',
            callerServiceId: $screenId
        );
    }

    /**
     * Handler to close error dialog
     */
    /**
     * @param array<string, mixed> $params
     */
    public function onCloseErrorDialog(array $params): void
    {
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onShowTimeoutDialog(array $params): void
    {
        $screenId = $this->getScreenComponentId();
        $duration  = $params['duration'] ?? 10;

        ConfirmDialogService::open(
            type: DialogType::TIMEOUT,
            title: t('screen.demo.modal_demo.timeout_dialog.title'),
            message: t('screen.demo.modal_demo.timeout_dialog.message'),
            timeout: $duration,
            timeUnit: TimeUnit::SECONDS,
            showCountdown: true,
            confirmAction: 'close_timeout_dialog',
            callerServiceId: $screenId
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onShowTimeoutNoButton(array $params): void
    {
        $screenId = $this->getScreenComponentId();

        ConfirmDialogService::open(
            type: DialogType::TIMEOUT,
            title: t('screen.demo.modal_demo.auto_close_dialog.title'),
            message: t('screen.demo.modal_demo.auto_close_dialog.message'),
            timeout: 5,
            timeUnit: TimeUnit::SECONDS,
            showCountdown: true,
            showCloseButton: false, // No mostrar botón de cerrar
            callerServiceId: $screenId
        );
    }

    /**
     * Handler to close timeout dialog
     */
    /**
     * @param array<string, mixed> $params
     */
    public function onCloseTimeoutDialog(array $params): void
    {
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onShowSettingsConfirm(array $params): void
    {
        // Get this screen ID to receive the callback.
        $screenId = $this->getScreenComponentId();

        ConfirmDialogService::open(
            type: DialogType::WARNING,
            title: t('screen.demo.modal_demo.settings_dialog.title'),
            message: t('screen.demo.modal_demo.settings_dialog.message'),
            confirmAction: 'reset_settings',
            confirmParams: [],
            cancelAction: 'cancel_settings',
            callerServiceId: $screenId
        );
    }

    /**
     * Handler for cancel button (closes modal)
     */
    /**
     * @param array<string, mixed> $params
     */
    public function onCancelSettings(array $params): void
    {
        $this->closeModal();
    }

    /**
     * Handler for reset button - shows success dialog
     */
    /**
     * @param array<string, mixed> $params
     */
    public function onResetSettings(array $params): void
    {
        // Get this screen ID to receive the callback.
        $screenId = $this->getScreenComponentId();

        ConfirmDialogService::open(
            type: DialogType::SUCCESS,
            title: t('screen.demo.modal_demo.success_dialog.title'),
            message: t('screen.demo.modal_demo.success_dialog.message'),
            confirmAction: 'close_success_dialog',
            callerServiceId: $screenId
        );
    }

    /**
     * Handler to close success dialog
     */
    /**
     * @param array<string, mixed> $params
     */
    public function onCloseSuccessDialog(array $params): void
    {
        $this->closeModal();
    }
}
