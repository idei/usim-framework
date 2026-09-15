<?php
namespace Idei\Usim\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class DevicePairingManager
{
    /**
     * Tiempo de vida del PIN de emparejamiento en segundos (15 minutos).
     */
    protected int $ttl = 900;

    /**
     * 1. Inicia el proceso: Genera un PIN para el dispositivo y un Token de Sesión para el polling.
     *
     * @return array{pin: string, session_token: string, expires_in: int}
     */
    public function initiate(): array
    {
        $pin = $this->generateUniquePin();
        $sessionToken = Str::uuid()->toString();

        // Guardamos el estado del PIN (lo que el Admin buscará)
        Cache::put("usim_pairing_pin:{$pin}", [
            'session_token' => $sessionToken,
            'status' => 'pending',
            'access_token' => null,
        ], $this->ttl);

        // Guardamos un índice inverso para que el dispositivo haga polling seguro
        // sin exponer el PIN a ataques de fuerza bruta.
        Cache::put("usim_pairing_session:{$sessionToken}", $pin, $this->ttl);

        return [
            'pin' => $pin,
            'session_token' => $sessionToken,
            'expires_in' => $this->ttl
        ];
    }

    /**
     * 2. El dispositivo consulta periódicamente su estado usando el token oculto.
     *
     * @return string|null Devuelve el Token de Acceso (Sanctum) si se aprobó, 'pending' si sigue esperando, o null si expiró.
     */
    public function pollStatus(string $sessionToken): ?string
    {
        $pin = Cache::get("usim_pairing_session:{$sessionToken}");

        if (!$pin) {
            return null; // Expiró o el token de sesión es inválido
        }

        $data = Cache::get("usim_pairing_pin:{$pin}");

        if ($data && $data['status'] === 'approved' && !empty($data['access_token'])) {
            // El admin ya lo aprobó. Limpiamos la caché para un solo uso.
            Cache::forget("usim_pairing_pin:{$pin}");
            Cache::forget("usim_pairing_session:{$sessionToken}");

            return $data['access_token'];
        }

        return 'pending';
    }

    /**
     * 3. El Administrador aprueba el PIN y genera el Token Definitivo.
     *
     * @param string $pin El PIN que el admin leyó de la pantalla.
     * @param mixed $device La instancia del modelo App\Models\Device.
     * @return bool True si fue exitoso, False si el PIN no existe o expiró.
     */
    public function approve(string $pin, $device): bool
    {
        $data = Cache::get("usim_pairing_pin:{$pin}");

        if (!$data || $data['status'] !== 'pending') {
            return false;
        }

        // Generamos el token definitivo mediante Sanctum/Passport para este dispositivo
        $token = $device->createToken('Device Access Token')->plainTextToken;

        $data['status'] = 'approved';
        $data['access_token'] = $token;

        // Actualizamos la caché para que el dispositivo lo recoja en su próximo polling
        Cache::put("usim_pairing_pin:{$pin}", $data, $this->ttl);

        return true;
    }

    /**
     * Asegura que no haya colisiones de PINs activos en la caché.
     */
    protected function generateUniquePin(): string
    {
        do {
            // Genera un PIN numérico de 4 dígitos fácil de leer a la distancia
            $pin = (string) random_int(1000, 9999);
        } while (Cache::has("usim_pairing_pin:{$pin}"));

        return $pin;
    }
}
