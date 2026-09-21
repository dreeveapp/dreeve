<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\GpxFileName;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use PHPUnit\Framework\TestCase;

class GpxFileNameTest extends TestCase
{
    public function testItCombinesTheLocalStartDateAndASluggedName(): void
    {
        $this->assertEquals(
            '2023-09-04-evening-ride.gpx',
            (string) GpxFileName::for(ActivityBuilder::fromDefaults()
                ->withStartDateTime(SerializableDateTime::fromString('2023-09-04 21:05:38'))
                ->withName('Evening Ride')
                ->build()),
        );
    }

    public function testItStripsCharactersThatAreUnsafeInAFileName(): void
    {
        $this->assertEquals(
            '2023-10-10-morning-ride-with-jane-doe.gpx',
            (string) GpxFileName::for(ActivityBuilder::fromDefaults()
                ->withName('Morning ride /with\\ Jane & Doe!')
                ->build()),
        );
    }
}
