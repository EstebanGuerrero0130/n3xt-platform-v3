<?php
// Entrada de Vercel al backend Laravel. Si falla el arranque (APP_KEY, base de
// datos…), el detalle va al log de la función y el visitante solo ve un 500.
try {
    require __DIR__ . '/../backend/public/index.php';
} catch (\Throwable $e) {
    error_log('N3XT bootstrap error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'Error', 'message' => 'Error interno del servidor.', 'data' => null]);
    exit(1);
}
