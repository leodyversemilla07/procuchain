<?php

namespace App\Services;

class StreamKeyService
{
    public function generate(string $procurementId, string $procurementTitle): string
    {
        $cleanId = preg_replace('/[^a-zA-Z0-9\-]/', '', $procurementId);
        $cleanTitle = strtolower(trim($procurementTitle));
        $cleanTitle = preg_replace('/[^a-zA-Z0-9\-]+/', '_', $cleanTitle);
        $cleanTitle = preg_replace('/[_-]+/', '_', $cleanTitle);
        $cleanTitle = trim($cleanTitle, '_-');
        $streamKey = $cleanId . '_' . $cleanTitle;
        $maxLength = 64;
        if (strlen($streamKey) > $maxLength) {
            $streamKey = substr($streamKey, 0, $maxLength);
            $streamKey = rtrim($streamKey, '_-');
        }
        return $streamKey;
    }
}
