<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Handles file storage operations using Laravel's filesystem abstraction
 * 
 * This service manages file uploads to the DigitalOcean Spaces storage backend
 * using Laravel's Storage facade. Files are stored with private access permissions.
 */
class FileStorageService
{
    /**
     * Uploads a file to the configured storage disk
     *
     * Stores the uploaded file in DigitalOcean Spaces with private access permissions.
     * The file is stored using a combination of the provided path and optional suffix,
     * maintaining the original file extension.
     *
     * @param UploadedFile $file The uploaded file instance from the request
     * @param string $path The storage path/prefix for the file
     * @param string $suffix Optional suffix to append to the filename before extension
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException When file upload fails
     * @return string The complete file key/path in storage
     */
    public function uploadFile($file, string $path, string $suffix = ''): string
    {
        $fileKey = $path.$suffix.'.'.$file->getClientOriginalExtension();
        Storage::disk('spaces')->put($fileKey, file_get_contents($file), 'private');

        return $fileKey;
    }
}
