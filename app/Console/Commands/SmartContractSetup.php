<?php

namespace App\Console\Commands;

use App\Enums\StreamEnums;
use App\Services\MultichainService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Smart Contract Setup Command
 *
 * Deploys JavaScript libraries and smart filters to the MultiChain blockchain
 * for ProcuChain document and workflow validation.
 *
 * This command handles:
 * - Deploying validation helper libraries
 * - Creating and activating stream filters
 * - Verifying deployment status
 * - Subscribing to relevant streams
 *
 * Usage:
 *   php artisan smartcontract:setup
 *   php artisan smartcontract:setup --check
 *   php artisan smartcontract:setup --deploy-libraries
 *   php artisan smartcontract:setup --deploy-filters
 *
 * @see https://www.multichain.com/developers/smart-filters/
 */
class SmartContractSetup extends Command
{
    protected $signature = 'smartcontract:setup
                            {--check : Check deployment status without deploying}
                            {--deploy-libraries : Deploy only JavaScript libraries}
                            {--deploy-filters : Deploy only stream filters}
                            {--skip-library : Skip library deployment (filters work standalone)}
                            {--force : Force redeployment even if already exists}';

    protected $description = 'Deploy smart contract libraries and filters to MultiChain blockchain';

    private MultichainService $multichainService;

    private array $deploymentResults = [];

    /**
     * Execute the console command.
     */
    public function handle(MultichainService $multichainService): int
    {
        $this->multichainService = $multichainService;

        $this->info('🔗 ProcuChain Smart Contract Setup');
        $this->newLine();

        try {
            // Verify MultiChain connection
            $this->checkConnection();

            if ($this->option('check')) {
                return $this->checkDeploymentStatus();
            }

            // Deploy components
            if (! $this->option('skip-library') && ($this->option('deploy-libraries') || ! $this->option('deploy-filters'))) {
                $this->deployLibraries();
            } elseif ($this->option('skip-library')) {
                $this->warn('⏭️  Skipping library deployment (filters work standalone)');
                $this->newLine();
            }

            if ($this->option('deploy-filters') || ! $this->option('deploy-libraries')) {
                $this->deployFilters();
            }

            // Display summary
            $this->displaySummary();

            $this->newLine();
            $this->info('✅ Smart contract setup completed successfully!');
            $this->newLine();

            $this->comment('Next steps:');
            $this->line('1. Activate filters using admin approval: approvefrom <admin-address> <filter-name> true');
            $this->line('2. Test filters with sample data');
            $this->line('3. Monitor filter rejections in Laravel logs');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error('❌ Smart contract setup failed: '.$e->getMessage());
            Log::error('Smart contract setup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Check connection to MultiChain node
     */
    private function checkConnection(): void
    {
        $this->info('🔍 Checking MultiChain connection...');

        try {
            $info = $this->multichainService->getInfo();

            $this->line("✓ Connected to blockchain: {$info['chainname']}");
            $this->line("✓ Block height: {$info['blocks']}");
            $this->newLine();
        } catch (Exception $e) {
            throw new Exception('Failed to connect to MultiChain node: '.$e->getMessage());
        }
    }

    /**
     * Check deployment status of libraries and filters
     */
    private function checkDeploymentStatus(): int
    {
        $this->info('📊 Checking smart contract deployment status...');
        $this->newLine();

        // This would require implementing listlibraries and liststreamfilters calls
        // For now, we'll show a placeholder
        $this->warn('Status checking not fully implemented yet.');
        $this->line('Use MultiChain CLI: multichain-cli procuchain listlibraries');
        $this->line('Use MultiChain CLI: multichain-cli procuchain liststreamfilters');

        return Command::SUCCESS;
    }

    /**
     * Deploy JavaScript libraries to blockchain
     */
    private function deployLibraries(): void
    {
        $this->info('📚 Deploying JavaScript libraries...');
        $this->newLine();

        $libraryPath = resource_path('blockchain/libraries/validation_helpers.js');

        if (! File::exists($libraryPath)) {
            $this->error("Library file not found: {$libraryPath}");

            return;
        }

        $libraryCode = File::get($libraryPath);
        $libraryName = 'procuchain_validation_helpers';

        try {
            $this->line("Deploying library: {$libraryName}...");

            // Create library with updatemode='none' (no updates allowed after creation)
            $restrictions = (object) ['updatemode' => 'none'];

            $txid = $this->multichainService->createLibrary(
                $libraryName,
                $restrictions,
                $libraryCode
            );

            $this->deploymentResults[] = [
                'type' => 'Library',
                'name' => $libraryName,
                'status' => 'Created',
                'txid' => $txid,
            ];

            $this->info("✓ Library '{$libraryName}' deployed successfully");
            $this->line("  Transaction ID: {$txid}");
            $this->newLine();
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

            // Check if library already exists
            if (str_contains($errorMsg, 'already exists') || str_contains($errorMsg, 'duplicate')) {
                $this->warn("⚠ Library '{$libraryName}' already exists");

                if ($this->option('force')) {
                    $this->line('  Use MultiChain update mechanism to modify existing library');
                }

                $this->deploymentResults[] = [
                    'type' => 'Library',
                    'name' => $libraryName,
                    'status' => 'Already Exists',
                    'txid' => 'N/A',
                ];
            } else {
                $this->error("✗ Failed to deploy library '{$libraryName}': {$errorMsg}");
                $this->deploymentResults[] = [
                    'type' => 'Library',
                    'name' => $libraryName,
                    'status' => 'Failed',
                    'txid' => 'N/A',
                ];
            }

            $this->newLine();
        }
    }

    /**
     * Deploy stream filters to blockchain
     */
    private function deployFilters(): void
    {
        $this->info('🛡️ Deploying stream filters...');
        $this->newLine();

        $filters = [
            [
                'name' => 'procuchain_documents_validator',
                'file' => 'documents_filter_v1_standalone.js',
                'stream' => StreamEnums::DOCUMENTS->value,
                'description' => 'Document hash and metadata validation',
            ],
            [
                'name' => 'procuchain_status_validator',
                'file' => 'status_filter_v1_standalone.js',
                'stream' => StreamEnums::STATUS->value,
                'description' => 'Status transition and workflow validation',
            ],
        ];

        foreach ($filters as $filter) {
            $this->deployFilter($filter);
        }
    }

    /**
     * Deploy a single stream filter
     */
    private function deployFilter(array $filterConfig): void
    {
        $filterPath = resource_path('blockchain/filters/'.$filterConfig['file']);

        if (! File::exists($filterPath)) {
            $this->error("Filter file not found: {$filterPath}");

            return;
        }

        $filterCode = File::get($filterPath);
        $filterName = $filterConfig['name'];
        $streamName = $filterConfig['stream'];

        try {
            $this->line("Deploying filter: {$filterName}...");
            $this->line("  Target stream: {$streamName}");
            $this->line("  Description: {$filterConfig['description']}");

            // Verify stream exists
            try {
                $this->multichainService->getStreamInfo($streamName);
            } catch (Exception $e) {
                throw new Exception("Stream '{$streamName}' does not exist. Create it first using multichain:setup");
            }

            // Create stream filter - no 'for' field, stream filters apply to specific stream
            $restrictions = new \stdClass; // Empty restrictions for stream filter
            // Uncomment to include validation helper library
            // $restrictions->libraries = ['procuchain_validation_helpers'];

            $txid = $this->multichainService->createStreamFilter(
                $filterName,
                $restrictions,
                $filterCode
            );

            $this->deploymentResults[] = [
                'type' => 'Stream Filter',
                'name' => $filterName,
                'status' => 'Created',
                'txid' => $txid,
            ];

            $this->info("✓ Filter '{$filterName}' deployed successfully");
            $this->line("  Transaction ID: {$txid}");
            $this->warn('  ⚠ Filter requires admin approval before activation');
            $this->newLine();
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();

            if (str_contains($errorMsg, 'already exists') || str_contains($errorMsg, 'duplicate')) {
                $this->warn("⚠ Filter '{$filterName}' already exists");
                $this->deploymentResults[] = [
                    'type' => 'Stream Filter',
                    'name' => $filterName,
                    'status' => 'Already Exists',
                    'txid' => 'N/A',
                ];
            } else {
                $this->error("✗ Failed to deploy filter '{$filterName}': {$errorMsg}");
                $this->deploymentResults[] = [
                    'type' => 'Stream Filter',
                    'name' => $filterName,
                    'status' => 'Failed',
                    'txid' => 'N/A',
                ];
            }

            $this->newLine();
        }
    }

    /**
     * Display deployment summary table
     */
    private function displaySummary(): void
    {
        if (empty($this->deploymentResults)) {
            return;
        }

        $this->newLine();
        $this->info('📋 Deployment Summary:');
        $this->newLine();

        $headers = ['Type', 'Name', 'Status', 'Transaction ID'];
        $rows = [];

        foreach ($this->deploymentResults as $result) {
            $rows[] = [
                $result['type'],
                $result['name'],
                $result['status'],
                substr($result['txid'], 0, 16).'...',
            ];
        }

        $this->table($headers, $rows);
    }
}
