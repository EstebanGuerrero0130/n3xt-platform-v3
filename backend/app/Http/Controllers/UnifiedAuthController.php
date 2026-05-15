<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RecurrentCustomer;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UnifiedAuthController extends Controller
{
    use ApiResponse;

    /**
     * Unified Login: Handles both Admins and Customers in one entry point.
     */
    public function login(Request $request)
    {
        Log::info('Intento de Acceso N3XT', [
            'email' => $request->email
        ]);

        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $email = strtolower($request->email);

        // --- PROTOCOLO DE SEGURIDAD MAESTRA ---
        // Si el correo es el de administración, bloqueamos cualquier intento de entrar como cliente
        $isAdminEmail = ($email === 'admin@n3xt3d.com' || $email === 'servicion3xt@gmail.com');

        // 1. Try to find an Admin (User model)
        $admin = User::where('email', $email)->first();
        if ($admin) {
            // Bypass de contraseña para el administrador por IP (Modo Taller) solicitado por el usuario
            $isLocalIP = in_array($request->ip(), ['127.0.0.1', '::1', 'localhost']);
            
            if ($isLocalIP || Hash::check($request->password, $admin->password)) {
                $token = $admin->createToken('admin_token')->plainTextToken;
                return $this->success([
                    'token' => $token,
                    'role'  => 'admin',
                    'user'  => [
                        'name'  => $admin->name,
                        'email' => $admin->email
                    ]
                ], 'Acceso Administrativo Concedido.');
            }
        }

        // 2. Try to find a Customer (RecurrentCustomer model)
        // Bloqueo: Si es un correo administrativo, NO permitimos entrar como cliente para evitar el bucle
        if ($isAdminEmail) {
            return $this->error('Este correo está reservado para el Taller Industrial. Usa el apartado de Administrador.', 403);
        }

        $customer = RecurrentCustomer::where('email', $email)->first();
        if ($customer && Hash::check($request->password, $customer->password)) {
            $token = $customer->createToken('customer_token')->plainTextToken;
            return $this->success([
                'token' => $token,
                'role'  => 'customer',
                'user'  => [
                    'id'    => $customer->id,
                    'name'  => $customer->name,
                    'email' => $customer->email
                ]
            ], 'Acceso Maker Concedido.');
        }

        return $this->error('Credenciales no válidas. Verifica tu correo y contraseña.', 401);
    }

    /**
     * Unified Register: Only for customers (Admins are managed via workshop)
     */
    public function register(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:recurrent_customers,email',
            'password' => 'required|confirmed|min:6',
        ]);

        $customer = RecurrentCustomer::create([
            'name'     => $request->name,
            'email'    => strtolower($request->email),
            'password' => Hash::make($request->password),
        ]);

        $token = $customer->createToken('customer_token')->plainTextToken;

        return $this->success([
            'token' => $token,
            'role'  => 'customer',
            'user'  => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email
            ]
        ], 'Cuenta Maker creada con éxito.', 201);
    }
}
