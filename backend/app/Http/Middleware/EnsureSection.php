<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permisos del equipo: `section:kanban,history` deja pasar al dueño y al personal
 * con alguna de esas secciones. `section:owner` es solo para el dueño.
 */
class EnsureSection
{
    public function handle(Request $request, Closure $next, string ...$sections): Response
    {
        $user = auth('sanctum')->user();
        $allowed = $user instanceof User
            && ($sections === ['owner'] ? $user->isOwner() : $user->canAccess(...$sections));

        if (!$allowed) {
            return response()->json(['status' => 'Error', 'message' => 'Tu usuario no tiene permiso para esta sección.', 'data' => null], 403);
        }

        return $next($request);
    }
}
