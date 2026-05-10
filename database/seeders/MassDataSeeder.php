<?php

namespace Database\Seeders;

use App\DataTransferObjects\CorrectionData;
use App\DataTransferObjects\EventData;
use App\DataTransferObjects\ProcurementCorrectionData;
use App\DataTransferObjects\ProcurementData;
use App\DataTransferObjects\ProcurementDocumentData;
use App\DataTransferObjects\StatusData;
use App\Enums\StreamEnums;
use App\Models\User;
use App\Services\Manager;
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
        private readonly Manager $multichain,
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
        $procurementData = new ProcurementData(
            prNumber: $prNumber,
            title: $title,
            status: 'procurement_initiated',
            stage: self::STAGES[0],
            procurementMode: $mode,
            timestamp: $now->toIso8601String(),
            userAddress: $user->blockchain_address ?? '',
        );

        $this->publish(StreamEnums::METADATA->value, $prNumber, array_merge(
            $procurementData->toBlockchainArray(),
            [
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
            ]
        ));

        // 2. publish status transitions (2-6 status changes)
        $stageCount = rand(2, min(6, count(self::STAGES)));
        $previousStatus = null;

        for ($s = 0; $s < $stageCount; $s++) {
            $stage = self::STAGES[$s];
            $status = $s === 0 ? 'procurement_initiated' : 'stage_completed';
            $statusTimestamp = (clone $now)->addHours($s * rand(24, 168));

            $statusData = new StatusData(
                prNumber: $prNumber,
                procurementTitle: $title,
                stage: $stage,
                currentStatus: $status,
                previousStatus: $previousStatus,
                userAddress: $user->blockchain_address ?? '',
                timestamp: $statusTimestamp,
                metadata: ['stage_index' => $s],
            );

            $this->publish(StreamEnums::STATUS->value, $prNumber, $statusData->toBlockchainArray());
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

            $docData = new ProcurementDocumentData(
                prNumber: $prNumber,
                procurementTitle: $title,
                userAddress: $user->blockchain_address ?? '',
                stage: $stage,
                status: 'uploaded',
                documentType: $docType,
                fileName: $fileName,
                fileSize: (string) rand(100000, 5000000),
                mimeType: 'application/pdf',
                hash: bin2hex(random_bytes(32)),
                dataTxid: 'data_tx_'.bin2hex(random_bytes(8)),
                metadataTxid: 'meta_tx_'.bin2hex(random_bytes(8)),
                description: "{$docType} document for {$prNumber}",
                timestamp: $docTimestamp,
            );

            $docArray = $docData->toBlockchainArray();
            $docArray['file_key'] = 'file_'.bin2hex(random_bytes(8));
            $this->publish(StreamEnums::DOCUMENTS->value, $prNumber, $docArray);

            // event for document upload
            $this->publishEvent($prNumber, $title, $stage, $user, $docTimestamp, 99, 'document_upload', "Document uploaded: {$docType}");
        }

        // 5. occasionally add corrections (30% chance)
        if (rand(1, 100) <= 30) {
            $this->publishCorrection($prNumber, $title, $user, $now, $vendor);
        }

        // 6. occasionally add archive (20% chance)
        if (rand(1, 100) <= 20) {
            $this->publish(StreamEnums::ARCHIVE->value, $prNumber, [
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
        $eventData = new EventData(
            prNumber: $prNumber,
            procurementTitle: $title,
            stage: $stage,
            eventType: $eventType,
            category: $this->randomItem(['workflow', 'document', 'milestone']),
            severity: 'info',
            details: $details ?? "Stage transition to {$stage}",
            documentCount: rand(0, 5),
            userAddress: $user->blockchain_address ?? '',
            timestamp: $timestamp,
            metadata: ['event_index' => $index],
        );

        $this->publish(StreamEnums::EVENTS->value, "{$prNumber}_event_{$index}", $eventData->toBlockchainArray());
    }

    private function publishCorrection(string $prNumber, string $title, User $user, Carbon $now, string $vendor): void
    {
        $correctionTimestamp = (clone $now)->addDays(rand(5, 20));

        // Document correction
        $correctionData = new CorrectionData(
            prNumber: $prNumber,
            procurementTitle: $title,
            originalTxid: 'orig_tx_'.bin2hex(random_bytes(8)),
            originalDocumentHash: bin2hex(random_bytes(32)),
            correctionType: 'document_replacement',
            action: 'replace',
            reason: $this->randomItem(['Incorrect document uploaded', 'Updated version available', 'File was corrupted']),
            correctedBy: $user->name,
            userAddress: $user->blockchain_address ?? '',
            timestamp: $correctionTimestamp,
            correctedMetadata: [
                'vendor' => $vendor,
                'new_filename' => 'corrected_'.strtolower(str_replace(' ', '_', $vendor)).'.pdf',
            ],
        );

        $this->publish(StreamEnums::CORRECTIONS->value, $prNumber, $correctionData->toBlockchainArray());

        // Metadata correction
        $metaCorrectionData = new ProcurementCorrectionData(
            prNumber: $prNumber,
            procurementTitle: $title,
            correctionType: 'metadata_update',
            reason: $this->randomItem(['ABC amount updated', 'Delivery date extended', 'Funding source corrected']),
            correctedBy: $user->name,
            userAddress: $user->blockchain_address ?? '',
            timestamp: $correctionTimestamp,
            originalValues: ['abc_amount' => '1000000', 'delivery_date' => '2025-06-30'],
            correctedValues: ['abc_amount' => '1200000', 'delivery_date' => '2025-08-15'],
            metadata: [
                'approved_by' => $user->name,
                'approval_date' => $correctionTimestamp->toDateString(),
            ],
        );

        $this->publish(StreamEnums::PROCUREMENTS_CORRECTIONS->value, $prNumber, $metaCorrectionData->toBlockchainArray());
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
        foreach (StreamEnums::cases() as $stream) {
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
