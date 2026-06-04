<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\AuthController;

use App\Http\Controllers\MaterialController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\UnifiedAuthController;

// --- PROTOCOLO DE ACCESO UNIFICADO N3XT ---
Route::any('/ping', function() { return response()->json(['status' => 'online', 'timestamp' => now()]); });
Route::middleware('throttle:10,1')->post('/auth-master-industrial-access', [UnifiedAuthController::class, 'login']);
Route::middleware('throttle:5,1')->post('/auth/unified-register', [UnifiedAuthController::class, 'register']);

// Ruta de Diagnóstico para el Lanzador N3XT
Route::get('/health-check', function() { return response()->json(['status' => 'online', 'version' => '3.5.0']); });

// Ruta pública protegida por rate limit
Route::middleware('throttle:5,1')->post('/orders', [OrderController::class, 'store']);
Route::middleware('throttle:15,1')->post('/process-stl', [OrderController::class, 'processStl']);
Route::get('/orders/track', [OrderController::class, 'track']);

// Ruta pública para leer configuraciones y materiales
Route::get('/settings', [SettingController::class, 'index']);
Route::get('/materials', [MaterialController::class, 'index']);

// Rutas de autenticación Maker (Clientes) - Legacy
Route::post('/maker-access/register', [CustomerAuthController::class, 'register']);
Route::post('/maker-access/login', [CustomerAuthController::class, 'login']);
Route::middleware('auth:sanctum')->get('/customer/profile', [CustomerAuthController::class, 'profile']);
Route::middleware('auth:sanctum')->post('/customer/logout', [CustomerAuthController::class, 'logout']);

// Rutas protegidas para Administrador
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json($request->user());
    });
    // Analytics & Accounting
    Route::get('/admin/analytics', [\App\Http\Controllers\Admin\AnalyticsController::class, 'index']);
    Route::get('/admin/export-csv', [\App\Http\Controllers\Admin\AnalyticsController::class, 'exportCsv']);

    // Backup & Sync
    Route::get('/admin/backup/export', [\App\Http\Controllers\Admin\BackupController::class, 'exportAll']);
    Route::post('/admin/backup/import', [\App\Http\Controllers\Admin\BackupController::class, 'importAll']);

    Route::post('/logout', [AuthController::class, 'logout']);
    
    // Panel de control
    Route::get('/admin/orders', [OrderController::class, 'index']);
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
