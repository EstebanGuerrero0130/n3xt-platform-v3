<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\AuthController;

use App\Http\Controllers\MaterialController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\UnifiedAuthController;
use App\Http\Controllers\SitemapController;

// Ruta login para Sanctum (redirección cuando no autenticado)
Route::any('/login', function() {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

// --- PROTOCOLO DE ACCESO UNIFICADO N3XT ---
Route::any('/ping', function() { return response()->json(['status' => 'online', 'timestamp' => now()]); });
Route::middleware('throttle:10,1')->post('/auth-master-industrial-access', [UnifiedAuthController::class, 'login']);
Route::middleware('throttle:5,1')->post('/auth/unified-register', [UnifiedAuthController::class, 'register']);

// Ruta de Diagnóstico para el Lanzador N3XT
Route::get('/health-check', function() { return response()->json(['status' => 'online', 'version' => '3.5.0']); });

// Sitemap dinámico (catalog + gallery desde settings)
Route::get('/sitemap', [SitemapController::class, 'index'])->name('sitemap');

// Ruta pública protegida por rate limit
Route::middleware('throttle:5,1')->post('/orders', [OrderController::class, 'store']);
Route::middleware('throttle:15,1')->post('/process-stl', [OrderController::class, 'processStl']);
Route::middleware('throttle:20,1')->get('/orders/track', [OrderController::class, 'track']);
Route::middleware('throttle:10,1')->post('/quote/coupon', [OrderController::class, 'validateCoupon']);

// Ruta pública para leer configuraciones y materiales
Route::get('/settings', [SettingController::class, 'index']);
Route::get('/materials', [MaterialController::class, 'index']);

// Portal del cliente (registro y login en /auth/*)
Route::middleware('auth:customer')->group(function () {
    Route::get('/customer/profile', [CustomerAuthController::class, 'profile']);
    Route::get('/customer/orders', [CustomerAuthController::class, 'orders']);
    Route::middleware('throttle:10,1')->post('/customer/verify', [CustomerAuthController::class, 'verify']);
    Route::middleware('throttle:3,1')->post('/customer/verify/resend', [CustomerAuthController::class, 'resendCode']);
    Route::middleware('throttle:10,1')->post('/customer/catalog-orders', [CustomerAuthController::class, 'catalogOrder']);
    Route::post('/customer/logout', [CustomerAuthController::class, 'logout']);
});

// Sesión actual (admin o cliente) con rol explícito
Route::get('/user', function (Request $request) {
    if ($admin = auth('web')->user()) {
        return response()->json(array_merge($admin->only(['id', 'name', 'email']), ['role' => 'admin']));
    }
    if ($customer = auth('customer')->user()) {
        return response()->json(array_merge($customer->only(['id', 'name', 'email']), ['role' => 'customer', 'email_verified' => $customer->isVerified()]));
    }
    // Visitante sin sesión: 200 con rol nulo (un 401 en cada página ensucia la consola y Lighthouse)
    return response()->json(['role' => null]);
});

// Rutas protegidas para Administrador
Route::middleware(['auth:sanctum', 'admin'])->group(function () {
    // Analytics & Accounting
    Route::get('/admin/analytics', [\App\Http\Controllers\Admin\AnalyticsController::class, 'index']);
    Route::get('/admin/export-csv', [\App\Http\Controllers\Admin\AnalyticsController::class, 'exportCsv']);

    // Backup & Sync
    Route::get('/admin/backup/export', [\App\Http\Controllers\Admin\BackupController::class, 'exportAll']);
    Route::post('/admin/backup/import', [\App\Http\Controllers\Admin\BackupController::class, 'importAll']);

    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Panel de control
    Route::get('/admin/orders', [OrderController::class, 'index']);
    Route::get('/admin/orders/{id}', [OrderController::class, 'show']);
    Route::patch('/admin/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::post('/admin/orders/{id}/toggle-paid', [OrderController::class, 'togglePaid']);
    Route::delete('/admin/orders/{id}', [OrderController::class, 'destroy']);
    Route::get('/admin/orders/{id}/download', [OrderController::class, 'download']);
    Route::post('/admin/purge-all', [OrderController::class, 'purgeAll']);
    Route::post('/admin/orders/{id}/extras', [OrderController::class, 'addExtra']);

    // Compras & Gastos
    Route::get('/admin/purchases', [\App\Http\Controllers\PurchaseController::class, 'index']);
    Route::post('/admin/purchases', [\App\Http\Controllers\PurchaseController::class, 'store']);
    Route::delete('/admin/purchases/{id}', [\App\Http\Controllers\PurchaseController::class, 'destroy']);

    // Configuración
    Route::post('/admin/settings', [SettingController::class, 'update']);
    Route::post('/admin/settings/logo', [SettingController::class, 'uploadLogo']);
    Route::post('/admin/upload-image', [SettingController::class, 'uploadImage']);


    // Inventario
    Route::post('/materials', [MaterialController::class, 'store']);
    Route::patch('/materials/{id}', [MaterialController::class, 'update']);
    Route::post('/materials/{id}/stock', [MaterialController::class, 'updateStock']);
    Route::delete('/materials/{id}', [MaterialController::class, 'destroy']);

    // Maquinaria (Granja)
    Route::get('/admin/printers', [\App\Http\Controllers\PrinterController::class, 'index']);
    Route::post('/admin/printers', [\App\Http\Controllers\PrinterController::class, 'store']);
    Route::patch('/admin/printers/{id}', [\App\Http\Controllers\PrinterController::class, 'update']);
    Route::post('/admin/printers/{id}/reset', [\App\Http\Controllers\PrinterController::class, 'reset']);
    Route::post('/admin/printers/{id}/maintenance-complete', [\App\Http\Controllers\PrinterController::class, 'maintenanceComplete']);
    Route::delete('/admin/printers/{id}', [\App\Http\Controllers\PrinterController::class, 'destroy']);

    // Contactos (Clientes y Proveedores)
    Route::get('/admin/contacts/customers', [\App\Http\Controllers\Admin\ContactController::class, 'getCustomers']);
    Route::post('/admin/contacts/customers', [\App\Http\Controllers\Admin\ContactController::class, 'storeCustomer']);
    Route::patch('/admin/contacts/customers/{id}', [\App\Http\Controllers\Admin\ContactController::class, 'updateCustomer']);
    Route::delete('/admin/contacts/customers/{id}', [\App\Http\Controllers\Admin\ContactController::class, 'deleteCustomer']);

    Route::get('/admin/contacts/suppliers', [\App\Http\Controllers\Admin\ContactController::class, 'getSuppliers']);
    Route::post('/admin/contacts/suppliers', [\App\Http\Controllers\Admin\ContactController::class, 'storeSupplier']);
    Route::patch('/admin/contacts/suppliers/{id}', [\App\Http\Controllers\Admin\ContactController::class, 'updateSupplier']);
    Route::delete('/admin/contacts/suppliers/{id}', [\App\Http\Controllers\Admin\ContactController::class, 'deleteSupplier']);
});
