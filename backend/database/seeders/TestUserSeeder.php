<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@n3xt.com'],
            [
                'name' => 'Admin N3XT',
                'password' => Hash::make('admin123')
            ]
        );
    }
}
