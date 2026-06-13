<?php

namespace App\Console\Commands;

use App\Enums\Stream;
use App\Services\BlockchainRpcClient;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Deploy and manage MultiChain Smart Filters for ProcuChain.
 *
 * @see https://www.multichain.com/developers/smart-filters/
 */
class SmartFilterSetup extends Command
{
    protected $signature = 'smartcontract:setup
                            {--check : Check deployment status only}
                            {--activate : Activate deployed filters}
                            {--deactivate : Deactivate all filters}';

    protected $description = 'Deploy and manage MultiChain smart contracts (deploys by default)';

    private BlockchainRpcClient $multichain;

    /**
     * Stream filters - validated when items are published to streams
     *
     * Note: Only one filter per stream should be activated at a time.
     * The primary filters are recommended for production use.
     */
    private const STREAM_FILTERS = [
        // Primary validation filters (recommended for production)
        ['name' => 'sf_metadata_validation', 'File' => 'stream_metadata_validation.js', 'stream' => 'METADATA'],
        ['name' => 'sf_document_validation', 'File' => 'stream_document_validation.js', 'stream' => 'DOCUMENTS'],
        ['name' => 'sf_status_validation', 'File' => 'stream_status_validation.js', 'stream' => 'STATUS'],
        ['name' => 'sf_FILE_METADATA_validation', 'File' => 'stream_FILE_METADATA_validation.js', 'stream' => 'FILE_METADATA'],
        ['name' => 'sf_event_validation', 'File' => 'stream_event_validation.js', 'stream' => 'EVENTS'],
        ['name' => 'sf_corrections_validation', 'File' => 'corrections_filter_v1_standalone.js', 'stream' => 'CORRECTIONS'],
        ['name' => 'sf_proc_corr_validation', 'File' => 'stream_procurement_corrections_validation.js', 'stream' => 'PROCUREMENTS_CORRECTIONS'],
        ['name' => 'sf_archive_validation', 'File' => 'stream_archive_validation.js', 'stream' => 'ARCHIVE'],
        ['name' => 'sf_FILE_DATA_validation', 'File' => 'stream_FILE_DATA_validation.js', 'stream' => 'FILE_DATA'],
        ['name' => 'sf_FILE_CHUNKS_validation', 'File' => 'stream_FILE_CHUNKS_validation.js', 'stream' => 'FILE_CHUNKS'],
    ];

    /**
     * Transaction filters - validated before transactions are accepted
     */
    private const TX_FILTERS = [
        ['name' => 'tf_procurement_validation', 'File' => 'tx_procurement_validation.js'],
    ];

    public function handle(BlockchainRpcClient $multichain): int
    {
        $this->multichain = $multichain;

        try {
            $info = $this->multichain->getinfo();
            $this->info("Connected to: {$info['chainname']} (block: {$info['blocks']})");
            $this->newLine();

            return match (true) {
                $this->option('check') => $this->checkStatus(),
                $this->option('activate') => $this->toggleFilters(true),
                $this->option('deactivate') => $this->toggleFilters(false),
                default => $this->deploy(),
            };
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    private function checkStatus(): int
    {
        $this->info('Stream Filters:');

        try {
            $filters = $this->multichain->liststreamfilters();
            if (empty($filters)) {
                $this->warn('  No stream filters deployed');
            } else {
                foreach ($filters as $f) {
                    $status = ($f['compiled'] ?? false) ? '[OK]' : '';
                    $this->line("  {$status} {$f['name']}");
                }
            }
        } catch (Exception $e) {
            $this->warn("  Error: {$e->getMessage()}");
        }

        $this->newLine();
        $this->info('Transaction Filters:');

        try {
            $txFilters = $this->multichain->listtxfilters();
            if (empty($txFilters)) {
                $this->warn('  No transaction filters deployed');
            } else {
                foreach ($txFilters as $f) {
                    $status = ($f['compiled'] ?? false) ? '[OK]' : '';
                    $this->line("  {$status} {$f['name']}");
                }
            }
        } catch (Exception $e) {
            $this->warn("  Error: {$e->getMessage()}");
        }

        return Command::SUCCESS;
    }

    private function deploy(): int
    {
        $this->info('Deploying smart contracts...');
        $filtersPath = resource_path('blockchain/filters');

        // Deploy stream filters
        $this->newLine();
        $this->comment('Stream Filters:');
        foreach (self::STREAM_FILTERS as $filter) {
            $path = "{$filtersPath}/{$filter['File']}";

            if (! File::exists($path)) {
                $this->warn("  [FAIL] {$filter['name']}: File not found");

                continue;
            }

            try {
                $this->multichain->create('streamfilter', $filter['name'], false, File::get($path));
                $this->info("  [OK] {$filter['name']}: deployed");
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'already exists')) {
                    $this->warn("  [WARN] {$filter['name']}: already exists");
                } else {
                    $this->error("  [FAIL] {$filter['name']}: {$e->getMessage()}");
                }
            }
        }

        // Deploy transaction filters
        $this->newLine();
        $this->comment('Transaction Filters:');
        foreach (self::TX_FILTERS as $filter) {
            $path = "{$filtersPath}/{$filter['File']}";

            if (! File::exists($path)) {
                $this->warn("  [FAIL] {$filter['name']}: File not found");

                continue;
            }

            try {
                $this->multichain->create('txfilter', $filter['name'], false, File::get($path));
                $this->info("  [OK] {$filter['name']}: deployed");
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'already exists')) {
                    $this->warn("  [WARN] {$filter['name']}: already exists");
                } else {
                    $this->error("  [FAIL] {$filter['name']}: {$e->getMessage()}");
                }
            }
        }

        $this->newLine();
        $this->comment('Run with --activate to enable filters');

        return Command::SUCCESS;
    }

    private function toggleFilters(bool $activate): int
    {
        $action = $activate ? 'Activating' : 'Deactivating';
        $this->info("{$action} filters...");

        try {
            $addresses = $this->multichain->getaddresses();
            $admin = $addresses[0] ?? null;

            if (! $admin) {
                $this->error('No admin address available');

                return Command::FAILURE;
            }

            // Build stream map from STREAM_FILTERS constant
            $streamMap = [];
            foreach (self::STREAM_FILTERS as $filter) {
                $streamEnum = constant(Stream::class.'::'.$filter['stream']);
                $streamMap[$filter['name']] = $streamEnum->value;
            }

            // Activate/deactivate stream filters
            $this->newLine();
            $this->comment('Stream Filters:');
            foreach ($this->multichain->liststreamfilters() as $filter) {
                $name = $filter['name'] ?? null;
                if ($name && isset($streamMap[$name])) {
                    try {
                        $this->multichain->approvefrom($admin, $name, [
                            'for' => $streamMap[$name],
                            'approve' => $activate,
                        ]);
                        $symbol = $activate ? '[OK]' : '';
                        $this->info("  {$symbol} {$name}");
                    } catch (Exception $e) {
                        $this->warn("  [WARN] {$name}: {$e->getMessage()}");
                    }
                }
            }

            // Activate/deactivate transaction filters
            $this->newLine();
            $this->comment('Transaction Filters:');
            foreach ($this->multichain->listtxfilters() as $filter) {
                $name = $filter['name'] ?? null;
                if ($name) {
                    try {
                        $this->multichain->approvefrom($admin, $name, $activate);
                        $symbol = $activate ? '[OK]' : '';
                        $this->info("  {$symbol} {$name}");
                    } catch (Exception $e) {
                        $this->warn("  [WARN] {$name}: {$e->getMessage()}");
                    }
                }
            }
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
