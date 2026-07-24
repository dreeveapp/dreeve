<?php

namespace App\Tests\Domain\Milestone\FunComparison;

use App\Domain\Milestone\FunComparison\ElevationFunComparison;
use App\Infrastructure\Measurement\Length\Meter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

class ElevationFunComparisonTest extends TestCase
{
    public function testTransReturnsNonEmptyStringForAllCases(): void
    {
        $translator = new IdentityTranslator();

        foreach (ElevationFunComparison::cases() as $case) {
            $this->assertNotEmpty($case->trans($translator));
        }
    }

    public function testResolveReturnsNullBelowMinimum(): void
    {
        $this->assertNull(ElevationFunComparison::resolve(Meter::from(100)));
        $this->assertNull(ElevationFunComparison::resolve(Meter::from(299)));
    }

    #[DataProvider(methodName: 'resolveProvider')]
    public function testResolve(float $meters, ElevationFunComparison $expected): void
    {
        $this->assertEquals($expected, ElevationFunComparison::resolve(Meter::from($meters)));
    }

    public static function resolveProvider(): \Generator
    {
        yield 'eiffel tower' => [300, ElevationFunComparison::EIFFEL_TOWER];
        yield 'empire state' => [500, ElevationFunComparison::EMPIRE_STATE_BUILDING];
        yield 'two eiffels' => [1_000, ElevationFunComparison::TWO_EIFFEL_TOWERS];
        yield 'deepest cave' => [2_500, ElevationFunComparison::DEEPEST_CAVE];
        yield 'mont blanc' => [5_000, ElevationFunComparison::HIGHER_THAN_MONT_BLANC];
        yield 'everest' => [8_849, ElevationFunComparison::MOUNT_EVEREST];
        yield 'cruising altitude' => [10_000, ElevationFunComparison::CRUISING_ALTITUDE];
        yield 'everest twice' => [17_772, ElevationFunComparison::EVEREST_TWICE];
        yield 'nearly everest 3x' => [25_000, ElevationFunComparison::NEARLY_EVEREST_THREE_TIMES];
        yield 'baumgartner' => [50_000, ElevationFunComparison::BAUMGARTNER_SPACE_JUMP];
        yield 'everest 8x' => [75_000, ElevationFunComparison::EVEREST_EIGHT_TIMES];
        yield 'karman line' => [100_000, ElevationFunComparison::KARMAN_LINE];
        yield 'above karman' => [150_000, ElevationFunComparison::ABOVE_KARMAN_LINE];
        yield 'halfway iss' => [200_000, ElevationFunComparison::HALFWAY_TO_ISS];
        yield 'low earth orbit' => [300_000, ElevationFunComparison::LOW_EARTH_ORBIT];
        yield 'iss altitude' => [400_000, ElevationFunComparison::ISS_ALTITUDE];
        yield 'higher than iss' => [500_000, ElevationFunComparison::HIGHER_THAN_ISS];
        yield 'twice iss' => [750_000, ElevationFunComparison::TWICE_ISS_ALTITUDE];
        yield 'edge of space 5x' => [1_000_000, ElevationFunComparison::EDGE_OF_SPACE_FIVE_TIMES];
    }
}
