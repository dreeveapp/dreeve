<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateSettings;

use App\Domain\Settings\UpdateSettings\UpdateSettings;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\TestCase;

class UpdateSettingsTest extends TestCase
{
    public function testItThrowsWhenGroupIsMissing(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('A valid "group" is required.');

        UpdateSettings::fromPayload(['data' => []]);
    }

    public function testItThrowsWhenGroupIsUnknown(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('A valid "group" is required.');

        UpdateSettings::fromPayload(['group' => 'does-not-exist', 'data' => []]);
    }

    public function testItThrowsWhenDataIsNotAnArray(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('"data" must be an object.');

        UpdateSettings::fromPayload(['group' => 'general', 'data' => 'not-an-array']);
    }

    public function testItThrowsWhenGeneralDataIsInvalid(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('A "birthday" is required for the athlete in the general settings');

        UpdateSettings::fromPayload([
            'group' => 'general',
            'data' => ['athlete' => ['firstName' => 'Jane']],
        ]);
    }

    public function testItThrowsWhenAppearanceDataIsInvalid(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);

        UpdateSettings::fromPayload([
            'group' => 'appearance',
            'data' => ['dateFormat' => ['short' => 'q', 'normal' => 'q']],
        ]);
    }

    public function testItThrowsWhenImportDataIsInvalid(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);

        // A webhook that is enabled but has no verify token is invalid.
        UpdateSettings::fromPayload([
            'group' => 'import',
            'data' => ['webhooks' => ['enabled' => true]],
        ]);
    }

    public function testItThrowsWhenMetricsDataIsInvalid(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);

        // An unknown sport type in the Eddington configuration is invalid.
        UpdateSettings::fromPayload([
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
        ]);
    }

    public function testItThrowsWhenZwiftDataIsInvalid(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);

        // A racing score above 1000 is invalid.
        UpdateSettings::fromPayload([
            'group' => 'zwift',
            'data' => ['racingScore' => 1001],
        ]);
    }
}
