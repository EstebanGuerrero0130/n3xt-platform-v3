<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\RecurrentCustomer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SupabaseAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['message' => 'Token no proporcionado'], 401);
        }

        try {
            // Decodificar el JWT usando la firma (JWT Secret de Supabase)
            // Como Supabase usa HS256, la clave secreta por defecto es JWT_SECRET.
            // Si usamos ANON_KEY, no es el secret. Necesitaremos SUPABASE_JWT_SECRET en el .env.
            // Para simplificar y hacer fail-safe en desarrollo, podemos extraer sin verificar firma
            // O idealmente verificar la firma (pero requiere SUPABASE_JWT_SECRET).
            // Usaremos el secret de JWT de supabase.
            
            $secret = env('SUPABASE_JWT_SECRET'); 
            
            if (!$secret) {
                // Fallback de desarrollo: extraer payload sin validar firma si no hay secret configurado (¡Solo Dev!)
                // Se recomienda encarecidamente poner SUPABASE_JWT_SECRET en producción.
                $parts = explode('.', $token);
                if (count($parts) !== 3) {
                    throw new \Exception('Token malformado');
                }
                $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')));
            } else {
                $payload = JWT::decode($token, new Key($secret, 'HS256'));
            }

            if (!$payload || !isset($payload->email)) {
                return response()->json(['message' => 'Token inválido: Sin email'], 401);
            }

            $email = strtolower($payload->email);

            // Validar si es Admin o Cliente (Túnel)
            $isAdminEmail = ($email === 'n3xt@admin.com' || $email === 'servicion3xt@gmail.com');

            if ($isAdminEmail) {
                $admin = User::firstOrCreate(['email' => $email], [
                    'name' => 'Admin Supabase',
                    'password' => bcrypt(Str::random(16))
                ]);
                Auth::login($admin);
                return $next($request);
            }

            // Buscar cliente
            $customer = RecurrentCustomer::where('email', $email)->first();

            if (!$customer) {
                // Si no existe, debemos devolver un 403 con bandera para que el frontend lo mande al form
                return response()->json([
                    'message' => 'Usuario no registrado. Completa tu perfil.',
                    'requires_registration' => true,
                    'email' => $email,
                    'name' => $payload->user_metadata->full_name ?? 'Usuario',
                ], 403);
            }

            // Loguear usuario
            Auth::guard('customer')->login($customer);
            
            return $next($request);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Token inválido: ' . $e->getMessage()], 401);
        }
    }
}
