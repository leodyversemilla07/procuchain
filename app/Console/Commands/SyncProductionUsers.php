<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SyncProductionUsers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'users:sync-production
                            {--dry-run : Preview changes without writing to the database}';

    public function __construct()
    {
        parent::__construct();

        $this->productionUsers = config('production-users');
    }

    /**
     * The console command description.
     */
    protected $description = 'Sync production user accounts (name, blockchain address, roles) into the local database';

    /**
     * Known production users to sync (loaded from config/production-users.php).
     */
    private array $productionUsers;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->info('=== Syncing Production User Data to Local Database ===');

        if ($isDryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

        $this->newLine();

        $created = 0;
        $updated = 0;
        $rows = [];

        foreach ($this->productionUsers as $userData) {
            $user = User::where('email', $userData['email'])->first();
            $action = $user ? 'Update' : 'Create';

            if (! $isDryRun) {
                if ($user) {
                    $user->update([
                        'name' => $userData['name'],
                        'blockchain_address' => $userData['blockchain_address'],
                    ]);
                    $user->syncRoles($userData['roles']);
                    $updated++;
                } else {
                    $user = User::make([
                        'name' => $userData['name'],
                        'email' => $userData['email'],
                        'email_verified_at' => now(),
                        'blockchain_address' => $userData['blockchain_address'],
                    ]);
                    $user->password = Hash::make('password');
                    $user->save();
                    $user->assignRole($userData['roles']);
                    $created++;
                }
            }

            $rows[] = [
                $action,
                $userData['email'],
                $userData['name'],
                implode(', ', $userData['roles']),
                $userData['blockchain_address'],
            ];
        }

        $this->table(
            ['Action', 'Email', 'Name', 'Roles', 'Blockchain Address'],
            $rows
        );

        $this->newLine();

        if ($isDryRun) {
            $this->warn('Dry run complete. Run without --dry-run to apply changes.');
        } else {
            $this->info('=== Sync Complete ===');
            $this->line("Created : {$created}");
            $this->line("Updated : {$updated}");
            $this->line('Total   : '.($created + $updated));
        }

        return Command::SUCCESS;
    }
}
