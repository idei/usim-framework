<?php

namespace App\Services\User;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;

class UserService
{
    /**
     * Find user by ID
     *
     * @param int $userId
     * @return User|null
     */
    public function findUser(int $userId): ?User
    {
        return User::find($userId);
    }

    /**
     * Get user with roles
     *
     * @param int $userId
     * @return array Response array with status, message, and data
     */
    public function getUser(int $userId): array
    {
        $user = User::find($userId);

        if (!$user) {
            return [
                'status' => 'error',
                'message' => 'Usuario no encontrado',
                'errors' => ['user_id' => ['The user does not exist.']],
            ];
        }

        return [
            'status' => 'success',
            'message' => "Usuario $user->name recuperado exitosamente",
            'data' => $user->load('roles')->toArray(),
        ];
    }

    /**
     * Update user with roles and optional email notifications
     *
     * @param User $user
     * @param array $data Update data (name, email, password, password_confirmation, roles, send_reset_email, send_verification_email)
     * @return array Response array with status, message, and data
     */
    public function updateUser(User $user, array $data): array
    {
        // Validate common update fields
        $validated = [
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'password' => $data['password'] ?? null,
            'password_confirmation' => $data['password_confirmation'] ?? null,
            'roles' => $data['roles'] ?? null,
            'send_reset_email' => $data['send_reset_email'] ?? false,
            'send_verification_email' => $data['send_verification_email'] ?? false,
        ];

        // Remove null values
        $validated = array_filter($validated, fn($value) => $value !== null);

        // Update basic fields (name, email, password)
        $updateData = [];

        if (isset($validated['name'])) {
            // Validate name
            if (!is_string($validated['name']) || strlen($validated['name']) > 255) {
                return [
                    'status' => 'error',
                    'message' => 'Validation errors',
                    'errors' => ['name' => ['The name must be a string with max 255 characters.']],
                ];
            }
            $updateData['name'] = $validated['name'];
        }

        if (isset($validated['email'])) {
            // Validate email
            if (!filter_var($validated['email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'status' => 'error',
                    'message' => 'Validation errors',
                    'errors' => ['email' => ['The email must be a valid email address.']],
                ];
            }

            // Check if email is unique (excluding current user)
            $existingUser = User::where('email', $validated['email'])
                ->where('id', '!=', $user->id)
                ->first();

            if ($existingUser) {
                return [
                    'status' => 'error',
                    'message' => 'Validation errors',
                    'errors' => ['email' => ['The email has already been taken.']],
                ];
            }

            $updateData['email'] = $validated['email'];
        }

        if (isset($validated['password'])) {
            // Validate password
            if (strlen($validated['password']) < 8) {
                return [
                    'status' => 'error',
                    'message' => 'Validation errors',
                    'errors' => ['password' => ['The password must be at least 8 characters.']],
                ];
            }

            // Check password confirmation
            if (!isset($validated['password_confirmation']) || $validated['password'] !== $validated['password_confirmation']) {
                return [
                    'status' => 'error',
                    'message' => 'Validation errors',
                    'errors' => ['password' => ['The password confirmation does not match.']],
                ];
            }

            $updateData['password'] = Hash::make($validated['password']);
        }

        // Update user
        if (!empty($updateData)) {
            $user->update($updateData);
        }

        // Sync roles if provided
        if (isset($validated['roles']) && is_array($validated['roles'])) {
            // Validate roles exist
            $invalidRoles = [];
            foreach ($validated['roles'] as $roleName) {
                // Check if role exists using the roles table
                $roleExists = \DB::table('roles')
                    ->where('name', $roleName)
                    ->exists();

                if (!$roleExists) {
                    $invalidRoles[] = $roleName;
                }
            }

            if (!empty($invalidRoles)) {
                return [
                    'status' => 'error',
                    'message' => 'Validation errors',
                    'errors' => ['roles' => ['The role ' . implode(', ', $invalidRoles) . ' does not exist.']],
                ];
            }

            $user->syncRoles($validated['roles']);
        }

        // Send reset email if requested
        if (!empty($validated['send_reset_email'])) {
            $token = PasswordBroker::createToken($user);
            $user->sendPasswordResetNotification($token);
        }

        // Send verification email if requested
        if (!empty($validated['send_verification_email'])) {
            $user->email_verified_at = null;
            $user->save();
            $user->sendEmailVerificationNotification();
        }

        return [
            'status' => 'success',
            'message' => 'Usuario actualizado exitosamente',
            'data' => $user->fresh()->load('roles')->toArray(),
        ];
    }

    /**
     * Delete user with authorization check
     *
     * @param User $user
     * @return array Response array with status and message
     */
    public function deleteUser(User $user): array
    {
        // Delete the user if it is different from the currently authenticated user
        if (Auth::id() === $user->id) {
            return [
                'status' => 'error',
                'message' => 'No se puede eliminar el usuario autenticado actualmente',
            ];
        }

        $userName = $user->name;
        $user->delete();

        return [
            'status' => 'success',
            'message' => "Usuario $userName eliminado exitosamente",
        ];
    }
}
