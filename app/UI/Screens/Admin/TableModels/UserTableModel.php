<?php

// @usim: feature="admin", type="screen"

namespace App\UI\Screens\Admin\TableModels;

use App\Models\User;
use App\Services\User\UserListingService;
use Idei\Usim\Components\Table;
use Idei\Usim\DataTable\AbstractTableModel;

class UserTableModel extends AbstractTableModel
{
    protected UserListingService $listingService;

    public function __construct(Table $tableBuilder)
    {
        parent::__construct($tableBuilder);

        // Resolve listing service from container since Table doesn't support DI
        $this->listingService = app(UserListingService::class);
    }

    public function getColumns(): array
    {
        $prefix = 'screen.admin.users_manager.table';
        return [
            'name' => ['label' => t("{$prefix}.name"), 'width' => 250, 'sort_by' => 'name'],
            'email' => ['label' => t("{$prefix}.email"), 'width' => 200, 'sort_by' => 'email'],
            'email_verified' => ['label' => t("{$prefix}.email_verified"), 'width' => 100, 'sort_by' => 'email_verified_at'],
            'roles' => ['label' => t("{$prefix}.roles"), 'width' => 200, 'sort_by' => 'role_name']
        ];
    }

    protected function countTotal(): int
    {
        $searchTerm = $this->tableBuilder->getSearchTerm();
        return $this->listingService->countMatching($searchTerm);
    }

    public function getPageData(): array
    {
        $users = $this->getUserItems();

        return array_map(
            static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
            ],
            $users
        );
    }

    /**
     * @return list<User>
     */
    private function getUserItems(): array
    {
        $paginationData = $this->tableBuilder->getPaginationData();
        $sortBy = $this->tableBuilder->getSortColumn();
        $sortDirection = $this->tableBuilder->getSortDirection();
        $searchTerm = $this->tableBuilder->getSearchTerm();

        $result = $this->listingService->paginate(
            page: (int) $paginationData['current_page'],
            perPage: (int) $paginationData['per_page'],
            search: $searchTerm ?: null,
            sortField: $sortBy ?: null,
            sortDirection: (string) ($sortDirection ?: 'asc'),
        );

        return array_values($result['items']);
    }

    public function getFormattedPageData(int $currentPage, int $perPage): array
    {
        $users = $this->getUserItems();
        $formatted = [];

        foreach ($users as $user) {
            $roles = $this->formatRoles($user);

            $emailVerified = $user->email_verified_at ?? false;

            $formatted[] = [
                '_model_id' => $user->id,
                'name' => $user->name ?? '',
                'email' => $user->email ?? '',
                'email_verified' => $emailVerified ? '✅' : '⚠️',
                'roles' => $roles,
            ];
        }

        return $formatted;
    }

    private function formatRoles(User $user): string
    {
        /** @var list<string> $roleNames */
        $roleNames = $user->roles
            ->pluck('name')
            ->filter(static fn ($name): bool => is_string($name))
            ->values()
            ->toArray();

        /** @var list<string> $roles */
        $roles = array_map(static fn (string $name): string => t("role.$name.name"), $roleNames);

        return $roles ? implode(', ', $roles) : t('role.none');
    }
}
