<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions based on procurement workflow stages and actions
        $permissions = [
            // Dashboard access permissions
            'view admin dashboard',
            'view bac-secretariat dashboard',
            'view bac-chairman dashboard',
            'view hope dashboard',

            // Procurement management permissions
            'create procurement',
            'view procurement',
            'edit procurement',
            'delete procurement',
            'publish procurement',
            'manage procurements',

            // Document management permissions
            'upload documents',
            'view documents',
            'download documents',
            'delete documents',

            // User management permissions (admin only)
            'manage users',
            'create users',
            'edit users',
            'delete users',
            'assign roles',

            // Procurement stage permissions
            'manage procurement initiation',
            'manage pre-procurement conference',
            'manage bidding documents',
            'manage pre-bid conference',
            'manage supplemental bid bulletin',
            'manage bid opening',
            'manage bid evaluation',
            'manage post-qualification',
            'manage bac resolution',
            'manage notice of award',
            'manage performance bond contract po',
            'manage notice to proceed',
            'manage monitoring',
            'manage completion',

            // Approval permissions
            'approve procurement',
            'reject procurement',
            'approve stage transition',

            // Blockchain permissions
            'view blockchain transactions',
            'publish to blockchain',

            // Notification permissions
            'manage notifications',
            'send notifications',

            // Settings permissions
            'manage settings',
            'view settings',
        ];

        // Create all permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Admin role - has all permissions except procurement creation
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(
            Permission::where('name', '!=', 'create procurement')->get()
        );

        // BAC Secretariat role - manages procurement workflow
        $bacSecretariatRole = Role::create(['name' => 'bac_secretariat']);
        $bacSecretariatRole->givePermissionTo([
            'view bac-secretariat dashboard',
            'create procurement',
            'view procurement',
            'edit procurement',
            'publish procurement',
            'manage procurements',
            'upload documents',
            'view documents',
            'download documents',
            'manage procurement initiation',
            'manage pre-procurement conference',
            'manage bidding documents',
            'manage pre-bid conference',
            'manage supplemental bid bulletin',
            'manage bid opening',
            'manage bid evaluation',
            'manage post-qualification',
            'manage bac resolution',
            'manage notice of award',
            'manage performance bond contract po',
            'manage notice to proceed',
            'manage monitoring',
            'manage completion',
            'publish to blockchain',
            'view blockchain transactions',
            'view settings',
        ]);

        // BAC Chairman role - approves and oversees
        $bacChairmanRole = Role::create(['name' => 'bac_chairman']);
        $bacChairmanRole->givePermissionTo([
            'view bac-chairman dashboard',
            'view procurement',
            'view documents',
            'download documents',
            'approve procurement',
            'reject procurement',
            'approve stage transition',
            'view blockchain transactions',
            'view settings',
        ]);

        // HOPE role - oversight and monitoring
        $hopeRole = Role::create(['name' => 'hope']);
        $hopeRole->givePermissionTo([
            'view hope dashboard',
            'view procurement',
            'view documents',
            'download documents',
            'approve procurement',
            'view blockchain transactions',
            'view settings',
        ]);

        $this->command->info('Roles and permissions created successfully!');
    }
}
