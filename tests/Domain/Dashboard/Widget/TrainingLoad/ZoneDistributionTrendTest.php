<?php

namespace App\Tests\Domain\Dashboard\Widget\TrainingLoad;

use App\Domain\Dashboard\Widget\TrainingLoad\ZoneDistributionTrend;
use App\Infrastructure\ValueObject\String\KernelProjectDir;
use App\Tests\ContainerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Contracts\Translation\TranslatorInterface;

class ZoneDistributionTrendTest extends ContainerTestCase
{
    use MatchesSnapshots;

    #[DataProvider(methodName: 'fromPercentagesProvider')]
    public function testFromPercentages(float $current, float $previous, ZoneDistributionTrend $expected): void
    {
        self::assertSame($expected, ZoneDistributionTrend::fromPercentages(current: $current, previous: $previous));
    }

    public function testGetSvgIcon(): void
    {
        $kernelProjectDir = $this->getContainer()->get(KernelProjectDir::class);

        $snapshot = [];
        foreach (ZoneDistributionTrend::cases() as $trend) {
            $svgIcon = $trend->getSvgIcon();
            $snapshot[$trend->name] = $svgIcon;

            if (is_null($svgIcon)) {
                continue;
            }
            self::assertFileExists($kernelProjectDir.'/templates/svg/icons/'.$svgIcon.'.svg');
        }
        $this->assertMatchesJsonSnapshot($snapshot);
    }

    public function testGetTranslations(): void
    {
        $snapshot = [];
        foreach (ZoneDistributionTrend::cases() as $trend) {
            $snapshot[$trend->name] = $trend->trans($this->getContainer()->get(TranslatorInterface::class));
        }
        $this->assertMatchesJsonSnapshot($snapshot);
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
