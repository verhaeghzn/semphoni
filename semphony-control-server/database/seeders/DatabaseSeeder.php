<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CommandSeeder::class,
            ClientTypeSeeder::class,
            SystemSeeder::class,
            RolePermissionSeeder::class,
        ]);

        // User::factory(10)->create();

        $testUser = User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );

        $testUser->syncRoles(['User']);

        $adminUser = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'email_verified_at' => now(),
                'password' => 'password',
            ],
        );

        $adminUser->syncRoles(['Admin']);
    }
}
