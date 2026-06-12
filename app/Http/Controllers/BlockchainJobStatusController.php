<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BlockchainJobStatusController extends Controller
{
    /**
     * Return the current status of a queued blockchain write job.
     *
     * Statuses: pending -> done | failed
     */
    public function status(Request $request, string $jobId): JsonResponse
    {
        $this->authorize('view-blockchain-transactions');

        $cached = Cache::get("blockchain_job:{$jobId}");

        if ($cached === null) {
            return response()->json(['status' => 'pending'], 202);
        }

        $ownerId = data_get($cached, 'user_id');
        if ($ownerId !== null && $request->user()->id !== (int) $ownerId) {
            abort(403);
        }

        $httpStatus = $cached['status'] === 'done' ? 200 : 422;

        return response()->json($cached, $httpStatus);
    }
}
