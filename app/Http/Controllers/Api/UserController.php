<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\Auth\RegisterService;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {
    }

    /**
     * Display a listing of users.
     *
     * Query params:
     * - per_page: items per page (default: 15)
     * - search: search by name, email or role
     * - sort_by: name|email|roles (default: created_at)
     * - sort_direction: asc|desc (default: desc)
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'in:name,email,roles,email_verified_at,updated_at'],
            'sort_direction' => ['sometimes', 'in:asc,desc'],
        ]);

        $response = $this->userService->getUsersList($validated);
        return response()->json($response);
    }

    public function count(Request $request): JsonResponse
    {
        $search = $request->query('search', null);
        $totalUsers = $this->userService->countUsers($search);

        return response()->json([
            'status' => 'success',
            'message' => 'Conteo de usuarios recuperado exitosamente',
            'data' => [
                'count' => $totalUsers,
            ]
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request, RegisterService $registerService): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['exists:roles,name'],
            "send_verification_email" => ['sometimes', 'boolean'],
        ]);

        $response = $registerService->register(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
            passwordConfirmation: $validated['password_confirmation'],
            roles: $validated['roles'] ?? ['user'],
            sendVerificationEmail: $validated['send_verification_email'] ?? true
        );

        $httpStatus = $response['status'] === 'success' ? 201 : 422;

        // Remove the Eloquent user model from the API response
        unset($response['user']);

        // Customize message for admin context
        if ($response['status'] === 'success') {
            $response['message'] = 'Usuario creado exitosamente';
        }

        return response()->json($response, $httpStatus);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        $response = $this->userService->getUser($user->id);
        $httpStatus = $response['status'] === 'success' ? 200 : 404;
        return response()->json($response, $httpStatus);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['sometimes', 'confirmed', Password::defaults()],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['exists:roles,name'],
            "send_reset_email" => ['sometimes', 'boolean'],
            "send_verification_email" => ['sometimes', 'boolean'],
        ]);

        $response = $this->userService->updateUser($user, $request->all());
        $httpStatus = $response['status'] === 'success' ? 200 : 422;
        return response()->json($response, $httpStatus);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): JsonResponse
    {
        $response = $this->userService->deleteUser($user);
        $httpStatus = $response['status'] === 'success' ? 200 : 403;
        return response()->json($response, $httpStatus);
    }
}
