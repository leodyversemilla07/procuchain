<?php

namespace Tests;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

trait SeedsPermissions
{
    /**
     * @var list<string>
     */
    private static array $testPermissions = [
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

    /**
     * @var array<string, list<string>>
     */
    private static array $testRolePermissions = [
        'bac_secretariat' => [
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
        ],
        'bac_chairman' => [
            'view bac-chairman dashboard',
            'view procurement',
            'view documents',
            'download documents',
            'approve procurement',
            'reject procurement',
            'approve stage transition',
            'view blockchain transactions',
            'view settings',
        ],
        'hope' => [
            'view hope dashboard',
            'view procurement',
            'view documents',
            'download documents',
            'approve procurement',
            'view blockchain transactions',
            'view settings',
        ],
    ];

    protected function seedPermissions(): void
    {
        $this->seedPermissionsAndRoles();
    }

    protected function seedPermissionsAndRoles(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $now = now();

        DB::table('permissions')->insertOrIgnore(array_map(fn (string $permission): array => [
            'name' => $permission,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ], self::$testPermissions));

        DB::table('roles')->insertOrIgnore(array_map(fn (string $role): array => [
            'name' => $role,
            'guard_name' => 'web',
            'created_at' => $now,
            'updated_at' => $now,
        ], ['admin', 'bac_secretariat', 'bac_chairman', 'hope']));

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->pluck('id', 'name');
        $roleIds = DB::table('roles')
            ->where('guard_name', 'web')
            ->pluck('id', 'name');

        $rolePermissions = [
            'admin' => self::$testPermissions,
            ...self::$testRolePermissions,
        ];

        $pivotRows = [];
        foreach ($rolePermissions as $role => $permissions) {
            foreach ($permissions as $permission) {
                $pivotRows[] = [
                    'role_id' => $roleIds[$role],
                    'permission_id' => $permissionIds[$permission],
                ];
            }
        }

        DB::table('role_has_permissions')->insertOrIgnore($pivotRows);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
