<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UserRole;
use App\Models\IntegrityViolationLog;
use App\Models\User;
use App\Notifications\IntegrityBreachNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Integrity Breach Digest Job
 *
 * Sends a daily summary email of all integrity breach violations
 * detected during the previous day's verification runs.
 *
 * Only sent to users who have 'integrity_breach' email preference enabled.
 */
class IntegrityBreachDigestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The date for the digest (Y-m-d format).
     */
    public string $date;

    /**
     * The number of days to look back (default: 1 for daily digest).
     */
    public int $lookbackDays;

    public function __construct(?string $date = null, int $lookbackDays = 1)
    {
        $this->date = $date ?? now()->subDays($lookbackDays)->format('Y-m-d');
        $this->lookbackDays = $lookbackDays;
    }

    public function handle(): void
    {
        if (! config('integrity.breach_notifications.digest_enabled', true)) {
            Log::info('IntegrityBreachDigestJob: digest disabled via config, skipping');

            return;
        }

        $startOfDay = now()->parse($this->date)->startOfDay();
        $endOfDay = now()->parse($this->date)->endOfDay();

        // Get all violations recorded during this period
        $violations = IntegrityViolationLog::whereBetween('created_at', [$startOfDay, $endOfDay])
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($violations->isEmpty()) {
            Log::info('IntegrityBreachDigestJob: no violations for period, skipping', ['date' => $this->date]);

            return;
        }

        // Build summary
        $summary = [
            'total' => $violations->count(),
            'critical' => $violations->where('severity', 'critical')->count(),
            'high' => $violations->where('severity', 'high')->count(),
            'medium' => $violations->where('severity', 'medium')->count(),
            'low' => $violations->where('severity', 'low')->count(),
            'by_type' => $violations->groupBy('violation_type')->map->count()->toArray(),
            'by_stream' => $violations->groupBy('stream')->map->count()->toArray(),
        ];

        // Build violation details for email
        $violationData = $violations->map(function ($v) {
            return [
                'id' => $v->id,
                'display_name' => $this->getDisplayName($v->violation_type),
                'severity' => $v->severity,
                'stream' => $v->stream,
                'stream_key' => $v->stream_key,
                'txid' => $v->txid,
                'run_id' => $v->verification_run_id,
                'recovery_status' => $v->recovery_status,
                'field_diffs' => $v->field_differences,
                'created_at' => $v->created_at->format('M j, Y g:i A'),
            ];
        })->toArray();

        // Get recipients with email preference enabled
        $recipientRoles = config('integrity.breach_notifications.recipient_roles', [UserRole::ADMIN->value, UserRole::BAC_CHAIRMAN->value, UserRole::HOPE->value]);
        $recipients = User::whereHas('roles', fn ($q) => $q->whereIn('name', $recipientRoles))
            ->where('email_notifications_enabled', true)
            ->get()
            ->filter(fn ($u) => $u->isNotificationEnabled('integrity_breach', 'email'));

        if ($recipients->isEmpty()) {
            Log::info('IntegrityBreachDigestJob: no eligible recipients', ['date' => $this->date]);

            return;
        }

        // Send digest notification to each recipient
        foreach ($recipients as $recipient) {
            try {
                $recipient->notify(new IntegrityBreachNotification(
                    breachType: 'digest_summary',
                    stream: 'multiple',
                    streamKey: 'daily',
                    txid: '',
                    breachData: [],
                    recordId: null,
                    runId: null,
                    fieldDiffs: null,
                    isDigest: true,
                    violations: $violationData,
                    summary: $summary,
                ));

                Log::info('IntegrityBreachDigestJob: sent to recipient', [
                    'date' => $this->date,
                    'recipient' => $recipient->email,
                    'total_violations' => $summary['total'],
                ]);
            } catch (\Exception $e) {
                Log::error('IntegrityBreachDigestJob: failed to send to recipient', [
                    'date' => $this->date,
                    'recipient' => $recipient->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('IntegrityBreachDigestJob: completed', [
            'date' => $this->date,
            'recipients_notified' => $recipients->count(),
            'total_violations' => $summary['total'],
        ]);
    }

    private function getDisplayName(string $violationType): string
    {
        return match ($violationType) {
            'hash_mismatch' => 'Hash Mismatch',
            'content_mismatch' => 'Content Mismatch',
            'user_address_tampered' => 'User Address Tampered',
            'unauthorized_publisher' => 'Unauthorized Publisher',
            'row_deleted' => 'Row Deleted',
            'unauthorized_record' => 'Unauthorized Record',
            default => ucfirst(str_replace('_', ' ', $violationType)),
        };
    }
}
