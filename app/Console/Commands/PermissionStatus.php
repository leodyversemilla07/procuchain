<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionStatus extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'permission:status {--role= : Show details for a specific role} {--user= : Show details for a specific user ID}';

    /**
     * The console command description.
     */
    protected $description = 'Display roles and permissions status';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Spatie Laravel Permission Status');
        $this->newLine();

        if ($this->option('role')) {
            return $this->showRoleDetails($this->option('role'));
        }

        if ($this->option('user')) {
            return $this->showUserPermissions($this->option('user'));
        }

        // Show overview
        $this->showOverview();
        $this->newLine();
        $this->showRoles();
        $this->newLine();
        $this->showPermissions();
        $this->newLine();
        $this->showUsageExamples();

        return Command::SUCCESS;
    }

    protected function showOverview(): void
    {
        $this->components->info('Overview');

        $roleCount = Role::count();
        $permissionCount = Permission::count();
        $usersWithRoles = User::has('roles')->count();
        $totalUsers = User::count();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Roles', $roleCount],
                ['Total Permissions', $permissionCount],
                ['Users with Roles', $usersWithRoles],
                ['Total Users', $totalUsers],
            ]
        );
    }

    protected function showRoles(): void
    {
        $this->components->info('Roles & Their Permissions');

        $roles = Role::with('permissions')->get();

        if ($roles->isEmpty()) {
            $this->warn('No roles found. Run: php artisan db:seed --class=RoleAndPermissionSeeder');

            return;
        }

        foreach ($roles as $role) {
            $userCount = $role->users()->count();
            $permissionCount = $role->permissions->count();

            $this->line("  <fg=cyan>Role:</> {$role->name}");
            $this->line("  <fg=gray>Users:</> {$userCount} | <fg=gray>Permissions:</> {$permissionCount}");

            if ($permissionCount > 0) {
                $permissions = $role->permissions->pluck('name')->toArray();
                $chunked = array_chunk($permissions, 4);

                foreach ($chunked as $chunk) {
                    $this->line('    • '.implode(', ', $chunk));
                }
            }

            $this->newLine();
        }
    }

    protected function showPermissions(): void
    {
        $this->components->info('All Permissions');

        $permissions = Permission::withCount('roles')->get()->groupBy(function ($permission) {
            // Group by category (first word)
            $parts = explode(' ', $permission->name);

            return $parts[0] ?? 'other';
        });

        foreach ($permissions as $category => $perms) {
            $this->line("  <fg=yellow>{$category}</> ({$perms->count()} permissions)");

            foreach ($perms as $permission) {
                $roleCount = $permission->roles_count;
                $this->line("    • {$permission->name} <fg=gray>({$roleCount} roles)</>");
            }

            $this->newLine();
        }
    }

    protected function showRoleDetails(string $roleName): int
    {
        $role = Role::where('name', $roleName)->with('permissions', 'users')->first();

        if (! $role) {
            $this->error("Role '{$roleName}' not found.");

            return Command::FAILURE;
        }

        $this->components->info("Role Details: {$role->name}");
        $this->newLine();

        // Basic info
        $this->table(
            ['Property', 'Value'],
            [
                ['Name', $role->name],
                ['Guard', $role->guard_name],
                ['Users', $role->users->count()],
                ['Permissions', $role->permissions->count()],
                ['Created', $role->created_at->diffForHumans()],
            ]
        );

        // Permissions
        if ($role->permissions->isNotEmpty()) {
            $this->newLine();
            $this->components->info('Permissions');
            $this->table(
                ['Permission Name'],
                $role->permissions->map(fn ($p) => [$p->name])->toArray()
            );
        }

        // Users
        if ($role->users->isNotEmpty()) {
            $this->newLine();
            $this->components->info('Users with this Role');
            $this->table(
                ['ID', 'Name', 'Email'],
                $role->users->map(fn ($u) => [$u->id, $u->name, $u->email])->toArray()
            );
        }

        return Command::SUCCESS;
    }

    protected function showUserPermissions(string $userId): int
    {
        $user = User::with('roles', 'permissions')->find($userId);

        if (! $user) {
            $this->error("User with ID {$userId} not found.");

            return Command::FAILURE;
        }

        $this->components->info("User Permissions: {$user->name}");
        $this->newLine();

        // Basic info
        $this->table(
            ['Property', 'Value'],
            [
                ['ID', $user->id],
                ['Name', $user->name],
                ['Email', $user->email],
                ['Roles', $user->roles->count()],
                ['Direct Permissions', $user->permissions->count()],
                ['Total Permissions', $user->getAllPermissions()->count()],
            ]
        );

        // Roles
        if ($user->roles->isNotEmpty()) {
            $this->newLine();
            $this->components->info('Assigned Roles');
            $this->table(
                ['Role Name'],
                $user->roles->map(fn ($r) => [$r->name])->toArray()
            );
        }

        // Direct permissions
        if ($user->permissions->isNotEmpty()) {
            $this->newLine();
            $this->components->info('Direct Permissions');
            $this->table(
                ['Permission Name'],
                $user->permissions->map(fn ($p) => [$p->name])->toArray()
            );
        }

        // All permissions
        $allPermissions = $user->getAllPermissions();
        if ($allPermissions->isNotEmpty()) {
            $this->newLine();
            $this->components->info('All Permissions (via roles + direct)');

            $grouped = $allPermissions->groupBy(function ($permission) {
                $parts = explode(' ', $permission->name);

                return $parts[0] ?? 'other';
            });

            foreach ($grouped as $category => $permissions) {
                $this->line("  <fg=yellow>{$category}</>: ".implode(', ', $permissions->pluck('name')->toArray()));
            }
        }

        return Command::SUCCESS;
    }

    protected function showUsageExamples(): void
    {
        $this->components->info('Usage Examples');

        $this->line('  View specific role:');
        $this->line('    <fg=green>php artisan permission:status --role=admin</>');
        $this->newLine();

        $this->line('  View user permissions:');
        $this->line('    <fg=green>php artisan permission:status --user=1</>');
        $this->newLine();

        $this->line('  Clear permission cache:');
        $this->line('    <fg=green>php artisan permission:cache-reset</>');
    }
}
