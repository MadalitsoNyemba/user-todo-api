<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Two fixed accounts so a reviewer can log in immediately, and so the
     * ownership tests later have a second user to be denied as.
     *
     * firstOrCreate keeps this safe to re-run against an existing database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'alice@example.com'],
            ['name' => 'Alice Example', 'password' => 'password'],
        );

        User::firstOrCreate(
            ['email' => 'bob@example.com'],
            ['name' => 'Bob Example', 'password' => 'password'],
        );
    }
}
