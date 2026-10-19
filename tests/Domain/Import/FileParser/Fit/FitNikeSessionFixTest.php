<?php

declare(strict_types=1);

namespace App\Tests\Domain\Import\FileParser\Fit;

use App\Domain\Import\FileParser\Fit\FitManufacturer;
use App\Domain\Import\FileParser\Fit\FitNikeSessionFix;
use PHPUnit\Framework\TestCase;

class FitNikeSessionFixTest extends TestCase
{
    private const array NIKE_SESSION = [
        'total_timer_time' => 1085,
        'total_distance' => 3499.98,
        'avg_speed' => 11.606,
        'enhanced_avg_speed' => 11.606,
        'max_speed' => 18.356,
        'enhanced_max_speed' => 18.356,
        'avg_cadence' => 140,
        'max_cadence' => 180,
        'total_cycles' => 1270,
    ];

    public function testApplyFixesSpeedAndCadence(): void
    {
        $session = FitNikeSessionFix::apply(self::NIKE_SESSION, FitManufacturer::NIKE);

        $this->assertEqualsWithDelta(3.224, $session['avg_speed'], 0.001);
        $this->assertEqualsWithDelta(3.224, $session['enhanced_avg_speed'], 0.001);
        $this->assertEqualsWithDelta(5.099, $session['max_speed'], 0.001);
        $this->assertEqualsWithDelta(5.099, $session['enhanced_max_speed'], 0.001);
        $this->assertSame(70.0, $session['avg_cadence']);
        $this->assertSame(90.0, $session['max_cadence']);
    }

    public function testApplyIgnoresOtherManufacturers(): void
    {
        $this->assertSame(self::NIKE_SESSION, FitNikeSessionFix::apply(self::NIKE_SESSION, FitManufacturer::GARMIN));
        $this->assertSame(self::NIKE_SESSION, FitNikeSessionFix::apply(self::NIKE_SESSION, null));
    }

    public function testApplyKeepsValuesThatAreAlreadyInTheRightUnit(): void
    {
        $session = [
            ...self::NIKE_SESSION,
            'avg_speed' => 3.224,
            'enhanced_avg_speed' => 3.224,
            'max_speed' => 5.099,
            'enhanced_max_speed' => 5.099,
            'avg_cadence' => 70,
            'max_cadence' => 90,
        ];

        $this->assertSame($session, FitNikeSessionFix::apply($session, FitManufacturer::NIKE));
    }

    public function testApplyFixesEachMetricIndependently(): void
    {
        $session = FitNikeSessionFix::apply([...self::NIKE_SESSION, 'avg_cadence' => 70, 'max_cadence' => 90], FitManufacturer::NIKE);

        $this->assertEqualsWithDelta(3.224, $session['avg_speed'], 0.001);
        $this->assertSame(70, $session['avg_cadence']);
        $this->assertSame(90, $session['max_cadence']);
    }

    public function testApplyLeavesSessionUntouchedWithoutReferenceValues(): void
    {
        $session = self::NIKE_SESSION;
        unset($session['total_distance'], $session['total_cycles']);

        $this->assertSame($session, FitNikeSessionFix::apply($session, FitManufacturer::NIKE));
        $this->assertSame([...self::NIKE_SESSION, 'total_timer_time' => 0], FitNikeSessionFix::apply([...self::NIKE_SESSION, 'total_timer_time' => 0], FitManufacturer::NIKE));
    }
}
