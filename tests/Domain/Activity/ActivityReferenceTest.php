<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\ActivityId;
use App\Domain\Activity\ActivityReference;
use PHPUnit\Framework\TestCase;

class ActivityReferenceTest extends TestCase
{
    public function testFormat(): void
    {
        $this->assertSame('Morning Run (activity-1)', ActivityReference::format('Morning Run', ActivityId::fromUnprefixed('1')));
    }

    public function testParse(): void
    {
        $this->assertEquals(ActivityId::fromUnprefixed('1'), ActivityReference::parse('Morning Run (activity-1)'));
        $this->assertEquals(ActivityId::fromUnprefixed('1'), ActivityReference::parse('Run (easy) (activity-1) '));
        $this->assertEquals(ActivityId::fromUnprefixed('1'), ActivityReference::parse(' activity-1 '));
    }

    public function testParseThrowsWithoutAnActivityId(): void
    {
        $this->expectExceptionObject(
            new \InvalidArgumentException('Identifier does not start with prefix "activity-", got: Morning Run (1)')
        );

        ActivityReference::parse('Morning Run (1)');
    }
}
