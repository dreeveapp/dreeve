<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Athlete\InvalidHeartRateFormula;
use App\Domain\Settings\AthleteSettingsPayload;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AthleteSettingsPayloadTest extends TestCase
{
    public function testItLeavesCanonicalValuesUntouched(): void
    {
        $athlete = [
            'birthday' => '1990-01-01',
            'maxHeartRateFormula' => 'fox',
            'restingHeartRateFormula' => 'heuristicAgeBased',
        ];

        $this->assertSame($athlete, AthleteSettingsPayload::normalize($athlete));
    }

    public function testItLeavesCanonicalDateRangesAndFixedValuesUntouched(): void
    {
        $athlete = [
            'maxHeartRateFormula' => ['2023-01-01' => 180],
            'restingHeartRateFormula' => 58,
        ];

        $this->assertSame($athlete, AthleteSettingsPayload::normalize($athlete));
    }

    public function testItDoesNotAddKeysThatWereNotSubmitted(): void
    {
        $this->assertSame(
            ['birthday' => '1990-01-01'],
            AthleteSettingsPayload::normalize(['birthday' => '1990-01-01'])
        );
    }

    public function testItBuildsAFixedRestingHeartRate(): void
    {
        $this->assertSame(
            ['restingHeartRateFormula' => 58],
            AthleteSettingsPayload::normalize([
                'restingHeartRateFormula' => 'fixed',
                'restingHeartRateFormulaFixedValue' => '58',
            ])
        );
    }

    public function testItBuildsDateRanges(): void
    {
        $this->assertSame(
            [
                'maxHeartRateFormula' => ['2023-01-01' => 180, '2024-06-01' => 178],
                'restingHeartRateFormula' => ['2023-01-01' => 58],
            ],
            AthleteSettingsPayload::normalize([
                'maxHeartRateFormula' => 'dateRangeBased',
                'maxHeartRateFormulaRanges' => [
                    ['on' => '2023-01-01', 'bpm' => '180'],
                    ['on' => '2024-06-01', 'bpm' => '178'],
                ],
                'restingHeartRateFormula' => 'dateRangeBased',
                'restingHeartRateFormulaRanges' => [
                    ['on' => '2023-01-01', 'bpm' => '58'],
                ],
            ])
        );
    }

    public function testItDropsTheFieldsOfTheFormulasThatWereNotSelected(): void
    {
        $this->assertSame(
            [
                'maxHeartRateFormula' => 'fox',
                'restingHeartRateFormula' => 'heuristicAgeBased',
            ],
            AthleteSettingsPayload::normalize([
                'maxHeartRateFormula' => 'fox',
                'maxHeartRateFormulaRanges' => [['on' => '2023-01-01', 'bpm' => '180']],
                'restingHeartRateFormula' => 'heuristicAgeBased',
                'restingHeartRateFormulaFixedValue' => '58',
                'restingHeartRateFormulaRanges' => [['on' => '2023-01-01', 'bpm' => '58']],
            ])
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('provideInvalidPayloads')]
    public function testItThrowsOnInvalidPayload(array $payload, string $expectedExceptionMessage): void
    {
        $this->expectExceptionObject(new InvalidHeartRateFormula($expectedExceptionMessage));

        AthleteSettingsPayload::normalize($payload);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidPayloads(): iterable
    {
        yield 'fixed resting heart rate is empty' => [
            [
                'restingHeartRateFormula' => 'fixed',
                'restingHeartRateFormulaFixedValue' => '',
            ],
            'The resting heart rate formula needs a heart rate greater than zero',
        ];

        yield 'date ranges are not a list' => [
            [
                'maxHeartRateFormula' => 'dateRangeBased',
                'maxHeartRateFormulaRanges' => 'lol',
            ],
            'Invalid date ranges provided for the max heart rate formula',
        ];

        yield 'a date range is not an array' => [
            [
                'restingHeartRateFormula' => 'dateRangeBased',
                'restingHeartRateFormulaRanges' => ['lol'],
            ],
            'Invalid date ranges provided for the resting heart rate formula',
        ];

        yield 'a date range has no date' => [
            [
                'maxHeartRateFormula' => 'dateRangeBased',
                'maxHeartRateFormulaRanges' => [['on' => '', 'bpm' => '180']],
            ],
            'Every date range of the max heart rate formula needs a date',
        ];

        yield 'a date range has no heart rate' => [
            [
                'maxHeartRateFormula' => 'dateRangeBased',
                'maxHeartRateFormulaRanges' => [['on' => '2023-01-01', 'bpm' => '']],
            ],
            'The max heart rate formula needs a heart rate greater than zero',
        ];

        yield 'a date is used twice' => [
            [
                'restingHeartRateFormula' => 'dateRangeBased',
                'restingHeartRateFormulaRanges' => [
                    ['on' => '2023-01-01', 'bpm' => '58'],
                    ['on' => '2023-01-01', 'bpm' => '60'],
                ],
            ],
            'The resting heart rate formula cannot contain the same date more than once',
        ];
    }
}
