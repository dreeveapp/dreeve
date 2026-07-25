<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings\UpdateAthleteSettings;

use App\Domain\Settings\UpdateAthleteSettings\UpdateAthleteSettings;
use App\Infrastructure\CQRS\Command\Deserialize\CouldNotDeserializeCommand;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateAthleteSettingsTest extends TestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testItThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(CouldNotDeserializeCommand::invalidPayload($expectedExceptionMessage));

        UpdateAthleteSettings::fromPayload($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'athlete is missing' => [
            [],
            '"athlete" must be an object.',
        ];

        yield 'athlete is not an array' => [
            ['athlete' => 'not-an-array'],
            '"athlete" must be an object.',
        ];

        yield 'birthday is missing' => [
            ['athlete' => ['firstName' => 'Jane']],
            'A "birthday" is required for the athlete in the general settings',
        ];

        yield 'maxHeartRateFormula is missing' => [
            ['athlete' => ['birthday' => '1990-01-01']],
            'A "maxHeartRateFormula" is required for the athlete in the general settings',
        ];

        yield 'fixed resting heart rate is missing' => [
            ['athlete' => [
                'birthday' => '1990-01-01',
                'maxHeartRateFormula' => 'fox',
                'restingHeartRateFormula' => 'fixed',
                'restingHeartRateFormulaFixedValue' => '',
            ]],
            'The resting heart rate formula needs a heart rate greater than zero',
        ];
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

    public function testItNormalizesTheHeartRateFormulas(): void
    {
        $command = UpdateAthleteSettings::fromPayload(['athlete' => [
            'birthday' => '1990-01-01',
            'maxHeartRateFormula' => 'dateRangeBased',
            'maxHeartRateFormulaRanges' => [['on' => '2023-01-01', 'bpm' => '180']],
            'restingHeartRateFormula' => 'fixed',
            'restingHeartRateFormulaFixedValue' => '58',
        ]]);

        $this->assertSame([
            'birthday' => '1990-01-01',
            'maxHeartRateFormula' => ['2023-01-01' => 180],
            'restingHeartRateFormula' => 58,
        ], $command->getAthlete());
    }
}
