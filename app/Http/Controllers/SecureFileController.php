<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StreamEnums;
use App\Services\ProcurementServices;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SecureFileController extends BaseController
{
    private ProcurementServices $services;

    public function __construct(ProcurementServices $services)
    {
        $this->services = $services;
        $this->middleware('auth');
        $this->middleware('role:bac_chairman,bac_secretariat,hope');
    }

    /**
     * Securely download a file with authentication validation
     */
    public function downloadFile(Request $request, string $fileKey): Response
    {
        try {
            // Validate the file key
            if (empty($fileKey)) {
                abort(400, 'Invalid file key');
            }

            // Verify the file exists in our document stream (security check)
            if (!$this->validateFileAccess($fileKey)) {
                abort(404, 'File not found or access denied');
            }

            // Get the file from DigitalOcean Spaces
            $disk = Storage::disk('spaces');
            
            if (!$disk->exists($fileKey)) {
                abort(404, 'File not found');
            }

            // Get file content and metadata
            $fileContent = $disk->get($fileKey);
            $mimeType = $disk->mimeType($fileKey) ?: 'application/pdf';
            $fileName = basename($fileKey);

            // Log the secure access
            Log::info('Secure file access', [
                'file_key' => $fileKey,
                'user_id' => auth()->id(),
                'user_role' => auth()->user()->role ?? 'unknown',
                'ip' => $request->ip(),
            ]);

            // Return the file with appropriate headers
            return response($fileContent)
                ->header('Content-Type', $mimeType)
                ->header('Content-Disposition', 'inline; filename="' . $fileName . '"')
                ->header('Cache-Control', 'private, no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0')
                ->header('X-Content-Type-Options', 'nosniff')
                ->header('X-Frame-Options', 'DENY');

        } catch (Exception $e) {
            Log::error('Secure file download failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
                'user_id' => auth()->id() ?? 'guest',
            ]);

            abort(500, 'Unable to retrieve file');
        }
    }

    /**
     * Validate that the file exists in our document stream and user has access
     */
    private function validateFileAccess(string $fileKey): bool
    {
        try {
            // Fetch all document items to validate the file exists in our system
            $allDocumentItems = $this->services->getMultiChain()->listStreamItems(
                StreamEnums::DOCUMENTS->value,
                true,
                10000, // Large enough to get all documents
                0,
                false
            );

            if ($allDocumentItems === null) {
                return false;
            }

            // Check if the file key exists in our document stream
            foreach ($allDocumentItems as $item) {
                $data = $item['data']['json'] ?? [];
                if (($data['file_key'] ?? '') === $fileKey) {
                    return true;
                }
            }

            return false;

        } catch (Exception $e) {
            Log::error('File access validation failed', [
                'file_key' => $fileKey,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
