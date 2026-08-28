<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser\Fit;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\Shifting\ActivityDrivetrainUsage;
use App\Domain\Activity\Shifting\ActivityDrivetrainUsages;
use App\Domain\Activity\Shifting\DrivetrainPosition;

/**
 * @phpstan-type GearChange array{timeOffset: int, front: array{0: ?int, 1: ?int}, rear: array{0: ?int, 1: ?int}}
 */
final readonly class FitDrivetrainUsageExtractor
{
    private const int FIT_EVENT_FRONT_GEAR_CHANGE = 42;
    private const int FIT_EVENT_REAR_GEAR_CHANGE = 43;

    /**
     * @param list<array<string, mixed>> $eventMessages
     * @param array<int, mixed>          $timeStream
     */
    public static function extract(
        array $eventMessages,
        int $startTimestamp,
        array $timeStream,
        ActivityId $activityId,
    ): ActivityDrivetrainUsages {
        if ([] === $gearChanges = self::gearChanges($eventMessages, $startTimestamp)) {
            return ActivityDrivetrainUsages::empty();
        }

        $gears = self::countShifts($gearChanges);
        self::attributeRecordedTime($gears, $gearChanges, $timeStream);

        $drivetrainUsages = ActivityDrivetrainUsages::empty();
        foreach ($gears as $position => $perGearNumber) {
            ksort($perGearNumber);
            foreach ($perGearNumber as $gearNumber => $gear) {
                $drivetrainUsages->add(ActivityDrivetrainUsage::create(
                    activityId: $activityId,
                    position: DrivetrainPosition::from($position),
                    gearNumber: $gearNumber,
                    teeth: $gear['teeth'],
                    timeInSeconds: $gear['timeInSeconds'],
                    shiftCount: $gear['shiftCount'],
                ));
            }
        }

        return $drivetrainUsages;
    }

    /**
     * @param list<array<string, mixed>> $eventMessages
     *
     * @return list<GearChange>
     */
    private static function gearChanges(array $eventMessages, int $startTimestamp): array
    {
        $gearChanges = [];
        foreach ($eventMessages as $fields) {
            $event = self::toInteger($fields['event'] ?? null);
            if (self::FIT_EVENT_FRONT_GEAR_CHANGE !== $event && self::FIT_EVENT_REAR_GEAR_CHANGE !== $event) {
                continue;
            }
            if (null === $timestamp = self::toInteger($fields['timestamp'] ?? null)) {
                continue;
            }

            $gearChanges[] = [
                'timeOffset' => $timestamp - $startTimestamp,
                'front' => [self::toInteger($fields['front_gear_num'] ?? null), self::toInteger($fields['front_gear'] ?? null)],
                'rear' => [self::toInteger($fields['rear_gear_num'] ?? null), self::toInteger($fields['rear_gear'] ?? null)],
            ];
        }

        usort($gearChanges, static fn (array $a, array $b): int => $a['timeOffset'] <=> $b['timeOffset']);

        return self::fillFrontGearGaps($gearChanges);
    }

    /**
     * @param list<GearChange> $gearChanges
     *
     * @return list<GearChange>
     */
    private static function fillFrontGearGaps(array $gearChanges): array
    {
        $chainring = null;
        foreach ($gearChanges as $gearChange) {
            if (null !== $gearChange['front'][1]) {
                $chainring = $gearChange['front'];
                break;
            }
        }

        $filled = [];
        foreach ($gearChanges as $gearChange) {
            if (null !== $gearChange['front'][1]) {
                $chainring = $gearChange['front'];
            }

            $filled[] = [
                'timeOffset' => $gearChange['timeOffset'],
                'front' => $chainring ?? [null, null],
                'rear' => $gearChange['rear'],
            ];
        }

        return $filled;
    }

    /**
     * @param list<GearChange> $gearChanges
     *
     * @return array<string, array<int, array{teeth: int, timeInSeconds: int, shiftCount: int}>>
     */
    private static function countShifts(array $gearChanges): array
    {
        $gears = [];
        foreach ([DrivetrainPosition::FRONT, DrivetrainPosition::REAR] as $position) {
            $previousGearNumber = null;
            foreach ($gearChanges as $gearChange) {
                [$gearNumber, $teeth] = $gearChange[$position->value];
                if (null === $gearNumber || null === $teeth) {
                    continue;
                }

                $gears[$position->value][$gearNumber] ??= ['teeth' => $teeth, 'timeInSeconds' => 0, 'shiftCount' => 0];
                if (null !== $previousGearNumber && $gearNumber !== $previousGearNumber) {
                    ++$gears[$position->value][$gearNumber]['shiftCount'];
                }
                $previousGearNumber = $gearNumber;
            }
        }

        return $gears;
    }

    /**
     * @param array<string, array<int, array{teeth: int, timeInSeconds: int, shiftCount: int}>> $gears
     * @param list<GearChange>                                                                  $gearChanges
     * @param array<int, mixed>                                                                 $timeStream
     */
    private static function attributeRecordedTime(array &$gears, array $gearChanges, array $timeStream): void
    {
        $timeOffsets = array_values(array_filter($timeStream, is_int(...)));
        sort($timeOffsets);

        $index = 0;
        foreach ($timeOffsets as $timeOffset) {
            while (isset($gearChanges[$index + 1]) && $gearChanges[$index + 1]['timeOffset'] <= $timeOffset) {
                ++$index;
            }
            if ($gearChanges[$index]['timeOffset'] > $timeOffset) {
                continue;
            }

            foreach ([DrivetrainPosition::FRONT, DrivetrainPosition::REAR] as $position) {
                [$gearNumber] = $gearChanges[$index][$position->value];
                if (null === $gearNumber || !isset($gears[$position->value][$gearNumber])) {
                    continue;
                }
                ++$gears[$position->value][$gearNumber]['timeInSeconds'];
            }
        }
    }

    private static function toInteger(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }
}
