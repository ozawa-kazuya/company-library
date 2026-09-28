<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'user_code' => '001',
                'name' => '管理者 太郎',
                'password' => Hash::make('password'),
                'must_change_password' => true,
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'user_code' => '002',
                'name' => '山田 太郎',
                'password' => Hash::make('password'),
                'must_change_password' => true,
                'role' => 'user',
            ]
        );

        User::updateOrCreate(
            ['email' => 'user2@example.com'],
            [
                'user_code' => '003',
                'name' => '小澤 和也',
                'password' => Hash::make('password'),
                'must_change_password' => true,
                'role' => 'user',
            ]
        );
    }
}
