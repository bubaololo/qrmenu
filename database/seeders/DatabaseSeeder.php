<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
            'name' => 'buba',
            'email' => 'bubaololo@gmail.com',
            'password' => Hash::make('nookie22'),
        ]);

        $this->call(IconsSyncSeeder::class);
        $this->call(PromptTypeSeeder::class);
        $this->call(PromptSeeder::class);
    }
}
