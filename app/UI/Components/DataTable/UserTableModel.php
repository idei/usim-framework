<?php

namespace App\UI\Components\DataTable;

use App\Services\User\UserListingService;
use Idei\Usim\DataTable\AbstractTableModel;
use Idei\Usim\Components\Table;

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
        return [
            'name' => ['label' => t('datatable.user_api.columns.name'), 'width' => 250, 'sort_by' => 'name'],
            'email' => ['label' => t('datatable.user_api.columns.email'), 'width' => 200, 'sort_by' => 'email'],
            'email_verified' => ['label' => t('datatable.user_api.columns.email_verified'), 'width' => 100, 'sort_by' => 'email_verified_at'],
            'roles' => ['label' => t('datatable.user_api.columns.roles'), 'width' => 100, 'sort_by' => 'role_name'],
            'updated_at' => ['label' => t('datatable.user_api.columns.updated_at'), 'width' => 150, 'sort_by' => 'updated_at']
        ];
    }

    protected function countTotal(): int
    {
        $searchTerm = $this->tableBuilder->getSearchTerm();
        return $this->listingService->countMatching($searchTerm);
    }

    public function getPageData(): array
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

        return $result['items'];
    }

    public function getFormattedPageData(int $currentPage, int $perPage): array
    {
        $users = $this->getPageData();
        $formatted = [];

        foreach ($users as $user) {
            $roles = $user->roles
                ->pluck('name')
                ->sort()
                ->values()
                ->implode(', ');

            $emailVerified = $user->email_verified_at ?? false;
            $updatedAt = $user->updated_at?->diffForHumans() ?? '';

            $formatted[] = [
                '_model_id' => $user->id,
                'name' => $user->name ?? '',
                'email' => $user->email ?? '',
                'email_verified' => $emailVerified ? '✅' : '⚠️',
                'roles' => $roles,
                'updated_at' => $updatedAt,
            ];
        }

        return $formatted;
    }
}
