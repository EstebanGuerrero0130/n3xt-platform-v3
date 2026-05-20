<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\RecurrentCustomer;

echo "=== DIAGNÓSTICO DE BASE DE DATOS (SUPABASE) ===\n";

try {
    // Probar conexión
    DB::connection()->getPdo();
    echo "[✓] Conexión a la base de datos exitosa.\n\n";

    // Obtener Administradores
    echo "--- Administradores Registrados ---\n";
    $admins = User::all();
    if ($admins->count() > 0) {
        foreach ($admins as $admin) {
            echo "- " . $admin->email . "\n";
        }
    } else {
        echo "Ningún administrador encontrado.\n";
    }
    echo "\n";

    // Obtener Clientes Registrados
    echo "--- Clientes Registrados (RecurrentCustomer) ---\n";
    $customers = RecurrentCustomer::all();
    if ($customers->count() > 0) {
        foreach ($customers as $customer) {
            echo "- " . $customer->email . " (Nombre: " . $customer->name . ")\n";
        }
    } else {
        echo "Ningún cliente encontrado.\n";
    }

} catch (\Exception $e) {
    echo "[X] Error conectando a la base de datos: " . $e->getMessage() . "\n";
}
