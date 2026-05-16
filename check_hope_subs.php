#!/usr/bin/env php
<?php
// Check HOPE node stream subscription status
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Libraries\MultiChain\Client;

$client = new Client('172.31.42.5', 6834, config('multichain.rpc_user'), config('multichain.rpc_password'), false);
$client->setoption('chain_name', config('multichain.chain_name'));

$streams = ['procurement.metadata','procurement.status','procurement.documents','procurement.corrections','procurement.metadata.corrections','procurement.archive','procurement.events','file.data','file.metadata','file.chunks'];

foreach ($streams as $s) {
    $info = $client->getstreaminfo($s);
    $sub = $info['subscribed'] ?? 'ERROR';
    $items = $info['items'] ?? 'N/A';
    echo "$s => subscribed=$sub, items=$items\n";
}
