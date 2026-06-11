<?php

namespace App\UI\Screens\Admin\TableModels;

use App\Services\Role\RoleListingService;
use Idei\Usim\Components\Table;
use Idei\Usim\DataTable\AbstractTableModel;

class RoleTableModel extends AbstractTableModel
{
    private RoleListingService $listingService;

    public function __construct(Table $tableBuilder)
    {
        parent::__construct($tableBuilder);
        $this->listingService = app(RoleListingService::class);
    }

    public function getColumns(): array
    {
        return [
            'name' => [
                'label' =>
                    t('screen.admin.users_manager.roles_column_name'),
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

        return $result['items'];
    }

    public function getFormattedPageData(int $currentPage, int $perPage): array
    {
        $roles = $this->getPageData();
        $formatted = [];

        foreach ($roles as $role) {
            $formatted[] = [
                '_model_id' => $role->id,
                'name' => t("role.{$role->name}.name"),
            ];
        }

        return $formatted;
    }
}
