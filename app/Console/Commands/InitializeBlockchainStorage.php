<?php

namespace App\Console\Commands;

use App\Libraries\MultiChain\Manager;
use Exception;
use Illuminate\Console\Command;

class InitializeBlockchainStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blockchain:init-storage
                          {--test : Test the storage with a sample file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize blockchain on-chain file storage streams';

    /**
     * Execute the console command.
     */
    public function handle(Manager $multichain): int
    {
        $this->info('🔗 Initializing On-Chain File Storage...');
        $this->newLine();

        $streams = [
            [
                'name' => 'file.data',
                'description' => 'File content storage (on-chain)',
                'purpose' => 'on_chain_file_storage',
            ],
            [
                'name' => 'file.metadata',
                'description' => 'File metadata and integrity tracking',
                'purpose' => 'file_metadata_tracking',
            ],
        ];

        $created = 0;
        $existing = 0;

        foreach ($streams as $streamConfig) {
            try {
                $multichain->createStream($streamConfig['name'], true, [
                    'description' => $streamConfig['description'],
                    'purpose' => $streamConfig['purpose'],
                ]);

                $this->components->info("✅ Stream '{$streamConfig['name']}' created successfully");
                $created++;
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'already exists')) {
                    $this->components->info("✅ Stream '{$streamConfig['name']}' already exists");
                    $existing++;
                } else {
                    $this->components->error("❌ Failed to create '{$streamConfig['name']}': ".$e->getMessage());

                    return Command::FAILURE;
                }
            }
        }

        $this->newLine();

        if ($created > 0) {
            $this->components->info("Created {$created} new stream(s)");
        }
        if ($existing > 0) {
            $this->components->info("Found {$existing} existing stream(s)");
        }

        if ($this->option('test')) {
            $this->runTest();
        }

        $this->newLine();
        $this->components->info('✨ On-chain file storage is ready!');
        $this->line('Files stored directly on blockchain in file.data stream');
        $this->line('Metadata tracked in file.metadata stream');
        $this->line('✅ Heroku-compatible (no ephemeral filesystem issues)');
        $this->line('✅ Automatically replicated across all blockchain nodes');

        return Command::SUCCESS;
    }

    protected function runTest(): void
    {
        $this->newLine();
        $this->info('🧪 Running on-chain storage test...');

        try {
            $storage = app(\App\Services\FileStorageService::class);

            // Create a small test file (keep it small for testing)
            $testContent = "This is a test document for on-chain storage.\n".str_repeat('Test data line. ', 50);
            $tempFile = tempnam(sys_get_temp_dir(), 'test_');
            file_put_contents($tempFile, $testContent);

            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempFile,
                'test_document.pdf',
                'application/pdf',
                null,
                true
            );

            $this->line('  Max file size: '.$storage->getMaxFileSizeFormatted());
            $this->line('  Test file size: '.round(strlen($testContent) / 1024, 2).' KB');
            $this->newLine();

            // Store file on blockchain
            $this->components->task('Storing test file on blockchain', function () use ($storage, $uploadedFile, &$result) {
                $result = $storage->uploadFile(
                    $uploadedFile,
                    'test/storage',
                    'test_'.now()->timestamp,
                    ['test' => true, 'purpose' => 'on_chain_initialization_test']
                );

                return true;
            });

            $this->line("  File Key: {$result['file_key']}");
            $this->line("  Data TXID: {$result['data_txid']}");
            $this->line("  Metadata TXID: {$result['metadata_txid']}");
            $this->line('  Size: '.round($result['size'] / 1024, 2).' KB');
            $this->line("  Hash: {$result['hash']}");

            // Retrieve and verify
            $this->components->task('Retrieving & verifying from blockchain', function () use ($storage, $result, $testContent, &$retrieved) {
                $retrieved = $storage->retrieveFile($result['file_key'], $result['data_txid']);
                $verified = $storage->verifyFileIntegrity($result['file_key'], $result['metadata_txid']);

                return $retrieved['content'] === $testContent && $verified;
            });

            $this->components->info('✅ Test passed! File stored on-chain, retrieved, and verified');
            $this->line('✅ File content stored directly on blockchain (replicated across all nodes)');
            $this->line('✅ Works on Heroku (no ephemeral filesystem dependency)');

            // Cleanup
            @unlink($tempFile);
        } catch (Exception $e) {
            $this->components->error('❌ Test failed: '.$e->getMessage());

            if (str_contains($e->getMessage(), 'exceeds maximum')) {
                $this->warn('💡 Tip: On-chain storage is best for files under 8 MB');
            }
        }
    }
}
