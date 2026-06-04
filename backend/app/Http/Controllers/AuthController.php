<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * AuthController — Logout para el panel admin.
 *
 * La autenticación (login) se maneja via UnifiedAuthController con
 * Sanctum SPA mode (cookies de sesión). Este controller solo provee
 * el endpoint de logout.
 */
class AuthController extends Controller
{
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Cierre de sesión exitoso']);
    }
}
