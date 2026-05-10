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
        $bacSecretariatAddress = $multichain->getnewaddress();
        $bacSecretariat = User::create([
            'name' => 'Leodyver Semilla',
            'email' => 'leodyversemilla07@gmail.com',
            'blockchain_address' => $bacSecretariatAddress,
            'password' => Hash::make('Leodyver07'),
        ]);
        $bacSecretariat->assignRole(UserRoleEnums::BAC_SECRETARIAT->value);
        Log::info('BAC Secretariat user created', ['address' => $bacSecretariatAddress]);

        // Create BAC Chairman user
        Role::firstOrCreate(['name' => UserRoleEnums::BAC_CHAIRMAN->value, 'guard_name' => 'web']);
        $bacChairmanAddress = $multichain->getnewaddress();
        $bacChairman = User::create([
            'name' => 'Adrian Gupit',
            'email' => 'adriangupit18@gmail.com',
            'blockchain_address' => $bacChairmanAddress,
            'password' => Hash::make('Adrian10'),
        ]);
        $bacChairman->assignRole(UserRoleEnums::BAC_CHAIRMAN->value);
        Log::info('BAC Chairman user created', ['address' => $bacChairmanAddress]);

        // Create HOPE user
        Role::firstOrCreate(['name' => UserRoleEnums::HOPE->value, 'guard_name' => 'web']);
        $hopeAddress = $multichain->getnewaddress();
        $hope = User::create([
            'name' => 'Leif Sage Semilla',
            'email' => 'leifsagesemilla@gmail.com',
            'blockchain_address' => $hopeAddress,
            'password' => Hash::make('LeifSage07'),
        ]);
        $hope->assignRole(UserRoleEnums::HOPE->value);
        Log::info('HOPE user created', ['address' => $hopeAddress]);

        // Create Admin user
        Role::firstOrCreate(['name' => UserRoleEnums::ADMIN->value, 'guard_name' => 'web']);
        $adminAddress = $multichain->getnewaddress();
        $admin = User::create([
            'name' => 'LeoBriel Zilvrak',
            'email' => 'leobrielzilvrak@gmail.com',
            'blockchain_address' => $adminAddress,
            'password' => Hash::make('LeoBriel07'),
        ]);
        $admin->assignRole(UserRoleEnums::ADMIN->value);
        Log::info('Admin user created', ['address' => $adminAddress]);
    }
}
