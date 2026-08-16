<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser\Fit;

/**
 * Chest straps paired over ANT+ record heart rate far more often than the head unit
 * writes "record" messages, and store those extra beats in separate "hr" messages
 * instead. Those messages carry no absolute time of their own: each one holds a run
 * of samples counted off a 32 bit event timestamp, which only becomes a real time
 * once a message arrives that pins the two clocks together.
 *
 * Folding the samples back onto the records recovers a per second heart rate stream
 * for files whose "record" messages hold none.
 */
final class FitStrapHeartRate
{
    // The event_timestamp counter has a 1/1024s resolution, so it wraps every 2^32/1024 seconds.
    private const float EVENT_TIMESTAMP_ROLLOVER = 4194304.0;
    // 0xFF is the FIT "invalid" sentinel for the uint8 fields a bpm is stored in.
    private const int INVALID_BPM = 255;
    private const float GAP_FILL_STEP_IN_SECONDS = 0.25;
    private const int MAX_GAP_FILL_STEPS = 20;

    /**
     * @param list<array<string, mixed>> $records
     * @param list<array<string, mixed>> $hrMessages
     *
     * @return list<array<string, mixed>>
     */
    public static function mergeIntoRecords(array $records, array $hrMessages): array
    {
        $samples = self::expandSamples($hrMessages);
        if ([] === $samples) {
            return $records;
        }

        $sampleIndex = 0;
        $countSamples = count($samples);
        $rangeStart = null;

        foreach ($records as $key => $record) {
            if (!is_numeric($record['timestamp'] ?? null)) {
                continue;
            }
            $rangeEnd = (float) $record['timestamp'];
            if (null === $rangeStart || $rangeStart >= $rangeEnd) {
                $rangeStart = $rangeEnd - 1.0;
                $sampleIndex = max(0, $sampleIndex - 1);
            }

            $sum = 0;
            $count = 0;
            while ($sampleIndex < $countSamples) {
                [$timestamp, $bpm] = $samples[$sampleIndex];
                if ($timestamp > $rangeEnd) {
                    break;
                }
                if ($timestamp > $rangeStart) {
                    $sum += $bpm;
                    ++$count;
                }
                ++$sampleIndex;
            }

            if ($count > 0) {
                $records[$key]['heart_rate'] = (int) round($sum / $count);
            }
            $rangeStart = $rangeEnd;
        }

        return $records;
    }

    /**
     * Turns the runs of counter-relative samples into absolute [timestamp, bpm] pairs,
     * ordered as they were recorded.
     *
     * @param list<array<string, mixed>> $hrMessages
     *
     * @return list<array{float, int}>
     */
    private static function expandSamples(array $hrMessages): array
    {
        $anchorTimestamp = null;
        $anchorEventTimestamp = null;
        /** @var list<array{float, int}> $samples */
        $samples = [];

        foreach ($hrMessages as $hrMessage) {
            $eventTimestamps = array_values(is_array($hrMessage['event_timestamp'] ?? null) ? $hrMessage['event_timestamp'] : [$hrMessage['event_timestamp'] ?? null]);
            $bpms = array_values(is_array($hrMessage['filtered_bpm'] ?? null) ? $hrMessage['filtered_bpm'] : [$hrMessage['filtered_bpm'] ?? null]);

            // A message holding a single sample alongside a real timestamp is what ties
            // the counter to the clock; every later message is measured against it.
            if (is_numeric($hrMessage['timestamp'] ?? null) && 1 === count($eventTimestamps) && is_numeric($eventTimestamps[0] ?? null)) {
                $anchorTimestamp = (float) $hrMessage['timestamp'] + (is_numeric($hrMessage['fractional_timestamp'] ?? null) ? (float) $hrMessage['fractional_timestamp'] : 0.0);
                $anchorEventTimestamp = (float) $eventTimestamps[0];
            }
            if (null === $anchorTimestamp || null === $anchorEventTimestamp) {
                continue;
            }
            if (count($eventTimestamps) !== count($bpms)) {
                continue;
            }

            foreach ($eventTimestamps as $index => $eventTimestamp) {
                $bpm = $bpms[$index];
                if (!is_numeric($eventTimestamp) || !is_numeric($bpm)) {
                    continue;
                }
                $bpm = (int) round((float) $bpm);
                if ($bpm <= 0 || $bpm >= self::INVALID_BPM) {
                    continue;
                }

                $eventTimestamp = (float) $eventTimestamp;
                if ($eventTimestamp < $anchorEventTimestamp) {
                    $eventTimestamp += self::EVENT_TIMESTAMP_ROLLOVER;
                }

                $timestamp = $anchorTimestamp + ($eventTimestamp - $anchorEventTimestamp);
                $samples = [...$samples, ...self::gapFillTo($samples, $timestamp)];
                $samples[] = [$timestamp, $bpm];
            }
        }

        return $samples;
    }

    /**
     * @param list<array{float, int}> $samples
     *
     * @return list<array{float, int}>
     */
    private static function gapFillTo(array $samples, float $timestamp): array
    {
        if ([] === $samples) {
            return [];
        }

        [$previousTimestamp, $previousBpm] = array_last($samples);
        $gap = $timestamp - $previousTimestamp;

        $filled = [];
        for ($step = 1; $gap > self::GAP_FILL_STEP_IN_SECONDS && $step <= self::MAX_GAP_FILL_STEPS; ++$step) {
            $filled[] = [$previousTimestamp + $step * self::GAP_FILL_STEP_IN_SECONDS, $previousBpm];
            $gap -= self::GAP_FILL_STEP_IN_SECONDS;
        }

        return $filled;
    }
}
