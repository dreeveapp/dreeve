<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Split;

use App\Domain\Activity\Split\ActivitySplit;
use App\Infrastructure\Measurement\Velocity\MetersPerSecond;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActivitySplitTest extends TestCase
{
    #[DataProvider('provideRelativePacePercentages')]
    public function testGetRelativePacePercentage(
        ActivitySplit $activitySplit,
        float $expectedPercentage): void
    {
        $this->assertEquals($expectedPercentage, $activitySplit->getRelativePacePercentage());
    }

    /**
     * @return list<array{ActivitySplit, float}>
     */
    public static function provideRelativePacePercentages(): array
    {
        return [
            [
                ActivitySplitBuilder::fromDefaults()
                    ->withAverageSpeed(MetersPerSecond::from(0.87))
                    ->withMinAverageSpeed(MetersPerSecond::from(0.72))
                    ->withMaxAverageSpeed(MetersPerSecond::from(0.98))
                    ->build(),
                61.87,
            ],
            [
                ActivitySplitBuilder::fromDefaults()
                    ->withAverageSpeed(MetersPerSecond::from(13.3))
                    ->withMinAverageSpeed(MetersPerSecond::from(0))
                    ->withMaxAverageSpeed(MetersPerSecond::from(100))
                    ->build(),
                12.67,
            ],
            [
                ActivitySplitBuilder::fromDefaults()
                    ->withAverageSpeed(MetersPerSecond::from(3.6))
                    ->withMinAverageSpeed(MetersPerSecond::from(2.4))
                    ->withMaxAverageSpeed(MetersPerSecond::from(4.8))
                    ->build(),
                52.0,
            ],
            [
                ActivitySplitBuilder::fromDefaults()
                    ->withAverageSpeed(MetersPerSecond::from(13.3))
                    ->withMinAverageSpeed(MetersPerSecond::from(20))
                    ->withMaxAverageSpeed(MetersPerSecond::from(0))
                    ->build(),
                0,
            ],
            [
                ActivitySplitBuilder::fromDefaults()
                    ->withAverageSpeed(MetersPerSecond::from(13.3))
                    ->withMinAverageSpeed(MetersPerSecond::from(20))
                    ->withMaxAverageSpeed(MetersPerSecond::from(20))
                    ->build(),
                0,
            ],
        ];
    }
}
