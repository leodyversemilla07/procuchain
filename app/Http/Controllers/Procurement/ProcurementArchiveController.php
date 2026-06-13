<?php

declare(strict_types=1);

namespace App\Http\Controllers\Procurement;

use App\Enums\StageEnums;
use App\Http\Controllers\Controller;
use App\Repositories\ProcurementArchiveRepository;
use App\Services\AuditLogService;
use App\Services\ProcurementDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProcurementArchiveController extends Controller
{
    public function __construct(
        private readonly ProcurementArchiveRepository $archiveRepository,
        private readonly ProcurementDataService $procurementDataService,
        private readonly AuditLogService $AuditLogService,
    ) {}

    /**
     * Archive a procurement
     */
    public function store(Request $request, string $pr_number): RedirectResponse
    {
        $this->authorize('archive-procurement', $pr_number);

        try {
            // Get current status to validate stage
            $currentStatus = $this->procurementDataService->getCurrentProcurementStatus($pr_number);

            if (! $currentStatus) {
                return back()->with('error', 'Procurement not found.');
            }

            // CRITICAL VALIDATION: Only allow archiving if stage is COMPLETED
            $stage = $currentStatus['stage'] ?? '';
            // Check against both the string value and enum to be safe
            if ($stage !== StageEnums::COMPLETED->value && $stage !== 'completed') {
                Log::warning('Attempted to archive incomplete procurement', [
                    'pr_number' => $pr_number,
                    'stage' => $stage,
                    'user_id' => $request->user()->id,
                ]);

                return back()->with('error', 'Only fully completed procurements can be archived.');
            }

            $this->archiveRepository->archive(
                $pr_number,
                (string) $request->user()->id,
                $request->input('reason')
            );

            $this->AuditLogService->log(
                'procurement.archived',
                'procurement',
                $pr_number,
                [],
                ['reason' => $request->input('reason')],
            );

            return back()->with('success', 'Procurement archived successfully.');
        } catch (\Exception $e) {
            report($e);
            Log::error('Failed to archive procurement', [
                'pr_number' => $pr_number,
                'error' => 'An error occurred with the archive operation.',
            ]);

            return back()->with('error', 'Failed to archive procurement. Please try again.');
        }
    }

    /**
     * Restore an archived procurement
     */
    public function destroy(Request $request, string $pr_number): RedirectResponse
    {
        $this->authorize('restore-procurement', $pr_number);

        try {
            $this->archiveRepository->restore(
                $pr_number,
                (string) $request->user()->id
            );

            $this->AuditLogService->log(
                'procurement.restored',
                'procurement',
                $pr_number,
            );

            return back()->with('success', 'Procurement restored successfully.');
        } catch (\Exception $e) {
            report($e);
            Log::error('Failed to restore procurement', [
                'pr_number' => $pr_number,
                'error' => 'An error occurred with the archive operation.',
            ]);

            return back()->with('error', 'Failed to restore procurement. Please try again.');
        }
    }
}
