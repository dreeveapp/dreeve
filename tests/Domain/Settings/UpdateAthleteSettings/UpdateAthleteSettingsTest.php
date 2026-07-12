<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateAthleteSettings;

use App\Domain\Settings\UpdateAthleteSettings\UpdateAthleteSettings;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\TestCase;

class UpdateAthleteSettingsTest extends TestCase
{
    public function testItThrowsWhenAthleteIsMissing(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('"athlete" must be an object.');

        UpdateAthleteSettings::fromPayload([]);
    }

    public function testItThrowsWhenAthleteIsNotAnArray(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('"athlete" must be an object.');

        UpdateAthleteSettings::fromPayload(['athlete' => 'not-an-array']);
    }

    public function testItThrowsWhenBirthdayIsMissing(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('A "birthday" is required for the athlete in the general settings');

        UpdateAthleteSettings::fromPayload([
            'athlete' => ['firstName' => 'Jane'],
        ]);
    }

    public function testItThrowsWhenMaxHeartRateFormulaIsMissing(): void
    {
        $this->expectException(CouldNotDeserializeCommand::class);
        $this->expectExceptionMessage('A "maxHeartRateFormula" is required for the athlete in the general settings');

        UpdateAthleteSettings::fromPayload([
            'athlete' => ['birthday' => '1990-01-01'],
        ]);
    }

    public function testItDeserializes(): void
    {
        $athlete = [
            'birthday' => '1990-01-01',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'maxHeartRateFormula' => 'fox',
        ];

        $command = UpdateAthleteSettings::fromPayload(['athlete' => $athlete]);

        $this->assertSame($athlete, $command->getAthlete());
    }
}
