<?php

namespace App\Tests\Domain\Athlete\HeartRateZone;

use App\Domain\Activity\SportType\SportType;
use App\Domain\Athlete\HeartRateZone\HeartRateZoneConfiguration;
use App\Domain\Athlete\HeartRateZone\HeartRateZoneMode;
use App\Domain\Athlete\HeartRateZone\HeartRateZones;
use App\Domain\Athlete\HeartRateZone\InvalidHeartZoneConfiguration;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HeartRateZoneConfigurationTest extends TestCase
{
    /**
     * A valid flat config with a top-level date-range override and a per-sport-type override (with its
     * own default and date range), so every branch of getHeartRateZonesFor() is covered. Only zone4's
     * "to" differs between the levels so the resolved zone is easy to assert.
     */
    private const string ADVANCED = <<<YML
        dateRanges:
          "2025-01-01":
            zone1: {from: 50, to: 60}
            zone2: {from: 61, to: 70}
            zone3: {from: 71, to: 80}
            zone4: {from: 81, to: 85}
            zone5: {from: 86, to: null}
        sportTypes:
          GravelRide:
            default:
              zone1: {from: 50, to: 60}
              zone2: {from: 61, to: 70}
              zone3: {from: 71, to: 80}
              zone4: {from: 81, to: 83}
              zone5: {from: 84, to: null}
            dateRanges:
              "2025-01-01":
                zone1: {from: 50, to: 60}
                zone2: {from: 61, to: 70}
                zone3: {from: 71, to: 80}
                zone4: {from: 81, to: 82}
                zone5: {from: 83, to: null}
        YML;

    #[DataProvider(methodName: 'provideValidConfig')]
    public function testGetHeartRateZonesFor(SportType $sportType, SerializableDateTime $on, int $expectedZone4To): void
    {
        $config = HeartRateZoneConfiguration::fromArray([
            'mode' => 'relative',
            'zones' => [
                ['from' => 50, 'to' => 60],
                ['from' => 61, 'to' => 70],
                ['from' => 71, 'to' => 80],
                ['from' => 81, 'to' => 90],
                ['from' => 91, 'to' => null],
            ],
            'advanced' => self::ADVANCED,
        ]);

        $this->assertEquals(
            HeartRateZones::fromScalarValues(HeartRateZoneMode::RELATIVE, [
                'zone1' => ['from' => 50, 'to' => 60],
                'zone2' => ['from' => 61, 'to' => 70],
                'zone3' => ['from' => 71, 'to' => 80],
                'zone4' => ['from' => 81, 'to' => $expectedZone4To],
                'zone5' => ['from' => $expectedZone4To + 1, 'to' => null],
            ]),
            $config->getHeartRateZonesFor($sportType, $on),
        );
    }

    public static function provideValidConfig(): iterable
    {
        yield 'sport type + matching date' => [SportType::GRAVEL_RIDE, SerializableDateTime::fromString('2025-06-01'), 82];
        yield 'sport type, no matching date' => [SportType::GRAVEL_RIDE, SerializableDateTime::fromString('2020-01-01'), 83];
        yield 'no sport type override, matching date' => [SportType::WALK, SerializableDateTime::fromString('2025-06-01'), 85];
        yield 'no sport type override, no matching date' => [SportType::WALK, SerializableDateTime::fromString('2020-01-01'), 90];
    }

    public function testFromArrayWhenEmptyUsesDefaults(): void
    {
        $this->assertEquals(
            HeartRateZoneConfiguration::fromArray([
                'mode' => 'relative',
                'zones' => [
                    ['from' => 50, 'to' => 60],
                    ['from' => 61, 'to' => 70],
                    ['from' => 71, 'to' => 80],
                    ['from' => 81, 'to' => 90],
                    ['from' => 91, 'to' => null],
                ],
            ]),
            HeartRateZoneConfiguration::fromArray([])
        );
    }

    public function testItCoercesStringZoneValuesFromTheForm(): void
    {
        $this->assertEquals(
            HeartRateZoneConfiguration::fromArray([]),
            HeartRateZoneConfiguration::fromArray([
                'mode' => 'relative',
                'zones' => [
                    ['from' => '50', 'to' => '60'],
                    ['from' => '61', 'to' => '70'],
                    ['from' => '71', 'to' => '80'],
                    ['from' => '81', 'to' => '90'],
                    ['from' => '91', 'to' => ''],
                ],
            ]),
        );
    }

    #[DataProvider(methodName: 'provideInvalidConfig')]
    public function testFromArrayItShouldThrow(array $config, string $expectedException): void
    {
        $this->expectExceptionObject(new InvalidHeartZoneConfiguration($expectedException));
        HeartRateZoneConfiguration::fromArray($config);
    }

    public static function provideInvalidConfig(): iterable
    {
        $validZones = [
            ['from' => 50, 'to' => 60],
            ['from' => 61, 'to' => 70],
            ['from' => 71, 'to' => 80],
            ['from' => 81, 'to' => 90],
            ['from' => 91, 'to' => null],
        ];

        yield 'invalid mode' => [['mode' => 'lol', 'zones' => $validZones], '"lol" is not a valid mode'];

        yield 'zone5 "to" not null' => [
            ['mode' => 'relative', 'zones' => [...\array_slice($validZones, 0, 4), ['from' => 91, 'to' => 99]]],
            'zone5 "to" value needs to be null, got 99',
        ];

        yield 'invalid "from"' => [
            ['mode' => 'relative', 'zones' => [['from' => 'lol', 'to' => 60], ...\array_slice($validZones, 1)]],
            'zone1 "from" value needs to a positive integer, got lol',
        ];

        yield 'gap between zones' => [
            ['mode' => 'relative', 'zones' => [['from' => 50, 'to' => 60], ['from' => 70, 'to' => 80], ...\array_slice($validZones, 2)]],
            'Gap detected before zone2: expected "from" to be 61, got 70',
        ];

        yield 'invalid advanced dateRanges' => [
            ['mode' => 'relative', 'zones' => $validZones, 'advanced' => 'dateRanges: lol'],
            '"dateRanges" property must be an array',
        ];

        yield 'invalid advanced sportType' => [
            ['mode' => 'relative', 'zones' => $validZones, 'advanced' => "sportTypes:\n  NotASport:\n    default: {}"],
            '"NotASport" is not a valid sport type',
        ];

        yield 'negative "from"' => [
            ['mode' => 'relative', 'zones' => [['from' => -1, 'to' => 60], ...\array_slice($validZones, 1)]],
            'zone1 "from" value needs to a positive integer, got -1',
        ];

        yield 'invalid "to"' => [
            ['mode' => 'relative', 'zones' => [['from' => 50, 'to' => 'lol'], ...\array_slice($validZones, 1)]],
            'zone1 "to" value needs to a valid integer, got lol',
        ];

        yield 'relative "to" higher than 99' => [
            ['mode' => 'relative', 'zones' => [['from' => 50, 'to' => 100], ...\array_slice($validZones, 1)]],
            'zone1 "to" value cannot be higher than 99, got 100',
        ];

        yield '"from" greater than "to"' => [
            ['mode' => 'relative', 'zones' => [['from' => 60, 'to' => 50], ...\array_slice($validZones, 1)]],
            'zone1 has "from" (60) greater than "to" (50), which is invalid',
        ];

        $validZonesYaml = <<<YML
                zone1: {from: 50, to: 60}
                zone2: {from: 61, to: 70}
                zone3: {from: 71, to: 80}
                zone4: {from: 81, to: 90}
                zone5: {from: 91, to: null}
            YML;

        yield 'advanced sportTypes not an array' => [
            ['mode' => 'relative', 'zones' => $validZones, 'advanced' => 'sportTypes: lol'],
            '"sportTypes" property must be an array',
        ];

        yield 'advanced sportType without default' => [
            ['mode' => 'relative', 'zones' => $validZones, 'advanced' => "sportTypes:\n  Ride:\n    dateRanges: {}"],
            '"default" property is required for sportType Ride',
        ];

        yield 'advanced sportType default not an array' => [
            ['mode' => 'relative', 'zones' => $validZones, 'advanced' => "sportTypes:\n  Ride:\n    default: lol"],
            '"default" property must be an array for sportType Ride',
        ];

        yield 'advanced sportType dateRanges not an array' => [
            [
                'mode' => 'relative',
                'zones' => $validZones,
                'advanced' => "sportTypes:\n  Ride:\n    default:\n"
                    .implode("\n", array_map(static fn (string $line): string => '      '.$line, explode("\n", $validZonesYaml)))
                    ."\n    dateRanges: lol",
            ],
            '"dateRanges" property must be an array for sportType Ride',
        ];

        yield 'advanced dateRanges invalid date' => [
            [
                'mode' => 'relative',
                'zones' => $validZones,
                'advanced' => "dateRanges:\n  \"not-a-date\":\n"
                    .implode("\n", array_map(static fn (string $line): string => '    '.$line, explode("\n", $validZonesYaml))),
            ],
            'Invalid date "not-a-date" set for athlete heartRateZone',
        ];

        yield 'advanced dateRanges missing zone' => [
            [
                'mode' => 'relative',
                'zones' => $validZones,
                'advanced' => "dateRanges:\n  \"2025-01-01\":\n    zone1: {from: 50, to: 60}\n    zone2: {from: 61, to: 70}\n    zone3: {from: 71, to: 80}\n    zone4: {from: 81, to: 90}",
            ],
            '"zone5" property is required for each range of heart zones',
        ];
    }

    public function testFromArrayThrowsOnInvalidAdvancedYaml(): void
    {
        $this->expectExceptionObject(new InvalidHeartZoneConfiguration('Invalid YAML in advanced heart rate zone configuration: Malformed inline YAML string at line 1 (near "{ this is: not valid").'));

        HeartRateZoneConfiguration::fromArray([
            'mode' => 'relative',
            'zones' => [
                ['from' => 50, 'to' => 60],
                ['from' => 61, 'to' => 70],
                ['from' => 71, 'to' => 80],
                ['from' => 81, 'to' => 90],
                ['from' => 91, 'to' => null],
            ],
            'advanced' => '{ this is: not valid',
        ]);
    }
}
