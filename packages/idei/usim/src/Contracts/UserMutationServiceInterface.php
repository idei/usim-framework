<?php

namespace Idei\Usim\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contract for administrative user mutations and retrieval operations.
 */
interface UserMutationServiceInterface
{
    /**
     * Find user model by ID.
     */
    public function findUser(int $userId): ?Model;

    /**
     * Get detailed user data array including roles and units.
     *
     * @return array{status: string, message: string, data?: array<string, mixed>, errors?: array<string, mixed>}
     */
    public function getUser(int $userId): array;

    /**
     * Update user attributes, roles, and unit assignments.
     *
     * @param  array<string, mixed>  $data
     * @return array{status: string, message: string, data?: array<string, mixed>, errors?: array<string, mixed>}
     */
    public function updateUser(Model $user, array $data): array;

    /**
     * Delete a user.
     *
     * @return array{status: string, message: string}
     */
    public function deleteUser(Model $user): array;
}
