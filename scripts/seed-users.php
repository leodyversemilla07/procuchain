<?php

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "Seeding roles and permissions...\n";
$seeder = new RoleAndPermissionSeeder;
$seeder->run();
echo "Roles and permissions created.\n";

$users = [
    ['name' => 'Leodyver Semilla', 'email' => 'admin@example.com', 'password' => 'Leodyver07', 'role' => 'bac_secretariat'],
    ['name' => 'Lunar Spectre', 'email' => 'lunarspectre00@gmail.com', 'password' => 'LunarSpectre00', 'role' => 'bac_chairman'],
    ['name' => 'Leif Sage Semilla', 'email' => 'leifsagesemilla@gmail.com', 'password' => 'LeifSage07', 'role' => 'hope'],
    ['name' => 'LeoBriel Zilvrak', 'email' => 'leobrielzilvrak@gmail.com', 'password' => 'LeoBriel07', 'role' => 'admin'],
];

foreach ($users as $u) {
    $user = User::firstOrCreate(
        ['email' => $u['email']],
        [
            'name' => $u['name'],
            'password' => Hash::make($u['password']),
            'blockchain_address' => null,
            'email_notifications_enabled' => true,
            'notification_preferences' => User::getDefaultNotificationPreferences(),
        ]
    );
    $user->syncRoles([$u['role']]);
    echo "✓ {$u['email']} ({$u['role']})\n";
}

echo 'Done! '.User::count()." users total.\n";
