<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // 2. Create the Super Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'], // Check by email
            [
                'name' => 'Super Admin',
                'password' => Hash::make('pass@admin'), // Default password
                'email_verified_at' => now(),
            ]
        );

        // 3. Assign the Admin Role
        $admin->assignRole($adminRole);
        
        $this->command->info('Admin user created');
    }
}