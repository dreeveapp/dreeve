<?php

namespace App\Tests\Application\Import\StravaImport\ImportActivities;

use App\Application\Import\StravaImport\ImportActivities\NumberOfNewActivitiesToProcessPerImport;
use PHPUnit\Framework\TestCase;

class NumberOfNewActivitiesToProcessPerImportTest extends TestCase
{
    public function testHasBeenReachedBy(): void
    {
        $numberOfActivitiesToProcessPerImport = NumberOfNewActivitiesToProcessPerImport::fromInt(2);

        $this->assertFalse($numberOfActivitiesToProcessPerImport->hasBeenReachedBy(0));
        $this->assertFalse($numberOfActivitiesToProcessPerImport->hasBeenReachedBy(1));
        $this->assertTrue($numberOfActivitiesToProcessPerImport->hasBeenReachedBy(2));
        $this->assertTrue($numberOfActivitiesToProcessPerImport->hasBeenReachedBy(3));
    }

    public function testItShouldThrow(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('NumberOfNewActivitiesToProcessPerImport must be greater than 0'));

        NumberOfNewActivitiesToProcessPerImport::fromInt(0);
    }
}
