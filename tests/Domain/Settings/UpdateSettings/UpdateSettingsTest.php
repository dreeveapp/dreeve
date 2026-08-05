<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateSettings;

use App\Domain\Settings\SettingsGroup;
use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateSettingsTest extends TestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testItThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(new CouldNotDeserializeCommand($expectedExceptionMessage));

        UpdateSettings::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'group is missing' => [
            ['data' => []],
            'A valid "group" is required.',
        ];

        yield 'group is unknown' => [
            ['group' => 'does-not-exist', 'data' => []],
            'A valid "group" is required.',
        ];

        yield 'data is not an array' => [
            ['group' => 'general', 'data' => 'not-an-array'],
            '"data" must be an object.',
        ];

        yield 'general data without a birthday' => [
            [
                'group' => 'general',
                'data' => ['athlete' => ['firstName' => 'Jane']],
            ],
            'A "birthday" is required for the athlete in the general settings',
        ];

        yield 'appearance data with an invalid date format' => [
            [
                'group' => 'appearance',
                'data' => ['dateFormat' => ['short' => 'q', 'normal' => 'q']],
            ],
            'Invalid date format provided "q", invalid format characters found: q',
        ];

        yield 'import data with an enabled webhook without verify token' => [
            [
                'group' => 'import',
                'data' => ['webhooks' => ['enabled' => true]],
            ],
            '"verifyToken" property cannot be empty.',
        ];

        yield 'metrics data with an unknown sport type in the Eddington configuration' => [
            [
                'group' => 'metrics',
                'data' => [
                    'eddington' => [
                        [
                            'label' => 'Ride',
                            'sportTypesToInclude' => ['NotASportType'],
                        ],
                    ],
                ],
            ],
            '"NotASportType" is not a valid sport type',
        ];

        yield 'zwift data with a racing score above 1000' => [
            [
                'group' => 'zwift',
                'data' => ['racingScore' => 1001],
            ],
            'ZwiftRacingScore must be a number between 0 and 1000',
        ];

        yield 'maps data with an invalid polyline color' => [
            [
                'group' => 'maps',
                'data' => ['polylineColor' => 'notacolour'],
            ],
            'notacolour is not a valid CSS color',
        ];

        yield 'maps data with an invalid tile layer url' => [
            [
                'group' => 'maps',
                'data' => ['tileLayerUrl' => ['not-a-url']],
            ],
            'Invalid url "not-a-url"',
        ];

        yield 'maps data with a heatmap zoom level out of range' => [
            [
                'group' => 'maps',
                'data' => ['heatmap' => ['initialZoom' => '19']],
            ],
            'ZoomLevel must be a number between 1 and 18, got 19',
        ];

        yield 'maps data with a tile layer url missing placeholders' => [
            [
                'group' => 'maps',
                'data' => ['tileLayerUrl' => ['https://example.com/tiles.png']],
            ],
            'Invalid tile layer url "https://example.com/tiles.png", it must contain the placeholders {z}, {x} and {y}',
        ];

        yield 'maps data with a heatmap latitude out of range' => [
            [
                'group' => 'maps',
                'data' => ['heatmap' => ['initialCenter' => ['200.0', '3.7'], 'initialZoom' => '12']],
            ],
            'Invalid latitude value: 200',
        ];
    }

    public function testItKeepsTheSubmittedMapsPayloadVerbatim(): void
    {
        $data = [
            'polylineColor' => 'red',
            'tileLayerUrl' => ['https://tile.example.com/{z}/{x}/{y}.png'],
            'enableGreyScale' => '0',
            'heatmap' => [
                'initialCenter' => ['51.05', '3.72'],
                'initialZoom' => '14',
            ],
        ];

        $command = UpdateSettings::fromPayload(['group' => 'maps', 'data' => $data]);

        $this->assertSame(SettingsGroup::MAPS, $command->getGroup());
        $this->assertSame($data, $command->getData());
    }
}
