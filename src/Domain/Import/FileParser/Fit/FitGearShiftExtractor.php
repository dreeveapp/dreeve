<?php

declare(strict_types=1);

namespace App\Domain\Import\FileParser\Fit;

use App\Domain\Activity\Shifting\GearShift;
use App\Domain\Activity\Shifting\GearShifts;

final readonly class FitGearShiftExtractor
{
    private const int FIT_EVENT_FRONT_GEAR_CHANGE = 42;
    private const int FIT_EVENT_REAR_GEAR_CHANGE = 43;

    /**
     * @param iterable<array<string, mixed>> $messages
     */
    public static function extract(iterable $messages): GearShifts
    {
        $sessionStartTimestamp = null;
        $firstRecordTimestamp = null;
        /** @var list<array{timestamp: int, fields: array<string, mixed>}> $gearChangeEvents */
        $gearChangeEvents = [];

        foreach ($messages as $message) {
            $fields = self::fieldMap($message['fields'] ?? []);
            $timestamp = self::toInteger($fields['timestamp'] ?? null);

            switch ($message['name'] ?? null) {
                case 'session':
                    $sessionStartTimestamp ??= self::toInteger($fields['start_time'] ?? null);
                    break;
                case 'record':
                    $firstRecordTimestamp ??= $timestamp;
                    break;
                case 'event':
                    $event = self::toInteger($fields['event'] ?? null);
                    if (self::FIT_EVENT_FRONT_GEAR_CHANGE !== $event && self::FIT_EVENT_REAR_GEAR_CHANGE !== $event) {
                        break;
                    }
                    if (null === $timestamp) {
                        break;
                    }
                    $gearChangeEvents[] = ['timestamp' => $timestamp, 'fields' => $fields];
                    break;
            }
        }

        // Must match how FitFileParser derives the start of the time stream, otherwise the
        // shift offsets no longer line up with the samples they need to be joined to.
        $startTimestamp = $sessionStartTimestamp ?? $firstRecordTimestamp;
        if (null === $startTimestamp || [] === $gearChangeEvents) {
            return GearShifts::empty();
        }

        usort($gearChangeEvents, static fn (array $a, array $b): int => $a['timestamp'] <=> $b['timestamp']);

        return self::fillGearGaps(GearShifts::fromArray(array_map(
            static fn (array $event): GearShift => GearShift::create(
                timeOffsetInSeconds: $event['timestamp'] - $startTimestamp,
                frontGearNumber: self::toInteger($event['fields']['front_gear_num'] ?? null),
                frontGearTeeth: self::toInteger($event['fields']['front_gear'] ?? null),
                rearGearNumber: self::toInteger($event['fields']['rear_gear_num'] ?? null),
                rearGearTeeth: self::toInteger($event['fields']['rear_gear'] ?? null),
            ),
            $gearChangeEvents
        )));
    }

    /**
     * A derailleur that has not reported yet leaves its gear unset, which the decoder emits as
     * null. Carry the closest known chainring into those events so every shift knows both ends
     * of the drivetrain.
     */
    private static function fillGearGaps(GearShifts $gearShifts): GearShifts
    {
        $shifts = $gearShifts->toArray();

        $previousNumber = null;
        $previousTeeth = null;
        foreach ($shifts as $i => $shift) {
            if (null === $shift->getFrontGearTeeth()) {
                $shifts[$i] = $shift->withFrontGear($previousNumber, $previousTeeth);
                continue;
            }
            if (null === $previousTeeth) {
                for ($j = 0; $j < $i; ++$j) {
                    $shifts[$j] = $shifts[$j]->withFrontGear($shift->getFrontGearNumber(), $shift->getFrontGearTeeth());
                }
            }
            $previousNumber = $shift->getFrontGearNumber();
            $previousTeeth = $shift->getFrontGearTeeth();
        }

        return GearShifts::fromArray($shifts);
    }

    /**
     * @return array<string, mixed>
     */
    private static function fieldMap(mixed $fields): array
    {
        if (!is_array($fields)) {
            return [];
        }

        $map = [];
        foreach ($fields as $field) {
            if (!is_array($field) || !is_string($field['name'] ?? null)) {
                continue;
            }
            $map[$field['name']] = $field['value'] ?? null;
        }

        return $map;
    }

    private static function toInteger(mixed $value): ?int
    {
        return is_numeric($value) ? (int) round((float) $value) : null;
    }
}
