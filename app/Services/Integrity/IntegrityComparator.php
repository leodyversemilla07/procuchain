<?php

declare(strict_types=1);

namespace App\Services\Integrity;

/**
 * Compares a normalized DB snapshot against projected blockchain data.
 */
class IntegrityComparator
{
    /**
     * @param  array<string, mixed>  $dbData
     * @param  array<string, mixed>  $projectedChainData
     * @return list<array{field: string, old_value: mixed, new_value: mixed}>
     */
    public function diff(array $dbData, array $projectedChainData): array
    {
        $diffs = [];
        $sharedKeys = array_intersect(array_keys($projectedChainData), array_keys($dbData));

        foreach ($sharedKeys as $key) {
            if ($this->isOperationalColumn($key)) {
                continue;
            }

            $chainValue = $projectedChainData[$key] ?? null;
            $dbValue = $dbData[$key] ?? null;

            if (! $this->valuesAreEquivalent($chainValue, $dbValue)) {
                $diffs[] = [
                    'field' => $key,
                    'old_value' => $chainValue,
                    'new_value' => $dbValue,
                ];
            }
        }

        return $diffs;
    }

    private function isOperationalColumn(string $key): bool
    {
        return in_array($key, [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            'last_verified_at',
            'is_blockchain_verified',
            'has_breach',
            'data_hash',
            'blockchain_hash',
        ], true);
    }

    private function valuesAreEquivalent(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }

        if (($a === null && $b === '') || ($a === '' && $b === null)) {
            return true;
        }

        // Handle timestamp timezone differences (UTC vs local)
        if (is_string($a) && is_string($b) && $this->looksLikeTimestamp($a) && $this->looksLikeTimestamp($b)) {
            return $this->timestampsAreEquivalent($a, $b);
        }

        if (is_array($a) && is_array($b)) {
            return json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                === json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $a === (string) $b;
    }

    /**
     * Check if a string looks like a timestamp.
     */
    private function looksLikeTimestamp(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}/', $value) === 1;
    }

    /**
     * Compare timestamps that may be in different timezones.
     * Blockchain stores UTC, DB may store local time.
     * Normalize both to UTC before comparing.
     */
    private function timestampsAreEquivalent(string $a, string $b): bool
    {
        try {
            $timeA = new \DateTime($a);
            $timeB = new \DateTime($b);

            // Convert both to UTC for comparison
            $timeA->setTimezone(new \DateTimeZone('UTC'));
            $timeB->setTimezone(new \DateTimeZone('UTC'));

            return $timeA->getTimestamp() === $timeB->getTimestamp();
        } catch (\Exception) {
            // If parsing fails, fall back to string comparison
            return (string) $a === (string) $b;
        }
    }
}
