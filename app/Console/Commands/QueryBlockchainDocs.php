<?php

namespace App\Console\Commands;

use App\Models\ProcurementDocument;
use Illuminate\Console\Command;

class QueryBlockchainDocs extends Command
{
    protected $signature = 'blockchain:query-docs {pr_number}';

    protected $description = 'Query blockchain documents for a procurement';

    public function handle(): int
    {
        $prNumber = $this->argument('pr_number');
        $this->info("Querying documents for {$prNumber}...");

        $docs = ProcurementDocument::with('procurement')
            ->whereHas('procurement', fn ($q) => $q->where('pr_number', $prNumber))
            ->orderByDesc('uploaded_at')
            ->get();

        if ($docs->isEmpty()) {
            $this->warn("No documents found for {$prNumber}");

            return 0;
        }

        $this->info("Found {$docs->count()} documents:");

        foreach ($docs as $doc) {
            $this->line(sprintf(
                '  Stage: %-30s | Type: %-40s | File: %s (%d bytes) | Hash: %s',
                $doc->stage,
                $doc->document_type,
                $doc->filename,
                $doc->file_size,
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
                $doc->document_type,
                $doc->filename,
                $doc->file_size
            ));
        }

        return 0;
    }
}
