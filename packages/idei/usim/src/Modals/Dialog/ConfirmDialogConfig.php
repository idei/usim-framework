<?php

namespace Idei\Usim\Modals\Dialog;

use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\TimeUnit;

/**
 * Value object / DTO encapsulating configuration for ConfirmDialog.
 *
 * Adheres to SRP by handling normalization, validation, and default resolution
 * of dialog parameters independently of the Screen presentation layer.
 */
final class ConfirmDialogConfig
{
    /**
     * @param  array<string, mixed>  $confirmParams
     * @param  list<array<string, mixed>>|null  $buttons
     */
    public function __construct(
        public readonly DialogType $type = DialogType::CONFIRM,
        public readonly string $title = '',
        public readonly string $message = '',
        public readonly string $icon = '',
        public readonly string $confirmAction = 'close_modal',
        public readonly array $confirmParams = [],
        public readonly string $confirmLabel = '',
        public readonly string $cancelAction = 'close_modal',
        public readonly string $cancelLabel = '',
        public readonly int|string|null $callerServiceId = null,
        public readonly ?array $buttons = null,
        public readonly ?int $timeout = null,
        public readonly TimeUnit $timeUnit = TimeUnit::SECONDS,
        public readonly bool $showCountdown = true,
        public readonly bool $showCloseButton = true,
        public readonly string $timeoutAction = 'close_modal',
    ) {}

    /**
     * Factory method creating a validated config instance from raw parameters.
     */
    public static function from(mixed ...$params): self
    {
        if (isset($params[0]) && $params[0] instanceof self) {
            return $params[0];
        }

        if (isset($params[0]) && is_array($params[0])) {
            $params = $params[0];
        }

        /** @var array<string, mixed> $params */
        return self::fromArray($params);
    }

    /**
     * Build config from associative parameters array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $type = self::resolveDialogType($data['type'] ?? DialogType::CONFIRM);

        return new self(
            type: $type,
            title: self::toString($data['title'] ?? t('usim.dialog.default.title')),
            message: self::toString($data['message'] ?? t('usim.dialog.default.message')),
            icon: self::toString($data['icon'] ?? $type->getDefaultIcon()),
            confirmAction: self::toString($data['confirmAction'] ?? $data['confirm_action'] ?? 'close_modal'),
            confirmParams: self::toArray($data['confirmParams'] ?? $data['confirm_params'] ?? []),
            confirmLabel: self::toString($data['confirmLabel'] ?? $data['confirm_label'] ?? $type->getDefaultConfirmLabel()),
            cancelAction: self::toString($data['cancelAction'] ?? $data['cancel_action'] ?? 'close_modal'),
            cancelLabel: self::toString($data['cancelLabel'] ?? $data['cancel_label'] ?? $type->getDefaultCancelLabel()),
            callerServiceId: self::toString($data['callerServiceId'] ?? $data['caller_service_id'] ?? null),
            buttons: self::toButtonsList($data['buttons'] ?? null),
            timeout: isset($data['timeout']) ? self::toInt($data['timeout']) : null,
            timeUnit: self::resolveTimeUnit($data['timeUnit'] ?? $data['time_unit'] ?? TimeUnit::SECONDS),
            showCountdown: (bool) ($data['showCountdown'] ?? $data['show_countdown'] ?? true),
            showCloseButton: (bool) ($data['showCloseButton'] ?? $data['show_close_button'] ?? true),
            timeoutAction: self::toString($data['timeoutAction'] ?? $data['timeout_action'] ?? 'close_modal'),
        );
    }

    public function isTimeout(): bool
    {
        return $this->type === DialogType::TIMEOUT && $this->timeout !== null;
    }

    public function isChoice(): bool
    {
        return $this->type === DialogType::CHOICE && ! empty($this->buttons);
    }

    public function hasCountdown(): bool
    {
        return $this->isTimeout() && $this->showCountdown;
    }

    public function hasCancelButton(): bool
    {
        return $this->type->hasCancelButton();
    }

    public function shouldRenderButtons(): bool
    {
        if ($this->type === DialogType::TIMEOUT && ! $this->showCloseButton) {
            return false;
        }

        return true;
    }

    public function getFormattedCountdown(): string
    {
        if ($this->timeout === null) {
            return '';
        }

        return "{$this->timeout} {$this->timeUnit->getLabel($this->timeout)}";
    }

    public function getConfirmButtonStyle(): string
    {
        return $this->type->getConfirmButtonStyle();
    }

    public function getTimeoutMilliseconds(): int
    {
        return $this->timeUnit->toMilliseconds($this->timeout ?? 0);
    }

    /**
     * Get container metadata attributes for timeout dialogs.
     *
     * @return array<string, mixed>
     */
    public function getTimeoutMetadata(): array
    {
        if (! $this->isTimeout()) {
            return [];
        }

        return [
            '_timeout' => $this->timeout,
            '_time_unit' => $this->timeUnit->value,
            '_time_unit_label' => $this->timeUnit->getPluralLabel(),
            '_show_countdown' => $this->showCountdown,
            '_timeout_action' => $this->timeoutAction,
            '_timeout_ms' => $this->getTimeoutMilliseconds(),
            '_caller_service_id' => $this->callerServiceId,
        ];
    }

    private static function resolveDialogType(mixed $type): DialogType
    {
        if ($type instanceof DialogType) {
            return $type;
        }

        if (is_string($type)) {
            return DialogType::tryFrom(strtolower(trim($type))) ?? DialogType::CONFIRM;
        }

        return DialogType::CONFIRM;
    }

    private static function resolveTimeUnit(mixed $unit): TimeUnit
    {
        if ($unit instanceof TimeUnit) {
            return $unit;
        }

        if (is_string($unit)) {
            return TimeUnit::tryFrom(strtolower(trim($unit))) ?? TimeUnit::SECONDS;
        }

        return TimeUnit::SECONDS;
    }

    private static function toString(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return $default;
    }

    private static function toInt(mixed $value, int $default = 0): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    private static function toArray(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private static function toButtonsList(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $buttons = [];

        foreach (array_values($value) as $button) {
            if (is_array($button)) {
                /** @var array<string, mixed> $button */
                $buttons[] = $button;
            }
        }

        return $buttons;
    }
}
