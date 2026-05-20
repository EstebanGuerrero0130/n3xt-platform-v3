<?php
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\User;

$admin = User::where('email', 'admin@n3xt3d.com')->first();
$tokenObj = $admin->createToken('debug_token');
$plainToken = $tokenObj->plainTextToken;
echo "Plain Token: $plainToken\n";

// Sanctum normally splits by | 
$parts = explode('|', $plainToken);
$actualToken = end($parts);

$found = PersonalAccessToken::findToken($actualToken);
echo "Token encontrado en DB: " . ($found ? 'SI' : 'NO') . "\n";
if ($found) {
    echo "Pertenece a: " . $found->tokenable->email . "\n";
}
