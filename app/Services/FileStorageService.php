<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Service for handling file storage operations.
 */
class FileStorageService
{
    /**
     * The storage disk to use.
     */
    protected string $disk;

    /**
     * Create a new FileStorageService instance.
     */
    public function __construct(string $disk = 'spaces')
    {
        $this->disk = $disk;
    }

    /**
     * Upload a file to the specified storage disk and path.
     *
     * @param  UploadedFile  $file  The file to upload.
     * @param  string  $path  The path where the file will be stored.
     * @param  string  $suffix  Optional suffix for the file name.
     * @return string The key of the stored file.
     */
    public function uploadFile(UploadedFile $file, string $path, string $suffix = ''): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = $suffix.'.'.$extension;
        $fileKey = Storage::disk($this->disk)->putFileAs($path, $file, $filename, 'private');

        return $fileKey;
    }
}
