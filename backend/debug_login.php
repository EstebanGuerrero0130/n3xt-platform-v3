<?php
use App\Http\Controllers\UnifiedAuthController;
use Illuminate\Http\Request;

$controller = new UnifiedAuthController();
$request = new Request([
    'email' => 'admin@n3xt3d.com',
    'password' => 'admin123'
]);

$response = $controller->login($request);
echo $response->getContent();
