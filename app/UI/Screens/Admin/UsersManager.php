<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens\Admin;

use App\Models\User;
use App\Services\Auth\RegisterService;
use App\Services\Device\DeviceService;
use App\Services\Role\RoleService;
use App\Services\Units\UnitContextResolver;
use App\Services\User\UserService;
use App\UI\Components\Modals\DevicePairingDialog;
use App\UI\Components\Modals\EditDeviceDialog;
use App\UI\Components\Modals\EditUserDialog;
use App\UI\Components\Modals\RegisterDialog;
use App\UI\Screens\Admin\Concerns\BuildsDevicesSection;
use App\UI\Screens\Admin\Concerns\BuildsRolesSection;
use App\UI\Screens\Admin\Concerns\BuildsUsersSection;
use App\UI\Screens\Admin\Concerns\HandlesModalFeedback;
use App\UI\Screens\Admin\Concerns\HandlesScreenParameters;
use App\UI\Screens\Admin\Presenters\UserEditDialogPresenter;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Split;
use Idei\Usim\Components\Table;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Modals\ConfirmDialogService;
use Idei\Usim\Models\UsimRole;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

class UsersManager extends Screen
{
    use HandlesScreenParameters;
    use HandlesModalFeedback;
    use BuildsUsersSection;
    use BuildsDevicesSection;
    use BuildsRolesSection;

    private const I18N_PREFIX = 'screen.admin.users_manager.';

    protected Table $users_table;
    protected Table $devices_table;
    protected Table $roles_table;
    protected Table $permissions_table;
    protected Input $search_users;
    protected Input $search_devices;
    protected Button $add_user_btn;
    protected Button $add_device_btn;
    protected Button $pair_device_btn;
    protected Split $roles_split;
    protected Container $tabs_container;
    protected string $store_unit = '';
    protected DeviceService $deviceService;

    public function __construct(
        protected RegisterService $registerService,
        protected UserService $userService,
        protected RoleService $roleService,
        ?DeviceService $deviceService = null,
        protected ?UserEditDialogPresenter $userEditDialogPresenter = null,
    ) {
        $this->deviceService = $deviceService ?? app(DeviceService::class);
        $this->userEditDialogPresenter = $userEditDialogPresenter ?? new UserEditDialogPresenter();
    }

    public static function authorize(): bool
    {
        return self::requirePermission('admin.users_manager.access');
    }

    public static function getMenuLabel(): string
    {
        return t(self::I18N_PREFIX . 'menu_label');
    }

    public static function getMenuIcon(): ?string
    {
        return '🛠️';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->plain()
            ->maxWidth(Size::px(1280))
            ->padding(Spacing::px(0))
            ->centerHorizontal();

        $this->tabs_container = UI::container('tabs_container')
            ->width(Size::full())
            ->padding(Spacing::px(10))
            ->minHeight(Size::px(620))
            ->rounded(0)
            ->gap(Spacing::px(2))
            ->tabs(
                [
                    'users_tab' => ['label' => t(self::I18N_PREFIX . 'users_tab')],
                    'devices_tab' => ['label' => t(self::I18N_PREFIX . 'devices_tab')],
                    'roles_tab' => [
                        'label' => t(self::I18N_PREFIX . 'roles_tab'),
                        'disabled' => !$this->userCan('manage.roles'),
                    ],
                ],
                'users_tab'
            );

        $this->tabs_container->add($this->buildUsersCrudContainer(), tab: 'users_tab');
        $this->tabs_container->add($this->buildDevicesCrudContainer(), tab: 'devices_tab');
        $this->tabs_container->add($this->buildRolesContainer(), tab: 'roles_tab');
        $container->add($this->tabs_container);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onAddUserClicked(array $params): void
    {
        RegisterDialog::open(
            fakeData: config('app.env') === 'local',
            askForRole: true,
            callerServiceId: $this->getScreenComponentId()
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onUsersTableColumnClicked(array $params): void
    {
        $this->handleTableSort($this->users_table, $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onDevicesTableColumnClicked(array $params): void
    {
        $this->handleTableSort($this->devices_table, $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onRolesTableColumnClicked(array $params): void
    {
        $this->handleTableSort($this->roles_table, $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onPermissionsTableColumnClicked(array $params): void
    {
        $this->handleTableSort($this->permissions_table, $params);
    }

    /**
     * Generic table sorting helper (DRY).
     *
     * @param Table $table
     * @param array<string, mixed> $params
     */
    private function handleTableSort(Table $table, array $params): void
    {
        $column = $this->optionalStringParam($params, 'sort_by');
        if ($column === null || $column === '') {
            return;
        }

        $table->sortedBy($column);
        $table->page(1);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSubmitRegister(array $params): void
    {
        $response = $this->registerService->register(
            name: $this->stringParamOrDefault($params, 'name', ''),
            email: $this->stringParamOrDefault($params, 'email', ''),
            password: $this->stringParamOrDefault($params, 'password', ''),
            passwordConfirmation: $this->stringParamOrDefault($params, 'password_confirmation', ''),
            roles: $this->normalizeRoles($params['roles'] ?? null),
            sendVerificationEmail: $this->boolParamOrDefault($params, 'send_verification_email', true)
        );

        $status = $response['status'];
        $message = $response['message'];

        if ($status === 'success') {
            $this->toast($message, 'success');
            $this->users_table->refresh();
            $this->closeModal();
        } else {
            $this->updateModalWithErrors($response['errors'] ?? []);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onUsersTableRowClicked(array $params): void
    {
        $userId = $this->optionalIntParam($params, 'model_id');
        if ($userId === null) {
            $this->toast(t('User ID is required'), 'error');
            return;
        }

        $response = $this->userService->getUser($userId);
        if ($response['status'] !== 'success' || empty($response['data'])) {
            $this->toast($response['message'], 'error');
            return;
        }

        $this->users_table->select($userId);

        $activeUnit = $this->resolveActiveUnit();
        $presenter = $this->userEditDialogPresenter ?? new UserEditDialogPresenter();
        $modalUser = $presenter->present($response['data'], $activeUnit);
        if ($modalUser === null) {
            $this->toast(t('User not found'), 'error');
            return;
        }

        EditUserDialog::open(
            user: $modalUser,
            callerServiceId: $this->getScreenComponentId()
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSubmitUpdateUser(array $params): void
    {
        $userId = $this->optionalIntParam($params, 'user_id');
        if ($userId === null) {
            $this->toast(t('User ID is required for update'), 'error');
            return;
        }

        $user = $this->userService->findUser($userId);
        if (!$user) {
            $this->toast(t('User not found'), 'error');
            return;
        }

        $updateData = $params;
        if (isset($updateData['roles'])) {
            $updateData['roles'] = (array) $updateData['roles'];
        }

        $activeUnit = $this->resolveActiveUnit();
        if ($activeUnit) {
            $updateData['target_unit'] = $activeUnit->id;
        }

        $response = $this->userService->updateUser($user, $updateData);
        $status = $response['status'];
        $message = $response['message'];

        if ($status === 'success') {
            $this->toast($message, 'success');
            $this->users_table->refresh();
            $this->closeModal();
        } else {
            $this->toast($message, 'error');
            $this->updateModalWithErrors($response['errors'] ?? []);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onDeleteUser(array $params): void
    {
        $userId = $this->optionalIntParam($params, 'user_id');
        if ($userId === null) {
            $this->toast(t('User ID is required'), 'error');
            return;
        }

        $response = $this->userService->getUser($userId);
        if ($response['status'] !== 'success' || empty($response['data'])) {
            $this->toast(t('User not found'), 'error');
            return;
        }

        $user = $response['data'];
        $name = $user['name'] ?? null;
        $userName = is_string($name) ? $name : '';

        ConfirmDialogService::open(
            type: DialogType::WARNING,
            title: t("Delete User"),
            message: t("Are you sure you want to delete user '{$userName}'?"),
            confirmAction: 'confirm_delete_user',
            confirmParams: ['user_id' => $userId],
            callerServiceId: $this->getScreenComponentId()
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onConfirmDeleteUser(array $params): void
    {
        $userId = $this->optionalIntParam($params, 'user_id');
        if ($userId === null) {
            $this->toast(t('User ID is required for deletion'), 'error');
            return;
        }

        $user = $this->userService->findUser($userId);
        if (!$user) {
            $this->toast(t('User not found'), 'error');
            return;
        }

        $response = $this->userService->deleteUser($user);
        $status = $response['status'];
        $message = $response['message'];

        $this->toast($message, $status);
        $this->users_table->refresh();
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onChangePage(array $params): void
    {
        $page = $this->intParamOrDefault($params, 'page', 1);
        $rawCompId = $this->optionalIntParam($params, '_component_id')
            ?? request()->input('component_id');
        $componentId = is_numeric($rawCompId) ? (int) $rawCompId : null;

        if (isset($this->devices_table) && $componentId !== null && $componentId === $this->devices_table->getId()) {
            $this->devices_table->page($page);
            return;
        }

        $this->users_table->page($page);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSearchUsers(array $params): void
    {
        $search = trim($this->searchParam($params, ['value', 'search_users']));
        $this->users_table->setSearchTerm($search);
        $this->search_users->value($search);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSearchDevices(array $params): void
    {
        $search = trim($this->searchParam($params, ['value', 'search_devices']));
        $this->devices_table->setSearchTerm($search);
        $this->search_devices->value($search);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onAddDeviceClicked(array $params): void
    {
        EditDeviceDialog::open(
            callerServiceId: $this->getScreenComponentId()
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onPairDeviceClicked(array $params): void
    {
        $deviceId = $this->optionalIntParam($params, 'device_id');
        if ($deviceId === null) {
            $selected = $this->devices_table->select();
            if (is_int($selected) || (is_string($selected) && is_numeric($selected))) {
                $deviceId = (int) $selected;
            }
        }

        $device = $deviceId !== null ? $this->deviceService->getDevice($deviceId) : null;
        $devicesOptions = [];
        if (!$device) {
            $devices = $this->deviceService->getAllDevices();
            foreach ($devices as $d) {
                $devicesOptions[] = [
                    'value' => $d->id,
                    'label' => $d->name,
                ];
            }
        }

        DevicePairingDialog::open(
            device: $device,
            devicesOptions: $devicesOptions,
            callerServiceId: $this->getScreenComponentId()
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onDevicesTableRowClicked(array $params): void
    {
        $deviceId = $this->selectableId($params, 'model_id');
        if ($deviceId === null) {
            $this->toast('Dispositivo no encontrado', 'error');
            return;
        }

        $device = $this->deviceService->getDevice($deviceId);
        if (!$device) {
            $this->toast('Dispositivo no encontrado', 'error');
            return;
        }

        $this->devices_table->select($deviceId);

        EditDeviceDialog::open(
            device: $device,
            callerServiceId: $this->getScreenComponentId()
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSubmitSaveDevice(array $params): void
    {
        $deviceId = $this->optionalIntParam($params, 'device_id') ?? $this->optionalIntParam($params, 'id');
        $name = trim($this->stringParamOrDefault($params, 'device_name', $this->stringParamOrDefault($params, 'name', '')));

        if ($name === '') {
            $this->toast(t('validation.required', ['attribute' => t(self::I18N_PREFIX . 'device_name_label')]), 'error');
            return;
        }

        $rawUnitId = $params['device_unit_id'] ?? $params['unit_id'] ?? null;
        $unitId = null;
        if (is_int($rawUnitId) || (is_string($rawUnitId) && $rawUnitId !== '' && $rawUnitId !== '0')) {
            $unitId = is_numeric($rawUnitId) ? (int) $rawUnitId : $rawUnitId;
        }

        $rawRoles = $params['device_roles'] ?? $params['roles'] ?? [];
        if (is_string($rawRoles)) {
            $roles = [$rawRoles];
        } elseif (is_array($rawRoles)) {
            $roles = array_values(array_filter($rawRoles, static fn($r): bool => is_string($r) && $r !== ''));
        } else {
            $roles = [];
        }

        if ($deviceId) {
            $this->deviceService->updateDevice($deviceId, [
                'name' => $name,
                'unit_id' => $unitId,
                'roles' => $roles,
            ]);
            $this->toast(t(self::I18N_PREFIX . 'device_updated'), 'success');
        } else {
            $this->deviceService->createDevice([
                'name' => $name,
                'unit_id' => $unitId,
                'roles' => $roles,
            ]);
            $this->toast(t(self::I18N_PREFIX . 'device_created'), 'success');
        }

        $this->devices_table->refresh();
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onSubmitApproveDevicePairing(array $params): void
    {
        $deviceId = $this->optionalIntParam($params, 'pairing_device_id')
            ?? $this->optionalIntParam($params, 'device_id');
        $pin = trim($this->stringParamOrDefault($params, 'input_pin', $this->stringParamOrDefault($params, 'pin', '')));

        if ($deviceId === null) {
            $this->toast(t(self::I18N_PREFIX . 'device_select_label'), 'error');
            return;
        }

        if (strlen($pin) !== 4) {
            $this->toast(t(self::I18N_PREFIX . 'device_pin_length_error'), 'error');
            return;
        }

        $result = $this->deviceService->pairDevice($deviceId, $pin);
        if ($result['success']) {
            $this->toast($result['message'], 'success');
            $this->devices_table->refresh();
            $this->closeModal();
        } else {
            $this->toast($result['message'], 'error');
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onUnpairDevice(array $params): void
    {
        $deviceId = $this->optionalIntParam($params, 'device_id');
        if ($deviceId === null) {
            $this->toast(t('Device ID is required'), 'error');
            return;
        }

        $this->deviceService->unpairDevice($deviceId);
        $this->toast(t(self::I18N_PREFIX . 'device_unpaired'), 'success');
        $this->devices_table->refresh();
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onDeleteDevice(array $params): void
    {
        $deviceId = $this->optionalIntParam($params, 'device_id');
        if ($deviceId === null) {
            $this->toast(t('Device ID is required'), 'error');
            return;
        }

        $device = $this->deviceService->getDevice($deviceId);
        if (!$device) {
            $this->toast(t('Device not found'), 'error');
            return;
        }

        ConfirmDialogService::open(
            type: DialogType::WARNING,
            title: t(self::I18N_PREFIX . 'device_delete_confirm_title'),
            message: t(self::I18N_PREFIX . 'device_delete_confirm_msg', ['name' => $device->name]),
            confirmAction: 'confirm_delete_device',
            confirmParams: ['device_id' => $deviceId],
            callerServiceId: $this->getScreenComponentId()
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onConfirmDeleteDevice(array $params): void
    {
        $deviceId = $this->optionalIntParam($params, 'device_id');
        if ($deviceId === null) {
            $this->toast(t('Device ID is required for deletion'), 'error');
            return;
        }

        $deleted = $this->deviceService->deleteDevice($deviceId);
        if ($deleted) {
            $this->toast(t(self::I18N_PREFIX . 'device_deleted'), 'success');
        }

        $this->devices_table->refresh();
        $this->closeModal();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onRolesTableRowClicked(array $params): void
    {
        if (!$this->userCan('manage.roles')) {
            $this->toast(t('You don\'t have permission to manage roles'), 'error');
            return;
        }

        $roleId = $this->selectableId($params, 'model_id');
        if ($roleId === null) {
            $this->toast(t('Role ID is required'), 'error');
            return;
        }

        $this->roles_table->select($roleId);

        $permissionIds = $this->roleService->getPermissionIds($roleId);
        $this->permissions_table->select($permissionIds);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function onPermissionsTableRowClicked(array $params): void
    {
        if (!$this->userCan('manage.roles')) {
            $this->toast(t('You don\'t have permission to manage roles'), 'error');
            return;
        }

        $selectedRole = $this->roles_table->select();
        $roleId = is_array($selectedRole) ? ($selectedRole[0] ?? null) : $selectedRole;

        if ($roleId instanceof Table || $roleId === null || $roleId === '') {
            $this->toast(t(self::I18N_PREFIX . 'role_selection_warning'), 'warning');
            return;
        }

        $permissionId = $this->selectableId($params, 'model_id');
        if ($permissionId === null) {
            $this->toast(t('Permission ID is required'), 'error');
            return;
        }

        $this->roleService->togglePermission($roleId, $permissionId);

        $permissionIds = $this->roleService->getPermissionIds($roleId);
        $this->permissions_table->select($permissionIds);
    }

    /**
     * @param mixed $roles
     * @return list<string>
     */
    private function normalizeRoles(mixed $roles): array
    {
        if (is_string($roles)) {
            return [$roles];
        }

        if (!is_array($roles)) {
            return ['user'];
        }

        $normalized = [];
        foreach ($roles as $role) {
            if (is_string($role)) {
                $normalized[] = $role;
            }
        }

        return $normalized !== [] ? $normalized : ['user'];
    }

    /**
     * Legacy proxy for backwards compatibility.
     *
     * @deprecated Use UserEditDialogPresenter::present() instead.
     * @param array<string, mixed> $user
     * @return array<string, mixed>|null
     */
    protected function editDialogUser(array $user): ?array
    {
        $presenter = $this->userEditDialogPresenter ?? new UserEditDialogPresenter();
        return $presenter->present($user);
    }

    /**
     * Resolves the active organizational unit for the current user and context.
     */
    protected function resolveActiveUnit(): ?UsimUnit
    {
        /** @var Authenticatable|null $user */
        $user = Auth::user();
        $unitSlug = !empty($this->store_unit) ? $this->store_unit : null;
        if (!$unitSlug) {
            $requestStorage = request()->storage;
            $storageUnit = request()->input('storage.store_unit')
                ?? (is_array($requestStorage) ? $requestStorage['store_unit'] ?? null : null);
            if (is_string($storageUnit) && $storageUnit !== '') {
                $unitSlug = $storageUnit;
            }
        }

        if ($unitSlug !== null) {
            $unit = UnitContextResolver::resolve($user, $unitSlug);
            if ($unit) {
                return $unit;
            }
        }

        if (function_exists('getPermissionsTeamId') && getPermissionsTeamId()) {
            $unit = UsimUnit::find(getPermissionsTeamId());
            if ($unit) {
                return $unit;
            }
        }

        return UnitContextResolver::resolve($user, null) ?? UsimUnit::where('slug', 'main')->first();
    }
}
