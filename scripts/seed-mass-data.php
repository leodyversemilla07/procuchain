<?php

use App\Enums\StreamEnums;
use App\Models\User;
use App\Services\Manager;
use Carbon\Carbon;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$output = new OutputStyle(new StringInput(''), new ConsoleOutput());

/** @var Manager $multichain */
$multichain = app(Manager::class);

$users = User::all();
if ($users->isEmpty()) {
    $output->error('No users found. Run seed-users.php first.');
    exit(1);
}

$procurementCount = 50;

$stages = [
    'procurement_initiation', 'pre_procurement_conference', 'bidding_documents',
    'pre_bid_conference', 'bid_opening', 'bid_evaluation', 'post_qualification',
    'bac_resolution', 'notice_of_award', 'performance_bond', 'notice_to_proceed',
    'post_procurement_monitoring', 'completion',
];

$modes = ['public_bidding', 'alternative_mode_shopping', 'alternative_mode_direct_contracting', 'alternative_mode_negotiated'];

$offices = [
    'City Engineering Office', 'City Health Office', 'City Mayor\'s Office',
    'General Services Office', 'City Budget Office', 'City Accounting Office',
    'City Treasurer\'s Office', 'City Planning Office', 'City Agriculture Office',
    'City Social Welfare Office', 'City Education Office', 'City Transport Office',
];

$titles = [
    'Procurement of Office Equipment', 'Supply and Delivery of Medical Supplies',
    'Procurement of Construction Materials', 'IT Infrastructure and Networking Equipment',
    'Furniture and Fixtures', 'Procurement of Service Vehicles',
    'Agricultural Inputs and Supplies', 'Educational Materials and Equipment',
    'Water System Components', 'Solar Panel Installation and Equipment',
    'Procurement of Heavy Equipment', 'ICT Equipment and Accessories',
    'Laboratory Equipment and Supplies', 'Procurement of Printing Services',
    'Office Renovation and Improvement', 'Procurement of Security Equipment',
    'Fire Safety Equipment and Supplies', 'Procurement of Janitorial Services',
    'Supply of Fuel and Lubricants', 'Procurement of Uniforms and PPE',
];

$docTypes = ['bid_documents', 'technical_proposal', 'financial_proposal', 'eligibility_documents', 'performance_security', 'contract', 'omnibus_sworn_statement', 'philgeps_certificate'];

$output->writeln("<info>Seeding {$procurementCount} procurements with blockchain data...</info>");
$output->newLine();

$progress = $output->createProgressBar($procurementCount);
$progress->start();

for ($i = 1; $i <= $procurementCount; $i++) {
    $prNumber = sprintf('PR-%d-%03d', date('Y'), $i);
    $user = $users->random();
    $mode = $modes[array_rand($modes)];
    $office = $offices[array_rand($offices)];
    $title = $titles[array_rand($titles)] . ' - ' . $office;
    $abcAmount = rand(500000, 50000000);
    $now = Carbon::now()->subDays($procurementCount - $i);

    // 1. PUBLISH METADATA
    try {
        $multichain->publish(StreamEnums::METADATA->value, $prNumber, ['json' => [
            'pr_number' => $prNumber,
            'title' => $title,
            'description' => "Procurement for {$office} FY " . date('Y'),
            'abc_amount' => (string) $abcAmount,
            'procurement_mode' => $mode,
            'office' => $office,
            'end_user' => $office,
            'category' => ['goods', 'infrastructure', 'consulting'][array_rand([0,1,2])],
            'funding_source' => ['GAA', 'LGU Fund', 'Trust Fund', 'Foreign Assistance'][array_rand([0,1,2,3])],
            'delivery_location' => $office,
            'delivery_date' => $now->copy()->addDays(90)->toDateString(),
            'prepared_by' => $user->name,
            'approved_by' => 'BAC Chairman',
            'approval_date' => $now->toDateString(),
            'status' => 'procurement_initiated',
            'user_address' => $user->blockchain_address ?? '',
            'created_at' => $now->toIso8601String(),
        ]]);
    } catch (\Exception $e) {
        // silently skip
    }

    // 2. STATUS TRANSITIONS (2-8)
    $stageCount = rand(2, min(8, count($stages)));
    $prevStatus = null;

    for ($s = 0; $s < $stageCount; $s++) {
        $stage = $stages[$s];
        $status = $s === 0 ? 'procurement_initiated' : 'stage_completed';
        $ts = (clone $now)->addHours($s * rand(24, 168));

        try {
            $multichain->publish(StreamEnums::STATUS->value, $prNumber, ['json' => [
                'pr_number' => $prNumber,
                'procurement_title' => $title,
                'stage' => $stage,
                'current_status' => $status,
                'previous_status' => $prevStatus,
                'user_address' => $user->blockchain_address ?? '',
                'timestamp' => $ts->toIso8601String(),
                'stage_index' => $s,
            ]]);
        } catch (\Exception $e) {}

        $prevStatus = $status;

        // Event for each status
        try {
            $multichain->publish(StreamEnums::EVENTS->value, "{$prNumber}_event_{$s}", ['json' => [
                'pr_number' => $prNumber,
                'procurement_title' => $title,
                'stage' => $stage,
                'event_type' => 'stage_transition',
                'category' => ['workflow', 'milestone'][array_rand([0,1])],
                'severity' => 'info',
                'details' => "Stage transition: {$stage}",
                'user_address' => $user->blockchain_address ?? '',
                'timestamp' => $ts->toIso8601String(),
            ]]);
        } catch (\Exception $e) {}
    }

    // 3. DOCUMENTS (3-8)
    $docCount = rand(3, 8);
    for ($d = 0; $d < $docCount; $d++) {
        $stageIdx = min($d, count($stages) - 1);
        $docTs = (clone $now)->addHours($d * rand(12, 72));
        $docType = $docTypes[array_rand($docTypes)];
        $fileName = $docType . '-' . bin2hex(random_bytes(4)) . '.pdf';

        try {
            $multichain->publish(StreamEnums::DOCUMENTS->value, $prNumber, ['json' => [
                'pr_number' => $prNumber,
                'stage' => $stages[$stageIdx],
                'document_type' => $docType,
                'file_name' => $fileName,
                'file_size' => (string) rand(100000, 5000000),
                'mime_type' => 'application/pdf',
                'hash' => bin2hex(random_bytes(32)),
                'status' => 'uploaded',
                'uploaded_by' => $user->name,
                'user_address' => $user->blockchain_address ?? '',
                'data_txid' => bin2hex(random_bytes(16)),
                'metadata_txid' => bin2hex(random_bytes(16)),
                'timestamp' => $docTs->toIso8601String(),
            ]]);
        } catch (\Exception $e) {}

        // Document upload events
        try {
            $multichain->publish(StreamEnums::EVENTS->value, "{$prNumber}_doc_{$d}", ['json' => [
                'pr_number' => $prNumber,
                'procurement_title' => $title,
                'stage' => $stages[$stageIdx],
                'event_type' => 'document_upload',
                'category' => 'document',
                'severity' => 'info',
                'details' => "Uploaded: {$docType}",
                'user_address' => $user->blockchain_address ?? '',
                'timestamp' => $docTs->toIso8601String(),
            ]]);
        } catch (\Exception $e) {}
    }

    // 4. CORRECTIONS (30% chance)
    if (rand(1, 100) <= 30) {
        $corrTs = (clone $now)->addDays(rand(5, 30));
        $reasons = ['Incorrect document uploaded', 'Updated version available', 'File was corrupted', 'Missing signatures', 'Amount discrepancy found'];

        // Document correction
        try {
            $multichain->publish(StreamEnums::CORRECTIONS->value, $prNumber, ['json' => [
                'pr_number' => $prNumber,
                'procurement_title' => $title,
                'correction_type' => 'document_replacement',
                'action' => 'replace',
                'reason' => $reasons[array_rand($reasons)],
                'corrected_by' => $user->name,
                'user_address' => $user->blockchain_address ?? '',
                'original_txid' => bin2hex(random_bytes(16)),
                'timestamp' => $corrTs->toIso8601String(),
            ]]);
        } catch (\Exception $e) {}

        // Correction event
        try {
            $multichain->publish(StreamEnums::EVENTS->value, "{$prNumber}_corr", ['json' => [
                'pr_number' => $prNumber,
                'procurement_title' => $title,
                'stage' => $stages[array_rand($stages)],
                'event_type' => 'correction_submitted',
                'category' => 'correction',
                'severity' => 'warning',
                'details' => 'Correction submitted',
                'user_address' => $user->blockchain_address ?? '',
                'timestamp' => $corrTs->toIso8601String(),
            ]]);
        } catch (\Exception $e) {}
    }

    // 5. ARCHIVE (25% chance)
    if (rand(1, 100) <= 25) {
        $archTs = (clone $now)->addDays(rand(30, 120));
        try {
            $multichain->publish(StreamEnums::ARCHIVE->value, $prNumber, ['json' => [
                'pr_number' => $prNumber,
                'archived' => true,
                'archived_by' => $user->name,
                'user_address' => $user->blockchain_address ?? '',
                'archived_at' => $archTs->toIso8601String(),
                'reason' => ['Completed', 'Cancelled - budget realignment', 'Awarded', 'Closed'][array_rand([0,1,2,3])],
            ]]);
        } catch (\Exception $e) {}
    }

    $progress->advance();
}

$progress->finish();
$output->newLine(2);
$output->writeln('<info>✓ Done! All ' . $procurementCount . ' procurements seeded.</info>');
$output->newLine();

// Count items per stream
$output->writeln('<comment>Stream counts:</comment>');
foreach (StreamEnums::cases() as $stream) {
    try {
        $items = $multichain->liststreamitems($stream->value, true, 5000, 0, false);
        $count = is_array($items) ? count($items) : 0;
        $output->writeln("  {$stream->value}: {$count}");
    } catch (\Exception $e) {
        $output->writeln("  {$stream->value}: <error>Error - {$e->getMessage()}</error>");
    }
}

$output->newLine();
$output->writeln('<info>🎉 Mass data seeding complete!</info>');