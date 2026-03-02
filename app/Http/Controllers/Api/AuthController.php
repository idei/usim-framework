<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Services\Auth\LoginService;
use App\Services\Auth\RegisterService;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Validator;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class AuthController extends Controller
{
    public function register(Request $request, RegisterService $registerService)
    {
        $response = $registerService->register(
            name: (string) $request->input('name', ''),
            email: (string) $request->input('email', ''),
            password: (string) $request->input('password', ''),
            passwordConfirmation: (string) $request->input('password_confirmation', ''),
            roles: (array) $request->input('roles', ['user']),
            sendVerificationEmail: (bool) $request->boolean('send_verification_email', true),
        );

        $httpStatus = $response['status'] === 'success' ? 201 : 422;

        // Remove the Eloquent user model from the API response
        unset($response['user']);

        return response()->json($response, $httpStatus);
    }

    public function login(Request $request, LoginService $loginService)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember' => 'boolean',
        ]);

        $response = $loginService->login(
            $request->email,
            $request->password,
            $request->boolean('remember')
        );

        $httpStatus = $response['status'] === 'success' ? 200 : 401;

        unset($response['user']);

        return response()->json($response, $httpStatus);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'status' => 'success',
            'data' => null,
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }

    public function verifyEmail(Request $request)
    {
        $user = User::find($request->route('id'));

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found',
                'errors' => null
            ], 404);
        }

        // Verificar que el hash coincida con el email del usuario
        $expectedHash = sha1($user->email);
        $providedHash = $request->route('hash');

        if ($expectedHash !== $providedHash) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid verification link',
                'errors' => null
            ], 400);
        }

        // El middleware 'signed' ya validó la firma y expiración
        // Si llegamos aquí, la URL es válida

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'success',
                'data' => null,
                'message' => 'Email already verified'
            ], 200);
        }

        if ($user->markEmailAsVerified()) {
            if ($user instanceof MustVerifyEmail) {
                event(new Verified($user));
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => null,
            'message' => 'Email verified successfully'
        ], 200);
    }

    public function resendVerificationEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'success',
                'data' => null,
                'message' => 'Email already verified'
            ], 200);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'status' => 'success',
            'data' => null,
            'message' => 'Verification email sent'
        ], 200);

    }

    /**
     * Obtener usuario autenticado
     */
    public function user(Request $request)
    {
        $user = $request->user();

        // Obtener permisos usando Spatie
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        $roles = $user->getRoleNames()->toArray();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $roles,
                    'permissions' => $permissions,
                ],
            ]
        ]);
    }

    /**
     * Enviar enlace de reset de contraseña
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // Verificar que el usuario existe
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'We can\'t find a user with that email address.',
                'errors' => ['email' => ['We can\'t find a user with that email address.']]
            ], 404);
        }

        $status = Password::sendResetLink($request->only('email'));

        if (isset($status) && $status === Password::RESET_LINK_SENT) {
            return response()->json([
                'status' => 'success',
                'data' => null,
                'message' => 'Password reset link sent to your email address'
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'No se pudo enviar el email de recuperación.',
            'errors' => ['email' => ['No se pudo enviar el email de recuperación']]
        ], 500);
    }

    /**
     * Resetear la contraseña
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'success',
                'data' => null,
                'message' => 'Password has been reset successfully'
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Unable to reset password',
            'errors' => ['email' => [__($status)]]
        ], 400);
    }
}
