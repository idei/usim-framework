<?php

namespace App\UI\Screens\Admin\Concerns;

use App\UI\Screens\Admin\TableModels\DeviceTableModel;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\SelectionMode;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

trait BuildsDevicesSection
{
    protected const DEVICES_I18N_PREFIX = 'screen.admin.users_manager.';

    protected function buildDevicesCrudContainer(): Container
    {
        $devicesCrudContainer = UI::container('devices_crud_container')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(4))
            ->rounded(0)
            ->height(Size::px(340))
            ->plain();

        $toolbar = UI::container('devices_toolbar')
            ->layout(LayoutType::HORIZONTAL)
            ->fullWidth()
            ->padding(Spacing::px(10))
            ->gap(Spacing::px(10));

        $search = UI::input('search_devices')
            ->placeholder(t(self::DEVICES_I18N_PREFIX . 'devices_search_placeholder'))
            ->width(Size::px(300))
            ->autocomplete('off')
            ->onInput('search_devices', [])
            ->debounce(500);

        $addBtn = UI::button('add_device_btn')
            ->label(t(self::DEVICES_I18N_PREFIX . 'add_device'))
            ->style('secondary')
            ->action('add_device_clicked')
            ->icon('plus');

        $pairBtn = UI::button('pair_device_btn')
            ->label(t(self::DEVICES_I18N_PREFIX . 'pair_device'))
            ->style('secondary')
            ->action('pair_device_clicked')
            ->icon('link');

        $toolbar
            ->add($search)
            ->add($addBtn)
            ->add($pairBtn);

        $devicesTable = UI::table('devices_table');
        $devicesTable->pagination(7);
        $devicesTable->sortedBy('name');
        $devicesTable->dataModel(DeviceTableModel::class);
        $devicesTable->selectionMode(SelectionMode::SINGLE);
        $devicesTable->bodyOverflowX('hidden');
        $devicesTable->bodyOverflowY('auto');
        $devicesTable->minHeight(Size::px(440));
        $devicesTable->bodyMinHeight('340px');
        $devicesTable->align('center');

        $devicesCrudContainer
            ->add($toolbar)
            ->add($devicesTable);

        return $devicesCrudContainer;
    }
}

