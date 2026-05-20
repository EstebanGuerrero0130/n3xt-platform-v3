<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\RecurrentCustomer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

echo "=== PRUEBA DE LOGIN ===\n";

$email = 'admin@n3xt3d.com';
$isAdminEmail = ($email === 'admin@n3xt3d.com' || $email === 'servicion3xt@gmail.com');

$admin = User::where('email', $email)->first();
if ($admin) {
    echo "1. Encontró administrador.\n";
    $isLocalIP = true; // Simular IP local
    if ($isLocalIP || Hash::check('admin123', $admin->password)) {
        echo "2. Contraseña o IP validada.\n";
        $token = $admin->createToken('admin_token')->plainTextToken;
        $response = [
            'status' => 'Success',
            'data' => [
                'token' => substr($token, 0, 10) . '...',
                'role'  => 'admin',
                'user'  => [
                    'name'  => $admin->name,
                    'email' => $admin->email
                ]
            ]
        ];
        echo "3. Respuesta generada:\n";
        print_r($response);
    } else {
        echo "Error: Contraseña incorrecta y no es IP local.\n";
    }
} else {
    echo "Error: Administrador no encontrado.\n";
}
