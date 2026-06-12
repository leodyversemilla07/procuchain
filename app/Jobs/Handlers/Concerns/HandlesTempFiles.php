<?php

declare(strict_types=1);

namespace App\Jobs\Handlers\Concerns;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Handles temp file reconstitution and cleanup for blockchain upload jobs.
 */
trait HandlesTempFiles
{
    protected function reconstituteTempFile(string $tempPath, string $originalName, string $mimeType): UploadedFile
    {
        $fullPath = Storage::path($tempPath);

        if (! file_exists($fullPath)) {
            Log::error('HandlesTempFiles: temp file missing', [
                'temp_path' => $tempPath,
                'full_path' => $fullPath,
                'disk_root' => Storage::path(''),
                'dir_exists' => is_dir(dirname($fullPath)),
                'dir_contents' => is_dir(dirname($fullPath))
                    ? scandir(dirname($fullPath))
                    : 'directory does not exist',
            ]);

            throw new Exception("Temp file not found: {$tempPath}");
        }

        return new UploadedFile(
            path: $fullPath,
            originalName: $originalName,
            mimeType: $mimeType,
            error: null,
            test: true,
        );
    }

    protected function cleanupTempFile(string $tempPath): void
    {
        try {
            if (Storage::exists($tempPath)) {
                Storage::delete($tempPath);
            }
        } catch (Exception $e) {
            Log::warning('HandlesTempFiles: Failed to cleanup temp file', [
                'path' => $tempPath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
