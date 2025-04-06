<?php

namespace App\Services;

/**
 * Generates standardized stream keys for blockchain operations
 * 
 * This service creates consistent, URL-safe keys for blockchain streams by combining
 * procurement IDs with sanitized titles. Keys are used to organize and retrieve
 * related blockchain records across different streams.
 */
class StreamKeyService
{
    /**
     * Generates a stream key by combining procurement ID and title
     *
     * Creates a URL-safe stream key by:
     * 1. Converting title to lowercase
     * 2. Replacing non-alphanumeric chars with hyphens
     * 3. Removing duplicate hyphens
     * 4. Trimming hyphens from ends
     * 5. Combining ID and processed title
     * 6. Enforcing maximum length of 200 chars
     *
     * @param string $procurementId Unique identifier for the procurement
     * @param string $procurementTitle Title of the procurement
     * @return string The generated stream key
     */
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
