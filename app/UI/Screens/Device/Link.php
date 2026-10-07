<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Device;

use App\Models\Device;
use App\Services\Device\DeviceListingService;
use App\Services\Device\DeviceService;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * Screen for linking and pairing a device using PIN.
 */
class Link extends Screen
{
    public const DEFAULT_SUBMIT_ACTION = 'submit_approve_device_pairing';
    public const DEFAULT_CANCEL_ACTION = 'close_link_device';

    private const DEVICES_I18N_PREFIX = 'screen.admin.users_manager.';
    private const CONTAINER_PADDING = 20;
    private const CONTENT_GAP = 16;
    private const BUTTONS_GAP = 8;

    public static Visibility $visibility = Visibility::AUTHENTICATED;

    protected DeviceService $deviceService;
    protected ?DeviceListingService $deviceListingService = null;

    public function __construct(
        ?DeviceService $deviceService = null,
        ?DeviceListingService $deviceListingService = null,
    ) {
        $this->deviceService = $deviceService ?? app(DeviceService::class);
        $this->deviceListingService = $deviceListingService ?? app(DeviceListingService::class);
    }

    public static function getMenuLabel(): string
    {
        return t(self::DEVICES_I18N_PREFIX . 'pair_device', [], 'Vincular Dispositivo');
    }

    public static function getMenuIcon(): ?string
    {
        return '🔗';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $rawDevice = $params['device'] ?? null;
        $rawDeviceId = $params['device_id'] ?? $params['id'] ?? request()->query('id') ?? request()->query('device_id');
        $device = $this->resolveDevice($rawDevice, $rawDeviceId);

        $wrapper = UI::container('device_link_wrapper')
            ->maxWidth(Size::px(500))
            ->width(Size::full())
            ->centerHorizontal();

        $card = UI::container('device_pairing_dialog')
            ->plain()
            ->shadow(false)
            ->width(Size::full())
            ->padding(Spacing::px(self::CONTAINER_PADDING))
            ->gap(Spacing::px(self::CONTENT_GAP));

        $header = UI::container('dialog_header')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->plain()
            ->padding(Spacing::zero());

        $header->add(
            UI::label('dialog_title')
                ->text(t(self::DEVICES_I18N_PREFIX . 'device_pairing_title'))
                ->style('title')
        );

        $header->add(
            UI::button('btn_close_modal')
                ->label('✕')
                ->action(self::DEFAULT_CANCEL_ACTION)
                ->style('secondary')
                ->variant('ghost')
                ->plain()
        );

        $card->add($header);

        $card->add(
            UI::label('dialog_instruction')
                ->text(t(self::DEVICES_I18N_PREFIX . 'device_pairing_instruction'))
                ->style('info')
        );

        if ($device) {
            $card->add(
                UI::label('lbl_selected_device')
                    ->text(t(self::DEVICES_I18N_PREFIX . 'devices_column_name') . ': ' . $device->name)
                    ->style('secondary')
            );

            $card->add(
                UI::input('pairing_device_id')
                    ->type('hidden')
                    ->value((string) $device->id)
            );
        } else {
            $rawOptions = $params['devicesOptions'] ?? null;
            /** @var list<array{value: int|string, label: string}>|null $customOptions */
            $customOptions = is_array($rawOptions) ? $rawOptions : null;
            $selectOptions = $this->buildDevicesOptions($customOptions);

            $card->add(
                UI::select('pairing_device_id')
                    ->label(t(self::DEVICES_I18N_PREFIX . 'device_select_label'))
                    ->options($selectOptions)
                    ->value(!empty($selectOptions) ? (string) $selectOptions[0]['value'] : '')
                    ->required(true)
                    ->width(Size::full())
            );
        }

        $card->add(
            UI::input('input_pin')
                ->label(t(self::DEVICES_I18N_PREFIX . 'device_pin_label'))
                ->placeholder('Ej: 4419')
                ->value('')
                ->required(true)
                ->type('number')
                ->width(Size::full())
        );

        $buttonsContainer = UI::container('dialog_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->gap(Spacing::px(self::BUTTONS_GAP));

        $buttonsContainer->add(
            UI::button('btn_cancel_pairing')
                ->label(t('modal.cancel'))
                ->style('secondary')
                ->action(self::DEFAULT_CANCEL_ACTION)
        );

        $buttonsContainer->add(
            UI::button('btn_submit_pairing')
                ->label(t(self::DEVICES_I18N_PREFIX . 'pair_device'))
                ->style('primary')
                ->action(self::DEFAULT_SUBMIT_ACTION)
        );

        $card->add($buttonsContainer);
        $wrapper->add($card);
        $container->add($wrapper);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSubmitApproveDevicePairing(array $params): void
    {
        $pairingIdRaw = $params['pairing_device_id'] ?? $params['device_id'] ?? null;
        $pin = trim($this->stringParamOrDefault($params, 'input_pin', $this->stringParamOrDefault($params, 'pin', '')));

        if (strlen($pin) !== 4) {
            $this->toast(t(self::DEVICES_I18N_PREFIX . 'device_pin_length_error'), 'error');
            return;
        }

        $deviceId = is_numeric($pairingIdRaw) ? (int) $pairingIdRaw : null;
        if ($deviceId === null || $deviceId <= 0) {
            $this->toast(t(self::DEVICES_I18N_PREFIX . 'device_select_label'), 'error');
            return;
        }

        $result = $this->deviceService->pairDevice($deviceId, $pin);

        event(new UsimEvent('device_paired', [
            'device_id' => $deviceId,
            'message' => $result['message'],
        ]));

        $this->closeModal();
    }

    /**
     * Alias for submit_pair_device.
     *
     * @param array<string, mixed> $params
     */
    public function onSubmitPairDevice(array $params): void
    {
        $this->onSubmitApproveDevicePairing($params);
    }

    /**
     * Closes the pairing screen or modal.
     *
     * @param array<string, mixed> $params
     */
    public function onCloseLinkDevice(array $params = []): void
    {
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onCloseModal(array $params = []): void
    {
        $this->onCloseLinkDevice($params);
    }

    /**
     * @param list<array{value: int|string, label: string}>|null $customOptions
     * @return list<array{value: int|string, label: string}>
     */
    protected function buildDevicesOptions(?array $customOptions = null): array
    {
        if (!empty($customOptions)) {
            return $customOptions;
        }

        $listingService = $this->deviceListingService ?? app(DeviceListingService::class);
        $devices = $listingService->all();

        $options = [];
        foreach ($devices as $d) {
            $options[] = [
                'value' => (string) $d->id,
                'label' => $d->name,
            ];
        }

        if (empty($options)) {
            return [
                ['value' => '', 'label' => '- ' . t(self::DEVICES_I18N_PREFIX . 'no_devices_available', [], 'No hay dispositivos registrados') . ' -'],
            ];
        }

        return $options;
    }

    protected function resolveDevice(mixed $rawDevice, mixed $rawDeviceId): ?Device
    {
        if ($rawDevice instanceof Device) {
            return $rawDevice;
        }

        $deviceId = null;
        if (is_int($rawDeviceId) || (is_string($rawDeviceId) && is_numeric($rawDeviceId))) {
            $deviceId = (int) $rawDeviceId;
        } elseif (is_array($rawDevice) && isset($rawDevice['id']) && is_numeric($rawDevice['id'])) {
            $deviceId = (int) $rawDevice['id'];
        }

        if ($deviceId !== null && $deviceId > 0) {
            return $this->deviceService->getDevice($deviceId);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $params
     */
    protected function stringParamOrDefault(array $params, string $key, string $default): string
    {
        $value = $params[$key] ?? null;
        return is_string($value) || is_numeric($value) ? (string) $value : $default;
    }
}
