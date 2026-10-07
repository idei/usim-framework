<?php

namespace Idei\Usim\Http\Middleware;

use App\Services\Units\UnitContextResolver;
use Closure;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware PrepareUIContext
 *
 * Prepara el contexto del request para el framework UI:
 * 1. Desencripta el contenido del header X-USIM-Storage
 * 2. Inyecta route params desde query params con prefijo "route_"
 * 3. Configura autenticación Bearer si existe token
 */
class PrepareUIContext
{
    public function handle(Request $request, Closure $next): Response
    {
        // 0. Resetear estado de layout para evitar fugas entre requests
        if (class_exists(\Idei\Usim\Layout\AbstractLayout::class)) {
            \Idei\Usim\Layout\AbstractLayout::setCurrent(null);
        }

        // 1. Desencriptar USIM Storage
        $this->decryptUsimStorage($request);

        // 2. Inyectar route params desde query
        $this->injectRouteParamsFromQuery($request);

        return $next($request);
    }

    /**
     * Desencripta el contenido del header X-USIM-Storage
     * y lo inyecta como $request->storage
     */
    private function decryptUsimStorage(Request $request): void
    {
        /** @var array<string, mixed> $storage */
        $storage = [];
        $encrypted = null;

        // 1. Intentar obtener desde Header (y verificar que no esté vacío)
        if ($request->hasHeader('X-USIM-Storage')) {
            $headerValue = $request->header('X-USIM-Storage');
            if (!empty($headerValue) && $headerValue !== 'null' && $headerValue !== 'undefined') {
                if (str_starts_with($headerValue, 'b64:')) {
                    $decodedHeader = base64_decode(substr($headerValue, 4), true);
                    if ($decodedHeader !== false) {
                        $headerValue = $decodedHeader;
                    }
                }
                $encrypted = $headerValue;
            }
        }

        $storageKeyConfig = config('usim.front_store_key');
        $storageKey = \is_string($storageKeyConfig) ? $storageKeyConfig : '';

        // 2. Si no hay header válido, intentar desde Input storage_key
        if (empty($encrypted) && $request->has($storageKey)) {
            $encrypted = $request->input($storageKey);
        }

        if (\is_string($encrypted) && $encrypted !== '') {
            $decodedStorage = json_decode($encrypted, true);
            $storage = \is_array($decodedStorage) ? $decodedStorage : [];
        }

        $request->merge(['storage' => $storage]);

        // Apply locale from store_lang so all t() calls in this request use the user's selected language
        $storageLang = $storage['store_lang'] ?? null;
        if (\is_scalar($storageLang) && $storageLang !== '') {
            app()->setLocale((string) $storageLang);
        }

        $storeTokenValue = $storage['store_token'] ?? null;
        $storeToken = \is_scalar($storeTokenValue) ? (string) $storeTokenValue : null;

        $request->headers->set('Authorization', 'Bearer ' . ($storeToken ?? ''));
        UIStateManager::setAuthToken($storeToken);

        $hasTabClientId = $request->hasHeader(UIStateManager::CLIENT_ID_HEADER);

        if (!empty($storeToken)) {
            if (class_exists(PersonalAccessToken::class)) {
                $tokenModel = PersonalAccessToken::findToken($storeToken);
                if ($tokenModel && $tokenModel->tokenable instanceof \Illuminate\Contracts\Auth\Authenticatable) {
                    $actor = $tokenModel->tokenable;
                    if ($actor instanceof \App\Models\Device) {
                        if (!$actor->isPaired()) {
                            $this->clearWebGuardUser();
                            $request->setUserResolver(fn () => null);
                        } else {
                            Auth::guard('device')->setUser($actor);
                            $request->setUserResolver(fn () => $actor);
                        }
                    } else {
                        $this->setWebGuardUser($actor);
                        $request->setUserResolver(fn () => $actor);
                    }
                } else {
                    $this->clearWebGuardUser();
                    $request->setUserResolver(fn () => null);
                }
            }
        } elseif ($hasTabClientId) {
            // Per-tab isolation: when request explicitly sends tab client ID,
            // but no store_token is present for this tab, treat this tab as GUEST
            // and prevent leaking user session from the shared browser cookie!
            $this->clearWebGuardUser();
            $request->setUserResolver(fn () => null);
        }

        $effectiveUser = $request->user() ?? Auth::guard('device')->user();
        if ($effectiveUser instanceof \App\Models\Device && !$effectiveUser->isPaired()) {
            $this->clearWebGuardUser();
            $request->setUserResolver(fn () => null);
            $effectiveUser = null;
        }

        $unitSlug = UIStateManager::getActiveUnit();
        UnitContextResolver::resolveAndApply($effectiveUser, $unitSlug);
    }

    /**
     * Set the authenticated user for the web guard in the current request.
     */
    private function setWebGuardUser(\Illuminate\Contracts\Auth\Authenticatable $user): void
    {
        $guard = Auth::guard('web');
        $guard->setUser($user);

        if ($guard instanceof \Illuminate\Auth\SessionGuard) {
            try {
                $loggedOutProp = new \ReflectionProperty($guard, 'loggedOut');
                $loggedOutProp->setAccessible(true);
                $loggedOutProp->setValue($guard, false);
            } catch (\ReflectionException) {
                // Ignore reflection errors
            }
        }
    }

    /**
     * Clear authenticated user for web guard for the current request without destroying disk session.
     */
    private function clearWebGuardUser(): void
    {
        $guard = Auth::guard('web');

        if ($guard instanceof SessionGuard) {
            $guard->forgetUser();
            try {
                $loggedOutProp = new \ReflectionProperty($guard, 'loggedOut');
                $loggedOutProp->setAccessible(true);
                $loggedOutProp->setValue($guard, true);
            } catch (\ReflectionException) {
                // Ignore reflection errors
            }
        } elseif (method_exists($guard, 'forgetUser')) {
            $guard->forgetUser();
        }

        $deviceGuard = Auth::guard('device');
        if ($deviceGuard->check()) {
            if ($deviceGuard instanceof SessionGuard) {
                $deviceGuard->forgetUser();
                try {
                    $loggedOutProp = new \ReflectionProperty($deviceGuard, 'loggedOut');
                    $loggedOutProp->setAccessible(true);
                    $loggedOutProp->setValue($deviceGuard, true);
                } catch (\ReflectionException) {
                    // Ignore reflection errors
                }
            } elseif (method_exists($deviceGuard, 'forgetUser')) {
                $deviceGuard->forgetUser();
            }
        }
    }

    /**
     * Inyecta query params con prefijo "route_" como route parameters
     *
     * Ejemplo: ?route_id=123&route_hash=abc
     * Resultado: request()->route('id') = "123", request()->route('hash') = "abc"
     */
    private function injectRouteParamsFromQuery(Request $request): void
    {
        $route = $request->route();

        if (!$route) {
            return; // No hay ruta, salir
        }

        $queryParams = $request->query();

        foreach ($queryParams as $key => $value) {
            // Solo procesar params que empiecen con "route_"
            if (strpos($key, 'route_') === 0) {
                // Extraer el nombre real del parámetro (sin el prefijo)
                $paramName = substr($key, 6); // Quitar "route_"

                // Solo inyectar si NO existe ya un route param real con ese nombre
                // (los route params reales tienen precedencia)
                if (!$route->hasParameter($paramName)) {
                    $route->setParameter($paramName, $value);
                }

                // Opcional: Remover del query string para limpiar
                $request->query->remove($key);
            }
        }
    }
}
