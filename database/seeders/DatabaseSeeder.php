<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'user@gmail.com'], // unique key
            [
                'name'     => 'Test User',
                'password' => Hash::make('password'),
                'status'   => 'active',
            ]
        );

        Admin::updateOrCreate(
            ['email' => 'admin@gmail.com'], // unique key
            [
                'name'     => 'Super Admin',
                'password' => Hash::make('password123'),
                'role'     => 'super_admin',
                'status'   => 'active',
            ]
        );
    }
}
