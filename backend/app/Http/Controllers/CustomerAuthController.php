<?php

namespace App\Http\Controllers;

use App\Models\RecurrentCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:recurrent_customers',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $customer = RecurrentCustomer::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Sanctum SPA: use session auth instead of tokens
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return response()->json([
            'customer' => $customer,
        ]);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $customer = RecurrentCustomer::where('email', $request->email)->first();

        if (! $customer || ! Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        // Sanctum SPA: use session auth instead of tokens
        Auth::guard('customer')->login($customer);
        $request->session()->regenerate();

        return response()->json([
            'customer' => $customer,
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Pedidos del cliente autenticado (por su email), solo campos públicos.
     */
    public function orders(Request $request)
    {
        $email = strtolower((string)$request->user()->email);

        $orders = \App\Models\Order::query()
            ->whereRaw('LOWER(customer_email) = ?', [$email])
            ->orderByDesc('created_at')
            ->get(['id', 'job_name', 'status', 'technology', 'material_name', 'estimated_weight_g', 'total_price', 'is_paid', 'created_at'])
            ->map(fn ($o) => $o->toArray() + ['project_name' => $o->job_name]);

        return response()->json(['data' => $orders]);
    }

    public function logout(Request $request)
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Cierre de sesión exitoso']);
    }
}
