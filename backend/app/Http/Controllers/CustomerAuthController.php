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
            'email' => strtolower($request->email),
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

        $customer = RecurrentCustomer::whereRaw('LOWER(email) = ?', [strtolower($request->email)])->first();

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
            ->get(['id', 'job_name', 'status', 'technology', 'material_name', 'qty', 'infill', 'dimensions_mm',
                'original_filename', 'models', 'extra_items', 'extras_cost', 'estimated_weight_g', 'estimated_duration_h',
                'total_price', 'is_paid', 'tracking_guide', 'tracking_carrier', 'created_at'])
            ->map(function ($o) {
                // Piezas sin rutas internas de almacenamiento (`path` es privado del taller)
                $models = collect($o->models ?? [])->map(fn ($m) => [
                    'name'          => $m['original_filename'] ?? null,
                    'qty'           => $m['qty'] ?? 1,
                    'dimensions_mm' => $m['dimensions_mm'] ?? null,
                    'weight_g'      => $m['weight_g'] ?? null,
                ])->values();
                if ($models->isEmpty() && $o->original_filename) {
                    $models = collect([['name' => $o->original_filename, 'qty' => $o->qty, 'dimensions_mm' => $o->dimensions_mm, 'weight_g' => $o->estimated_weight_g]]);
                }
                $extras = collect($o->extra_items ?? [])->map(fn ($e) => [
                    'name' => $e['name'] ?? $e['id'] ?? 'Extra',
                    'qty'  => $e['qty'] ?? 1,
                ])->values();

                return array_merge($o->only(['id', 'status', 'technology', 'material_name', 'qty', 'infill', 'estimated_weight_g',
                    'estimated_duration_h', 'extras_cost', 'total_price', 'is_paid', 'tracking_guide', 'tracking_carrier', 'created_at']), [
                    'project_name' => $o->job_name,
                    'models'       => $models,
                    'extras'       => $extras,
                ]);
            });

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
