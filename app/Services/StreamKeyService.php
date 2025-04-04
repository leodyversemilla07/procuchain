<?php

namespace App\Services;

class StreamKeyService
{
    public function generate(string $procurementId, string $procurementTitle): string
    {
        $modifiedTitle = strtolower(preg_replace('/[^a-zA-Z0-9]/', '-', $procurementTitle));

        $modifiedTitle = preg_replace('/-+/', '-', $modifiedTitle);

        $modifiedTitle = trim($modifiedTitle, '-');

        $streamKey = $procurementId.'-'.$modifiedTitle;

        $maxLength = 200;
        if (strlen($streamKey) > $maxLength) {
            $streamKey = substr($streamKey, 0, $maxLength);
        }

        return $streamKey;
    }
}
