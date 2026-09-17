<?php

// @usim: feature="admin", type="component"

namespace App\UI\Components\Modals;

use App\Models\Device;
use App\Services\Role\RoleService;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\UI;
use Idei\Usim\UIChangesCollector;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class DevicePairingDialog
{
    /**
     * @param Device|null $device
     * @param list<array{value: int|string, label: string}> $devicesOptions
     */
    public static function open(
        string $submitAction = 'submit_approve_device_pairing',
        string $cancelAction = 'close_modal',
        ?Device $device = null,
        array $devicesOptions = [],
        ?int $callerServiceId = null
    ): void {
        $dialog = new self();
        $format = $dialog->getUI($submitAction, $cancelAction, $device, $devicesOptions, $callerServiceId);
        $uiChanges = app(UIChangesCollector::class);
        $uiChanges->add($format);
    }

    /**
     * @param Device|null $device
     * @param list<array{value: int|string, label: string}> $devicesOptions
     * @return array<int, array<string, mixed>>
     */
    public function getUI(
        string $submitAction = 'submit_approve_device_pairing',
        string $cancelAction = 'close_modal',
        ?Device $device = null,
        array $devicesOptions = [],
        ?int $callerServiceId = null
    ): array {
        $prefix = 'screen.admin.users_manager.';

        $container = UI::container('device_pairing_dialog')
            ->parent('modal')
            ->shadow(false)
            ->plain()
            ->padding(Spacing::px(20))
            ->gap(Spacing::px(16));

        $container->add(
            UI::label('dialog_title')
                ->text(t($prefix . 'device_pairing_title'))
                ->style('title')
        );

        $container->add(
            UI::label('dialog_instruction')
                ->text(t($prefix . 'device_pairing_instruction'))
                ->style('info')
        );

        if ($device) {
            $container->add(
                UI::label('lbl_selected_device')
                    ->text(t($prefix . 'devices_column_name') . ': ' . $device->name)
                    ->style('secondary')
            );

            $container->add(
                UI::input('pairing_device_id')
                    ->type('hidden')
                    ->value((string) $device->id)
            );
        } else {
            $selectOptions = array_merge([
                [
                    'value' => 'new',
                    'label' => '➕ ' . t($prefix . 'pair_new_device_option', [], 'Registrar y vincular nuevo dispositivo...'),
                ],
            ], $devicesOptions);

            $container->add(
                UI::select('pairing_device_id')
                    ->label(t($prefix . 'device_select_label'))
                    ->options($selectOptions)
                    ->value(!empty($devicesOptions) ? (string) $devicesOptions[0]['value'] : 'new')
                    ->required(true)
                    ->width(Size::full())
            );

            $container->add(
                UI::input('new_device_name')
                    ->label(t($prefix . 'device_name_label'))
                    ->placeholder('Ej: Tótem de Entrada')
                    ->value('')
                    ->autocomplete('off')
                    ->width(Size::full())
            );

            $roleService = app(RoleService::class);
            $roles = $roleService->getAllowedRoles(excludedGuards: ['web', 'api']);
            $roleOptions = [
                ['value' => '', 'label' => '- ' . t('role.none') . ' -'],
            ];
            foreach ($roles as $r) {
                $roleOptions[] = [
                    'value' => $r->name,
                    'label' => t("role.{$r->name}.name"),
                ];
            }

            $container->add(
                UI::select('new_device_role')
                    ->label(t($prefix . 'device_roles_label'))
                    ->options($roleOptions)
                    ->value(!empty($roles) ? $roles[0]->name : '')
                    ->width(Size::full())
            );
        }

        $container->add(
            UI::input('input_pin')
                ->label(t($prefix . 'device_pin_label'))
                ->placeholder('Ej: 4419')
                ->value('')
                ->required(true)
                ->type('number')
                ->width(Size::full())
        );

        $buttonsContainer = UI::container('dialog_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->gap(Spacing::px(8));

        $buttonsContainer->add(
            UI::button('btn_cancel_pairing')
                ->label(t('modal.cancel'))
                ->style('secondary')
                ->action($cancelAction, [
                    '_caller_service_id' => $callerServiceId,
                ])
        );

        $buttonsContainer->add(
            UI::button('btn_submit_pairing')
                ->label(t($prefix . 'pair_device'))
                ->style('primary')
                ->action($submitAction, [
                    '_caller_service_id' => $callerServiceId,
                ])
        );

        $container->add($buttonsContainer);

        return $container->toJson();
    }
}
