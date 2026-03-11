<?php
namespace App\UI\Screens\Admin;

use App\Services\Auth\RegisterService;
use App\Services\User\UserService;
use Idei\Usim\Services\UIBuilder;
use Idei\Usim\Services\Enums\DialogType;
use Idei\Usim\Services\Enums\LayoutType;
use Idei\Usim\Services\AbstractUIService;
use Idei\Usim\Services\Components\UIContainer;
use Idei\Usim\Services\Components\InputBuilder;
use Idei\Usim\Services\Components\TableBuilder;
use Idei\Usim\Services\Components\ButtonBuilder;
use App\UI\Components\DataTable\UserApiTableModel;
use App\UI\Components\Modals\EditUserDialog;
use App\UI\Components\Modals\RegisterDialog;
use Idei\Usim\Services\Modals\ConfirmDialogService;

class Dashboard extends AbstractUIService
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
        return 'Admin Dashboard';
    }

    public static function getMenuIcon(): ?string
    {
        return '🛠️';
    }

    protected TableBuilder $users_table;
    protected InputBuilder $search_users;
    protected ButtonBuilder $add_user_btn;

    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container
            ->maxWidth('1024px')
            ->centerHorizontal()
            ->padding('10px')
            ->shadow(0);

        $toolbar = UIBuilder::container('users_toolbar')
            ->layout(LayoutType::HORIZONTAL)
            ->fullWidth()
            ->shadow(0)
            ->gap("12px");

        $search = UIBuilder::input('search_users')
            ->placeholder('Search users...')
            ->width('300px')
            ->autocomplete('off')
            ->onInput('search_users', [])
            ->debounce(500);

        $addBtn = UIBuilder::button('add_user_btn')
            ->label('Add user')
            ->style('primary')
            ->action('add_user_clicked')
            ->icon('plus');

        $toolbar->add($search)->add($addBtn);
        $container->add($toolbar);

        $users_table = UIBuilder::table('users_table')
            ->pagination(7)
            ->sortedBy('name')
            ->dataModel(UserApiTableModel::class)
            ->rowMinHeight(60);

        $container->add($users_table);
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
            callerServiceId: $this->getServiceComponentId()
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

    public function onEditUser(array $params): void
    {
        $userId = $params['user_id'] ?? null;
        if (!$userId) {
            $this->toast('User ID is required', 'error');
            return;
        }

        $response = $this->userService->getUser($userId);
        if ($response['status'] !== 'success') {
            $this->toast($response['message'], 'error');
            return;
        }

        $user = $response['data'] ?? null;
        if (!$user) {
            $this->toast('User not found', 'error');
            return;
        }

        EditUserDialog::open(
            user: $user,
            callerServiceId: $this->getServiceComponentId()
        );
    }

    public function onSubmitUpdateUser(array $params): void
    {
        $userId = $params['user_id'] ?? null;
        if (!$userId) {
            $this->toast('User ID is required for update', 'error');
            return;
        }

        // Get the user model
        $user = $this->userService->findUser($userId);
        if (!$user) {
            $this->toast('User not found', 'error');
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
            $this->toast('User ID is required', 'error');
            return;
        }

        $response = $this->userService->getUser($userId);
        if ($response['status'] !== 'success') {
            $this->toast('User not found', 'error');
            return;
        }

        $user = $response['data'] ?? null;
        if (!$user) {
            $this->toast('User not found', 'error');
            return;
        }

        ConfirmDialogService::open(
            type: DialogType::WARNING,
            title: "Delete User",
            message: "Are you sure you want to delete user '{$user['name']}'?",
            confirmAction: 'confirm_delete_user',
            confirmParams: ['user_id' => $params['user_id']],
            callerServiceId: $this->getServiceComponentId()
        );
    }

    public function onConfirmDeleteUser(array $params): void
    {
        $userId = $params['user_id'] ?? null;
        if (!$userId) {
            $this->toast('User ID is required for deletion', 'error');
            return;
        }

        // Get the user
        $user = $this->userService->findUser($userId);
        if (!$user) {
            $this->toast('User not found', 'error');
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
        $search = $params['value'] ?? '';

        // Set search term
        $this->users_table->setSearchTerm($search);

        // Reset to page 1 when searching
        $this->users_table->page(1);
    }
}
