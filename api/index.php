<?php
// Gateway Industrial N3XT - MODO DIAGNÓSTICO
try {
    require __DIR__ . '/../backend/public/index.php';
} catch (\Throwable $e) {
    header('Content-Type: text/plain');
    echo "ERROR N3XT DIAGNOSTIC:\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Ruta actual: " . __DIR__ . "\n";
    echo "Vendor path local: " . realpath(__DIR__ . '/../backend/vendor/autoload.php') . "\n";
    echo "Vendor path root: " . realpath(__DIR__ . '/../vendor/autoload.php') . "\n";
    exit(1);
}
