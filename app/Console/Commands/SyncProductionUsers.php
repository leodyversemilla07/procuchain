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

    /**
     * The console command description.
     */
    protected $description = 'Sync production user accounts (name, blockchain address, roles) into the local database';

    /**
     * Known production users to sync.
     */
    private array $productionUsers = [
        [
            'email' => 'leodyversemilla07@gmail.com',
            'name' => 'Leodyver Semilla',
            'blockchain_address' => '1R5Be5615e3b7MjDiWxA9HAuzAAE3xTuVS3N54',
            'roles' => ['bac_secretariat'],
        ],
        [
            'email' => 'lunarspectre00@gmail.com',
            'name' => 'Lunar Spectre',
            'blockchain_address' => '1Peoatte9cRGah5XQykUi62PJuBZM5otZjZkrz',
            'roles' => ['bac_chairman'],
        ],
        [
            'email' => 'leifsagesemilla@gmail.com',
            'name' => 'Leif Sage Semilla',
            'blockchain_address' => '13JUtJaimUnhbXcUeE97Uzj4c4vwLyJvqHhMw9',
            'roles' => ['hope'],
        ],
        [
            'email' => 'leobrielzilvrak@gmail.com',
            'name' => 'LeoBriel Zilvrak',
            'blockchain_address' => '1YwGYaqaeMJxMHXhKRaKqgaha1ncVQ7peXbvuF',
            'roles' => ['admin'],
        ],
        [
            'email' => 'brylemaamo@gmail.com',
            'name' => 'Bryle Maamo',
            'blockchain_address' => '1Y5R5CT8A1Be6RwnxQecEeWobYrmKH8p9HcQmb',
            'roles' => ['bac_secretariat'],
        ],
        [
            'email' => 'semillacelsojr@gmail.com',
            'name' => 'Celso Semilla',
            'blockchain_address' => '1TcErv2payomuanpZy5eJKVuNmrNjD2hMikjen',
            'roles' => ['bac_chairman'],
        ],
        [
            'email' => 'nidasemilla15@gmail.com',
            'name' => 'Leonida Monsanto',
            'blockchain_address' => '1RDVkCmEaeG9XBqL8NN6XsdGaoc4CnqURU5K4C',
            'roles' => ['bac_secretariat'],
        ],
    ];

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
                    $user = User::create([
                        'name' => $userData['name'],
                        'email' => $userData['email'],
                        'password' => Hash::make('password'),
                        'email_verified_at' => now(),
                        'blockchain_address' => $userData['blockchain_address'],
                    ]);
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
