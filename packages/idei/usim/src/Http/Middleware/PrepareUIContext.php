<?php

namespace Idei\Usim\Http\Middleware;

use Closure;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Http\Request;

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
    public function handle(Request $request, Closure $next)
    {
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
        $storage = [];
        $encrypted = null;

        // 1. Intentar obtener desde Header (y verificar que no esté vacío)
        if ($request->hasHeader('X-USIM-Storage')) {
            $headerValue = $request->header('X-USIM-Storage');
            if (!empty($headerValue) && $headerValue !== 'null' && $headerValue !== 'undefined') {
                $encrypted = $headerValue;
            }
        }

        $storage_key = config('ui-services.app_id');

        // 2. Si no hay header válido, intentar desde Input storage_key
        if (empty($encrypted) && $request->has($storage_key)) {
            $encrypted = $request->input($storage_key);
        }

        if ($encrypted) {
            $decodedStorage = json_decode($encrypted, true);
            $storage = \is_array($decodedStorage) ? $decodedStorage : [];
        }

        $request->merge(['storage' => $storage]);

        // Apply locale from store_lang so all t() calls in this request use the user's selected language
        if (!empty($storage['store_lang'])) {
            app()->setLocale($storage['store_lang']);
        }

        $store_token = $storage['store_token'] ?? '';

        $request->headers->set('Authorization', "Bearer $store_token");
        UIStateManager::setAuthToken($store_token);
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
