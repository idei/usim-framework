<?php

namespace App\UI\Components\DataTable;

use App\Services\User\UserListingService;
use Idei\Usim\DataTable\AbstractDataTableModel;
use Idei\Usim\Components\Table;

/**
 * User API Table Model
 *
 * Implementation for real User model from database
 */
class UserApiTableModel extends AbstractDataTableModel
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
            'email' => ['label' => t('datatable.user_api.columns.email'), 'width' => 250, 'sort_by' => 'email'],
            'email_verified' => ['label' => t('datatable.user_api.columns.email_verified'), 'width' => 100, 'sort_by' => 'email_verified_at'],
            'roles' => ['label' => t('datatable.user_api.columns.roles'), 'width' => 100, 'sort_by' => 'role_name'],
            'updated_at' => ['label' => t('datatable.user_api.columns.updated_at'), 'width' => 150, 'sort_by' => 'updated_at'],
            'edit' => ['label' => '', 'width' => 20],
            'delete' => ['label' => '', 'width' => 20],
        ];
    }

    protected function getAllData(): array
    {
        return [];
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
            $roles = '';
            if (is_array($user)) {
                $roles = (string) ($user['roles'] ?? '');
            } else {
                $roles = $user->roles
                    ->pluck('name')
                    ->sort()
                    ->values()
                    ->implode(', ');
            }

            $emailVerified = is_array($user)
                ? !empty($user['email_verified'])
                : (bool) ($user->email_verified_at ?? false);

            $updatedAt = is_array($user)
                ? (string) ($user['updated_at'] ?? '')
                : (string) ($user->updated_at?->diffForHumans() ?? '');

            $userId = is_array($user)
                ? ($user['id'] ?? null)
                : ($user->id ?? null);

            $formatted[] = [
                'name' => is_array($user) ? ($user['name'] ?? '') : (string) ($user->name ?? ''),
                'email' => is_array($user) ? ($user['email'] ?? '') : (string) ($user->email ?? ''),
                'email_verified' => $emailVerified ? '✅' : '⚠️',
                'roles' => $roles,
                'updated_at' => $updatedAt,
                'edit' => [
                    'button' => [
                        'label' => "✏️",
                        'action' => 'edit_user',
                        'style' => 'secondary',
                        'parameters' => [
                            'user_id' => $userId,
                        ]
                    ]
                ],
                'delete' => [
                    'button' => [
                        'label' => "🗑️",
                        'action' => 'delete_user',
                        'style' => 'danger',
                        'parameters' => [
                            'user_id' => $userId,
                        ]
                    ]
                ],
            ];
        }

        return $formatted;
    }
}
