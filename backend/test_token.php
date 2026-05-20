<?php
use App\Http\Controllers\UnifiedAuthController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

$admin = User::where('email', 'admin@n3xt3d.com')->first();
$token = $admin->createToken('test_token')->plainTextToken;
echo "Token: $token\n";

// Simular una petición con ese token
Auth::guard('sanctum')->setUser($admin); // Para simular que ya está logueado en este hilo
$user = Auth::guard('sanctum')->user();
echo "Autenticado como: " . ($user ? $user->email : 'Nadie') . "\n";
