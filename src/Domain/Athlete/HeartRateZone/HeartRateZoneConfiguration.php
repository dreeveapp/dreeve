<?php

declare(strict_types=1);

namespace App\Domain\Athlete\HeartRateZone;

use App\Domain\Activity\SportType\SportType;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class HeartRateZoneConfiguration
{
    private const string DEFAULT_KEY = 'default';
    private const string DATE_RANGES_KEY = 'dateRanges';

    /** @var array<string, mixed> */
    private array $hearRateZones;

    private function __construct(
    ) {
    }

    public function getDefaultHearRateZones(?SportType $sportType): HeartRateZones
    {
        if ($sportType && array_key_exists($sportType->value, $this->hearRateZones)) {
            return $this->hearRateZones[$sportType->value][self::DEFAULT_KEY];
        }

        return $this->hearRateZones['defaults'][self::DEFAULT_KEY];
    }

    public function getHeartRateZonesFor(SportType $sportType, SerializableDateTime $on): HeartRateZones
    {
        $on = SerializableDateTime::fromString($on->format('Y-m-d'));
        if (array_key_exists($sportType->value, $this->hearRateZones)) {
            // There's a heart rate zone set for this sport specifically.
            // Check if we can use a date specific one or if we have to use the default.
            if (!array_key_exists(self::DATE_RANGES_KEY, $this->hearRateZones[$sportType->value])) {
                // No date ranges defined, use default.
                return $this->hearRateZones[$sportType->value][self::DEFAULT_KEY];
            }

            foreach ($this->hearRateZones[$sportType->value][self::DATE_RANGES_KEY] as $timestamp => $heartRateZones) {
                $currentDate = SerializableDateTime::fromTimestamp($timestamp);
                if ($on->isAfterOrOn($currentDate)) {
                    return $heartRateZones;
                }
            }

            // No matching date range found, use default.
            return $this->hearRateZones[$sportType->value][self::DEFAULT_KEY];
        }

        // No heart rate zone set for this sport type.
        // Check if we can use a date specific one or if we have to use the default.
        if (!array_key_exists(self::DATE_RANGES_KEY, $this->hearRateZones['defaults'])) {
            // No date ranges defined, use default.
            return $this->hearRateZones['defaults'][self::DEFAULT_KEY];
        }

        foreach ($this->hearRateZones['defaults'][self::DATE_RANGES_KEY] as $timestamp => $heartRateZones) {
            $currentDate = SerializableDateTime::fromTimestamp($timestamp);
            if ($on->isAfterOrOn($currentDate)) {
                return $heartRateZones;
            }
        }

        // No matching date range found, use default.
        return $this->hearRateZones['defaults'][self::DEFAULT_KEY];
    }

    private function addHeartRateZones(
        HeartRateZones $heartRateZones,
        ?SportType $sportType,
        ?SerializableDateTime $on,
    ): void {
        if ($on instanceof SerializableDateTime) {
            $on = SerializableDateTime::fromString($on->format('Y-m-d'));
        }
        if ($sportType && $on) {
            $this->hearRateZones[$sportType->value][self::DATE_RANGES_KEY][$on->getTimestamp()] = $heartRateZones;
            // Make sure dates are sorted from new => old
            krsort($this->hearRateZones[$sportType->value][self::DATE_RANGES_KEY]);

            return;
        }
        if ($sportType instanceof SportType) {
            $this->hearRateZones[$sportType->value][self::DEFAULT_KEY] = $heartRateZones;

            return;
        }
        if ($on instanceof SerializableDateTime) {
            $this->hearRateZones['defaults'][self::DATE_RANGES_KEY][$on->getTimestamp()] = $heartRateZones;
            // Make sure dates are sorted from new => old
            krsort($this->hearRateZones['defaults'][self::DATE_RANGES_KEY]);

            return;
        }

        $this->hearRateZones['defaults'][self::DEFAULT_KEY] = $heartRateZones;
    }

    /**
     * @return list<array{from: int, to: int|null}>
     */
    public static function getDefaultZones(): array
    {
        return array_values(self::getDefaultConfig()['default']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function getDefaultConfig(): array
    {
        return [
            'mode' => 'relative',
            'default' => [
                'zone1' => [
                    'from' => 50,
                    'to' => 60,
                ],
                'zone2' => [
                    'from' => 61,
                    'to' => 70,
                ],
                'zone3' => [
                    'from' => 71,
                    'to' => 80,
                ],
                'zone4' => [
                    'from' => 81,
                    'to' => 90,
                ],
                'zone5' => [
                    'from' => 91,
                    'to' => null,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $modeValue = $config['mode'] ?? 'relative';
        $zones = is_array($config['zones'] ?? null) ? array_values($config['zones']) : [];
        $advanced = $config['advanced'] ?? null;

        if ([] === $zones) {
            $default = self::getDefaultConfig()['default'];
        } else {
            $default = [];
            foreach (['zone1', 'zone2', 'zone3', 'zone4', 'zone5'] as $index => $name) {
                $zone = is_array($zones[$index] ?? null) ? $zones[$index] : [];
                $from = $zone['from'] ?? null;
                $to = $zone['to'] ?? null;
                $default[$name] = [
                    'from' => is_numeric($from) ? (int) $from : ('' === $from ? null : $from),
                    'to' => is_numeric($to) ? (int) $to : ('' === $to ? null : $to),
                ];
            }
        }

        $config = ['mode' => $modeValue, 'default' => $default];

        if (is_string($advanced) && '' !== trim($advanced)) {
            try {
                $parsed = Yaml::parse($advanced);
            } catch (ParseException $e) {
                throw new InvalidHeartZoneConfiguration(sprintf('Invalid YAML in advanced heart rate zone configuration: %s', $e->getMessage()));
            }

            foreach (['dateRanges', 'sportTypes'] as $key) {
                if (is_array($parsed) && array_key_exists($key, $parsed)) {
                    $config[$key] = $parsed[$key];
                }
            }
        }

        if (!$mode = HeartRateZoneMode::tryFrom($config['mode'])) {
            throw new InvalidHeartZoneConfiguration(sprintf('"%s" is not a valid mode', $config['mode']));
        }

        if (array_key_exists('dateRanges', $config) && !is_array($config['dateRanges'])) {
            throw new InvalidHeartZoneConfiguration('"dateRanges" property must be an array');
        }

        self::guardValidZoneConfig($config, $mode);

        if (array_key_exists('sportTypes', $config) && !is_array($config['sportTypes'])) {
            throw new InvalidHeartZoneConfiguration('"sportTypes" property must be an array');
        }

        foreach ($config['sportTypes'] ?? [] as $sportTypeValue => &$sportTypeConfig) {
            if (!$sportType = SportType::tryFrom($sportTypeValue)) {
                throw new InvalidHeartZoneConfiguration(sprintf('"%s" is not a valid sport type', $sportTypeValue));
            }

            if (!array_key_exists('default', $sportTypeConfig)) {
                throw new InvalidHeartZoneConfiguration(sprintf('"default" property is required for sportType %s', $sportType->value));
            }

            if (!is_array($sportTypeConfig['default'])) {
                throw new InvalidHeartZoneConfiguration(sprintf('"default" property must be an array for sportType %s', $sportType->value));
            }

            if (array_key_exists('dateRanges', $sportTypeConfig) && !is_array($sportTypeConfig['dateRanges'])) {
                throw new InvalidHeartZoneConfiguration(sprintf('"dateRanges" property must be an array for sportType %s', $sportType->value));
            }

            self::guardValidZoneConfig($sportTypeConfig, $mode);
        }

        $heartRateConfig = new self();

        // Now that everything has been validated, add the zones to the config.
        $heartRateConfig->addHeartRateZones(
            heartRateZones: HeartRateZones::fromScalarValues(
                mode: $mode,
                zones: $config['default']
            ),
            sportType: null,
            on: null
        );

        foreach ($config['dateRanges'] ?? [] as $date => $zones) {
            $heartRateConfig->addHeartRateZones(
                heartRateZones: HeartRateZones::fromScalarValues(
                    mode: $mode,
                    zones: $zones
                ),
                sportType: null,
                on: SerializableDateTime::fromString($date)
            );
        }

        foreach ($config['sportTypes'] ?? [] as $sportTypeValue => $sportTypeConfiguration) {
            $sportType = SportType::tryFrom($sportTypeValue);
            $heartRateConfig->addHeartRateZones(
                heartRateZones: HeartRateZones::fromScalarValues(
                    mode: $mode,
                    zones: $sportTypeConfiguration['default']
                ),
                sportType: $sportType,
                on: null
            );

            foreach ($sportTypeConfiguration['dateRanges'] ?? [] as $date => $zones) {
                $heartRateConfig->addHeartRateZones(
                    heartRateZones: HeartRateZones::fromScalarValues(
                        mode: $mode,
                        zones: $zones
                    ),
                    sportType: $sportType,
                    on: SerializableDateTime::fromString($date)
                );
            }
        }

        return $heartRateConfig;
    }

    /**
     * @param non-empty-array<string, mixed> $config
     */
    private static function guardValidZoneConfig(array &$config, HeartRateZoneMode $mode): void
    {
        self::sortZones($config['default']);
        self::guardValidAndSequentialZones($config['default'], $mode);

        if (!array_key_exists('dateRanges', $config)) {
            return;
        }

        foreach ($config['dateRanges'] ?? [] as $date => &$zones) {
            try {
                SerializableDateTime::fromString($date);
                self::sortZones($zones);
                self::guardValidAndSequentialZones($zones, $mode);
            } catch (\DateMalformedStringException) {
                throw new InvalidHeartZoneConfiguration(sprintf('Invalid date "%s" set for athlete heartRateZone', $date));
            }
        }
    }

    /**
     * @param array<string, array{from: int, to: int}> $zones
     */
    private static function sortZones(array &$zones): void
    {
        foreach (['zone1', 'zone2', 'zone3', 'zone4', 'zone5'] as $requiredKey) {
            if (array_key_exists($requiredKey, $zones)) {
                continue;
            }
            throw new InvalidHeartZoneConfiguration(sprintf('"%s" property is required for each range of heart zones', $requiredKey));
        }

        $zones = [
            'zone1' => $zones['zone1'],
            'zone2' => $zones['zone2'],
            'zone3' => $zones['zone3'],
            'zone4' => $zones['zone4'],
            'zone5' => $zones['zone5'],
        ];
    }

    /**
     * @param array<string, array{from: mixed, to: mixed|null}> $zones
     */
    private static function guardValidAndSequentialZones(array $zones, HeartRateZoneMode $mode): void
    {
        if (null !== $zones['zone5']['to']) {
            throw new InvalidHeartZoneConfiguration(sprintf('zone5 "to" value needs to be null, got %s', $zones['zone5']['to']));
        }

        $expectedFrom = null;
        foreach ($zones as $key => $zone) {
            $zoneFrom = $zone['from'];
            $zoneTo = $zone['to'];

            if (!is_int($zoneFrom)) {
                throw new InvalidHeartZoneConfiguration(sprintf('%s "from" value needs to a positive integer, got %s', $key, $zoneFrom));
            }
            if ($zoneFrom < 0) {
                throw new InvalidHeartZoneConfiguration(sprintf('%s "from" value needs to a positive integer, got %s', $key, $zoneFrom));
            }
            if (!is_int($zoneTo) && !is_null($zoneTo)) {
                throw new InvalidHeartZoneConfiguration(sprintf('%s "to" value needs to a valid integer, got %s', $key, $zoneTo));
            }

            if (HeartRateZoneMode::RELATIVE === $mode && $zoneTo > 99) {
                throw new InvalidHeartZoneConfiguration(sprintf('%s "to" value cannot be higher than 99, got %s', $key, $zoneTo));
            }

            // First zone sets the baseline
            if (null === $expectedFrom) {
                $expectedFrom = $zoneFrom;
            }

            // Validate the 'from' matches expected
            if ($zoneFrom !== $expectedFrom) {
                throw new InvalidHeartZoneConfiguration(sprintf('Gap detected before %s: expected "from" to be %s, got %s', $key, $expectedFrom, $zoneFrom));
            }

            // If 'to' is null, we're at the end
            if (null === $zoneTo) {
                return;
            }

            // If 'from' > 'to', it's invalid
            if ($zoneFrom > $zoneTo) {
                throw new InvalidHeartZoneConfiguration(sprintf('%s has "from" (%s) greater than "to" (%s), which is invalid', $key, $zoneFrom, $zoneTo));
            }

            // Set next expected 'from'
            $expectedFrom = $zoneTo + 1;
        }
    }
}
