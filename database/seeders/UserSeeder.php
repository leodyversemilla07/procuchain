<?php

namespace Database\Seeders;

use App\Enums\UserRoleEnums;
use App\Models\User;
use App\Services\Manager;
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
        $multichain = app(Manager::class);

        // Create BAC Secretariat user
        Role::firstOrCreate(['name' => UserRoleEnums::BAC_SECRETARIAT->value, 'guard_name' => 'web']);
        $bacSecretariat = User::firstOrCreate(
            ['email' => 'brylemaamo@gmail.com'],
            [
                'name' => 'Bryle Maamo',
                'blockchain_address' => $multichain->getnewaddress(),
                'password' => Hash::make('BryleMaamo00'),
            ]
        );
        $bacSecretariat->assignRole(UserRoleEnums::BAC_SECRETARIAT->value);
        Log::info('BAC Secretariat user ready', ['address' => $bacSecretariat->blockchain_address]);

        // Create BAC Chairman user
        Role::firstOrCreate(['name' => UserRoleEnums::BAC_CHAIRMAN->value, 'guard_name' => 'web']);
        $bacChairman = User::firstOrCreate(
            ['email' => 'adriangupit18@gmail.com'],
            [
                'name' => 'Adrian Gupit',
                'blockchain_address' => $multichain->getnewaddress(),
                'password' => Hash::make('Adrian18'),
            ]
        );
        $bacChairman->assignRole(UserRoleEnums::BAC_CHAIRMAN->value);
        Log::info('BAC Chairman user ready', ['address' => $bacChairman->blockchain_address]);

        // Create HOPE user
        Role::firstOrCreate(['name' => UserRoleEnums::HOPE->value, 'guard_name' => 'web']);
        $hope = User::firstOrCreate(
            ['email' => 'leifsagesemilla@gmail.com'],
            [
                'name' => 'Leif Sage Semilla',
                'blockchain_address' => $multichain->getnewaddress(),
                'password' => Hash::make('LeifSage07'),
            ]
        );
        $hope->assignRole(UserRoleEnums::HOPE->value);
        Log::info('HOPE user ready', ['address' => $hope->blockchain_address]);

        // Create Admin user
        Role::firstOrCreate(['name' => UserRoleEnums::ADMIN->value, 'guard_name' => 'web']);
        $admin = User::firstOrCreate(
            ['email' => 'leobrielzilvrak@gmail.com'],
            [
                'name' => 'LeoBriel Zilvrak',
                'blockchain_address' => $multichain->getnewaddress(),
                'password' => Hash::make('LeoBriel07'),
            ]
        );
        $admin->assignRole(UserRoleEnums::ADMIN->value);
        Log::info('Admin user ready', ['address' => $admin->blockchain_address]);
    }
}
