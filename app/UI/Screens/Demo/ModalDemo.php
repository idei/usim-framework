<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\Services\UIBuilder;
use Idei\Usim\Services\Enums\TimeUnit;
use Idei\Usim\Services\Enums\DialogType;
use Idei\Usim\Services\Enums\LayoutType;
use Idei\Usim\Services\AbstractUIService;
use Idei\Usim\Services\Components\UIContainer;
use Idei\Usim\Services\Components\LabelBuilder;
use Idei\Usim\Services\Modals\ConfirmDialogService;

/**
 * Modal Demo Service
 *
 * Demonstrates modal functionality:
 * - Opening confirmation dialogs
 * - Handling user responses from modals
 * - Modal lifecycle (open → user action → close)
 */
class ModalDemo extends AbstractUIService
{
    protected LabelBuilder $lbl_result;
    protected LabelBuilder $lbl_instruction;

    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container
            ->title(t('app.screen.demo.modal_demo.title'))
            ->maxWidth('600px')
            ->centerHorizontal()
            ->shadow(2)
            ->padding('30px');

        $container->add(
            UIBuilder::label('lbl_instruction')
                ->text(t('app.screen.demo.modal_demo.instruction'))
                ->style('info')
        );

        $container->add(
            UIBuilder::label('lbl_result')
                ->text('')
                ->style('default')
        );

        $buttonContainer = UIBuilder::container('button_container')
            ->layout(LayoutType::HORIZONTAL)
            ->centerContent()
            ->gap("15px")
            ->shadow(false)
            ->add(
                UIBuilder::button('btn_open_modal')
                    ->label(t('app.screen.demo.modal_demo.actions.open_confirmation'))
                    ->style('primary')
                    ->action('open_confirmation')
                    ->width('230px')
            )->add(
                UIBuilder::button('btn_error_dialog')
                    ->label(t('app.screen.demo.modal_demo.actions.open_error'))
                    ->style('danger')
                    ->action('show_error_dialog')
                    ->width('230px')
            )->add(
                UIBuilder::button('btn_timeout_dialog')
                    ->label(t('app.screen.demo.modal_demo.actions.open_timeout_with_button'))
                    ->style('warning')
                    ->action('show_timeout_dialog', ['duration' => 10])
                    ->width('230px')
            )->add(
                UIBuilder::button('btn_timeout_no_button')
                    ->label(t('app.screen.demo.modal_demo.actions.open_timeout_without_button'))
                    ->style('warning')
                    ->action('show_timeout_no_button')
                    ->width('230px')
            )->add(
                UIBuilder::button('btn_show_settings')
                    ->label(t('app.screen.demo.modal_demo.actions.settings'))
                    ->style('secondary')
                    ->icon('settings')
                    ->action('show_settings_confirm')
                    ->width('230px')
            );
        $container->add($buttonContainer);
    }

    /**
     * Handle "Open Confirmation" button click
     * Opens a confirmation dialog modal
     *
     * @param array $params
     * @return array Response with modal UI
     */
    public function onOpenConfirmation(array $params): void
    {
        // Get this service's ID to receive the callback
        $serviceId = $this->getServiceComponentId();

        ConfirmDialogService::open(
            type: DialogType::CONFIRM,
            title: t('app.screen.demo.modal_demo.confirm_dialog.title'),
            message: t('app.screen.demo.modal_demo.confirm_dialog.message'),
            confirmAction: 'handle_confirm',
            confirmParams: ['action_type' => 'demo_action'],
            confirmLabel: t('app.screen.demo.modal_demo.confirm_dialog.confirm_label'),
            cancelAction: 'handle_cancel',
            cancelLabel: t('app.screen.demo.modal_demo.confirm_dialog.cancel_label'),
            callerServiceId: $serviceId
        );
    }

    /**
     * Handle user confirmation from modal
     *
     * @param array $params
     * @return array Response to close modal and update UI
     */
    public function onHandleConfirm(array $params): void
    {
        $actionType = $params['action_type'] ?? 'unknown';

        $this->lbl_result
            ->text(t('app.screen.demo.modal_demo.result.confirmed', ['type' => $actionType]))
            ->style('success');

        $this->closeModal();
    }

    /**
     * Handle user cancellation from modal
     *
     * @param array $params
     * @return array Response to close modal and update UI
     */
    public function onHandleCancel(array $params): void
    {
        $this->lbl_result
            ->text(t('app.screen.demo.modal_demo.result.cancelled'))
            ->style('warning');

        $this->closeModal();
    }

    /**
     * Handler for Error dialog demo
     */
    public function onShowErrorDialog(array $params): void
    {
        // Get this service ID to receive the callback
        $serviceId = $this->getServiceComponentId();

        ConfirmDialogService::open(
            type: DialogType::ERROR,
            title: t('app.screen.demo.modal_demo.error_dialog.title'),
            message: t('app.screen.demo.modal_demo.error_dialog.message'),
            confirmAction: 'close_error_dialog',
            callerServiceId: $serviceId
        );
    }

    /**
     * Handler to close error dialog
     */
    public function onCloseErrorDialog(array $params): void
    {
        $this->closeModal();
    }

    public function onShowTimeoutDialog(array $params): void
    {
        $serviceId = $this->getServiceComponentId();
        $duration  = $params['duration'] ?? 10;

        ConfirmDialogService::open(
            type: DialogType::TIMEOUT,
            title: t('app.screen.demo.modal_demo.timeout_dialog.title'),
            message: t('app.screen.demo.modal_demo.timeout_dialog.message'),
            timeout: $duration,
            timeUnit: TimeUnit::SECONDS,
            showCountdown: true,
            confirmAction: 'close_timeout_dialog',
            callerServiceId: $serviceId
        );
    }

    public function onShowTimeoutNoButton(array $params): void
    {
        $serviceId = $this->getServiceComponentId();

        ConfirmDialogService::open(
            type: DialogType::TIMEOUT,
            title: t('app.screen.demo.modal_demo.auto_close_dialog.title'),
            message: t('app.screen.demo.modal_demo.auto_close_dialog.message'),
            timeout: 5,
            timeUnit: TimeUnit::SECONDS,
            showCountdown: true,
            showCloseButton: false, // No mostrar botón de cerrar
            callerServiceId: $serviceId
        );
    }

    /**
     * Handler to close timeout dialog
     */
    public function onCloseTimeoutDialog(array $params): void
    {
        $this->closeModal();
    }

    public function onShowSettingsConfirm(array $params): void
    {
        // Get this service ID to receive the callback
        $serviceId = $this->getServiceComponentId();

        ConfirmDialogService::open(
            type: DialogType::WARNING,
            title: t('app.screen.demo.modal_demo.settings_dialog.title'),
            message: t('app.screen.demo.modal_demo.settings_dialog.message'),
            confirmAction: 'reset_settings',
            confirmParams: [],
            cancelAction: 'cancel_settings',
            callerServiceId: $serviceId
        );
    }

    /**
     * Handler for cancel button (closes modal)
     */
    public function onCancelSettings(array $params): void
    {
        $this->closeModal();
    }

    /**
     * Handler for reset button - shows success dialog
     */
    public function onResetSettings(array $params): void
    {
        // Get this service ID to receive the callback
        $serviceId = $this->getServiceComponentId();

        ConfirmDialogService::open(
            type: DialogType::SUCCESS,
            title: t('app.screen.demo.modal_demo.success_dialog.title'),
            message: t('app.screen.demo.modal_demo.success_dialog.message'),
            confirmAction: 'close_success_dialog',
            callerServiceId: $serviceId
        );
    }

    /**
     * Handler to close success dialog
     */
    public function onCloseSuccessDialog(array $params): void
    {
        $this->closeModal();
    }
}
