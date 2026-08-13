<?php

namespace App\Services\Auth;

use App\Models\User;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Support\Facades\Auth;

class AuthSessionService
{
    public function issueToken(User $user, bool $remember = false): string
    {
        $tokenName = $remember ? 'auth_token_remember' : 'auth_token';

        return $remember
            ? $user->createToken($tokenName, ['*'], now()->addDays(30))->plainTextToken
            : $user->createToken($tokenName, ['*'], now()->addDay())->plainTextToken;
    }

    public function start(User $user, ?string $token = null): string
    {
        if (is_string($token) && $token !== '') {
            UIStateManager::setAuthToken($token);
        }

        Auth::login($user);

        event(new UsimEvent('logged_user', [
            'user' => $user,
            'timestamp' => now(),
        ]));

        return $this->resolvePostLoginRedirect($user);
    }

    public function resolvePostLoginRedirect(User $user): string
    {
        $rolesConfig = config('usim.roles', config('users.roles', []));

        foreach ($user->getRoleNames() as $roleName) {
            if (!is_string($roleName)) {
                continue;
            }

            $screenClass = data_get($rolesConfig, "{$roleName}.home_screen");

            if (
                is_string($screenClass)
                && class_exists($screenClass)
                && is_subclass_of($screenClass, Screen::class)
            ) {
                return $screenClass::getRoutePath();
            }
        }

        return redirect()->intended('/')->getTargetUrl();
    }
}
