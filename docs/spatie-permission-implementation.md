# Spatie Laravel Permission Implementation Guide

## Overview

This document outlines the implementation of Spatie Laravel Permission package in the ProcuChain application for robust role-based access control (RBAC).

## What Was Implemented

### 1. Package Installation
- **Package**: `spatie/laravel-permission` v6.21.0
- **Installation Date**: October 10, 2025
- **Laravel Version**: 12.32.5
- **PHP Version**: 8.2.29

### 2. Database Schema

The following tables were created by running the migration:

- `roles` - Stores all role definitions
- `permissions` - Stores all permission definitions
- `model_has_permissions` - Pivot table for direct user permissions
- `model_has_roles` - Pivot table for user roles
- `role_has_permissions` - Pivot table for role permissions

### 3. Roles Defined

Four primary roles were created based on the existing ProcuChain user roles:

1. **Admin** (`admin`)
   - Full system access
   - All permissions granted

2. **BAC Secretariat** (`bac_secretariat`)
   - Manages procurement workflow
   - Creates, edits, and publishes procurements
   - Manages all procurement stages
   - Uploads and manages documents

3. **BAC Chairman** (`bac_chairman`)
   - Approves and oversees procurements
   - Views procurement and documents
   - Approves stage transitions

4. **HOPE** (`hope`)
   - Head of Procuring Entity
   - Oversight and monitoring role
   - Views procurements and documents
   - Approves major decisions

### 4. Permissions Defined

A total of 47 permissions were created, grouped into categories:

#### Dashboard Access (4 permissions)
- `view admin dashboard`
- `view bac-secretariat dashboard`
- `view bac-chairman dashboard`
- `view hope dashboard`

#### Procurement Management (5 permissions)
- `create procurement`
- `view procurement`
- `edit procurement`
- `delete procurement`
- `publish procurement`

#### Document Management (4 permissions)
- `upload documents`
- `view documents`
- `download documents`
- `delete documents`

#### User Management (5 permissions - Admin only)
- `manage users`
- `create users`
- `edit users`
- `delete users`
- `assign roles`

#### Procurement Stage Management (14 permissions)
- `manage procurement initiation`
- `manage pre-procurement conference`
- `manage bidding documents`
- `manage pre-bid conference`
- `manage supplemental bid bulletin`
- `manage bid opening`
- `manage bid evaluation`
- `manage post-qualification`
- `manage bac resolution`
- `manage notice of award`
- `manage performance bond contract po`
- `manage notice to proceed`
- `manage monitoring`
- `manage completion`

#### Approval Permissions (3 permissions)
- `approve procurement`
- `reject procurement`
- `approve stage transition`

#### Blockchain Permissions (2 permissions)
- `view blockchain transactions`
- `publish to blockchain`

#### Notification Permissions (2 permissions)
- `manage notifications`
- `send notifications`

#### Settings Permissions (2 permissions)
- `manage settings`
- `view settings`

## Code Changes

### 1. User Model (`app/Models/User.php`)

Added the `HasRoles` trait:

```php
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasPushSubscriptions, HasRoles, Notifiable, TwoFactorAuthenticatable;
    
    // ... rest of the model
}
```

### 2. Middleware Registration (`bootstrap/app.php`)

Replaced custom `CheckRole` middleware with Spatie's middleware:

```php
$middleware->alias([
    'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
    'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
    'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
]);
```

### 3. Database Seeders

#### RoleAndPermissionSeeder (`database/seeders/RoleAndPermissionSeeder.php`)
- Creates all 47 permissions
- Creates 4 roles
- Assigns permissions to roles

#### UserSeeder (`database/seeders/UserSeeder.php`)
- Updated to assign Spatie roles using `assignRole()` method
- Maintains backward compatibility with old `role` column

#### DatabaseSeeder (`database/seeders/DatabaseSeeder.php`)
- Updated to call `RoleAndPermissionSeeder` before `UserSeeder`

### 4. Migrations

#### Sync Migration (`2025_10_10_122500_sync_existing_users_with_spatie_roles.php`)
- Migrates existing users to Spatie permission system
- Maps old `role` column values to Spatie roles
- Safely handles errors during migration

## Usage Examples

### Checking Permissions in Controllers

```php
// Check if user has a specific permission
if ($user->can('create procurement')) {
    // User can create procurements
}

// Check if user has a specific role
if ($user->hasRole('admin')) {
    // User is an admin
}

// Check if user has any of the given roles
if ($user->hasAnyRole(['admin', 'bac_secretariat'])) {
    // User is either admin or bac_secretariat
}

// Check if user has all given roles
if ($user->hasAllRoles(['admin', 'bac_secretariat'])) {
    // User has both roles
}
```

### Using Middleware in Routes

```php
// Existing routes automatically work with new middleware
Route::middleware(['role:bac_secretariat'])->group(function () {
    // Only bac_secretariat can access
});

// Check for specific permission
Route::middleware(['permission:create procurement'])->group(function () {
    // Only users with 'create procurement' permission
});

// Check for role OR permission
Route::middleware(['role_or_permission:admin|create procurement'])->group(function () {
    // Admins OR users with 'create procurement' permission
});

// Multiple roles (OR logic)
Route::middleware(['role:admin|bac_secretariat'])->group(function () {
    // Admin OR bac_secretariat
});
```

### Using in Blade/Inertia

```php
// In Blade (if using)
@can('create procurement')
    <button>Create Procurement</button>
@endcan

@role('admin')
    <a href="/admin">Admin Panel</a>
@endrole

// In Controllers (for Inertia props)
return Inertia::render('Dashboard', [
    'canCreateProcurement' => $user->can('create procurement'),
    'isAdmin' => $user->hasRole('admin'),
    'permissions' => $user->getAllPermissions()->pluck('name'),
    'roles' => $user->getRoleNames(),
]);
```

### Assigning Roles and Permissions

```php
// Assign a role to a user
$user->assignRole('bac_secretariat');

// Assign multiple roles
$user->assignRole(['bac_secretariat', 'admin']);

// Remove a role
$user->removeRole('bac_secretariat');

// Sync roles (removes all current roles and assigns new ones)
$user->syncRoles(['admin']);

// Give a direct permission to a user
$user->givePermissionTo('create procurement');

// Revoke a permission
$user->revokePermissionTo('create procurement');

// Get all permissions (including via roles)
$permissions = $user->getAllPermissions();

// Get only direct permissions
$directPermissions = $user->getDirectPermissions();

// Get permissions via roles
$rolePermissions = $user->getPermissionsViaRoles();
```

### Working with Roles

```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

// Get a role
$role = Role::findByName('admin');

// Give permission to a role
$role->givePermissionTo('create procurement');

// Sync permissions for a role
$role->syncPermissions(['create procurement', 'view procurement']);

// Get all users with a specific role
$admins = User::role('admin')->get();

// Get users without a role
$usersWithoutRole = User::withoutRole('admin')->get();

// Get users with a specific permission
$users = User::permission('create procurement')->get();
```

## Migration Strategy

For existing production systems:

1. **Backup Database**: Always backup before migration
2. **Run Migrations**: Creates permission tables
3. **Seed Roles & Permissions**: Populate with seeder
4. **Sync Users**: Run sync migration to assign existing users to roles
5. **Test**: Verify all functionality works
6. **Monitor**: Check logs for any authorization issues

## Backward Compatibility

The implementation maintains backward compatibility:

- The `role` column in `users` table is still populated
- Old code checking `$user->role` will still work
- Middleware alias `'role'` now uses Spatie's middleware transparently
- Existing routes require no changes

## Performance Considerations

- **Caching**: Spatie Permission uses Laravel's cache to store permissions
- **Clear Cache**: Run `php artisan permission:cache-reset` after permission changes
- **Eager Loading**: Use `$user->load('roles', 'permissions')` to avoid N+1 queries

## Artisan Commands

Spatie provides useful artisan commands:

```bash
# Clear permission cache
php artisan permission:cache-reset

# Create a permission
php artisan permission:create-permission "permission name"

# Create a role
php artisan permission:create-role "role name"

# Show all permissions and roles
php artisan permission:show
```

## Testing Considerations

When writing tests:

```php
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

test('bac secretariat can create procurement', function () {
    $user = User::factory()->create();
    $user->assignRole('bac_secretariat');
    
    $this->actingAs($user)
        ->post(route('create-procurement'), $data)
        ->assertSuccessful();
});

test('user without permission cannot access admin area', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});
```

## Configuration

The config file is located at `config/permission.php`. Key configurations:

- **teams**: Set to `false` (not using team-based permissions)
- **cache**: Enabled for performance
- **models**: Can be customized to extend Spatie's models

## Future Enhancements

Potential improvements:

1. **Permission-based UI**: Show/hide UI elements based on permissions
2. **Audit Log**: Track permission changes
3. **Dynamic Permissions**: Create permissions from admin panel
4. **Permission Groups**: Organize permissions into logical groups
5. **Super Admin**: Implement a super admin that bypasses all permission checks

## Troubleshooting

Common issues and solutions:

1. **Permission denied after assignment**
   - Solution: Clear permission cache: `php artisan permission:cache-reset`

2. **Middleware not working**
   - Solution: Check middleware is registered in `bootstrap/app.php`

3. **Role not found**
   - Solution: Ensure seeder has run: `php artisan db:seed --class=RoleAndPermissionSeeder`

4. **Tests failing**
   - Solution: Reset permissions in test setup: `app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();`

## Resources

- [Official Documentation](https://spatie.be/docs/laravel-permission/v6/introduction)
- [GitHub Repository](https://github.com/spatie/laravel-permission)
- [Best Practices](https://spatie.be/docs/laravel-permission/v6/best-practices/roles-vs-permissions)

## Conclusion

The Spatie Laravel Permission package provides a robust, flexible, and scalable solution for managing roles and permissions in ProcuChain. It seamlessly integrates with existing authentication and maintains backward compatibility while providing powerful new features for access control.
