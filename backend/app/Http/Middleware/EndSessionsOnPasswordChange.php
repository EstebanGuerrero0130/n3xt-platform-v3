<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra las sesiones abiertas con una contraseña anterior (admin y cliente).
 *
 * Cada sesión guarda la huella de la contraseña con la que entró; si la cuenta
 * cambió de contraseña (recuperación, el dueño la cambió en Equipo), esa sesión
 * deja de valer. Funciona igual con sesiones en cookie (Vercel), que no se pueden
 * borrar desde el servidor.
 */
class EndSessionsOnPasswordChange
{
    private const GUARDS = ['web', 'customer'];

    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->hasSession()) {
            return $next($request);
        }
        $session = $request->session();

        foreach (self::GUARDS as $guard) {
            $user = Auth::guard($guard)->user();
            $stored = $session->get($this->key($guard));
            // Solo cuenta si es la misma cuenta con otra contraseña (no otro usuario en la sesión)
            if ($user && is_string($stored) && str_starts_with($stored, $user->getAuthIdentifier() . '|')
                && !hash_equals($stored, $this->fingerprint($user))) {
                Auth::guard($guard)->logout();
                $session->forget($this->key($guard));
            }
        }

        $response = $next($request);

        // Huella actual de quien quedó con sesión (también tras iniciar sesión o
        // cambiar la contraseña en esta misma petición)
        foreach (self::GUARDS as $guard) {
            $user = Auth::guard($guard)->user();
            $user ? $session->put($this->key($guard), $this->fingerprint($user)) : $session->forget($this->key($guard));
        }

        return $response;
    }

    private function key(string $guard): string
    {
        return "password_fingerprint_{$guard}";
    }

    /** Huella del hash (no el hash): la sesión puede ir en una cookie cifrada. */
    private function fingerprint($user): string
    {
        return $user->getAuthIdentifier() . '|' . hash_hmac('sha256', (string) $user->getAuthPassword(), (string) config('app.key'));
    }
}
