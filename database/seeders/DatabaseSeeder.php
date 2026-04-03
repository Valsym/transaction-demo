<?php

namespace Database\Seeders;

use App\Models\Account;
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
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Account::create(['name' => 'Alice', 'balance' => 1000]);
        Account::create(['name' => 'Bob', 'balance' => 500]);
        Account::create(['name' => 'Charlie', 'balance' => 0]);

    }
}
