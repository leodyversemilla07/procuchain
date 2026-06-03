<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Removed manual multichain:setup command wrapper to allow the
// dedicated Command class signature options to be registered.

// Scheduled node health check — auto-repairs unsubscribed nodes every 6 hours
use Illuminate\Support\Facades\Schedule;

Schedule::command('multichain:node-health --fix --notify')->everySixHours()->withoutOverlapping();

// Clean up orphaned temp files from blockchain uploads every hour
Schedule::command('temp:cleanup --hours=1')->hourly()->withoutOverlapping();

// ═══════════════════════════════════════════════════════════════════════
// Blockchain Mirror Integrity — Scheduled Verification & Self-Healing
// ═══════════════════════════════════════════════════════════════════════
//
// Architecture: Blockchain = source of truth, MySQL mirror = query cache.
// These scheduled jobs ensure the mirror stays synchronized and any
// integrity violations are detected, reported, and auto-repaired.
//
// Verification layers (IntegrityVerificationService):
//   Layer 1: SHA-256 hash check (fast, detects any change)
//   Layer 2: Field-level diff (identifies exactly what changed)
//   Layer 3: Content comparison against chain (authoritative)
//   Layer 4: Row existence check (detects deletions)
//   Layer 5: Publisher authorization check
//
// All violations recorded in integrity_audit_logs (append-only).

// Downstream sync: pull latest blockchain data into mirror (hourly)
Schedule::command('blockchain:sync')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(config('mail.from.address'));

// Integrity audit: verify mirror against blockchain + auto-repair (every 6 hours)
// Detects: hash mismatches, content mismatches, deleted rows, unauthorized publishers
// Auto-repairs: restores original data from blockchain, logs all recovery ops
Schedule::command('blockchain:audit --repair --source=scheduled')
    ->everySixHours()
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(config('mail.from.address'));

// Lightweight integrity check without auto-repair (daily at 06:00)
// Generates violation reports for admin review without modifying data
Schedule::command('blockchain:audit --source=scheduled')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->emailOutputOnFailure(config('mail.from.address'));
