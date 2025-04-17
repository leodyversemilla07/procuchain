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
            'email' => 'bac.secretariat@procuchain.com',
            'role' => 'bac_secretariat',
            'blockchain_address' => env('MULTICHAIN_BAC_SECRETARIAT_ADDRESS'),
            'password' => Hash::make('secretariat123'),
        ]);

        User::factory()->create([
            'name' => 'BAC Chairman',
            'email' => 'bac.chairman@procuchain.com',
            'role' => 'bac_chairman',
            'blockchain_address' => env('MULTICHAIN_BAC_CHAIRMAN_ADDRESS'),
            'password' => Hash::make('chairman123'),
        ]);

        User::factory()->create([
            'name' => 'HOPE',
            'email' => 'hope@procuchain.com',
            'role' => 'hope',
            'blockchain_address' => env('MULTICHAIN_HOPE_ADDRESS'),
            'password' => Hash::make('hope123'),
        ]);
    }
}
