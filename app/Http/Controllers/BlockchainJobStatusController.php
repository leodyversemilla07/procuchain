<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class BlockchainJobStatusController extends Controller
{
    /**
     * Return the current status of a queued blockchain write job.
     *
     * Statuses: pending → done | failed
     */
    public function status(string $jobId): JsonResponse
    {
        $cached = Cache::get("blockchain_job:{$jobId}");

        if ($cached === null) {
            return response()->json(['status' => 'pending'], 202);
        }

        $httpStatus = $cached['status'] === 'done' ? 200 : 422;

        return response()->json($cached, $httpStatus);
    }
}
