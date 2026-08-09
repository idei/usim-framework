<?php
namespace Idei\Usim\Modals;

use Idei\Usim\Contracts\UIModal;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\TimeUnit;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Dialog Service
 *
 * Helper service to generate different types of modal dialogs.
 * Supports: info, confirm, warning, error, success, choice, and timeout dialogs.
 * Does not inherit from Screen as it's a utility service.
 */
class ConfirmDialogService implements UIModal
{
    private static function toStringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return (string) $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }

    private static function toIntValue(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || is_bool($value) || is_string($value) || $value === null) {
            return (int) $value;
        }

        if (is_array($value)) {
            return $value === [] ? 0 : 1;
        }

        return 0;
    }

    /**
     * @param mixed ...$params
     */
    public static function open(...$params): void
    {
        $dialog = new self();
        $format = $dialog->getUI(...$params);
        $uiChanges = app(UIChangesCollector::class);
        $uiChanges->add($format);
    }

    /**
     * Build a dialog UI
     *
     * @param mixed ...$params Parameters:
     *   - type: DialogType enum (INFO, CONFIRM, WARNING, ERROR, SUCCESS, CHOICE, TIMEOUT)
     *   - title: Modal title
     *   - message: Dialog message
     *   - icon: Icon emoji (optional, uses default from DialogType if not specified)
     *   - confirmAction: Action name for confirm/primary button
     *   - confirmParams: Additional parameters for confirm action
     *   - confirmLabel: Label for confirm button (optional, uses default from DialogType)
     *   - cancelAction: Action name for cancel button (default: 'close_modal')
     *   - cancelLabel: Label for cancel button (optional, uses default from DialogType)
     *   - callerServiceId: ID of the service that opened the modal
     *   - buttons: Array of custom buttons for CHOICE type (each: ['label', 'action', 'params', 'style'])
     *
     *   TIMEOUT specific parameters:
     *   - timeout: Time value (int, required for TIMEOUT)
     *   - timeUnit: TimeUnit enum (SECONDS, MINUTES, HOURS, DAYS) - default: SECONDS
     *   - showCountdown: bool - Show countdown timer (default: true)
     *   - showCloseButton: bool - Show manual close button (default: true)
     *   - timeoutAction: Action to execute when timeout completes (default: 'close_modal')
     *
    * @param mixed ...$params
    * @return array<int, array<string, mixed>> UI configuration array
     */
    private function getUI(...$params): array
    {
        // Support passing an entire config array as the first argument
        if (isset($params[0]) && is_array($params[0])) {
            $params = $params[0];
        }

        /** @var array<string, mixed> $params */

        // Extract dialog type (default to CONFIRM for backward compatibility)
        $type = $params['type'] ?? DialogType::CONFIRM;
        if (\is_string($type)) {
            $type = DialogType::from($type);
        }
        /** @var DialogType $type */

        // Extract parameters with defaults from DialogType
        $title           = self::toStringValue($params['title'] ?? t('usim.dialog.default.title'));
        $message         = self::toStringValue($params['message'] ?? t('usim.dialog.default.message'));
        $icon            = self::toStringValue($params['icon'] ?? $type->getDefaultIcon());
        $confirmAction   = self::toStringValue($params['confirmAction'] ?? 'close_modal');
        /** @var array<string, mixed> $confirmParams */
        $confirmParams   = $params['confirmParams'] ?? [];
        $confirmLabel    = self::toStringValue($params['confirmLabel'] ?? $type->getDefaultConfirmLabel());
        $cancelAction    = self::toStringValue($params['cancelAction'] ?? 'close_modal');
        $cancelLabel     = self::toStringValue($params['cancelLabel'] ?? $type->getDefaultCancelLabel());
        $callerServiceId = $params['callerServiceId'] ?? null;
        /** @var list<array<string, mixed>>|null $customButtons */
        $customButtons   = $params['buttons'] ?? null;

        // TIMEOUT specific parameters
        $timeout  = $params['timeout'] ?? null;
        $timeUnit = $params['timeUnit'] ?? TimeUnit::SECONDS;
        if (is_string($timeUnit)) {
            $timeUnit = TimeUnit::from($timeUnit);
        }
        /** @var TimeUnit $timeUnit */
        $showCountdown   = $params['showCountdown'] ?? true;
        $showCloseButton = $params['showCloseButton'] ?? true;
        $timeoutAction   = self::toStringValue($params['timeoutAction'] ?? 'close_modal');


        // Build container - use 'modal' as parent to indicate it should be rendered in the modal overlay
        $container = UI::container('confirm_dialog')
            ->parent('modal')
            ->layout(LayoutType::VERTICAL)
            ->plain()
            ->gap(Spacing::px(8))           // Space between elements
            ->centerContent(); // Center content horizontally

        // Icon
        $container->add(
            UI::label('icon')
                ->text($icon)
                ->fontSize('48') // Large emoji (48px)
        );

        // Title
        $container->add(
            UI::label('title')
                ->text($title)
                ->style('h3')
        );

        // Message
        $container->add(
            UI::label('message')
                ->text($message)
                ->markdown()
        );

        // Countdown label (only for TIMEOUT type with showCountdown enabled)
        if ($type === DialogType::TIMEOUT && $showCountdown && $timeout !== null) {
            $container->add(
                UI::label('countdown')
                    ->text($this->formatCountdown(self::toIntValue($timeout), $timeUnit))
                    ->style('h2')
            );

        }

        // Buttons container (horizontal layout)
        $buttonsContainer = UI::container('buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->plain()          // No background or borders on buttons container
            ->gap(Spacing::px(15))      // Space between buttons
            ->centerContent(); // Center buttons horizontally

        // Build buttons based on dialog type or custom buttons
        if ($customButtons && $type === DialogType::CHOICE) {
            // Custom buttons for CHOICE type
            foreach ($customButtons as $button) {
                $buttonLabel  = self::toStringValue($button['label'] ?? null);
                $buttonStyle  = self::toStringValue($button['style'] ?? 'secondary');
                $buttonAction = self::toStringValue($button['action'] ?? null);
                /** @var array<string, mixed> $buttonParams */
                $buttonParams = $button['params'] ?? [];

                $buttonsContainer->add(
                    UI::button('btn_' . strtolower(str_replace(' ', '_', $buttonLabel)))
                        ->label($buttonLabel)
                        ->style($buttonStyle)
                        ->action($buttonAction, array_merge($buttonParams, [
                            '_caller_service_id' => $callerServiceId,
                        ]))
                );
            }

        } elseif ($type === DialogType::TIMEOUT && ! $showCloseButton) {
            // TIMEOUT type without close button - no buttons at all
            // Don't add any buttons, just the countdown
        } else {
            // Standard buttons based on DialogType

            // Cancel button (if type requires it)
            if ($type->hasCancelButton()) {
                $buttonsContainer->add(
                    UI::button('btn_cancel')
                        ->label($cancelLabel)
                        ->style('secondary')
                        ->action($cancelAction, [
                            '_caller_service_id' => $callerServiceId,
                        ])
                );
            }

            // Confirm/Primary button
            $buttonsContainer->add(
                UI::button('btn_confirm')
                    ->label($confirmLabel)
                    ->style($type->getConfirmButtonStyle())
                    ->action($confirmAction, array_merge($confirmParams, [
                        '_caller_service_id' => $callerServiceId,
                    ]))
            );
        }

        // Only add buttons container if it has buttons (not empty for TIMEOUT without close button)
        if (! ($type === DialogType::TIMEOUT && ! $showCloseButton)) {
            $container->add($buttonsContainer);
        }

        // Add timeout metadata if TIMEOUT type
        if ($type === DialogType::TIMEOUT && $timeout !== null) {
            $builtContainer = $container->build();

            // Get the container ID from the built structure
            /** @var int $containerId */
            $containerId = array_key_first($builtContainer);

            // Add timeout configuration to the container
            $builtContainer[$containerId]['_timeout']           = $timeout;
            $builtContainer[$containerId]['_time_unit']         = $timeUnit->value;
            $builtContainer[$containerId]['_time_unit_label']   = $timeUnit->getPluralLabel();
            $builtContainer[$containerId]['_show_countdown']    = $showCountdown;
            $builtContainer[$containerId]['_timeout_action']    = $timeoutAction;
            $builtContainer[$containerId]['_timeout_ms']        = $timeUnit->toMilliseconds(self::toIntValue($timeout));
            $builtContainer[$containerId]['_caller_service_id'] = $callerServiceId;


            return $builtContainer;
        }

        return $container->toJson();
    }

    /**
     * Format countdown text
     */
    private function formatCountdown(int $value, TimeUnit $unit): string
    {
        return "{$value} {$unit->getLabel($value)}";
    }
}
