<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\BlockchainRpcClient;
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
        $multichain = app(BlockchainRpcClient::class);

        // Create BAC Secretariat user
        Role::firstOrCreate(['name' => UserRole::BAC_SECRETARIAT->value, 'guard_name' => 'web']);
        $bacSecretariat = User::firstOrCreate(
            ['email' => 'brylemaamo@gmail.com'],
            [
                'name' => 'Bryle Maamo',
                'blockchain_address' => $multichain->getnewaddress(),
                'password' => Hash::make('BryleMaamo00'),
            ]
        );
        $bacSecretariat->assignRole(UserRole::BAC_SECRETARIAT->value);
        Log::info('BAC Secretariat user ready', ['address' => $bacSecretariat->blockchain_address]);

        // Create BAC Chairman user
        Role::firstOrCreate(['name' => UserRole::BAC_CHAIRMAN->value, 'guard_name' => 'web']);
        $bacChairman = User::firstOrCreate(
            ['email' => 'adriangupit18@gmail.com'],
            [
                'name' => 'Adrian Gupit',
                'blockchain_address' => $multichain->getnewaddress(),
                'password' => Hash::make('Adrian18'),
            ]
        );
        $bacChairman->assignRole(UserRole::BAC_CHAIRMAN->value);
        Log::info('BAC Chairman user ready', ['address' => $bacChairman->blockchain_address]);

        // Create HOPE user
        Role::firstOrCreate(['name' => UserRole::HOPE->value, 'guard_name' => 'web']);
        $hope = User::firstOrCreate(
            ['email' => 'leifsagesemilla@gmail.com'],
            [
                'name' => 'Leif Sage Semilla',
                'blockchain_address' => $multichain->getnewaddress(),
                'password' => Hash::make('LeifSage07'),
            ]
        );
        $hope->assignRole(UserRole::HOPE->value);
        Log::info('HOPE user ready', ['address' => $hope->blockchain_address]);

        // Create Admin user
        Role::firstOrCreate(['name' => UserRole::ADMIN->value, 'guard_name' => 'web']);
        $admin = User::firstOrCreate(
            ['email' => 'leobrielzilvrak@gmail.com'],
            [
                'name' => 'LeoBriel Zilvrak',
                'blockchain_address' => $multichain->getnewaddress(),
                'password' => Hash::make('LeoBriel07'),
            ]
        );
        $admin->assignRole(UserRole::ADMIN->value);
        Log::info('Admin user ready', ['address' => $admin->blockchain_address]);
    }
}
