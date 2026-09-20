<?php

namespace Idei\Usim\Console\Commands\Concerns;

trait InstallsActorsManagerScaffolding
{
    protected function installActorsManagerScaffolding(): void
    {
        $this->newLine();
        $this->info('Installing Users Devices Manager scaffolding...');

        $this->installModel('Device.php.stub', 'Device.php');
        $base = 'Device';
        $this->installScreen("$base/DevicePairingScreen.php.stub", 'DevicePairingScreen.php', $base);
        $this->installScreen("$base/KioskScreen.php.stub", 'KioskScreen.php', $base);
        $this->installComponent("Modals/DevicePairingDialog.php.stub", 'DevicePairingDialog.php', 'Modals');
        $this->installComponent("Modals/EditDeviceDialog.php.stub", 'EditDeviceDialog.php', 'Modals');

        $base = 'Admin';
        $tableModelsBase = "$base/TableModels";
        $presentersBase = "$base/Presenters";
        $concernsBase = "$base/Concerns";

        $this->installScreen("$base/UsersManager.php.stub", 'UsersManager.php', $base);

        $this->installScreen("$tableModelsBase/DeviceTableModel.php.stub", 'DeviceTableModel.php', $tableModelsBase);
        $this->installScreen("$tableModelsBase/PermissionTableModel.php.stub", 'PermissionTableModel.php', $tableModelsBase);
        $this->installScreen("$tableModelsBase/RoleTableModel.php.stub", 'RoleTableModel.php', $tableModelsBase);
        $this->installScreen("$tableModelsBase/UserTableModel.php.stub", 'UserTableModel.php', $tableModelsBase);

        $this->installScreen("$presentersBase/UserEditDialogPresenter.php.stub", 'UserEditDialogPresenter.php', $presentersBase);

        $this->installScreen("$concernsBase/HandlesModalFeedback.php.stub", 'HandlesModalFeedback.php', $concernsBase);
        $this->installScreen("$concernsBase/HandlesScreenParameters.php.stub", 'HandlesScreenParameters.php', $concernsBase);
        $this->installScreen("$concernsBase/ManagesDevicesSection.php.stub", 'ManagesDevicesSection.php', $concernsBase);
        $this->installScreen("$concernsBase/ManagesRolesSection.php.stub", 'ManagesRolesSection.php', $concernsBase);
        $this->installScreen("$concernsBase/ManagesUsersSection.php.stub", 'ManagesUsersSection.php', $concernsBase);
        $this->installScreen("$concernsBase/ResolvesActiveUnitContext.php.stub", 'ResolvesActiveUnitContext.php', $concernsBase);
    }
}
