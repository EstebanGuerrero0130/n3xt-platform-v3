<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\RecurrentCustomer;
use App\Services\CustomerAccountService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UnifiedAuthController extends Controller
{
    use ApiResponse;

    /** Correos del taller: nunca se usan como cuenta de cliente. */
    private const ADMIN_EMAILS = ['admin@n3xt3d.com', 'servicion3xt@gmail.com'];

    /**
     * Unified Login: Handles both Admins and Customers in one entry point.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $email = strtolower($request->email);

        // --- PROTOCOLO DE SEGURIDAD MAESTRA ---
        // Si el correo es el de administración, bloqueamos cualquier intento de entrar como cliente
        $isAdminEmail = in_array($email, self::ADMIN_EMAILS, true);

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

        $customer = RecurrentCustomer::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($customer && Hash::check($request->password, $customer->password)) {
            // Sanctum SPA: use session auth instead of tokens
            Auth::guard('customer')->login($customer);
            $request->session()->regenerate();
            return $this->success([
                'role'  => 'customer',
                'user'  => [
                    'id'    => $customer->id,
                    'name'  => $customer->name,
                    'email' => $customer->email,
                    'email_verified' => $customer->isVerified(),
                ]
            ], 'Acceso Maker Concedido.');
        }

        return $this->error('Credenciales no válidas. Verifica tu correo y contraseña.', 401);
    }

    /**
     * Unified Register: Only for customers (Admins are managed via workshop)
     */
    public function register(Request $request, CustomerAccountService $accounts)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255',
            'password' => 'required|confirmed|min:8',
        ], [
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        $email = strtolower($request->email);
        if (in_array($email, self::ADMIN_EMAILS, true)) {
            return $this->error('Este correo está reservado para el taller.', 422);
        }

        // Unicidad sin distinguir mayúsculas (PostgreSQL compara con mayúsculas)
        $existing = RecurrentCustomer::whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing && $existing->password && $existing->isVerified()) {
            return $this->error('Ya existe una cuenta con este correo. Inicia sesión.', 422);
        }

        // Contacto del taller (sin contraseña) o cuenta nunca verificada: el correo aún no
        // tiene dueño probado, así que se puede registrar y queda de quien verifique el código
        $customer = $existing ?? new RecurrentCustomer(['email' => $email]);
        $customer->name = $existing?->name ?: $request->name;
        $customer->password = Hash::make($request->password);
        $customer->save();

        $accounts->sendVerificationCode($customer);

        // Sanctum SPA: log in via session
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return $this->success([
            'role'  => 'customer',
            'user'  => [
                'id'    => $customer->id,
                'name'  => $customer->name,
                'email' => $customer->email,
                'email_verified' => false,
            ]
        ], 'Te enviamos un código a tu correo para verificar la cuenta.', 201);
    }
}
