<?php

declare(strict_types=1);

namespace App\Tests\Domain\Activity;

use App\Domain\Activity\GpxFileName;
use PHPUnit\Framework\TestCase;

class GpxFileNameTest extends TestCase
{
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
