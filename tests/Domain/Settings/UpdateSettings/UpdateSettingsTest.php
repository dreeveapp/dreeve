<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateSettings;

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
                            'showInNavBar' => true,
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
    }
}
