<?php

namespace App\Services\Auth;

use App\Models\User;
use Idei\Usim\Events\UsimEvent;
use Idei\Usim\Models\UsimUnit;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIStateManager;
use Idei\Usim\Support\UnitContextResolver;
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

    public function start(User $user, ?string $unit = null, ?string $token = null): string
    {
        // Support legacy callers passing ($user, $token) where $unit contains the auth token
        if ($token === null && is_string($unit)) {
            if (str_contains($unit, '|') || str_contains($unit, '.') || strlen($unit) > 30) {
                $token = $unit;
                $unit = null;
            }
        }

        if (\is_string($token) && $token !== '') {
            UIStateManager::setAuthToken($token);
        }

        Auth::login($user);

        $activeUnitSlug = $unit;

        if (config('permission.teams', false)) {
            $resolvedUnit = UnitContextResolver::resolveAndApply($user, $unit);
            if ($resolvedUnit) {
                $activeUnitSlug = $resolvedUnit->slug;
            } elseif (is_string($unit) && $unit !== '') {
                $fallbackUnit = UsimUnit::where('slug', $unit)->first();
                if ($fallbackUnit) {
                    setPermissionsTeamId($fallbackUnit->id);
                    $activeUnitSlug = $fallbackUnit->slug;
                }
            }

            if (getPermissionsTeamId() === null) {
                $defaultUnit = UsimUnit::where('slug', 'main')->first();
                if ($defaultUnit) {
                    setPermissionsTeamId($defaultUnit->id);
                    $activeUnitSlug = $activeUnitSlug ?? 'main';
                }
            }
        }

        event(new UsimEvent('logged_user', [
            'user' => $user,
            'timestamp' => now(),
            'unit' => $activeUnitSlug ?? 'main',
        ]));

        return $this->resolvePostLoginRedirect($user, $activeUnitSlug);
    }

    public function resolvePostLoginRedirect(User $user, ?string $unit = null): string
    {
        if (config('permission.teams', false)) {
            if ($unit !== null || getPermissionsTeamId() === null) {
                $resolvedUnit = UnitContextResolver::resolveAndApply($user, $unit);
                if (!$resolvedUnit && is_string($unit) && $unit !== '') {
                    $fallbackUnit = UsimUnit::where('slug', $unit)->first();
                    if ($fallbackUnit) {
                        setPermissionsTeamId($fallbackUnit->id);
                    }
                }
                $user->unsetRelation('roles');
            }
        }

        $rolesConfig = config('usim.roles', config('users.roles', []));

        $roleNames = $user->getRoleNames()->values()->toArray();

        if ($user->isRoot() && !in_array('root', $roleNames, true)) {
            $roleNames[] = 'root';
        }

        if (empty($roleNames)) {
            $roleNames = $user->globalRoles()->pluck('name')->toArray();
        }

        $roleNames = array_values(array_unique(array_filter($roleNames, 'is_string')));

        // Sort roles by priority in usim.roles (lower priority number = higher precedence)
        usort($roleNames, static function (string $a, string $b) use ($rolesConfig): int {
            $pA = data_get($rolesConfig, "{$a}.priority", 100);
            $pB = data_get($rolesConfig, "{$b}.priority", 100);
            $pA = is_numeric($pA) && (int) $pA >= 0 ? (int) $pA : 100;
            $pB = is_numeric($pB) && (int) $pB >= 0 ? (int) $pB : 100;

            return $pA <=> $pB;
        });

        foreach ($roleNames as $roleName) {
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
