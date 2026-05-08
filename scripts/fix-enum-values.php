<?php

use App\Enums\StreamEnums;
use App\Services\Manager;
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

$output->writeln('<info>Fixing blockchain records with invalid enum values...</info>');

// Category mapping: old wrong values → correct enum values
$categoryMap = [
    'goods' => 'goods',
    'infrastructure' => 'infrastructure_projects',
    'consulting' => 'consulting_services',
];

// Procurement mode mapping
$modeMap = [
    'public_bidding' => 'competitive_bidding',
    'alternative_mode_shopping' => 'small_value_procurement',
    'alternative_mode_direct_contracting' => 'direct_contracting',
    'alternative_mode_negotiated' => 'negotiated_procurement',
];

$fixed = 0;

// Fetch all metadata items
$items = $multichain->liststreamitems(StreamEnums::METADATA->value, true, 5000, 0, false);

if (!is_array($items)) {
    $output->error('No items found in metadata stream');
    exit(1);
}

$output->writeln('Found ' . count($items) . ' metadata items');

foreach ($items as $item) {
    $keys = $item['keys'] ?? [];
    $data = $item['data']['json'] ?? [];

    if (empty($keys) || empty($data)) {
        continue;
    }

    $prNumber = $keys[0];
    $changes = [];

    // Fix category
    $oldCategory = $data['category'] ?? null;
    if ($oldCategory && isset($categoryMap[$oldCategory])) {
        $correctCategory = $categoryMap[$oldCategory];
        if ($oldCategory !== $correctCategory) {
            $data['category'] = $correctCategory;
            $changes[] = "category: {$oldCategory} → {$correctCategory}";
        }
    }

    // Fix procurement mode
    $oldMode = $data['procurement_mode'] ?? null;
    if ($oldMode && isset($modeMap[$oldMode])) {
        $correctMode = $modeMap[$oldMode];
        if ($oldMode !== $correctMode) {
            $data['procurement_mode'] = $correctMode;
            $changes[] = "procurement_mode: {$oldMode} → {$correctMode}";
        }
    }

    if (!empty($changes)) {
        try {
            $multichain->publish(StreamEnums::METADATA->value, $prNumber, ['json' => $data]);
            $fixed++;
            $output->writeln("  Fixed {$prNumber}: " . implode(', ', $changes));
        } catch (\Exception $e) {
            $output->writeln("  <error>Failed to fix {$prNumber}: {$e->getMessage()}</error>");
        }
    }
}

$output->newLine();
$output->writeln("<info>✓ Fixed {$fixed} records</info>");

// Verify by reading one record back
if ($fixed > 0) {
    $output->writeln("\n<comment>Verifying fix...</comment>");
    $verifyItems = $multichain->liststreamitems(StreamEnums::METADATA->value, true, 2, 0, false);
    if (!empty($verifyItems[0])) {
        $sample = $verifyItems[0]['data']['json'] ?? [];
        $output->writeln("  category: " . ($sample['category'] ?? 'N/A'));
        $output->writeln("  procurement_mode: " . ($sample['procurement_mode'] ?? 'N/A'));
    }
}

$output->newLine();
$output->writeln('<info>Done!</info>');