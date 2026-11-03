<?php

namespace App\Tests\Infrastructure\Time\Format;

use App\Infrastructure\Time\Format\ProvideTimeFormats;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProvideTimeFormatsTest extends TestCase
{
    use ProvideTimeFormats;

    #[DataProvider('provideTimeFormatsForHumans')]
    public function testFormatDurationForHumans(int $timeInSeconds, string $expectedResult): void
    {
        $this->assertEquals(
            $expectedResult,
            $this->formatDurationAsClock($timeInSeconds)
        );
    }

    #[DataProvider('provideTimeFormatsForHumansForChartLabel')]
    public function testFormatDurationForChartLabel(int $timeInSeconds, string $expectedResult): void
    {
        $this->assertEquals(
            $expectedResult,
            $this->formatDurationAsPaddedClock($timeInSeconds)
        );
    }

    #[DataProvider('provideTimeFormatsForHumansWithoutTrimming')]
    public function testFormatDurationForHumansWithoutTrimming(int $timeInSeconds, string $expectedResult): void
    {
        $this->assertEquals(
            $expectedResult,
            $this->formatDurationAsHHMMSS($timeInSeconds)
        );
    }

    /**
     * @return list<array{int, string}>
     */
    public static function provideTimeFormatsForHumans(): array
    {
        return [
            [10, '10s'],
            [61, '1:01'],
            [601, '10:01'],
            [7312, '2:01:52'],
        ];
    }

    /**
     * @return list<array{int, string}>
     */
    public static function provideTimeFormatsForHumansForChartLabel(): array
    {
        return [
            [10, '00:10'],
            [61, '01:01'],
            [601, '10:01'],
            [7312, '2:01:52'],
        ];
    }

    /**
     * @return list<array{int, string}>
     */
    public static function provideTimeFormatsForHumansWithoutTrimming(): array
    {
        return [
            [10, '00:00:10'],
            [61, '00:01:01'],
            [601, '00:10:01'],
            [7312, '02:01:52'],
        ];
    }
}
