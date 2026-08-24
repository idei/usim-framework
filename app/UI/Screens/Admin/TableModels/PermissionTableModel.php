<?php

// @usim: feature="admin", type="screen"

namespace App\UI\Screens\Admin\TableModels;

use App\Services\Permissions\PermissionListingService;
use Idei\Usim\Components\Table;
use Idei\Usim\DataTable\AbstractTableModel;
use Spatie\Permission\Models\Permission;

class PermissionTableModel extends AbstractTableModel
{
    private PermissionListingService $listingService;

    public function __construct(Table $tableBuilder)
    {
        parent::__construct($tableBuilder);
        $this->listingService = app(PermissionListingService::class);
    }

    public function getColumns(): array
    {
        return [
            'roles' => [
                'label' => t('screen.admin.users_manager.permissions_column_roles'),
                'width' => 25,
            ],
            'name' => [
                'label' => t('screen.admin.users_manager.permissions_column_name'),
                'width' => 200,
                'sort_by' => 'name'
            ],
        ];
    }

    protected function countTotal(): int
    {
        return $this->listingService->countMatching(
            $this->tableBuilder->getSearchTerm()
        );
    }

    public function getPageData(): array
    {
        $permissions = $this->getPermissionItems();

        return array_map(
            fn(Permission $permission): array => [
                'id' => $permission->id,
                'roles' => $this->formatRoleIds($permission),
                'name' => $permission->name,
            ],
            $permissions
        );
    }

    /**
     * @return list<Permission>
     */
    private function getPermissionItems(): array
    {
        $pagination = $this->tableBuilder->getPaginationData();
        $sortBy = $this->tableBuilder->getSortColumn();
        $sortDir = $this->tableBuilder->getSortDirection();
        $searchTerm = $this->tableBuilder->getSearchTerm();

        $result = $this->listingService->paginate(
            page: (int) $pagination['current_page'],
            perPage: (int) $pagination['per_page'],
            search: $searchTerm ?: null,
            sortField: $sortBy ?: null,
            sortDirection: (string) ($sortDir ?: 'asc'),
        );

        return array_values($result['items']);
    }

    public function getFormattedPageData(int $currentPage, int $perPage): array
    {
        $permissions = $this->getPermissionItems();
        $formatted = [];

        foreach ($permissions as $permission) {
            $formatted[] = [
                '_model_id' => $permission->id,
                'roles' => $this->formatRoleIds($permission),
                'name' => t("permission.{$permission->name}"),
            ];
        }

        return $formatted;
    }

    private function formatRoleIds(Permission $permission): string
    {
        $roleIds = $permission->roles
            ->pluck('id')
            ->filter(static fn($id): bool => $id !== null && $id !== '')
            ->values()
            ->toArray();

        return $roleIds !== [] ? implode(', ', $roleIds) : '-';
    }
}
