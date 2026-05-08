<?php
namespace App\UI\Screens\Admin;

use App\Services\Auth\RegisterService;
use App\Services\User\UserService;
use App\UI\Components\DataTable\UserTableModel;
use App\UI\Components\Modals\EditUserDialog;
use App\UI\Components\Modals\RegisterDialog;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Table;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Modals\ConfirmDialogService;
use Idei\Usim\Screen;
use Idei\Usim\UI;

class Dashboard extends Screen
{
    public function __construct(
        protected RegisterService $registerService,
        protected UserService $userService
    ) {
    }

    public static function authorize(): bool
    {
        return self::requireRole('admin');
    }

    public static function getMenuLabel(): string
    {
        return t('screen.admin.dashboard.menu_label');
    }

    public static function getMenuIcon(): ?string
    {
        return '🛠️';
    }

    protected Table $users_table;
    protected Input $search_users;
    protected Button $add_user_btn;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->plain()
            ->maxWidth('900px')
            ->centerHorizontal();

        $tabs_container = UI::container('tabs_container')
            ->width('100%')
            ->padding('10px')
            ->minHeight('600px')
            ->rounded(0)
            ->gap('2px')
            ->tabs(
                [
                    'users_tab' => [
                        'label' => 'Users',
                    ],
                    'roles_tab' => [
                        'label' => 'Roles',
                    ],
                    'permissions_tab' => [
                        'label' => 'Permissions',
                    ],
                ],
                'users_tab'
            );

        $tabs_container->add($this->buildUsersCrudContainer(), tab: 'users_tab');
        $container->add($tabs_container);

    }

    private function buildUsersCrudContainer(): Container
    {
        $users_crud_container = UI::container('users_crud_container')
            ->layout(LayoutType::VERTICAL)
            ->gap('4px')
            ->plain();

        $toolbar = UI::container('users_toolbar')
            ->layout(LayoutType::HORIZONTAL)
            ->fullWidth()
            ->rounded(0)
            ->shadow(0)
            ->gap("5px");

        $search = UI::input('search_users')
            ->placeholder(t('screen.admin.dashboard.search_placeholder'))
            ->width('300px')
            ->autocomplete('off')
            ->onInput('search_users', [])
            ->debounce(500);

        $addBtn = UI::button('add_user_btn')
            ->label(t('screen.admin.dashboard.add_user'))
            ->style('primary')
            ->action('add_user_clicked')
            ->icon('plus');

        $toolbar->add($search)->add($addBtn);

        $users_table = UI::table('users_table')
            ->pagination(7)
            ->sortedBy('name')
            ->width('100%')
            ->dataModel(UserTableModel::class)
            ->rounded(0)
            ->rowMinHeight(45);

        $users_crud_container
            ->add($toolbar)
            ->add($users_table);

        return $users_crud_container;
    }

    protected function postLoadUI(): void
    {
        $search_users = $this->users_table->getSearchTerm();
        $this->search_users->value($search_users);
    }

    public function onAddUserClicked(array $params): void
    {
        RegisterDialog::open(
            fakeData: config('app.env') === 'local',
            askForRole: true,
            callerServiceId: $this->getScreenComponentId()
        );
    }

    public function onUsersTableColumnClicked(array $params): void
    {
        $column = $params['sort_by'] ?? null;
        if (!$column) {
            return;
        }

        $this->users_table->sortedBy($column);
        $this->users_table->page(1);
    }

    public function onSubmitRegister(array $params): void
    {
        $response = $this->registerService->register(
            name: $params['name'] ?? '',
            email: $params['email'] ?? '',
            password: $params['password'] ?? '',
            passwordConfirmation: $params['password_confirmation'] ?? '',
            roles: isset($params['roles']) ? [$params['roles']] : ['user'],
            sendVerificationEmail: (bool) ($params['send_verification_email'] ?? true)
        );

        $status = $response['status'];
        $message = $response['message'];

        if ($status === 'success') {
            $this->toast($message, 'success');
            $this->users_table->refresh();
            $this->closeModal();
        } else {
            // Update modal inputs with validation errors
            $errors = $response['errors'] ?? [];

            if (!empty($errors)) {
                $modalUpdates = [];

                foreach ($errors as $fieldName => $messages) {
                    // Concatenate all error messages for the field
                    $modalUpdates[$fieldName] = [
                        'error' => implode(' ', $messages)
                    ];
                }

                $this->updateModal($modalUpdates);
            }
        }
    }

    public function onEditUser(array $params): void
    {
        $userId = $params['user_id'] ?? null;
        if (!$userId) {
            $this->toast(t('User ID is required'), 'error');
            return;
        }

        $response = $this->userService->getUser($userId);
        if ($response['status'] !== 'success') {
            $this->toast($response['message'], 'error');
            return;
        }

        $user = $response['data'] ?? null;
        if (!$user) {
            $this->toast(t('User not found'), 'error');
            return;
        }

        EditUserDialog::open(
            user: $user,
            callerServiceId: $this->getScreenComponentId()
        );
    }

    public function onSubmitUpdateUser(array $params): void
    {
        $userId = $params['user_id'] ?? null;
        if (!$userId) {
            $this->toast(t('User ID is required for update'), 'error');
            return;
        }

        // Get the user model
        $user = $this->userService->findUser($userId);
        if (!$user) {
            $this->toast(t('User not found'), 'error');
            return;
        }

        // Prepare data for update
        $updateData = $params;
        if (isset($updateData['roles'])) {
            $updateData['roles'] = (array) $updateData['roles'];
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

            // Update modal inputs with validation errors
            $errors = $response['errors'] ?? [];

            if (!empty($errors)) {
                $modalUpdates = [];

                foreach ($errors as $fieldName => $messages) {
                    // Concatenate all error messages for the field
                    $modalUpdates[$fieldName] = [
                        'error' => implode(' ', $messages)
                    ];
                }

                $this->updateModal($modalUpdates);
            }
        }
    }

    public function onDeleteUser(array $params): void
    {
        $userId = $params['user_id'] ?? null;
        if (!$userId) {
            $this->toast(t('User ID is required'), 'error');
            return;
        }

        $response = $this->userService->getUser($userId);
        if ($response['status'] !== 'success') {
            $this->toast(t('User not found'), 'error');
            return;
        }

        $user = $response['data'] ?? null;
        if (!$user) {
            $this->toast(t('User not found'), 'error');
            return;
        }

        ConfirmDialogService::open(
            type: DialogType::WARNING,
            title: t("Delete User"),
            message: t("Are you sure you want to delete user '{$user['name']}'?"),
            confirmAction: 'confirm_delete_user',
            confirmParams: ['user_id' => $params['user_id']],
            callerServiceId: $this->getScreenComponentId()
        );
    }

    public function onConfirmDeleteUser(array $params): void
    {
        $userId = $params['user_id'] ?? null;
        if (!$userId) {
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

    public function onChangePage(array $params): void
    {
        $page = $params['page'] ?? 1;
        $this->users_table->page($page);
    }

    public function onSearchUsers(array $params): void
    {
        $search = (string) ($params['value'] ?? '');
        $this->users_table->setSearchTerm($search);
    }
}
