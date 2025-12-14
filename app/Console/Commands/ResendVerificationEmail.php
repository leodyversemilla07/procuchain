<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResendVerificationEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:resend-verification 
                            {email? : The email address of the user}
                            {--all : Resend to all unverified users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Resend email verification to user(s) with updated signature';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('all')) {
            return $this->resendToAll();
        }

        if (! $email = $this->argument('email')) {
            $this->error('Please provide an email address or use --all flag');

            return self::FAILURE;
        }

        return $this->resendToUser($email);
    }

    /**
     * Resend verification email to a specific user
     */
    protected function resendToUser(string $email): int
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email {$email} not found");

            return self::FAILURE;
        }

        if ($user->hasVerifiedEmail()) {
            $this->warn("User {$email} has already verified their email");

            return self::SUCCESS;
        }

        $user->sendEmailVerificationNotification();

        $this->info("✅ Verification email sent to {$email}");
        $this->line('Current APP_URL: '.config('app.url'));

        return self::SUCCESS;
    }

    /**
     * Resend verification emails to all unverified users
     */
    protected function resendToAll(): int
    {
        $unverifiedUsers = User::whereNull('email_verified_at')->get();

        if ($unverifiedUsers->isEmpty()) {
            $this->info('No unverified users found');

            return self::SUCCESS;
        }

        $this->info("Found {$unverifiedUsers->count()} unverified users");
        $this->line('Current APP_URL: '.config('app.url'));

        if (! $this->confirm('Do you want to send verification emails to all unverified users?')) {
            $this->warn('Operation cancelled');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($unverifiedUsers->count());
        $bar->start();

        $sent = 0;
        foreach ($unverifiedUsers as $user) {
            try {
                $user->sendEmailVerificationNotification();
                $sent++;
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Failed to send to {$user->email}: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Sent verification emails to {$sent} users");

        return self::SUCCESS;
    }
}
