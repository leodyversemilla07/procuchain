<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create BAC Secretariat user
        User::create([
            'name' => 'Leodyver Semilla',
            'email' => 'admin@example.com',
            'role' => 'bac_secretariat',
            'blockchain_address' => config('multichain.addresses.bac_secretariat'),
            'password' => Hash::make('Leodyver07'),
        ]);

        // Create BAC Chairman user
        User::create([
            'name' => 'Lunar Spectre',
            'email' => 'lunarspectre00@gmail.com',
            'role' => 'bac_chairman',
            'blockchain_address' => config('multichain.addresses.bac_chairman'),
            'password' => Hash::make('LunarSpectre00'),
        ]);

        // Create HOPE user
        User::create([
            'name' => 'Leif Sage Semilla',
            'email' => 'leifsagesemilla@gmail.com',
            'role' => 'hope',
            'blockchain_address' => config('multichain.addresses.hope'),
            'password' => Hash::make('LeifSage07'),
        ]);

        // Create Admin user
        User::create([
            'name' => 'LeoBriel Zilvrak',
            'email' => 'leobrielzilvrak@gmail.com',
            'role' => 'admin',
            'blockchain_address' => config('multichain.addresses.admin'),
            'password' => Hash::make('LeoBriel07'),
        ]);
    }
}
