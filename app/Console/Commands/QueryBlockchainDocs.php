<?php

namespace App\Console\Commands;

use App\Repositories\DocumentRepository;
use Illuminate\Console\Command;

class QueryBlockchainDocs extends Command
{
    protected $signature = 'blockchain:query-docs {pr_number}';

    protected $description = 'Query blockchain documents for a procurement';

    public function handle(DocumentRepository $repo): int
    {
        $prNumber = $this->argument('pr_number');
        $this->info("Querying documents for {$prNumber}...");

        $docs = $repo->findByProcurement($prNumber);

        if ($docs->isEmpty()) {
            $this->warn("No documents found for {$prNumber}");

            return 0;
        }

        $this->info("Found {$docs->count()} documents:");

        foreach ($docs as $doc) {
            $this->line(sprintf(
                '  Stage: %-30s | Type: %-40s | File: %s (%d bytes) | Hash: %s',
                $doc->stage,
                $doc->documentType,
                $doc->fileName,
                $doc->fileSize,
                substr($doc->hash, 0, 12).'...'
            ));
        }

        // Specifically check supplemental_bid_bulletin
        $sbbDocs = $docs->filter(fn ($d) => $d->stage === 'supplemental_bid_bulletin');
        $this->newLine();
        $this->info('Supplemental Bid Bulletin documents: '.$sbbDocs->count());
        foreach ($sbbDocs as $doc) {
            $this->line(sprintf(
                '  Type: %-40s | File: %s (%d bytes)',
                $doc->documentType,
                $doc->fileName,
                $doc->fileSize
            ));
        }

        return 0;
    }
}
