<?php

declare(strict_types=1);

namespace App\Tests\Domain\Settings;

use App\Domain\Athlete\InvalidHeartRateFormula;
use App\Domain\Settings\AthleteSettingsPayload;
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

    public function testItThrowsWhenTheFixedRestingHeartRateIsEmpty(): void
    {
        $this->expectExceptionObject(new InvalidHeartRateFormula('The resting heart rate formula needs a heart rate greater than zero'));

        AthleteSettingsPayload::normalize([
            'restingHeartRateFormula' => 'fixed',
            'restingHeartRateFormulaFixedValue' => '',
        ]);
    }

    public function testItThrowsWhenTheDateRangesAreNotAList(): void
    {
        $this->expectExceptionObject(new InvalidHeartRateFormula('Invalid date ranges provided for the max heart rate formula'));

        AthleteSettingsPayload::normalize([
            'maxHeartRateFormula' => 'dateRangeBased',
            'maxHeartRateFormulaRanges' => 'lol',
        ]);
    }

    public function testItThrowsWhenADateRangeIsNotAnArray(): void
    {
        $this->expectExceptionObject(new InvalidHeartRateFormula('Invalid date ranges provided for the resting heart rate formula'));

        AthleteSettingsPayload::normalize([
            'restingHeartRateFormula' => 'dateRangeBased',
            'restingHeartRateFormulaRanges' => ['lol'],
        ]);
    }

    public function testItThrowsWhenADateRangeHasNoDate(): void
    {
        $this->expectExceptionObject(new InvalidHeartRateFormula('Every date range of the max heart rate formula needs a date'));

        AthleteSettingsPayload::normalize([
            'maxHeartRateFormula' => 'dateRangeBased',
            'maxHeartRateFormulaRanges' => [['on' => '', 'bpm' => '180']],
        ]);
    }

    public function testItThrowsWhenADateRangeHasNoHeartRate(): void
    {
        $this->expectExceptionObject(new InvalidHeartRateFormula('The max heart rate formula needs a heart rate greater than zero'));

        AthleteSettingsPayload::normalize([
            'maxHeartRateFormula' => 'dateRangeBased',
            'maxHeartRateFormulaRanges' => [['on' => '2023-01-01', 'bpm' => '']],
        ]);
    }

    public function testItThrowsWhenADateIsUsedTwice(): void
    {
        $this->expectExceptionObject(new InvalidHeartRateFormula('The resting heart rate formula cannot contain the same date more than once'));

        AthleteSettingsPayload::normalize([
            'restingHeartRateFormula' => 'dateRangeBased',
            'restingHeartRateFormulaRanges' => [
                ['on' => '2023-01-01', 'bpm' => '58'],
                ['on' => '2023-01-01', 'bpm' => '60'],
            ],
        ]);
    }
}
