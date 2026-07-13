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
        $this->expectExceptionObject(new CouldNotDeserializeCommand('A valid "group" is required.'));

        UpdateSettings::fromPayload(['data' => []]);
    }

    public function testItThrowsWhenGroupIsUnknown(): void
    {
        $this->expectExceptionObject(new CouldNotDeserializeCommand('A valid "group" is required.'));

        UpdateSettings::fromPayload(['group' => 'does-not-exist', 'data' => []]);
    }

    public function testItThrowsWhenDataIsNotAnArray(): void
    {
        $this->expectExceptionObject(new CouldNotDeserializeCommand('"data" must be an object.'));

        UpdateSettings::fromPayload(['group' => 'general', 'data' => 'not-an-array']);
    }

    public function testItThrowsWhenGeneralDataIsInvalid(): void
    {
        $this->expectExceptionObject(new CouldNotDeserializeCommand('A "birthday" is required for the athlete in the general settings'));

        UpdateSettings::fromPayload([
            'group' => 'general',
            'data' => ['athlete' => ['firstName' => 'Jane']],
        ]);
    }

    public function testItThrowsWhenAppearanceDataIsInvalid(): void
    {
        $this->expectExceptionObject(new CouldNotDeserializeCommand('Invalid date format provided "q", invalid format characters found: q'));

        UpdateSettings::fromPayload([
            'group' => 'appearance',
            'data' => ['dateFormat' => ['short' => 'q', 'normal' => 'q']],
        ]);
    }

    public function testItThrowsWhenImportDataIsInvalid(): void
    {
        $this->expectExceptionObject(new CouldNotDeserializeCommand('"verifyToken" property cannot be empty.'));

        // A webhook that is enabled but has no verify token is invalid.
        UpdateSettings::fromPayload([
            'group' => 'import',
            'data' => ['webhooks' => ['enabled' => true]],
        ]);
    }

    public function testItThrowsWhenMetricsDataIsInvalid(): void
    {
        $this->expectExceptionObject(new CouldNotDeserializeCommand('"NotASportType" is not a valid sport type'));

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
        $this->expectExceptionObject(new CouldNotDeserializeCommand('ZwiftRacingScore must be a number between 0 and 1000'));

        // A racing score above 1000 is invalid.
        UpdateSettings::fromPayload([
            'group' => 'zwift',
            'data' => ['racingScore' => 1001],
        ]);
    }
}
