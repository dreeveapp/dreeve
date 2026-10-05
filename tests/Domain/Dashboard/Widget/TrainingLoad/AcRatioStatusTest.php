<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Dashboard\Widget\TrainingLoad\AcRatioStatus;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

class AcRatioStatusTest extends ContainerTestCase
{
    #[DataProvider(methodName: 'fromFloatProvider')]
    public function testFromFloat(float $value, AcRatioStatus $expected): void
    {
        self::assertSame($expected, AcRatioStatus::fromFloat($value));
    }

    public function testGetTranslations(): void
    {
        $actual = [];
        foreach (AcRatioStatus::cases() as $acRatioStatus) {
            $actual[$acRatioStatus->name] = $acRatioStatus->trans($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertEquals(
            ['HIGH_RISK' => 'High risk', 'LOW_RISK' => 'Low risk', 'LOW_TRAINING_LOAD' => 'Low training load'],
            $actual,
        );
    }

    public function testGetDescriptionsTranslations(): void
    {
        $actual = [];
        foreach (AcRatioStatus::cases() as $acRatioStatus) {
            $actual[$acRatioStatus->name] = $acRatioStatus->transDescription($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertEquals(
            [
                'HIGH_RISK' => 'Consider reducing load',
                'LOW_RISK' => 'Optimal training range',
                'LOW_TRAINING_LOAD' => 'Fitness may decline',
            ],
            $actual,
        );
    }

    #[DataProvider(methodName: 'presentationProvider')]
    public function testPresentation(AcRatioStatus $status, string $expectedRange, string $expectedTextColor, string $expectedPillColors): void
    {
        self::assertSame($expectedRange, $status->getRange());
        self::assertSame($expectedTextColor, $status->getTextColor());
        self::assertSame($expectedPillColors, $status->getPillColors());
    }

    public static function presentationProvider(): iterable
    {
        yield 'HIGH_RISK' => [AcRatioStatus::HIGH_RISK, '> 1.3', 'text-red-600', 'bg-red-100 text-red-800'];
        yield 'LOW_RISK' => [AcRatioStatus::LOW_RISK, '0.8 to 1.3', 'text-green-600', 'bg-green-100 text-green-800'];
        yield 'LOW_TRAINING_LOAD' => [AcRatioStatus::LOW_TRAINING_LOAD, '< 0.8', 'text-yellow-600', 'bg-yellow-100 text-yellow-800'];
    }

    public static function fromFloatProvider(): iterable
    {
        yield 'high risk' => [1.31, AcRatioStatus::HIGH_RISK];
        yield 'low training load' => [0.79, AcRatioStatus::LOW_TRAINING_LOAD];
        yield 'lower boundary low risk' => [0.8, AcRatioStatus::LOW_RISK];
        yield 'upper boundary low risk' => [1.3, AcRatioStatus::LOW_RISK];
        yield 'middle low risk' => [1.0, AcRatioStatus::LOW_RISK];
    }
}
