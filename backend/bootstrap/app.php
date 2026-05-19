<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// --- N3XT Vercel Serverless Patch ---
$isVercel = isset($_SERVER['VERCEL']) || isset($_SERVER['VERCEL_URL']) || getenv('VERCEL') || getenv('VERCEL_URL') || (isset($_SERVER['LAMBDA_TASK_ROOT']) || str_contains(getcwd(), '/var/task'));

if ($isVercel) {
    $storagePath = '/tmp/storage';
    if (!is_dir($storagePath)) {
        mkdir($storagePath, 0755, true);
        mkdir("$storagePath/framework/views", 0755, true);
        mkdir("$storagePath/framework/cache", 0755, true);
        mkdir("$storagePath/framework/sessions", 0755, true);
        mkdir("$storagePath/logs", 0755, true);
    }
    putenv("VITE_STORAGE_PATH=$storagePath");
    putenv("VIEW_COMPILED_PATH=$storagePath/framework/views");
    putenv("LOG_CHANNEL=stderr"); // Enviar logs a la consola de Vercel, no a archivo
}
// ------------------------------------

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Aplicar ruta de almacenamiento si estamos en Vercel
if ($isVercel) {
    $app->useStoragePath('/tmp/storage');
}

return $app;
