<?php

declare(strict_types=1);

namespace App\Tests\Domain\Automation\Condition;

use App\Domain\Automation\Condition\EndsNearCondition;
use App\Domain\Automation\RuleConfiguration;
use App\Infrastructure\ValueObject\Geography\EncodedPolyline;
use App\Tests\Domain\Activity\ActivityBuilder;
use PHPUnit\Framework\TestCase;

class EndsNearConditionTest extends TestCase
{
    private EndsNearCondition $condition;

    public function testDefaultConfiguration(): void
    {
        $this->assertSame(
            ['operator' => 'within', 'latitude' => 0.0, 'longitude' => 0.0, 'radius' => 1.0],
            $this->condition->getDefaultConfiguration()->toArray()
        );
    }

    public function testMatchesWhenActivityEndsWithinTheRadius(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline($this->polylineEndingAt(51.055, 4.0))
            ->build();

        $this->assertTrue($this->condition->matches($activity, $this->config('within', 51.05, 4.0, 1.0)));
    }

    public function testDoesNotMatchWhenActivityEndsOutsideTheRadius(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline($this->polylineEndingAt(51.10, 4.0))
            ->build();

        $this->assertFalse($this->condition->matches($activity, $this->config('within', 51.05, 4.0, 1.0)));
    }

    public function testMatchingIgnoresTheStartAndOnlyLooksAtTheEnd(): void
    {
        $activity = ActivityBuilder::fromDefaults()
            ->withPolyline((string) EncodedPolyline::fromCoordinates([[48.0, 2.0], [51.055, 4.0]]))
            ->build();

        $this->assertTrue($this->condition->matches($activity, $this->config('within', 51.05, 4.0, 1.0)));
    }

    public function testOutsideOperatorInvertsTheMatch(): void
    {
        $endsNear = ActivityBuilder::fromDefaults()->withPolyline($this->polylineEndingAt(51.055, 4.0))->build();
        $endsFar = ActivityBuilder::fromDefaults()->withPolyline($this->polylineEndingAt(51.10, 4.0))->build();

        $this->assertFalse($this->condition->matches($endsNear, $this->config('outside', 51.05, 4.0, 1.0)));
        $this->assertTrue($this->condition->matches($endsFar, $this->config('outside', 51.05, 4.0, 1.0)));
    }

    public function testDoesNotMatchWhenActivityHasNoPolyline(): void
    {
        $activity = ActivityBuilder::fromDefaults()->build();

        $this->assertFalse($this->condition->matches($activity, $this->config('within', 51.05, 4.0, 1.0)));
        $this->assertFalse($this->condition->matches($activity, $this->config('outside', 51.05, 4.0, 1.0)));
    }

    private function polylineEndingAt(float $latitude, float $longitude): string
    {
        return (string) EncodedPolyline::fromCoordinates([[50.0, 3.0], [$latitude, $longitude]]);
    }

    private function config(string $operator, float $latitude, float $longitude, float $radius): RuleConfiguration
    {
        return RuleConfiguration::fromConfig([
            'operator' => $operator,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius' => $radius,
        ]);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->condition = new EndsNearCondition();
    }
}
