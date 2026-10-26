<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Solo deja pasar a administradores (modelo User / tabla `users`).
 * Los clientes (RecurrentCustomer, guard `customer`) nunca son admin.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!self::check()) {
            return response()->json(['status' => 'Error', 'message' => 'Acceso restringido a administradores.', 'data' => null], 403);
        }

        return $next($request);
    }

    public static function check(): bool
    {
        return auth('sanctum')->user() instanceof User;
    }

    /** Admin con alguna de esas secciones (el dueño siempre). */
    public static function can(string ...$sections): bool
    {
        $user = auth('sanctum')->user();
        return $user instanceof User && $user->canAccess(...$sections);
    }
}
