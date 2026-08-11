<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\Auth\AuthSessionService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LoginService
{
    public function __construct(
        protected AuthSessionService $authSessionService
    ) {
    }

    /**
     * @return array{
     *     status: 'error',
     *     message: string,
     *     errors: array<string, list<string>>
     * }|array{
     *     status: 'success',
     *     message: string,
     *     data: array{
     *         user: array{
     *             id: int,
     *             name: string,
     *             email: string,
     *             roles: list<string>,
     *             permissions: list<string>
     *         },
     *         token: string,
     *         remember: bool
     *     },
     *     user: User
     * }
     */
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
                'message' => t('service.auth.login.validation_errors'),
                'errors' => $validator->errors()->toArray(),
            ];
        }

        $user = User::where('email', $email)->first();

        if (!$user || !Hash::check($password, $user->password)) {
            return [
                'status' => 'error',
                'message' => t('service.auth.login.invalid_credentials'),
                'errors' => ['email' => [t('service.auth.login.invalid_credentials_detail')]],
            ];
        }

        $token = $this->authSessionService->issueToken($user, $remember);

        /** @var list<string> $permissions */
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        /** @var list<string> $roles */
        $roles = $user->getRoleNames()->toArray();

        return [
            'status' => 'success',
            'message' => t('service.auth.login.success'),
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
