<?php

declare(strict_types=1);

namespace App\Jobs\Handlers\Concerns;

use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait HandlesTempFiles
{
    protected function reconstituteTempFile(string $tempPath, string $originalName, string $mimeType): UploadedFile
    {
        $fullPath = Storage::path($tempPath);

        if (! file_exists($fullPath)) {
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
            Storage::delete($tempPath);
        } catch (Exception $e) {
            Log::warning('BlockchainWriteJob: Failed to cleanup temp file', [
                'path' => $tempPath,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
