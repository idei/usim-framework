<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterService
{
    /**
     * Register a new user.
     *
     * @param string $name
     * @param string $email
     * @param string $password
     * @param string $passwordConfirmation
     * @param array $roles Roles to assign (default: ['user'])
     * @param bool $sendVerificationEmail Whether to fire the Registered event
     * @return array Response array with status, message, data, errors, and user
     */
    public function register(
        string $name,
        string $email,
        string $password,
        string $passwordConfirmation,
        array $roles = ['user'],
        bool $sendVerificationEmail = true,
    ): array {
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => 'required|string|max:100',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => 'Validation errors',
                'errors' => $validator->errors()->toArray(),
            ];
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        // Assign roles
        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        // Fire Registered event (triggers verification email, etc.)
        if ($sendVerificationEmail) {
            event(new Registered($user));
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        return [
            'status' => 'success',
            'message' => 'Usuario registrado exitosamente',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->toArray(),
                    'permissions' => $permissions,
                ],
                'token' => $token,
            ],
            'user' => $user,
        ];
    }
}
