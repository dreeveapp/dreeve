<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Dashboard\Widget\TrainingLoad\ZoneDistributionTrend;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

class ZoneDistributionTrendTest extends ContainerTestCase
{
    #[DataProvider('fromPercentagesProvider')]
    public function testFromPercentages(float $current, float $previous, ZoneDistributionTrend $expected): void
    {
        self::assertSame($expected, ZoneDistributionTrend::fromPercentages(current: $current, previous: $previous));
    }

    public function testGetSvgIcon(): void
    {
        $actual = [];
        foreach (ZoneDistributionTrend::cases() as $trend) {
            $actual[$trend->name] = [
                'svgIcon' => $trend->getSvgIcon(),
                'svgIconClasses' => $trend->getSvgIconClasses(),
            ];
        }
        $this->assertEquals(
            [
                'UP' => ['svgIcon' => 'chevron', 'svgIconClasses' => 'size-3 rotate-180'],
                'DOWN' => ['svgIcon' => 'chevron', 'svgIconClasses' => 'size-3'],
                'STEADY' => ['svgIcon' => null, 'svgIconClasses' => 'size-3'],
            ],
            $actual,
        );
        self::assertFileExists($this->getContainer()->get(KernelProjectDir::class).'/templates/svg/icons/chevron.svg');
    }

    public function testGetTranslations(): void
    {
        $actual = [];
        foreach (ZoneDistributionTrend::cases() as $trend) {
            $actual[$trend->name] = $trend->trans($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertEquals(
            [
                'UP' => 'Increased compared to yesterday',
                'DOWN' => 'Decreased compared to yesterday',
                'STEADY' => 'Unchanged compared to yesterday',
            ],
            $actual,
        );
    }

    /**
     * @return iterable<string, array{float, float, ZoneDistributionTrend}>
     */
    public static function fromPercentagesProvider(): iterable
    {
        yield 'increased' => [76.81, 74.02, ZoneDistributionTrend::UP];
        yield 'decreased' => [74.02, 76.81, ZoneDistributionTrend::DOWN];
        yield 'unchanged' => [76.81, 76.81, ZoneDistributionTrend::STEADY];
        yield 'increased from zero' => [0.01, 0.0, ZoneDistributionTrend::UP];
        yield 'both zero' => [0.0, 0.0, ZoneDistributionTrend::STEADY];
    }
}
