<?php

namespace App\Console\Commands;

use App\Services\Blockchain\NodeOperationsService;
use Illuminate\Console\Command;

class TestPurgeCommand extends Command
{
    protected $signature = 'test:purge {nodeId}';
    protected $description = 'Test purge operation on a node';

    public function handle(): int
    {
        $nodeId = $this->argument('nodeId');
        $this->info("Purging node: {$nodeId}");

        $result = app(NodeOperationsService::class)->purgeAllFromNode($nodeId);

        $this->info(json_encode($result, JSON_PRETTY_PRINT));
        return $result['success'] ? 0 : 1;
    }
}
