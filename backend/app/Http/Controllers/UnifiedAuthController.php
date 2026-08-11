<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RecurrentCustomer;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
        $isAdminEmail = ($email === 'n3xt@admin.com' || $email === 'servicion3xt@gmail.com');

        // 1. Try to find an Admin (User model)
        $admin = User::where('email', $email)->first();
        if ($admin) {
            if (Hash::check($request->password, $admin->password)) {
                // Sanctum SPA: use session auth instead of tokens
                Auth::login($admin);
                $request->session()->regenerate();
                return $this->success([
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
            // Sanctum SPA: use session auth instead of tokens
            Auth::guard('customer')->login($customer);
            $request->session()->regenerate();
            return $this->success([
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

        // Sanctum SPA: log in via session
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return $this->success([
            'role'  => 'customer',
            'user'  => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email
            ]
        ], 'Cuenta Maker creada con éxito.', 201);
    }

    /**
     * Túnel de Registro Supabase Auth
     */
    public function completeProfile(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'company' => 'required|string|max:255',
            'customer_id_document' => 'required|string|max:255',
            'supabase_id' => 'required|string',
        ]);

        $email = strtolower($request->email);
        $customer = RecurrentCustomer::where('email', $email)->first();

        if ($customer) {
            return $this->error('Este correo ya está registrado.', 422);
        }

        $customer = RecurrentCustomer::create([
            'name' => $request->name,
            'email' => $email,
            'password' => Hash::make(Str::random(16)), // Contraseña al azar, gestionada por Supabase
            'phone' => $request->phone,
            'company' => $request->company,
            'customer_id_document' => $request->customer_id_document,
            'supabase_id' => $request->supabase_id,
        ]);

        // Iniciar sesión
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return $this->success([
            'role' => 'customer',
            'user' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email
            ]
        ], 'Perfil completado y cuenta creada.');
    }
}
