<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create BAC Secretariat user
        Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
        $bacSecretariat = User::create([
            'name' => 'Leodyver Semilla',
            'email' => 'admin@example.com',
            'blockchain_address' => config('multichain.addresses.bac_secretariat'),
            'password' => Hash::make('Leodyver07'),
        ]);
        $bacSecretariat->assignRole('bac_secretariat');

        // Create BAC Chairman user
        Role::firstOrCreate(['name' => 'bac_chairman', 'guard_name' => 'web']);
        $bacChairman = User::create([
            'name' => 'Lunar Spectre',
            'email' => 'lunarspectre00@gmail.com',
            'blockchain_address' => config('multichain.addresses.bac_chairman'),
            'password' => Hash::make('LunarSpectre00'),
        ]);
        $bacChairman->assignRole('bac_chairman');

        // Create HOPE user
        Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web']);
        $hope = User::create([
            'name' => 'Leif Sage Semilla',
            'email' => 'leifsagesemilla@gmail.com',
            'blockchain_address' => config('multichain.addresses.hope'),
            'password' => Hash::make('LeifSage07'),
        ]);
        $hope->assignRole('hope');

        // Create Admin user
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::create([
            'name' => 'LeoBriel Zilvrak',
            'email' => 'leobrielzilvrak@gmail.com',
            'blockchain_address' => config('multichain.addresses.admin'),
            'password' => Hash::make('LeoBriel07'),
        ]);
        $admin->assignRole('admin');
    }
}
