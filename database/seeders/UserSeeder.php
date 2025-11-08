<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\MultichainService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $multichainService = app(MultichainService::class);

        // Create BAC Secretariat user
        Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
        $bacSecretariatAddress = $multichainService->getNewAddress();
        $bacSecretariat = User::create([
            'name' => 'Leodyver Semilla',
            'email' => 'leodyversemilla07@gmail.com',
            'blockchain_address' => $bacSecretariatAddress,
            'password' => Hash::make('Leodyver07'),
        ]);
        $bacSecretariat->assignRole('bac_secretariat');
        Log::info('BAC Secretariat user created', ['address' => $bacSecretariatAddress]);

        // Create BAC Chairman user
        Role::firstOrCreate(['name' => 'bac_chairman', 'guard_name' => 'web']);
        $bacChairmanAddress = $multichainService->getNewAddress();
        $bacChairman = User::create([
            'name' => 'Lunar Spectre',
            'email' => 'lunarspectre00@gmail.com',
            'blockchain_address' => $bacChairmanAddress,
            'password' => Hash::make('LunarSpectre00'),
        ]);
        $bacChairman->assignRole('bac_chairman');
        Log::info('BAC Chairman user created', ['address' => $bacChairmanAddress]);

        // Create HOPE user
        Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web']);
        $hopeAddress = $multichainService->getNewAddress();
        $hope = User::create([
            'name' => 'Leif Sage Semilla',
            'email' => 'leifsagesemilla@gmail.com',
            'blockchain_address' => $hopeAddress,
            'password' => Hash::make('LeifSage07'),
        ]);
        $hope->assignRole('hope');
        Log::info('HOPE user created', ['address' => $hopeAddress]);

        // Create Admin user
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminAddress = $multichainService->getNewAddress();
        $admin = User::create([
            'name' => 'LeoBriel Zilvrak',
            'email' => 'leobrielzilvrak@gmail.com',
            'blockchain_address' => $adminAddress,
            'password' => Hash::make('LeoBriel07'),
        ]);
        $admin->assignRole('admin');
        Log::info('Admin user created', ['address' => $adminAddress]);
    }
}
