<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// --- N3XT Vercel Serverless Patch ---
$isVercel = isset($_SERVER['VERCEL']) || isset($_SERVER['VERCEL_URL']) || getenv('VERCEL') || getenv('VERCEL_URL') || (isset($_SERVER['LAMBDA_TASK_ROOT']) || str_contains(getcwd(), '/var/task'));

if ($isVercel) {
    // Evitar que Symfony/Laravel detecte '/api' como base URL al venir de api/index.php
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
    if (isset($_SERVER['ORIG_SCRIPT_NAME'])) {
        $_SERVER['ORIG_SCRIPT_NAME'] = '/index.php';
    }

    $storagePath = '/tmp/storage';
    $dirs = [
        $storagePath,
        "$storagePath/framework",
        "$storagePath/framework/views",
        "$storagePath/framework/cache",
        "$storagePath/framework/sessions",
        "$storagePath/bootstrap",
        "$storagePath/bootstrap/cache",
        "$storagePath/logs"
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
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
        $middleware->alias([
            'supabase' => \App\Http\Middleware\SupabaseAuthMiddleware::class,
        ]);
        // Agregar middleware de sesión a rutas API para Sanctum SPA
        // (StartSession, EncryptCookies, AddQueuedCookiesToResponse)
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($isVercel): void {
        if ($isVercel) {
            $exceptions->render(function (\Throwable $e) {
                return response()->json([
                    'error' => 'N3XT_INTERNAL_ERROR',
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString())
                ], 500);
            });
        }
    })->create();

// Aplicar ruta de almacenamiento si estamos en Vercel
if ($isVercel) {
    $app->useStoragePath('/tmp/storage');
}

return $app;
