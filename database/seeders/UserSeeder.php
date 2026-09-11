<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'alice@example.com'],
            [
                'name' => 'Alice Example',
                'password' => 'password',
                'role' => UserRole::User,
            ],
        );

        User::firstOrCreate(
            ['email' => 'bob@example.com'],
            [
                'name' => 'Bob Example',
                'password' => 'password',
                'role' => UserRole::User,
            ],
        );

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin Example',
                'password' => 'password',
                'role' => UserRole::Admin,
            ],
        );
    }
}
