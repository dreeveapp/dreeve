<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Dashboard\Widget\TrainingLoad\TSBStatus;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

class TSBStatusTest extends ContainerTestCase
{
    #[DataProvider(methodName: 'fromFloatProvider')]
    public function testFromFloat(float $value, TSBStatus $expected): void
    {
        self::assertSame($expected, TSBStatus::fromFloat($value));
    }

    public function testGetTranslations(): void
    {
        $actual = [];
        foreach (TSBStatus::cases() as $status) {
            $actual[$status->name] = $status->trans($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertEquals(
            [
                'POSSIBLE_DETRAINING' => 'Risk of detraining',
                'PEAK_FRESH' => 'Peak fresh',
                'SLIGHTLY_FRESH' => 'Slightly fresh',
                'NEUTRAL' => 'Neutral',
                'ACCUMULATED_FATIGUE' => 'Accumulated fatigue',
                'OVER_FATIGUED' => 'Over-fatigued',
            ],
            $actual,
        );
    }

    public function testGetDescriptionsTranslations(): void
    {
        $actual = [];
        foreach (TSBStatus::cases() as $status) {
            $actual[$status->name] = $status->transDescription($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertEquals(
            [
                'POSSIBLE_DETRAINING' => 'Fitness may be declining due to low recent training load',
                'PEAK_FRESH' => 'Highly recovered and ready to perform',
                'SLIGHTLY_FRESH' => 'Light fatigue with good readiness. A great balance for quality training or longer efforts',
                'NEUTRAL' => 'Moderate fatigue with stable fitness. Well suited for consistent day-to-day training',
                'ACCUMULATED_FATIGUE' => 'Training load is accumulating, recovery days are important',
                'OVER_FATIGUED' => 'High fatigue levels may increase injury or burnout risk. Consider reducing load and prioritizing recovery',
            ],
            $actual,
        );
    }

    #[DataProvider(methodName: 'presentationProvider')]
    public function testPresentation(TSBStatus $status, string $expectedRange, string $expectedTextColor, string $expectedPillColors): void
    {
        self::assertSame($expectedRange, $status->getRange());
        self::assertSame($expectedTextColor, $status->getTextColor());
        self::assertSame($expectedPillColors, $status->getPillColors());
    }

    public static function presentationProvider(): iterable
    {
        yield 'POSSIBLE_DETRAINING' => [TSBStatus::POSSIBLE_DETRAINING, '> 25', 'text-orange-500', 'bg-orange-100 text-orange-800'];
        yield 'PEAK_FRESH' => [TSBStatus::PEAK_FRESH, '10 to 25', 'text-green-600', 'bg-green-100 text-green-800'];
        yield 'SLIGHTLY_FRESH' => [TSBStatus::SLIGHTLY_FRESH, '0 to 10', 'text-green-600', 'bg-green-100 text-green-800'];
        yield 'NEUTRAL' => [TSBStatus::NEUTRAL, '-10 to 0', 'text-gray-900', 'bg-gray-200 text-gray-900'];
        yield 'ACCUMULATED_FATIGUE' => [TSBStatus::ACCUMULATED_FATIGUE, '-30 to -10', 'text-yellow-600', 'bg-yellow-100 text-yellow-800'];
        yield 'OVER_FATIGUED' => [TSBStatus::OVER_FATIGUED, '< -30', 'text-red-600', 'bg-red-100 text-red-800'];
    }

    public static function fromFloatProvider(): iterable
    {
        // POSSIBLE_DETRAINING (> 25)
        yield 'possible detraining' => [25.1, TSBStatus::POSSIBLE_DETRAINING];

        // PEAK_FRESH (10 – 25]
        yield 'peak fresh middle' => [20.0, TSBStatus::PEAK_FRESH];
        yield 'peak fresh upper boundary' => [25.0, TSBStatus::PEAK_FRESH];

        // SLIGHTLY_FRESH (0 – 10]
        yield 'slightly fresh middle' => [5.0, TSBStatus::SLIGHTLY_FRESH];
        yield 'slightly fresh upper boundary' => [10.0, TSBStatus::SLIGHTLY_FRESH];

        // NEUTRAL (-10 – 0]
        yield 'neutral middle' => [-5.0, TSBStatus::NEUTRAL];
        yield 'neutral upper boundary' => [0.0, TSBStatus::NEUTRAL];

        // ACCUMULATED_FATIGUE (-30 – -10]
        yield 'accumulated fatigue middle' => [-20.0, TSBStatus::ACCUMULATED_FATIGUE];
        yield 'accumulated fatigue upper boundary' => [-10.0, TSBStatus::ACCUMULATED_FATIGUE];

        // OVER_FATIGUED (< -30)
        yield 'over fatigued' => [-30.1, TSBStatus::OVER_FATIGUED];
        yield 'over fatigued boundary' => [-30.0, TSBStatus::OVER_FATIGUED];
    }
}
