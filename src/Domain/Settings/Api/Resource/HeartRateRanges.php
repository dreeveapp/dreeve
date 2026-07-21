<?php

declare(strict_types=1);

namespace App\Domain\Settings\Api\Resource;

/**
 * Heart rate histories are stored as a date => bpm map, but the API speaks in
 * lists of objects so it stays consistent with the weight and FTP resources.
 */
final readonly class HeartRateRanges
{
    /**
     * @param array<int|string, mixed> $ranges
     *
     * @return list<array{on: string, bpm: int}>
     */
    public static function toEntries(array $ranges): array
    {
        $entries = [];
        foreach ($ranges as $on => $bpm) {
            if (!is_numeric($bpm)) {
                continue;
            }
            $entries[] = ['on' => (string) $on, 'bpm' => (int) $bpm];
        }

        return $entries;
    }
}
