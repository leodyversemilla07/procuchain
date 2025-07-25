<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\ProcurementStageNotification;
use Illuminate\Console\Command;
use NotificationChannels\WebPush\PushSubscription;

class TestPushNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'push:test {user_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test push notification functionality';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id') ?? 1;
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }

        $this->info("Testing push notification for user: {$user->name}");

        // Check if user has push subscriptions
        $subscriptions = $user->pushSubscriptions()->count();
        $this->info("User has {$subscriptions} push subscription(s)");

        if ($subscriptions === 0) {
            $this->warn("User has no push subscriptions. You need to subscribe through the web interface first.");
            return 0;
        }

        // Test the notification
        try {
            $user->notify(new ProcurementStageNotification([
                'title' => 'Test Push Notification',
                'message' => 'This is a test push notification to verify the system is working correctly.',
                'action_url' => route('notifications'),
                'procurement_id' => null,
                'stage' => 'test'
            ]));

            $this->info("✅ Push notification sent successfully!");
            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Failed to send push notification: " . $e->getMessage());
            return 1;
        }
    }
}
