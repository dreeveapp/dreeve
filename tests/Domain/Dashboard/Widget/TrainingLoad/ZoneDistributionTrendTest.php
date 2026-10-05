<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Dashboard\Widget\TrainingLoad\ZoneDistributionTrend;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Contracts\Translation\TranslatorInterface;

class ZoneDistributionTrendTest extends ContainerTestCase
{
    #[DataProvider(methodName: 'fromPercentagesProvider')]
    public function testFromPercentages(float $current, float $previous, ZoneDistributionTrend $expected): void
    {
        self::assertSame($expected, ZoneDistributionTrend::fromPercentages(current: $current, previous: $previous));
    }

    public function testGetSvgIcon(): void
    {
        $kernelProjectDir = $this->getContainer()->get(KernelProjectDir::class);

        $actual = [];
        foreach (ZoneDistributionTrend::cases() as $trend) {
            $svgIcon = $trend->getSvgIcon();
            $actual[$trend->name] = [
                'svgIcon' => $svgIcon,
                'svgIconClasses' => $trend->getSvgIconClasses(),
            ];

            if (is_null($svgIcon)) {
                continue;
            }
            self::assertFileExists($kernelProjectDir.'/templates/svg/icons/'.$svgIcon.'.svg');
        }
        $this->assertEquals(
            [
                'UP' => ['svgIcon' => 'chevron', 'svgIconClasses' => 'size-3 rotate-180'],
                'DOWN' => ['svgIcon' => 'chevron', 'svgIconClasses' => 'size-3'],
                'STEADY' => ['svgIcon' => null, 'svgIconClasses' => 'size-3'],
            ],
            $actual,
        );
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

    public static function fromPercentagesProvider(): iterable
    {
        yield 'increased' => [76.81, 74.02, ZoneDistributionTrend::UP];
        yield 'decreased' => [74.02, 76.81, ZoneDistributionTrend::DOWN];
        yield 'unchanged' => [76.81, 76.81, ZoneDistributionTrend::STEADY];
        yield 'increased from zero' => [0.01, 0.0, ZoneDistributionTrend::UP];
        yield 'both zero' => [0.0, 0.0, ZoneDistributionTrend::STEADY];
    }
}
