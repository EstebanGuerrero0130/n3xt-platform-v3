<?php
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$admin = User::where('email', 'admin@n3xt3d.com')->first();
if ($admin) {
    $admin->password = Hash::make('admin123');
    $admin->save();
    echo "Password reset for: " . $admin->email . "\n";
    echo "New Hash: " . $admin->password . "\n";
} else {
    echo "User not found!\n";
}
