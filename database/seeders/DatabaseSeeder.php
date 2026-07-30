<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Default Superuser
        User::factory()->create([
            'name' => 'Super User',
            'email' => 'admin@example.com',
            'is_superuser' => true,
            'is_verified' => true,
        ]);

        // Default Normal Verified User
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'is_superuser' => false,
            'is_verified' => true,
        ]);

        // Default Normal Pending User
        User::factory()->create([
            'name' => 'Pending User',
            'email' => 'pending@example.com',
            'is_superuser' => false,
            'is_verified' => false,
        ]);
    }
}
