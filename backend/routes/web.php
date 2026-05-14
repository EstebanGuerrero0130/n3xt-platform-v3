<?php

use Illuminate\Support\Facades\Route;

// Servir la interfaz de N3XT 3D (Vue) para cualquier ruta que no sea de API
Route::get('/', function () {
    return response()->file(public_path('index.html'));
});

// Manejar rutas de Vue (History Mode)
// Puerta Maestra Unificada (Bypass Total)
Route::any('/n3xt-access-core/', [\App\Http\Controllers\UnifiedAuthController::class, 'login']);

Route::fallback(function () {
    return response()->file(public_path('index.html'));
});
