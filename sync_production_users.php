<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

echo '=== Syncing Production User Data to Local Database ==='.PHP_EOL;
echo PHP_EOL;

// Production user data
$productionUsers = [
    [
        'email' => 'leodyversemilla07@gmail.com',
        'name' => 'Leodyver Semilla',
        'blockchain_address' => '1R5Be5615e3b7MjDiWxA9HAuzAAE3xTuVS3N54',
        'roles' => ['bac_secretariat'],
    ],
    [
        'email' => 'lunarspectre00@gmail.com',
        'name' => 'Lunar Spectre',
        'blockchain_address' => '1Peoatte9cRGah5XQykUi62PJuBZM5otZjZkrz',
        'roles' => ['bac_chairman'],
    ],
    [
        'email' => 'leifsagesemilla@gmail.com',
        'name' => 'Leif Sage Semilla',
        'blockchain_address' => '13JUtJaimUnhbXcUeE97Uzj4c4vwLyJvqHhMw9',
        'roles' => ['hope'],
    ],
    [
        'email' => 'leobrielzilvrak@gmail.com',
        'name' => 'LeoBriel Zilvrak',
        'blockchain_address' => '1YwGYaqaeMJxMHXhKRaKqgaha1ncVQ7peXbvuF',
        'roles' => ['admin'],
    ],
    [
        'email' => 'brylemaamo@gmail.com',
        'name' => 'Bryle Maamo',
        'blockchain_address' => '1Y5R5CT8A1Be6RwnxQecEeWobYrmKH8p9HcQmb',
        'roles' => ['bac_secretariat'],
    ],
    [
        'email' => 'semillacelsojr@gmail.com',
        'name' => 'Celso Semilla',
        'blockchain_address' => '1TcErv2payomuanpZy5eJKVuNmrNjD2hMikjen',
        'roles' => ['bac_chairman'],
    ],
    [
        'email' => 'nidasemilla15@gmail.com',
        'name' => 'Leonida Monsanto',
        'blockchain_address' => '1RDVkCmEaeG9XBqL8NN6XsdGaoc4CnqURU5K4C',
        'roles' => ['bac_secretariat'],
    ],
];

$created = 0;
$updated = 0;

foreach ($productionUsers as $userData) {
    $user = User::where('email', $userData['email'])->first();

    if ($user) {
        // Update existing user
        $user->update([
            'name' => $userData['name'],
            'blockchain_address' => $userData['blockchain_address'],
        ]);

        // Sync roles
        $user->syncRoles($userData['roles']);

        echo "✓ Updated: {$userData['email']}".PHP_EOL;
        echo "  Name: {$userData['name']}".PHP_EOL;
        echo "  Address: {$userData['blockchain_address']}".PHP_EOL;
        echo '  Roles: '.implode(', ', $userData['roles']).PHP_EOL;
        echo PHP_EOL;
        $updated++;
    } else {
        // Create new user with a default password
        $user = User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make('password'), // Default password
            'email_verified_at' => now(),
            'blockchain_address' => $userData['blockchain_address'],
        ]);

        // Assign roles
        $user->assignRole($userData['roles']);

        echo "✓ Created: {$userData['email']}".PHP_EOL;
        echo "  Name: {$userData['name']}".PHP_EOL;
        echo "  Address: {$userData['blockchain_address']}".PHP_EOL;
        echo '  Roles: '.implode(', ', $userData['roles']).PHP_EOL;
        echo '  Password: password (default)'.PHP_EOL;
        echo PHP_EOL;
        $created++;
    }
}

echo '=== Sync Complete ==='.PHP_EOL;
echo "Created: {$created}".PHP_EOL;
echo "Updated: {$updated}".PHP_EOL;
echo 'Total: '.($created + $updated).PHP_EOL;
