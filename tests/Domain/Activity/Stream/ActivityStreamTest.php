<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity\Stream;

use PHPUnit\Framework\TestCase;

class ActivityStreamTest extends TestCase
{
    public function testApplySimpleMovingAverage(): void
    {
        $activityStream = ActivityStreamBuilder::fromDefaults()
            ->withData([])
            ->build();

        $this->assertEquals(
            $activityStream,
            $activityStream->applySimpleMovingAverage(10),
        );
    }
}
