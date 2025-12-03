<?php

namespace App\Console\Commands;

use App\Enums\StreamEnums;
use App\Services\Manager;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Deploy and manage MultiChain Smart Filters for ProcuChain.
 *
 * @see https://www.multichain.com/developers/smart-filters/
 */
class SmartContractSetup extends Command
{
    protected $signature = 'smartcontract:setup
                            {--check : Check deployment status}
                            {--deploy : Deploy all smart contracts}
                            {--activate : Activate deployed filters}
                            {--deactivate : Deactivate all filters}';

    protected $description = 'Deploy and manage MultiChain smart contracts';

    private Manager $multichain;

    private const FILTERS = [
        ['name' => 'sf_document_validation', 'file' => 'stream_document_validation.js', 'stream' => 'DOCUMENTS'],
        ['name' => 'sf_status_validation', 'file' => 'stream_status_validation.js', 'stream' => 'STATUS'],
        ['name' => 'sf_file_metadata_validation', 'file' => 'stream_file_metadata_validation.js', 'stream' => 'FILE_METADATA'],
        ['name' => 'sf_event_validation', 'file' => 'stream_event_validation.js', 'stream' => 'EVENTS'],
    ];

    public function handle(Manager $multichain): int
    {
        $this->multichain = $multichain;

        try {
            $info = $this->multichain->getinfo();
            $this->info("Connected to: {$info['chainname']} (block: {$info['blocks']})");
            $this->newLine();

            return match (true) {
                $this->option('check') => $this->checkStatus(),
                $this->option('deploy') => $this->deploy(),
                $this->option('activate') => $this->toggleFilters(true),
                $this->option('deactivate') => $this->toggleFilters(false),
                default => $this->showHelp(),
            };
        } catch (Exception $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    private function showHelp(): int
    {
        $this->line('Usage:');
        $this->line('  --check      Check deployment status');
        $this->line('  --deploy     Deploy all smart contracts');
        $this->line('  --activate   Activate deployed filters');
        $this->line('  --deactivate Deactivate all filters');

        return Command::SUCCESS;
    }

    private function checkStatus(): int
    {
        $this->info('Stream Filters:');

        try {
            $filters = $this->multichain->liststreamfilters();
            if (empty($filters)) {
                $this->warn('  No filters deployed');
            } else {
                foreach ($filters as $f) {
                    $status = ($f['compiled'] ?? false) ? '✓' : '○';
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

        foreach (self::FILTERS as $filter) {
            $path = "{$filtersPath}/{$filter['file']}";

            if (! File::exists($path)) {
                $this->warn("  ✗ {$filter['name']}: file not found");

                continue;
            }

            try {
                $this->multichain->create('streamfilter', $filter['name'], false, File::get($path));
                $this->info("  ✓ {$filter['name']}: deployed");
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'already exists')) {
                    $this->warn("  ⚠ {$filter['name']}: already exists");
                } else {
                    $this->error("  ✗ {$filter['name']}: {$e->getMessage()}");
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

            $streamMap = [
                'sf_document_validation' => StreamEnums::DOCUMENTS->value,
                'sf_status_validation' => StreamEnums::STATUS->value,
                'sf_file_metadata_validation' => StreamEnums::FILE_METADATA->value,
                'sf_event_validation' => StreamEnums::EVENTS->value,
            ];

            foreach ($this->multichain->liststreamfilters() as $filter) {
                $name = $filter['name'] ?? null;
                if ($name && isset($streamMap[$name])) {
                    try {
                        $this->multichain->approvefrom($admin, $name, [
                            'for' => $streamMap[$name],
                            'approve' => $activate,
                        ]);
                        $symbol = $activate ? '✓' : '○';
                        $this->info("  {$symbol} {$name}");
                    } catch (Exception $e) {
                        $this->warn("  ⚠ {$name}: {$e->getMessage()}");
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
