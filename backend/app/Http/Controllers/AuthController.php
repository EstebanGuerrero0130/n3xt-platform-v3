<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('admin-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user
            ]);
        }

        return response()->json(['message' => 'Credenciales incorrectas'], 401);
    }

    public function loginByPin(Request $request)
    {
        $request->validate([
            'pin' => 'required|string|size:4'
        ]);

        // Prioridad 1: PIN Global de Taller (Workshop PIN) en Settings
        $workshopPin = \App\Models\Setting::where('key', 'security')->first();
        if ($workshopPin && isset($workshopPin->value['workshop_pin']) && $workshopPin->value['workshop_pin'] === $request->pin) {
            // Buscamos al primer administrador para asignarle la sesión
            $user = \App\Models\User::first();
            if ($user) {
                $token = $user->createToken('admin-token')->plainTextToken;
                return response()->json([
                    'token' => $token,
                    'user' => $user
                ]);
            }
        }

        // Prioridad 2: PIN individual de usuario
        $user = \App\Models\User::where('pin_code', $request->pin)->first();

        if ($user) {
            $token = $user->createToken('admin-token')->plainTextToken;

            return response()->json([
                'token' => $token,
                'user' => $user
            ]);
        }

        return response()->json(['message' => 'PIN de acceso incorrecto'], 401);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Cierre de sesión exitoso']);
    }
}
