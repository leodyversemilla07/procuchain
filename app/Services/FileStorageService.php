<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class FileStorageService
{
    public function uploadFile($file, string $path, string $suffix = ''): string
    {
        $fileKey = $path . $suffix . '.' . $file->getClientOriginalExtension();
        Storage::disk('spaces')->put($fileKey, file_get_contents($file), 'private');
        return $fileKey;
    }
}
