<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FilamentAdminUserSeeder extends Seeder
{
    /**
     * Seed the application's admin user for Filament access.
     */
    public function run(): void
    {
        $name = env('FILAMENT_ADMIN_NAME', 'Admin');
        $email = env('FILAMENT_ADMIN_EMAIL', 'admin@market-app.com');
        $phone = env('FILAMENT_ADMIN_PHONE', '0999999999');
        $password = env('FILAMENT_ADMIN_PASSWORD', 'Admin@123456');
        $role = env('FILAMENT_ADMIN_ROLE', 'admin');

        if (! in_array($role, ['admin', 'vendor', 'customer'], true)) {
            $role = 'admin';
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make($password),
                'role' => $role,
                'is_active' => true,
                'remember_token' => Str::random(10),
            ]
        );
    }
}
