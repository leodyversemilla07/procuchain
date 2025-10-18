<?php

namespace Tests;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

trait SeedsPermissions
{
    protected function seedPermissionsAndRoles(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define all permissions (same as RoleAndPermissionSeeder)
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
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions

        // Admin role - has all permissions
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        // BAC Secretariat role - manages procurement workflow
        $bacSecretariatRole = Role::firstOrCreate(['name' => 'bac_secretariat', 'guard_name' => 'web']);
        $bacSecretariatRole->syncPermissions([
            'view bac-secretariat dashboard',
            'create procurement',
            'view procurement',
            'edit procurement',
            'publish procurement',
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
        $bacChairmanRole = Role::firstOrCreate(['name' => 'bac_chairman', 'guard_name' => 'web']);
        $bacChairmanRole->syncPermissions([
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
        $hopeRole = Role::firstOrCreate(['name' => 'hope', 'guard_name' => 'web']);
        $hopeRole->syncPermissions([
            'view hope dashboard',
            'view procurement',
            'view documents',
            'download documents',
            'approve procurement',
            'view blockchain transactions',
            'view settings',
        ]);
    }
}
