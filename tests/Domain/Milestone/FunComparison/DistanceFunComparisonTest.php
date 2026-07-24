<?php

namespace App\Tests\Domain\Milestone\FunComparison;

use App\Domain\Milestone\FunComparison\DistanceFunComparison;
use App\Infrastructure\Measurement\Length\Kilometer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

class DistanceFunComparisonTest extends TestCase
{
    public function testTransReturnsNonEmptyStringForAllCases(): void
    {
        $translator = new IdentityTranslator();

        foreach (DistanceFunComparison::cases() as $case) {
            $this->assertNotEmpty($case->trans($translator));
        }
    }

    public function testResolveReturnsNullBelowMinimum(): void
    {
        $this->assertNull(DistanceFunComparison::resolve(Kilometer::from(50)));
        $this->assertNull(DistanceFunComparison::resolve(Kilometer::from(99)));
    }

    #[DataProvider(methodName: 'resolveProvider')]
    public function testResolve(float $km, DistanceFunComparison $expected): void
    {
        $this->assertEquals($expected, DistanceFunComparison::resolve(Kilometer::from($km)));
    }

    public static function resolveProvider(): \Generator
    {
        yield 'edge of space' => [100, DistanceFunComparison::EDGE_OF_SPACE];
        yield 'jamaica' => [250, DistanceFunComparison::LENGTH_OF_JAMAICA];
        yield 'madrid-barcelona' => [500, DistanceFunComparison::MADRID_TO_BARCELONA];
        yield 'france' => [1_000, DistanceFunComparison::FRANCE_LENGTH];
        yield 'danube' => [2_500, DistanceFunComparison::DANUBE_RIVER];
        yield 'great wall' => [5_000, DistanceFunComparison::GREAT_WALL_OF_CHINA];
        yield 'quarter earth' => [10_000, DistanceFunComparison::QUARTER_AROUND_EARTH];
        yield 'london-perth' => [15_000, DistanceFunComparison::LONDON_TO_PERTH];
        yield 'halfway earth' => [20_000, DistanceFunComparison::HALFWAY_AROUND_EARTH];
        yield 'mars circumference' => [25_000, DistanceFunComparison::CIRCUMFERENCE_OF_MARS];
        yield 'three quarters earth' => [30_000, DistanceFunComparison::THREE_QUARTERS_AROUND_EARTH];
        yield 'earth circumference' => [40_000, DistanceFunComparison::EARTH_CIRCUMFERENCE];
        yield 'neptune diameter' => [50_000, DistanceFunComparison::DIAMETER_OF_NEPTUNE];
        yield 'nearly twice earth' => [75_000, DistanceFunComparison::NEARLY_TWICE_AROUND_EARTH];
        yield '2.5x earth' => [100_000, DistanceFunComparison::TWO_AND_HALF_TIMES_AROUND_EARTH];
        yield 'jupiter diameter' => [150_000, DistanceFunComparison::DIAMETER_OF_JUPITER];
        yield 'five times earth' => [200_000, DistanceFunComparison::FIVE_TIMES_AROUND_EARTH];
        yield 'speed of light' => [300_000, DistanceFunComparison::SPEED_OF_LIGHT_PER_SECOND];
        yield 'moon' => [400_000, DistanceFunComparison::EARTH_TO_MOON];
        yield 'beyond moon' => [500_000, DistanceFunComparison::MORE_THAN_EARTH_TO_MOON];
    }
}
