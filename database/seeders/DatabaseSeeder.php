<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'BAC Secretariat',
            'email' => 'leodyversemillla07@gmail.com',
            'role' => 'bac_secretariat',
            'blockchain_address' => env('MULTICHAIN_BAC_SECRETARIAT_ADDRESS'),
            'password' => Hash::make('Leodyver07'),
        ]);

        User::factory()->create([
            'name' => 'BAC Chairman',
            'email' => 'lunarspectre00@gmail.com',
            'role' => 'bac_chairman',
            'blockchain_address' => env('MULTICHAIN_BAC_CHAIRMAN_ADDRESS'),
            'password' => Hash::make('LunarSpectre00'),
        ]);

        User::factory()->create([
            'name' => 'HOPE',
            'email' => 'leifsagesemilla@gmail.com',
            'role' => 'hope',
            'blockchain_address' => env('MULTICHAIN_HOPE_ADDRESS'),
            'password' => Hash::make('LeifSage07'),
        ]);
    }
}
