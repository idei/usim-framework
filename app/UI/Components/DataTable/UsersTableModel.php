<?php

namespace App\UI\Components\DataTable;

use App\UI\Screens\Demo\Support\TableDemoService;
use Idei\Usim\DataTable\AbstractDataTableModel;

/**
 * Users Table Model
 *
 * Demo table model backed by TableDemoService cache data
 */
class UsersTableModel extends AbstractDataTableModel
{

    /**
     * Get all users data
     *
     * @return array
     */
    protected function getAllData(): array
    {
        return TableDemoService::all();
    }

    /**
     * Get table columns definition
     *
     * @return array
     */
    public function getColumns(): array
    {
        return [
            'id' => ['label' => 'ID', 'width' => [75, 75]],
            'name' => ['label' => t('datatable.users_table.columns.name'), 'width' => [175, 175]],
            'email' => ['label' => t('datatable.users_table.columns.email'), 'width' => [250, 250]],
            'edit' => ['label' => '', 'width' => [50, 50]],
            'delete' => ['label' => '', 'width' => [50, 50]],
        ];
    }

    /**
     * Get formatted data for table display
     *
     * @return array
     */
    public function getFormattedPageData(int $currentPage, int $perPage): array
    {
        $users = $this->getPageData();
        $formatted = [];

        foreach ($users as $index => $user) {
            $formatted[] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'edit' => [
                    'button' => [
                        'label' => "✏️",
                        'action' => 'edit_user',
                        'parameters' => [
                            'user_id' => $user['id'],
                        ]
                    ]
                ],
                'delete' => [
                    'button' => [
                        'label' => "🗑️",
                        'action' => 'remove_user',
                        'parameters' => [
                            'user_id' => $user['id'],
                        ]
                    ],
                ],
            ];
        }

        return $formatted;
    }

    /**
     * Dado el id de un usuario, la cantidad de filas por página y la página actual,
     * determina la fila (row index) correspondiente en la página actual, o null si no está en la página.
     */
    private function getRowIndexInPage(int $userId): ?int
    {
        $pageData = $this->tableBuilder->getPaginationData();
        $currentPage = $pageData['current_page'];
        $perPage = $pageData['per_page'];

        $allData = $this->getAllData();
        $globalIndex = null;

        // Find the global index of the user
        foreach ($allData as $index => $user) {
            if ($user['id'] === $userId) {
                $globalIndex = $index;
                break;
            }
        }

        if ($globalIndex === null) {
            return null; // User not found
        }

        // Calculate start and end index for the current page
        $startIndex = ($currentPage - 1) * $perPage;
        $endIndex = $startIndex + $perPage - 1;

        // Check if the global index falls within the current page range
        if ($globalIndex >= $startIndex && $globalIndex <= $endIndex) {
            return $globalIndex - $startIndex; // Return row index within the page
        }

        return null; // User not in the current page
    }

    /**
     * Update user data
     *
     * @param int $userId
     * @param array $data
     * @return void
     */
    public function updateRow(int $userId, array $data): void
    {
        // Only update allowed fields
        $allowedFields = ['name', 'email'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updateData)) {
            return;
        }

        $updatedUser = TableDemoService::update($userId, $updateData);
        if ($updatedUser === null) {
            return;
        }

        $row = $this->getRowIndexInPage($userId);
        if ($row !== null) {
            $this->tableBuilder->editCell($row, 1, $updatedUser['name']);
            $this->tableBuilder->editCell($row, 2, $updatedUser['email']);
        }
    }

    /**
     * Delete user
     *
     * @param int $userId
     * @return bool
     */
    public function deleteUser(int $userId): bool
    {
        return TableDemoService::delete($userId);
    }
}
