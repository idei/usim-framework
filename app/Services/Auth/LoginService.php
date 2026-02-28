<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LoginService
{
    public function login(string $email, string $password, bool $remember = false): array
    {
        $validator = Validator::make([
            'email' => $email,
            'password' => $password,
            'remember' => $remember,
        ], [
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'boolean',
        ]);

        if ($validator->fails()) {
            return [
                'status' => 'error',
                'message' => 'Validation errors',
                'errors' => $validator->errors()->toArray(),
            ];
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return [
                'status' => 'error',
                'message' => 'Credenciales inválidas',
                'errors' => ['email' => ['The provided credentials are incorrect.']],
            ];
        }

        $tokenName = $remember ? 'auth_token_remember' : 'auth_token';
        $token = $remember
            ? $user->createToken($tokenName, ['*'], now()->addDays(30))->plainTextToken
            : $user->createToken($tokenName, ['*'], now()->addDay())->plainTextToken;

        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        $roles = $user->getRoleNames()->toArray();

        return [
            'status' => 'success',
            'message' => 'Autenticación exitosa',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $roles,
                    'permissions' => $permissions,
                ],
                'token' => $token,
                'remember' => $remember,
            ],
            'user' => $user,
        ];
    }
}
