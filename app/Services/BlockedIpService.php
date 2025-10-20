<?php

namespace App\Services;

use App\Models\BlockedIp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class BlockedIpService
{
    /**
     * Check if an IP address is currently blocked
     */
    public function isBlocked(string $ipAddress): bool
    {
        return BlockedIp::active()->forIp($ipAddress)->exists();
    }

    /**
     * Block an IP address
     */
    public function blockIp(string $ipAddress, ?string $reason = null, ?Carbon $expiresAt = null): BlockedIp
    {
        // Check if already blocked
        $existingBlock = BlockedIp::forIp($ipAddress)->first();

        if ($existingBlock) {
            // Update existing block
            $existingBlock->update([
                'blocked_by' => Auth::id(),
                'reason' => $reason ?? $existingBlock->reason,
                'expires_at' => $expiresAt,
                'is_active' => true,
            ]);

            Log::info('IP address block updated', [
                'ip_address' => $ipAddress,
                'blocked_by' => Auth::id(),
                'reason' => $reason,
                'expires_at' => $expiresAt?->toDateTimeString(),
            ]);

            return $existingBlock;
        }

        // Create new block
        $block = BlockedIp::create([
            'ip_address' => $ipAddress,
            'blocked_by' => Auth::id(),
            'reason' => $reason,
            'expires_at' => $expiresAt,
            'is_active' => true,
        ]);

        Log::info('IP address blocked', [
            'ip_address' => $ipAddress,
            'blocked_by' => Auth::id(),
            'reason' => $reason,
            'expires_at' => $expiresAt?->toDateTimeString(),
        ]);

        return $block;
    }

    /**
     * Unblock an IP address
     */
    public function unblockIp(string $ipAddress): bool
    {
        $block = BlockedIp::forIp($ipAddress)->first();

        if (! $block) {
            return false;
        }

        $block->update(['is_active' => false]);

        Log::info('IP address unblocked', [
            'ip_address' => $ipAddress,
            'unblocked_by' => Auth::id(),
        ]);

        return true;
    }

    /**
     * Get all blocked IPs
     */
    public function getBlockedIps(bool $activeOnly = true)
    {
        $query = BlockedIp::with('blocker:id,name,email');

        if ($activeOnly) {
            $query->active();
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get blocked IP details
     */
    public function getBlockedIpDetails(string $ipAddress): ?BlockedIp
    {
        return BlockedIp::with('blocker:id,name,email')
            ->forIp($ipAddress)
            ->first();
    }

    /**
     * Clean up expired blocks
     */
    public function cleanupExpiredBlocks(): int
    {
        $count = BlockedIp::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['is_active' => false]);

        if ($count > 0) {
            Log::info('Expired IP blocks cleaned up', ['count' => $count]);
        }

        return $count;
    }
}
