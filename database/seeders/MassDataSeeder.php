<?php

namespace Database\Seeders;

use App\Enums\Stream;
use App\Models\User;
use App\Services\BlockchainRpcClient;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class MassDataSeeder extends Seeder
{
    private const PROCUREMENT_COUNT = 25;

    private const DOCUMENTS_PER_PROCUREMENT = 4;

    private const STAGES = [
        'procurement_initiation',
        'pre_procurement_conference',
        'bidding_documents',
        'pre_bid_conference',
        'bid_opening',
        'bid_evaluation',
        'post_qualification',
        'bac_resolution',
        'notice_of_award',
        'performance_bond',
        'notice_to_proceed',
        'monitoring',
        'completion',
    ];

    private const PROCUREMENT_MODES = [
        'public_bidding',
        'alternative_mode_shopping',
        'alternative_mode_direct_contracting',
        'alternative_mode_negotiated',
    ];

    private const OFFICES = [
        'City Engineering Office',
        'City Health Office',
        'City Mayor\'s Office',
        'General Services Office',
        'City Budget Office',
        'City Accounting Office',
        'City Treasurer\'s Office',
        'City Planning Office',
        'City Agriculture Office',
        'City Social Welfare Office',
    ];

    private const VENDORS = [
        'BuildWell Construction Inc.',
        'TechPro Solutions Corp.',
        'GreenFields Agricultural Supply',
        'MedEquip Trading',
        'InfraCore Developers',
        'DataSys Technologies',
        'AquaPure Water Systems',
        'EduMaterials Publishing',
        'PowerGrid Electrical Supply',
        'SafeRoads Construction',
        'EcoWaste Management Inc.',
        'SolarPh Eneregy Corp.',
        'DigitalTrans Solutions',
        'HealthFirst Medical Supply',
        'UrbanDev Builders',
    ];

    public function __construct(
        private readonly BlockchainRpcClient $multichain,
    ) {}

    public function run(): void
    {
        $users = User::all();
        if ($users->isEmpty()) {
            $this->command->error('No users found. Run seed-users.php first.');

            return;
        }

        $this->command->info('Generating '.self::PROCUREMENT_COUNT.' procurements with blockchain data...');
        $this->command->newLine();

        $progressBar = $this->command->getOutput()->createProgressBar(self::PROCUREMENT_COUNT);

        for ($i = 1; $i <= self::PROCUREMENT_COUNT; $i++) {
            $this->generateProcurement($i, $users);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine(2);
        $this->command->info('Done! Generated '.self::PROCUREMENT_COUNT.' procurements with blockchain data.');
        $this->command->info("\nStream counts:");
        $this->showStreamCounts();
    }

    private function generateProcurement(int $index, $users): void
    {
        $prNumber = sprintf('PR-%d-%03d', now()->year, $index);
        $user = $users->random();
        $mode = self::PROCUREMENT_MODES[array_rand(self::PROCUREMENT_MODES)];
        $office = self::OFFICES[array_rand(self::OFFICES)];
        $vendor = self::VENDORS[array_rand(self::VENDORS)];
        $title = "Procurement of {$this->randomItem(['Office Equipment', 'Medical Supplies', 'Construction Materials', 'IT Infrastructure', 'Furniture and Fixtures', 'Vehicles', 'Agricultural Inputs', 'Educational Materials', 'Water System Components', 'Solar Panels'])} for {$office}";
        $abcAmount = rand(500000, 50000000);
        $now = Carbon::now()->subDays(self::PROCUREMENT_COUNT - $index);

        // 1. publish procurement metadata (creation)
        $procurementData = [
            'pr_number' => $prNumber,
            'title' => $title,
            'status' => 'procurement_initiated',
            'stage' => self::STAGES[0],
            'procurement_mode' => $mode,
            'timestamp' => $now->toIso8601String(),
            'user_address' => $user->blockchain_address ?? '',
            'description' => "This procurement covers the acquisition of various items needed by {$office} for the fiscal year ".now()->year.'.',
            'abc_amount' => (string) $abcAmount,
            'funding_source' => $this->randomItem(['GAA', 'LGU Fund', 'Trust Fund', 'Foreign Assistance']),
            'category' => $this->randomItem(['goods', 'infrastructure', 'consulting']),
            'office' => $office,
            'end_user' => $office,
            'delivery_location' => $office,
            'delivery_date' => $now->addDays(90)->toDateString(),
            'delivery_term_days' => (string) rand(30, 180),
            'prepared_by' => $user->name,
            'approved_by' => $users->where('bac_chairman', fn ($q) => $q)->first()?->name ?? 'Admin',
            'approval_date' => $now->toDateString(),
        ];

        $this->publish(Stream::METADATA->value, $prNumber, $procurementData);

        // 2. publish status transitions (2-6 status changes)
        $stageCount = rand(2, min(6, count(self::STAGES)));
        $previousStatus = null;

        for ($s = 0; $s < $stageCount; $s++) {
            $stage = self::STAGES[$s];
            $status = $s === 0 ? 'procurement_initiated' : 'stage_completed';
            $statusTimestamp = (clone $now)->addHours($s * rand(24, 168));

            $statusData = [
                'pr_number' => $prNumber,
                'procurement_title' => $title,
                'stage' => $stage,
                'current_status' => $status,
                'previous_status' => $previousStatus,
                'user_address' => $user->blockchain_address ?? '',
                'timestamp' => $statusTimestamp->toIso8601String(),
                'metadata' => json_encode(['stage_index' => $s]),
            ];

            $this->publish(Stream::STATUS->value, $prNumber, $statusData);
            $previousStatus = $status;

            // 3. publish event for each status
            $this->publishEvent($prNumber, $title, $stage, $user, $statusTimestamp, $s);
        }

        // 4. publish documents (2-6 per procurement)
        $docCount = rand(2, self::DOCUMENTS_PER_PROCUREMENT);
        for ($d = 0; $d < $docCount; $d++) {
            $stage = self::STAGES[min($d, count(self::STAGES) - 1)];
            $docTimestamp = (clone $now)->addHours($d * rand(12, 72));
            $docTypes = ['bid_documents', 'technical_proposal', 'financial_proposal', 'eligibility_documents', 'performance_security', 'contract'];
            $docType = $docTypes[array_rand($docTypes)];
            $fileName = str_replace('_', '_', $docType).'_'.strtolower(str_replace(' ', '_', $vendor)).'.pdf';

            $docData = [
                'pr_number' => $prNumber,
                'procurement_title' => $title,
                'user_address' => $user->blockchain_address ?? '',
                'stage' => $stage,
                'status' => 'uploaded',
                'document_type' => $docType,
                'filename' => $fileName,
                'file_size' => (string) rand(100000, 5000000),
                'mime_type' => 'application/pdf',
                'hash' => bin2hex(random_bytes(32)),
                'data_txid' => 'data_tx_'.bin2hex(random_bytes(8)),
                'metadata_txid' => 'meta_tx_'.bin2hex(random_bytes(8)),
                'description' => "{$docType} document for {$prNumber}",
                'timestamp' => $docTimestamp->toIso8601String(),
                'file_key' => 'file_'.bin2hex(random_bytes(8)),
            ];

            $this->publish(Stream::DOCUMENTS->value, $prNumber, $docData);

            // event for document upload
            $this->publishEvent($prNumber, $title, $stage, $user, $docTimestamp, 99, 'document_upload', "Document uploaded: {$docType}");
        }

        // 5. occasionally add corrections (30% chance)
        if (rand(1, 100) <= 30) {
            $this->publishCorrection($prNumber, $title, $user, $now, $vendor);
        }

        // 6. occasionally add archive (20% chance)
        if (rand(1, 100) <= 20) {
            $this->publish(Stream::ARCHIVE->value, $prNumber, [
                'pr_number' => $prNumber,
                'archived' => true,
                'archived_by' => $user->name,
                'archived_at' => (clone $now)->addDays(rand(30, 90))->toIso8601String(),
                'reason' => $this->randomItem(['Completed', 'Cancelled due to budget realignment', 'Awarded to winning bidder']),
            ]);
        }

        Log::info("Generated procurement: {$prNumber}");
    }

    private function publishEvent(
        string $prNumber,
        string $title,
        string $stage,
        User $user,
        Carbon $timestamp,
        int $index,
        string $eventType = 'stage_transition',
        ?string $details = null,
    ): void {
        $eventData = [
            'pr_number' => $prNumber,
            'procurement_title' => $title,
            'stage' => $stage,
            'event_type' => $eventType,
            'category' => $this->randomItem(['workflow', 'document', 'milestone']),
            'severity' => 'info',
            'details' => $details ?? "Stage transition to {$stage}",
            'document_count' => rand(0, 5),
            'user_address' => $user->blockchain_address ?? '',
            'timestamp' => $timestamp->toIso8601String(),
            'metadata' => json_encode(['event_index' => $index]),
        ];

        $this->publish(Stream::EVENTS->value, "{$prNumber}_event_{$index}", $eventData);
    }

    private function publishCorrection(string $prNumber, string $title, User $user, Carbon $now, string $vendor): void
    {
        $correctionTimestamp = (clone $now)->addDays(rand(5, 20));

        $correctionData = [
            'pr_number' => $prNumber,
            'procurement_title' => $title,
            'original_txid' => 'orig_tx_'.bin2hex(random_bytes(8)),
            'original_document_hash' => bin2hex(random_bytes(32)),
            'correction_type' => 'document_replacement',
            'action' => 'replace',
            'reason' => $this->randomItem(['Incorrect document uploaded', 'Updated version available', 'File was corrupted']),
            'corrected_by' => $user->name,
            'user_address' => $user->blockchain_address ?? '',
            'timestamp' => $correctionTimestamp->toIso8601String(),
            'corrected_metadata' => json_encode([
                'vendor' => $vendor,
                'new_filename' => 'corrected_'.strtolower(str_replace(' ', '_', $vendor)).'.pdf',
            ]),
        ];

        $this->publish(Stream::CORRECTIONS->value, $prNumber, $correctionData);

        $metaCorrectionData = [
            'pr_number' => $prNumber,
            'procurement_title' => $title,
            'correction_type' => 'metadata_update',
            'reason' => $this->randomItem(['ABC amount updated', 'Delivery date extended', 'Funding source corrected']),
            'corrected_by' => $user->name,
            'user_address' => $user->blockchain_address ?? '',
            'timestamp' => $correctionTimestamp->toIso8601String(),
            'original_values' => json_encode(['abc_amount' => '1000000', 'delivery_date' => '2025-06-30']),
            'corrected_values' => json_encode(['abc_amount' => '1200000', 'delivery_date' => '2025-08-15']),
            'metadata' => json_encode([
                'approved_by' => $user->name,
                'approval_date' => $correctionTimestamp->toDateString(),
            ]),
        ];

        $this->publish(Stream::PROCUREMENTS_CORRECTIONS->value, $prNumber, $metaCorrectionData);
    }

    private function publish(string $stream, string $key, array $data): void
    {
        try {
            $this->multichain->publish($stream, $key, ['json' => $data]);
        } catch (\Exception $e) {
            Log::warning("Failed to publish to {$stream}", [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function showStreamCounts(): void
    {
        foreach (Stream::cases() as $stream) {
            try {
                $count = $this->multichain->getstreamitems($stream, false, 1, 0, false);
                $this->command->info("  {$stream->value}: {$stream->getDisplayName()}");
            } catch (\Exception $e) {
                $this->command->warn("  {$stream->value}: Error - {$e->getMessage()}");
            }
        }
    }

    private function randomItem(array $items): string
    {
        return $items[array_rand($items)];
    }
}
