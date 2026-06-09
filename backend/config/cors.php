<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'storage/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    |--------------------------------------------------------------------------
    | Allowed Origins
    |--------------------------------------------------------------------------
    |
    | Define los orígenes permitidos para CORS.
    | En local se incluyen localhost y 127.0.0.1.
    | En producción, define CORS_ALLOWED_ORIGINS en .env
    | Ejemplo: CORS_ALLOWED_ORIGINS=https://n3xt3d.com,https://admin.n3xt3d.com
    |
    */
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:8000,http://localhost:4173,http://127.0.0.1:5173,http://127.0.0.1:8000,http://127.0.0.1:4173')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 86400,

    'supports_credentials' => true,

];
